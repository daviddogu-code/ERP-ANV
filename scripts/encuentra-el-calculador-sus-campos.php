<?php

/**
 * @file
 * Comprueba que el calculador de costes encuentra sus cinco campos.
 *
 * El JavaScript del formulario de materiales busca cinco casillas por su
 * identificador exacto. Si alguno no coincide se calla y no calcula, sin dar
 * error: el material se guarda con los dos costes vacios y nadie se entera.
 * Este guion dibuja el formulario y busca los cinco identificadores dentro.
 *
 * Uso: drush php:script scripts/encuentra-el-calculador-sus-campos
 */

// Los mismos cinco que nombra js/inventory-calculator.js.
$necesita = [
  'edit-field-tec-cost-0-value' => 'coste de compra, que es lo que se teclea',
  'edit-field-tec-units-0-value' => 'factor compra a inventario',
  'edit-field-tec-split-into-0-value' => 'factor inventario a consumo',
  'edit-field-tec-price-uos-0-value' => 'coste de inventario, calculado',
  'edit-field-tec-price-0-value' => 'coste de consumo, calculado',
];

// El formulario es de administracion, asi que hace falta quien pueda verlo.
$cuentas = \Drupal::entityTypeManager()->getStorage('user');
$admin = $cuentas->load(1);
\Drupal::currentUser()->setAccount($admin);

$termino = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create([
  'vid' => 'tec_inventory',
  'name' => 'no se guarda',
]);
$formulario = \Drupal::service('entity.form_builder')->getForm($termino, 'default');
$html = (string) \Drupal::service('renderer')->renderRoot($formulario);

echo "\n==============================================================================\n";
echo " Los cinco campos que busca el calculador\n";
echo "==============================================================================\n\n";

$faltan = 0;
foreach ($necesita as $id => $que) {
  $esta = str_contains($html, 'id="' . $id . '"');
  if (!$esta) {
    $faltan++;
  }
  echo sprintf("  %-8s %-38s %s\n", $esta ? 'esta' : 'FALTA', $id, $que);
}

echo "\n";
echo $faltan === 0
  ? "  Los cinco. El calculador va a funcionar.\n"
  : "  Faltan $faltan. El calculador no calcularia, y no daria error.\n";

// Y que la clase por la que se engancha siga en el formulario.
$clase = str_contains($html, 'taxonomy-term-tec-inventory-form');
echo $clase
  ? "  La clase por la que se engancha esta en el formulario.\n"
  : "  NO esta la clase taxonomy-term-tec-inventory-form: no se engancharia.\n";

// Los campos de Oscar no deberian aparecer por ningun lado.
$restos = [];
foreach (['field-tec-price-by-package', 'field-tec-price-uou', 'field-uop-to-uos', 'field-tec-package-units', 'field-tec-uou-split-qty'] as $viejo) {
  if (str_contains($html, $viejo)) {
    $restos[] = $viejo;
  }
}
echo $restos
  ? '  Todavia salen en el formulario: ' . implode(', ', $restos) . "\n"
  : "  Ninguno de los campos de Oscar sale en el formulario.\n";

echo "\n";
