<?php

/**
 * @file
 * Vuelve a guardar los materiales para que se rellenen los costes derivados.
 *
 * Los dos costes los calcula ahora el servidor al guardar, asi que basta con
 * guardar un material para que aparezcan. Sirve para los que se crearon por
 * codigo y quedaron vacios, y para repasar los que se guardaron cuando el
 * campo de consumo solo admitia dos decimales.
 *
 * Por defecto solo cuenta lo que cambiaria. Para hacerlo de verdad:
 *   drush php:script scripts/recalcular-los-costes-de-un-material -- de-verdad
 */

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

$almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$ids = $almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->execute();

echo "\n";
echo str_repeat('=', 78) . "\n";
echo ' Costes derivados de ' . count($ids) . ' material(es)' . ($deVerdad ? '' : '   (solo mirando)') . "\n";
echo str_repeat('=', 78) . "\n\n";

$cambian = 0;
$igual = 0;
$sinDatos = 0;

// La comparacion tiene que ser numerica. La base devuelve "200.00" y el calculo
// devuelve el numero 200, que como texto no se parecen en nada, asi que
// comparando cadenas saldrian todos los materiales como si cambiaran.
$mismoNumero = static function ($uno, $otro): bool {
  $unoVacio = $uno === NULL || $uno === '';
  $otroVacio = $otro === NULL || $otro === '';
  if ($unoVacio || $otroVacio) {
    return $unoVacio && $otroVacio;
  }
  return abs((float) $uno - (float) $otro) < 0.0000005;
};

foreach ($almacen->loadMultiple($ids) as $material) {
  $antesUos = $material->get('field_tec_price_uos')->value;
  $antesConsumo = $material->get('field_tec_price')->value;

  // El mismo calculo que hace el gancho, sin guardar, para poder ensenar antes
  // y despues sin tocar nada en la pasada en seco.
  $copia = clone $material;
  _tec_inventory_apply_derived_costs($copia);
  $despuesUos = $copia->get('field_tec_price_uos')->value;
  $despuesConsumo = $copia->get('field_tec_price')->value;

  if ($despuesUos === NULL && $despuesConsumo === NULL) {
    $sinDatos++;
  }

  if ($mismoNumero($antesUos, $despuesUos) && $mismoNumero($antesConsumo, $despuesConsumo)) {
    $igual++;
    continue;
  }

  $cambian++;
  printf(
    "  %-28s inventario %-12s -> %-12s consumo %-12s -> %s\n",
    mb_strimwidth($material->label(), 0, 28, '...'),
    $antesUos ?? 'vacio',
    $despuesUos ?? 'vacio',
    $antesConsumo ?? 'vacio',
    $despuesConsumo ?? 'vacio'
  );

  if ($deVerdad) {
    $material->save();
  }
}

echo "\n" . str_repeat('-', 78) . "\n";
echo " Cambian: $cambian. Ya estaban bien: $igual.\n";
if ($sinDatos) {
  echo " Sin coste de compra o sin alguno de los dos factores, y por eso vacios: $sinDatos.\n";
}
if (!$deVerdad && $cambian) {
  echo "\n Para hacerlo de verdad:\n";
  echo "   drush php:script scripts/recalcular-los-costes-de-un-material -- de-verdad\n";
}
echo str_repeat('-', 78) . "\n\n";
