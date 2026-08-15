<?php

/**
 * @file
 * Comprueba si el importador puede encontrar un proveedor por su nombre.
 *
 * Uso: php vendor/bin/drush.php scr scripts/como-encuentra-el-importador-al-proveedor.php
 *
 * field_tec_vendor es obligatorio y resuelve por una vista (tec_crm_references),
 * no por una lista de subtipos. Eso importa para Feeds: su complemento de
 * referencia NO usa la vista, busca directo con una consulta de entidades
 * filtrando por los subtipos declarados en el campo. Y este campo no declara
 * ninguno, o sea que buscara entre TODOS los tec_crm, organizaciones y personas.
 *
 * Asi que hay dos preguntas que contestar antes de mapear la columna: por que
 * clave hay que buscar -como se llama el campo del nombre en tec_crm-, y que
 * pasa si el nombre que trae el Excel coincide con una persona de contacto en
 * lugar de con una organizacion.
 *
 * No toca nada.
 */

$gestor = \Drupal::entityTypeManager();
$definicion = $gestor->getDefinition('tec_crm');

print "\n";
print "  --- 1. el tipo de entidad tec_crm ---\n\n";
printf("      clave de id:      %s\n", $definicion->getKey('id'));
printf("      clave de etiqueta:%s\n", $definicion->getKey('label') ?: '(ninguna)');
printf("      clave de subtipo: %s\n", $definicion->getKey('bundle'));
printf("      subtipos:         %s\n", implode(', ', array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('tec_crm'))));

print "\n      fichas que hay hoy:\n\n";
foreach ($gestor->getStorage('tec_crm')->loadMultiple() as $ficha) {
  printf("        id %-5s %-32s subtipo %s\n", $ficha->id(), $ficha->label(), $ficha->bundle());
}

// ---------------------------------------------------------------------------
// 2. Como buscara Feeds.
// ---------------------------------------------------------------------------
// Se reproduce la busqueda exacta que hace el complemento: el servicio
// feeds.entity_finder sobre el campo de la etiqueta, sin filtrar por subtipo.
print "\n  --- 2. la busqueda que hara Feeds ---\n\n";

$definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'tec_inventory');
$campo = $definiciones['field_tec_vendor'];
$ajustes = $campo->getSettings();
$subtipos = array_keys($ajustes['handler_settings']['target_bundles'] ?? []);

printf("      subtipos que declara el campo: %s\n", $subtipos ? implode(', ', $subtipos) : 'NINGUNO, o sea que busca entre todos');
printf("      clave por la que se buscara:   %s\n\n", $definicion->getKey('label'));

$buscador = \Drupal::service('feeds.entity_finder');

foreach ($gestor->getStorage('tec_crm')->loadMultiple() as $ficha) {
  $encontrados = $buscador->findEntities('tec_crm', $definicion->getKey('label'), $ficha->label(), $subtipos);
  printf("      buscando \"%s\"  ->  %s\n",
    $ficha->label(),
    $encontrados ? 'id ' . implode(', ', $encontrados) : 'NO LO ENCUENTRA');
}

// Y un nombre inventado, para ver que pasa cuando no esta.
$encontrados = $buscador->findEntities('tec_crm', $definicion->getKey('label'), 'Proveedor Que No Existe SL', $subtipos);
printf("      buscando \"Proveedor Que No Existe SL\"  ->  %s\n",
  $encontrados ? 'id ' . implode(', ', $encontrados) : 'no lo encuentra, y la fila se saltara con un aviso');

// ---------------------------------------------------------------------------
// 3. La red de seguridad: la vista.
// ---------------------------------------------------------------------------
// Si Feeds acaba enganchando una persona de contacto en lugar de una
// organizacion, el guardado no pasa: la restriccion ValidReference del campo si
// usa la vista, y la vista filtra. Aqui se mira que filtra la vista para saber
// si esa red esta puesta de verdad.
print "\n  --- 3. la vista tec_crm_references, que es la red de seguridad ---\n\n";

printf("      vista que usa el campo: %s (pantalla %s)\n",
  $ajustes['handler_settings']['view']['view_name'] ?? '(ninguna)',
  $ajustes['handler_settings']['view']['display_name'] ?? '(ninguna)');

$vista = $gestor->getStorage('view')->load($ajustes['handler_settings']['view']['view_name'] ?? '');
if (!$vista) {
  print "      la vista no existe\n";
}
else {
  $pantalla = $ajustes['handler_settings']['view']['display_name'] ?? 'default';
  $opciones = $vista->getDisplay($pantalla)['display_options'] ?? [];
  $porDefecto = $vista->getDisplay('default')['display_options'] ?? [];
  $filtros = $opciones['filters'] ?? $porDefecto['filters'] ?? [];

  printf("      tabla base: %s\n", $vista->get('base_table'));
  printf("      %d filtros:\n", count($filtros));
  foreach ($filtros as $id => $filtro) {
    printf("        %-22s operador %-6s valor %s\n",
      $filtro['field'] ?? $id,
      $filtro['operator'] ?? '=',
      is_array($filtro['value'] ?? NULL) ? implode(',', array_filter($filtro['value'])) : (string) ($filtro['value'] ?? ''));
  }
}

// Y se comprueba de verdad: se valida un material con un proveedor que sea una
// persona de contacto, si hay alguna, para ver si la restriccion lo rechaza.
print "\n  --- 4. la restriccion del campo, probada ---\n\n";

$personas = $gestor->getStorage('tec_crm')->loadByProperties(['type' => 'tec_contact_person']);
$organizaciones = $gestor->getStorage('tec_crm')->loadByProperties(['type' => 'tec_contact_organization']);

foreach ([['organizacion', $organizaciones], ['persona de contacto', $personas]] as [$que, $fichas]) {
  $ficha = reset($fichas);
  if (!$ficha) {
    printf("      no hay ninguna %s con la que probar\n", $que);
    continue;
  }

  $material = $gestor->getStorage('taxonomy_term')->create([
    'vid' => 'tec_inventory',
    'name' => 'PRUEBA DE VALIDACION ' . date('His'),
    'field_tec_vendor' => $ficha->id(),
  ]);

  $errores = [];
  foreach ($material->get('field_tec_vendor')->validate() as $violacion) {
    $errores[] = (string) $violacion->getMessage();
  }

  printf("      %-22s \"%s\"  ->  %s\n",
    $que,
    $ficha->label(),
    $errores ? 'RECHAZADO: ' . implode(' / ', $errores) : 'aceptado');
}

print "\n";
