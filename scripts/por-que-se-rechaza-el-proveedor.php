<?php

/**
 * @file
 * Averigua por que el campo de proveedor rechaza a los proveedores que hay.
 *
 * Uso: php vendor/bin/drush.php scr scripts/por-que-se-rechaza-el-proveedor.php
 *
 * Al validar un material con la organizacion "KJ" en field_tec_vendor, el nucleo
 * responde que esa ficha no se puede referenciar, y de paso avisa de que "the
 * reference view /tec_crm_references/ cannot be found". Si eso es verdad, el
 * campo obligatorio del proveedor rechaza cualquier valor, y entonces el
 * importador no puede crear ni un material: la validacion esta encendida.
 *
 * Aqui se separan las dos cosas que pueden estar pasando: que la pantalla de la
 * vista que declara el campo no exista, o que exista y su filtro deje fuera a
 * las organizaciones que hay.
 *
 * No toca nada.
 */

$gestor = \Drupal::entityTypeManager();

$definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'tec_inventory');
$ajustes = $definiciones['field_tec_vendor']->getSettings();
$nombreVista = $ajustes['handler_settings']['view']['view_name'] ?? '';
$nombrePantalla = $ajustes['handler_settings']['view']['display_name'] ?? '';

print "\n";
print "  --- 1. que pide el campo y que hay ---\n\n";
printf("      el campo pide la vista  %s\n", $nombreVista ?: '(ninguna)');
printf("      y la pantalla           %s\n\n", $nombrePantalla ?: '(ninguna)');

$vista = $gestor->getStorage('view')->load($nombreVista);

if (!$vista) {
  printf("      la vista %s NO EXISTE\n", $nombreVista);
}
else {
  printf("      la vista existe, esta %s\n", $vista->status() ? 'encendida' : 'APAGADA');
  print "      pantallas que tiene:\n\n";
  foreach ($vista->get('display') as $id => $pantalla) {
    $marca = ($id === $nombrePantalla) ? '  <-- la que pide el campo' : '';
    printf("        %-24s %-26s %s%s\n",
      $id,
      $pantalla['display_plugin'] ?? '?',
      $pantalla['display_title'] ?? '',
      $marca);
  }
  printf("\n      la pantalla %s %s\n", $nombrePantalla,
    isset($vista->get('display')[$nombrePantalla]) ? 'existe' : 'NO EXISTE: por eso el campo rechaza todo');
}

// ---------------------------------------------------------------------------
// 2. El filtro por tipo de contacto.
// ---------------------------------------------------------------------------
// Aunque la pantalla exista, la vista filtra por field_tec_contact_type. Si las
// organizaciones no llevan ese termino, tampoco salen.
print "\n  --- 2. el filtro por tipo de contacto ---\n\n";

print "      los terminos del vocabulario tec_crm_contact_type:\n\n";
foreach ($gestor->getStorage('taxonomy_term')->loadByProperties(['vid' => 'tec_crm_contact_type']) as $termino) {
  printf("        tid %-6s %s\n", $termino->id(), $termino->label());
}

print "\n      el tipo de contacto de cada ficha del CRM:\n\n";
foreach ($gestor->getStorage('tec_crm')->loadMultiple() as $ficha) {
  $tipos = [];
  if ($ficha->hasField('field_tec_contact_type')) {
    foreach ($ficha->get('field_tec_contact_type') as $elemento) {
      $tipos[] = $elemento->target_id . ' (' . ($elemento->entity ? $elemento->entity->label() : 'termino borrado') . ')';
    }
  }
  printf("        id %-5s %-26s subtipo %-28s tipo de contacto: %s\n",
    $ficha->id(), $ficha->label(), $ficha->bundle(), $tipos ? implode(', ', $tipos) : '(vacio)');
}

// ---------------------------------------------------------------------------
// 3. Que devuelve el manejador de seleccion del campo.
// ---------------------------------------------------------------------------
// Esto es lo que de verdad decide: es el mismo objeto que usa la restriccion
// ValidReference al guardar, y tambien el desplegable del formulario.
print "\n  --- 3. que devuelve el manejador de seleccion del campo ---\n\n";

try {
  $manejador = \Drupal::service('plugin.manager.entity_reference_selection')
    ->getSelectionHandler($definiciones['field_tec_vendor']);
  printf("      manejador: %s\n", get_class($manejador));

  $opciones = $manejador->getReferenceableEntities();
  $cuantas = 0;
  foreach ($opciones as $subtipo => $etiquetas) {
    foreach ($etiquetas as $id => $etiqueta) {
      printf("      elegible: id %-5s %s (subtipo %s)\n", $id, strip_tags((string) $etiqueta), $subtipo);
      $cuantas++;
    }
  }
  if (!$cuantas) {
    print "      NINGUNA ficha es elegible: el campo obligatorio no se puede rellenar\n";
  }

  // Y la validacion de los ids que hay, uno por uno.
  print "\n      validacion de los ids que hay:\n";
  $todas = array_keys($gestor->getStorage('tec_crm')->loadMultiple());
  $validos = $manejador->validateReferenceableEntities($todas);
  foreach ($todas as $id) {
    printf("        id %-5s %s\n", $id, in_array($id, $validos) ? 'valido' : 'RECHAZADO');
  }
}
catch (\Throwable $e) {
  printf("      el manejador se cae: %s: %s\n", get_class($e), $e->getMessage());
}

// ---------------------------------------------------------------------------
// 4. Y como estan los otros campos que la plantilla va a rellenar.
// ---------------------------------------------------------------------------
// Si la validacion del proveedor esta rota, conviene saber si los demas campos
// de referencia obligatorios estan bien antes de dar el importador por bueno.
print "\n  --- 4. los otros campos de referencia obligatorios ---\n\n";

$aProbar = [
  'field_tec_material_type' => ['tec_materials'],
  'field_tec_unit_purchase' => ['tec_units'],
  'field_tec_uos' => ['tec_units'],
  'field_tec_unit_use' => ['tec_units'],
  'field_tec_colors' => ['tec_colors'],
];

foreach ($aProbar as $nombre => $vocabularios) {
  $terminos = $gestor->getStorage('taxonomy_term')->loadByProperties(['vid' => $vocabularios]);
  $uno = reset($terminos);
  if (!$uno) {
    printf("      %-26s no hay ningun termino en %s\n", $nombre, implode(',', $vocabularios));
    continue;
  }
  $material = $gestor->getStorage('taxonomy_term')->create([
    'vid' => 'tec_inventory',
    'name' => 'PRUEBA ' . date('His'),
    $nombre => $uno->id(),
  ]);
  $errores = [];
  foreach ($material->get($nombre)->validate() as $violacion) {
    $errores[] = (string) $violacion->getMessage();
  }
  printf("      %-26s con \"%s\" (tid %s): %s\n",
    $nombre, $uno->label(), $uno->id(),
    $errores ? 'RECHAZADO: ' . strip_tags(implode(' / ', $errores)) : 'aceptado');
}

print "\n";
