<?php

/**
 * Recuento previo al borrado de los datos de prueba.
 *
 * No modifica nada. Su trabajo es contestar cuatro preguntas que hoy nadie
 * tiene contestadas, y que deciden si el orden de borrado que hemos fijado es
 * el correcto:
 *
 *   1. Que hay dentro de verdad, entidad por entidad y paquete por paquete,
 *      incluido lo que la comprobacion de 35 puntos no mide (los registros de
 *      produccion no estan en su lista de referencia).
 *   2. Quien apunta a los materiales. Esta es la pregunta importante: los 869
 *      materiales van al final del borrado, y para eso hay que saber que todo
 *      lo que los referencia se borra antes.
 *   3. Si el vocabulario de materiales tiene jerarquia, porque al borrar un
 *      termino Drupal borra tambien sus hijos y entonces la cifra de borrados
 *      no coincidira con la esperada.
 *   4. Si las listas que se quedan (unidades, tipos, colores, tallas, tipos de
 *      producto) tienen basura de la errata del importador, que inventa una
 *      entrada nueva cuando el texto del Excel no coincide exactamente.
 *
 * El grafo de referencias se deriva del sitio, no de una lista escrita a mano,
 * porque el objetivo es descubrir dependencias que nadie apunto.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-hay-dentro.php
 */

$etm = \Drupal::entityTypeManager();
$efm = \Drupal::service('entity_field.manager');
$bd = \Drupal::database();

/**
 * Imprime un titulo de seccion.
 */
function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n";
}

/**
 * Cuenta filas de una tabla, o -1 si no existe.
 */
function filas($bd, string $tabla): int {
  if (!$bd->schema()->tableExists($tabla)) {
    return -1;
  }
  return (int) $bd->select($tabla, 't')->countQuery()->execute()->fetchField();
}

// --- 1. Todo el contenido que hay, sin excepciones -------------------------
seccion('todo el contenido, por tipo y paquete');

$saltar = ['file', 'path_alias', 'crop', 'search_api_task', 'menu_link_content', 'flagging'];
$totalContenido = 0;

foreach ($etm->getDefinitions() as $id => $definicion) {
  if ($definicion->getGroup() !== 'content') {
    continue;
  }
  $claves = $definicion->getKeys();
  $tabla = $definicion->getBaseTable();
  if (!$tabla || !$bd->schema()->tableExists($tabla)) {
    continue;
  }
  $total = filas($bd, $tabla);
  if ($total === 0) {
    continue;
  }

  $etiqueta = in_array($id, $saltar, TRUE) ? '(auxiliar)' : '';
  printf("\n  %-26s %6d  %s\n", $id, $total, $etiqueta);

  if (!in_array($id, $saltar, TRUE)) {
    $totalContenido += $total;
  }

  // Reparto por paquete, cuando el tipo tenga paquetes.
  if (!empty($claves['bundle'])) {
    $reparto = $bd->select($tabla, 't')
      ->fields('t', [$claves['bundle']]);
    $reparto->addExpression('COUNT(*)', 'n');
    $reparto->groupBy('t.' . $claves['bundle']);
    foreach ($reparto->execute()->fetchAll() as $fila) {
      printf("      %-24s %6d\n", $fila->{$claves['bundle']}, $fila->n);
    }
  }
}

printf("\n  total de contenido (sin auxiliares): %d\n", $totalContenido);

// --- 2. Quien apunta a los materiales -------------------------------------
seccion('quien apunta a los materiales (vocabulario tec_inventory)');

$mapa = $efm->getFieldMapByFieldType('entity_reference');
$apuntanATerminos = [];

foreach ($mapa as $tipoEntidad => $campos) {
  foreach ($campos as $campo => $info) {
    foreach ($info['bundles'] as $paquete) {
      $configs = $etm->getStorage('field_config')->loadByProperties([
        'entity_type' => $tipoEntidad,
        'bundle' => $paquete,
        'field_name' => $campo,
      ]);
      foreach ($configs as $config) {
        if ($config->getSetting('target_type') !== 'taxonomy_term') {
          continue;
        }
        $destinos = $config->getSetting('handler_settings')['target_bundles'] ?? [];
        if (!isset($destinos['tec_inventory'])) {
          continue;
        }
        $apuntanATerminos[] = [$tipoEntidad, $paquete, $campo];
      }
    }
  }
}

