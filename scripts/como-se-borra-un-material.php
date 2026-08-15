<?php

/**
 * @file
 * Por donde se borra un material y quien le apunta, antes de poner el freno.
 *
 *   php vendor\bin\drush.php scr scripts/como-se-borra-un-material.php
 *
 * Borrar un material que esta en uso averia en silencio las lineas que lo usan:
 * el proceso de ECA que calcula el total intenta cargar el material, no lo
 * encuentra, y la accion devuelve acceso denegado. ECA anota un aviso, corta la
 * cadena, y la bandera de bloqueo se queda puesta, asi que esa linea no vuelve a
 * recalcular nunca mas.
 *
 * Para ponerle un freno hacen falta dos cosas, y las dos se averiguan aqui:
 *
 *   1. Que campos apuntan de verdad a un material. No vale preguntarle a la
 *      configuracion: field_tec_inventory de tec_bom_item no declara
 *      target_bundles, asi que por configuracion no se sabe que apunta a
 *      materiales. Se cuenta contra los datos.
 *   2. Como se llama el formulario de borrado y que botones lleva, que es donde
 *      hay que engancharse.
 *
 * No escribe nada: construir un formulario no lo envia.
 */

$gestor = \Drupal::entityTypeManager();
$bd = \Drupal::database();

echo "\n";
echo "Campos que pueden apuntar a un termino de taxonomia\n";
echo str_repeat('-', 92) . "\n";
printf("  %-16s %-32s %-24s %s\n", 'entidad', 'campo', 'vocabularios declarados', 'filas a materiales');
echo '  ' . str_repeat('-', 90) . "\n";

$materiales = $bd->select('taxonomy_term_field_data', 't')
  ->fields('t', ['tid'])
  ->condition('t.vid', 'tec_inventory')
  ->execute()
  ->fetchCol();
printf("  (hay %d materiales en la base)\n\n", count($materiales));

$apuntadores = [];
foreach ($gestor->getStorage('field_storage_config')->loadMultiple() as $almacen) {
  if (!in_array($almacen->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
    continue;
  }
  if ($almacen->getSetting('target_type') !== 'taxonomy_term') {
    continue;
  }

  $tipoEntidad = $almacen->getTargetEntityTypeId();
  $campo = $almacen->getName();

  try {
    $tabla = $gestor->getStorage($tipoEntidad)->getTableMapping()->getFieldTableName($campo);
  }
  catch (\Throwable $e) {
    continue;
  }
  if (!$bd->schema()->tableExists($tabla)) {
    continue;
  }

  // Que vocabularios dice la configuracion que acepta cada instancia.
  $declarados = [];
  foreach ($gestor->getStorage('field_config')->loadByProperties([
    'entity_type' => $tipoEntidad,
    'field_name' => $campo,
  ]) as $instancia) {
    $paquetes = $instancia->getSetting('handler_settings')['target_bundles'] ?? [];
    $declarados = array_merge($declarados, $paquetes ? array_keys($paquetes) : ['(sin declarar)']);
  }
  $declarados = array_values(array_unique($declarados));

  $consulta = $bd->select($tabla, 'f');
  $consulta->innerJoin('taxonomy_term_field_data', 't', 't.tid = f.' . $campo . '_target_id');
  $consulta->condition('t.vid', 'tec_inventory');
  $aMateriales = (int) $consulta->countQuery()->execute()->fetchField();

  $puede = in_array('tec_inventory', $declarados, TRUE) || in_array('(sin declarar)', $declarados, TRUE);
  if (!$puede && $aMateriales === 0) {
    continue;
  }

  printf(
    "  %-16s %-32s %-24s %s\n",
    $tipoEntidad,
    $campo,
    substr(implode(',', $declarados), 0, 24),
    $aMateriales
  );
  $apuntadores[] = [$tipoEntidad, $campo, $tabla];
}

echo "\n";
echo "El formulario de borrado de un material\n";
echo str_repeat('-', 92) . "\n";

$ids = $gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->sort('tid')
  ->range(0, 1)
  ->execute();
if (!$ids) {
  echo "  No hay materiales con los que probar.\n\n";
  return;
}
$material = $gestor->getStorage('taxonomy_term')->load(reset($ids));
printf("  material de prueba: %s (tid %s)\n\n", $material->label(), $material->id());

$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo($gestor->getStorage('user')->load(1));

$constructor = \Drupal::service('entity.form_builder');
try {
  $formulario = $constructor->getForm($material, 'delete');
  printf("  form_id:        %s\n", $formulario['#form_id'] ?? '(sin id)');
  printf("  #theme:         %s\n", is_array($formulario['#theme'] ?? NULL) ? implode(',', $formulario['#theme']) : (string) ($formulario['#theme'] ?? ''));
  echo "  botones:\n";
  foreach ($formulario['actions'] ?? [] as $clave => $boton) {
    if (str_starts_with((string) $clave, '#')) {
      continue;
    }
    printf("      %-14s %s\n", $clave, is_object($boton['#value'] ?? NULL) ? (string) $boton['#value'] : (string) ($boton['#value'] ?? $boton['#title'] ?? ''));
  }
}
catch (\Throwable $e) {
  printf("  no se ha podido construir: %s: %s\n", get_class($e), $e->getMessage());
}

echo "\n";
echo "Las rutas por las que se puede borrar\n";
echo str_repeat('-', 92) . "\n";
foreach (['entity.taxonomy_term.delete_form', 'entity.taxonomy_term.canonical', 'entity.taxonomy_term.edit_form'] as $ruta) {
  try {
    printf("  %-42s %s\n", $ruta, \Drupal\Core\Url::fromRoute($ruta, ['taxonomy_term' => $material->id()])->toString());
  }
  catch (\Throwable $e) {
    printf("  %-42s no existe\n", $ruta);
  }
}

$cambiador->switchBack();
echo "\n";
