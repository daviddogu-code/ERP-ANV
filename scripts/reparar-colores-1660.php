<?php

/**
 * @file
 * Repara colores Black huerfanos del producto 1660.
 *
 *   php vendor\bin\drush.php scr scripts/reparar-colores-1660.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('tec_product');
$producto = $storage->load(1660);
if (!$producto) {
  print "Producto 1660 no encontrado.\n";
  return;
}

$colores = [1685, 1686];
$lista = $producto->get('field_tec_color_variations')->getValue();
$ids = array_map('intval', array_column($lista, 'target_id'));

foreach ($colores as $color_id) {
  $color = $storage->load($color_id);
  if (!$color) {
    print "Color $color_id no encontrado.\n";
    continue;
  }
  if ($color->get('field_tec_product')->isEmpty()) {
    $color->set('field_tec_product', 1660);
    $color->save();
    print "Color $color_id: field_tec_product = 1660\n";
  }
  if (!in_array((int) $color_id, $ids, TRUE)) {
    $lista[] = ['target_id' => $color_id];
    $ids[] = (int) $color_id;
    print "Color $color_id: anadido a field_tec_color_variations\n";
  }
}

$producto->set('field_tec_color_variations', $lista);
$producto->save();

print 'Producto 1660 colores: ' . implode(', ', $ids) . "\n";