if (!$apuntanATerminos) {
  print "  ninguno declarado. Sospechoso: revisar a mano.\n";
}

$vistos = [];
foreach ($apuntanATerminos as [$tipoEntidad, $paquete, $campo]) {
  $clave = $tipoEntidad . '|' . $campo;
  try {
    $mapeo = $etm->getStorage($tipoEntidad)->getTableMapping();
    $tabla = $mapeo->getFieldTableName($campo);
  }
  catch (\Throwable $e) {
    $tabla = '(?)';
  }
  $n = isset($vistos[$clave]) ? $vistos[$clave] : filas($bd, $tabla);
  $vistos[$clave] = $n;
  printf("  %-14s %-26s %-32s %6d filas\n", $tipoEntidad, $paquete, $campo, $n);
}

// --- 3. Jerarquia y estado de los vocabularios ----------------------------
seccion('vocabularios: cuantos terminos y si tienen jerarquia');

foreach ($etm->getStorage('taxonomy_vocabulary')->loadMultiple() as $vid => $vocabulario) {
  $n = (int) $bd->select('taxonomy_term_field_data', 't')
    ->condition('t.vid', $vid)
    ->countQuery()->execute()->fetchField();

  $conPadre = (int) $bd->select('taxonomy_term__parent', 'p')
    ->condition('p.bundle', $vid)
    ->condition('p.parent_target_id', 0, '>')
    ->countQuery()->execute()->fetchField();

  printf("  %-24s %5d terminos, %d con padre  %s\n", $vid, $n, $conPadre, $vocabulario->label());
}

// --- 4. Las listas que se quedan, para buscar basura ----------------------
seccion('listas que se quedan: nombres completos, para cazar duplicados');

foreach (['tec_units', 'tec_materials', 'tec_colors', 'tec_sizes', 'tec_product_types', 'tec_crm_contact_type'] as $vid) {
  $nombres = $bd->select('taxonomy_term_field_data', 't')
    ->fields('t', ['tid', 'name'])
    ->condition('t.vid', $vid)
    ->orderBy('t.name')
    ->execute()->fetchAllKeyed();

  printf("\n  %s (%d)\n", $vid, count($nombres));
  foreach ($nombres as $tid => $nombre) {
    printf("      %-8s %s\n", $tid, $nombre);
  }
}

// --- 5. Huerfanos y banderas ---------------------------------------------
seccion('huerfanos y banderas');

$rotas = (int) $bd->query("
  SELECT COUNT(*)
  FROM {tec_inventory__field_tec_inventory} f
  LEFT JOIN {taxonomy_term_field_data} t ON t.tid = f.field_tec_inventory_target_id
  WHERE t.tid IS NULL
")->fetchField();
printf("  referencias a materiales que ya no existen: %d\n", $rotas);

if ($bd->schema()->tableExists('tec_line_item__field_tec_gui_product')) {
  printf("  filas de field_tec_gui_product: %d\n", filas($bd, 'tec_line_item__field_tec_gui_product'));
}

print "\n  banderas puestas:\n";
foreach ($bd->query("SELECT flag_id, COUNT(*) AS n FROM {flagging} GROUP BY flag_id")->fetchAll() as $fila) {
  printf("      %-30s %d\n", $fila->flag_id, $fila->n);
}

// --- 6. Procesos de ECA que habra que apagar y volver a encender ----------
seccion('procesos de ECA');

$ecas = $etm->getStorage('eca')->loadMultiple();
$encendidos = array_filter($ecas, fn($e) => $e->status());
printf("  %d procesos, %d encendidos\n", count($ecas), count($encendidos));

print "\n";
