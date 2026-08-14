<?php

/**
 * @file
 * Si un display tiene lista propia de campos y relaciones, o la hereda.
 *
 * Importa para saber donde hay que meter un campo nuevo: si el display hereda
 * y se lo meto en el maestro, se lo como todos los displays de la vista, y una
 * relacion que no usa nadie es una union mas en la consulta.
 *
 * Uso: drush php:script scripts/de-donde-saca-el-display-sus-listas
 */

$mirar = [
  'tec_inventory' => ['block_1'],
  'tec_order_material_calculation_terms' => ['block_1'],
];

$almacen = \Drupal::entityTypeManager()->getStorage('view');

foreach ($mirar as $nombre => $cuales) {
  $vista = $almacen->load($nombre);
  if (!$vista) {
    echo "\n  No existe la vista $nombre.\n";
    continue;
  }
  $displays = $vista->get('display') ?? [];

  echo "\n" . str_repeat('=', 78) . "\n";
  echo " $nombre\n";
  echo str_repeat('=', 78) . "\n";

  // Primero, quien tiene lista propia de cada cosa, para ver el reparto.
  echo "\n  Quien tiene lista propia:\n";
  foreach ($displays as $display => $datos) {
    $opciones = $datos['display_options'] ?? [];
    $tiene = [];
    foreach (['fields', 'relationships'] as $lista) {
      if (isset($opciones[$lista])) {
        $tiene[] = $lista . ' (' . count($opciones[$lista]) . ')';
      }
    }
    echo sprintf("    %-12s %s\n", $display, $tiene ? implode(', ', $tiene) : 'nada, hereda todo');
  }

  foreach ($cuales as $display) {
    $opciones = $displays[$display]['display_options'] ?? [];
    $defaults = $opciones['defaults'] ?? [];
    echo "\n  $display, en detalle:\n";
    foreach (['fields', 'relationships'] as $lista) {
      $propia = isset($opciones[$lista]);
      // La bandera dice si el display sigue al maestro. Si no esta escrita,
      // Views entiende que si.
      $sigueAlMaestro = $defaults[$lista] ?? TRUE;
      echo sprintf(
        "    %-14s lista propia: %-3s   bandera defaults: %s\n",
        $lista,
        $propia ? 'si' : 'no',
        $sigueAlMaestro ? 'sigue al maestro' : 'va por su cuenta'
      );
    }
  }
}

echo "\n";
