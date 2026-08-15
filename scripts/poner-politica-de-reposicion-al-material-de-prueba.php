<?php

/**
 * @file
 * Le pone punto de pedido al material del juego de pruebas que ya existe.
 *
 *   php vendor/bin/drush scr scripts/poner-politica-de-reposicion-al-material-de-prueba.php
 *
 * fabricar-el-juego-de-pruebas.php ya crea el material con su politica de
 * reposicion, pero el juego de pruebas de esta base se fabrico antes de que la
 * lista de compra existiera, y su material no tiene punto de pedido. Sin punto
 * de pedido no entra en /purchase, y esa pantalla no se puede probar.
 *
 * Esto no vuelve a fabricar el juego: solo rellena los cuatro campos que
 * faltan, y unicamente en los materiales que llevan PRUEBA en el nombre. Los
 * materiales de verdad no se tocan, que su politica de reposicion es una
 * decision del dueno y no de un script.
 */

$terminos = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$ids = $terminos->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->condition('name', 'PRUEBA', 'CONTAINS')
  ->execute();

if (!$ids) {
  echo "\n  No hay ningun material de prueba. Fabrica el juego primero.\n\n";
  return;
}

// Los mismos numeros que pone el fabricante, para que el juego salga igual se
// haya hecho antes o despues de la lista de compra.
$politica = [
  'field_tec_reorder_point' => '100.00',
  'field_tec_safety_stock' => '20.00',
  'field_tec_moq_quantity' => 5,
  'field_tec_lead_time_days' => 14,
];

echo "\n";
foreach ($terminos->loadMultiple($ids) as $material) {
  $puestos = [];
  foreach ($politica as $campo => $valor) {
    if (!$material->hasField($campo)) {
      continue;
    }
    if (!$material->get($campo)->isEmpty()) {
      continue;
    }
    $material->set($campo, $valor);
    $puestos[] = $campo;
  }

  if (!$puestos) {
    printf("  %s: ya tenia su politica, no se toca\n", $material->label());
    continue;
  }

  $material->save();
  printf("  %s: %d campos puestos (%s)\n", $material->label(), count($puestos), implode(', ', $puestos));
}
echo "\n";
