<?php

/**
 * Abre las portadas del ERP y dice que bloques lleva cada una dentro.
 *
 * Las doce portadas son nodos montados con Layout Builder, y por eso el
 * enganche entre una portada y la vista que ensena no esta en la configuracion
 * ni en ninguna columna de texto: vive serializado en el campo
 * `layout_builder__layout`, en una columna binaria. El primer barrido en busca
 * de Super BOM no lo vio por eso mismo.
 *
 * De paso queda el mapa completo: que pantalla ensena que, que es la pregunta
 * que aparece cada vez que se quiere retirar algo.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-hay-en-las-portadas.php
 */

const AGUJAS = ['superbom', 'super_bom'];

$almacen = \Drupal::entityTypeManager()->getStorage('node');
$ids = $almacen->getQuery()->accessCheck(FALSE)->sort('nid')->execute();

$sospechosos = [];

print "\n";

foreach ($almacen->loadMultiple($ids) as $nodo) {
  $ruta = '(sin ruta)';
  try {
    $ruta = $nodo->toUrl()->toString();
  }
  catch (\Throwable $e) {
  }

  printf("  nodo %-4s %-16s %s\n", $nodo->id(), $ruta, $nodo->label());

  if (!$nodo->hasField('layout_builder__layout')) {
    print "        (sin Layout Builder)\n\n";
    continue;
  }

  $secciones = $nodo->get('layout_builder__layout');
  if ($secciones->isEmpty()) {
    print "        (sin secciones)\n\n";
    continue;
  }

  foreach ($secciones as $i => $elemento) {
    // El objeto de la seccion se saca por propiedad, no con un getter: la clase
    // del campo lo resuelve en su __get().
    $seccion = $elemento->section;
    printf("        seccion %d: %s\n", $i + 1, $seccion->getLayoutId());

    foreach ($seccion->getComponents() as $componente) {
      $plugin = $componente->getPluginId();
      $config = $componente->get('configuration');
      $etiqueta = $config['label'] ?? '';
      $visible = ($config['label_display'] ?? '') ? 'con titulo' : '';

      printf("            %-52s %s %s\n", $plugin, $etiqueta, $visible);

      foreach (AGUJAS as $aguja) {
        if (stripos($plugin, $aguja) !== FALSE) {
          $sospechosos[] = 'nodo ' . $nodo->id() . ' (' . $ruta . '): ' . $plugin;
        }
      }
    }
  }
  print "\n";
}

// Por si algun dia hay Layout Builder en otro sitio que no sean los nodos.
print "  --- otros sitios con Layout Builder ---\n\n";
$otros = 0;
foreach (\Drupal::service('entity_field.manager')->getFieldMapByFieldType('layout_section') as $tipo => $campos) {
  foreach (array_keys($campos) as $campo) {
    printf("      %s.%s\n", $tipo, $campo);
    $otros++;
  }
}
print $otros ? "\n" : "      solo los nodos\n\n";

print "  --- resultado ---\n\n";
if ($sospechosos) {
  print "  Super BOM aparece en:\n";
  foreach ($sospechosos as $s) {
    print '      ' . $s . "\n";
  }
}
else {
  print "  Ninguna portada ensena las vistas de Super BOM.\n";
}
print "\n";
