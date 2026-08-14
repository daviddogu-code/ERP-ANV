<?php

/**
 * @file
 * Comprueba que los dos costes derivados salen sin navegador.
 *
 * Es la prueba que de verdad importa: hasta ahora los calculaba un JavaScript
 * del formulario, asi que un material creado por codigo -el importador, un
 * duplicado de ECA, un guion- se guardaba con los dos vacios, y son los dos
 * numeros por los que dividen todas las vistas de coste.
 *
 * Crea un material de mentira, lo guarda, mira los costes, comprueba tambien
 * los casos raros y lo borra siempre, pase lo que pase.
 *
 * Uso: drush php:script scripts/salen-los-costes-por-codigo
 */

use Drupal\taxonomy\Entity\Term;

$almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Las tres unidades y los dos factores de un material que ya existe, para no
// inventarse referencias que no van a ningun sitio.
$modelo = $almacen->loadByProperties(['vid' => 'tec_inventory', 'name' => 'khun']);
$modelo = reset($modelo);
if (!$modelo) {
  echo "\n  No encuentro el material de pruebas 'khun'. Sin el no hay unidades que copiar.\n\n";
  return;
}

$unidades = [
  'field_tec_unit_purchase' => $modelo->get('field_tec_unit_purchase')->target_id,
  'field_tec_uos' => $modelo->get('field_tec_uos')->target_id,
  'field_tec_unit_use' => $modelo->get('field_tec_unit_use')->target_id,
];

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Los costes derivados, creando materiales por codigo\n";
echo str_repeat('=', 78) . "\n";

// Cada caso: coste de compra, factor a inventario, factor a consumo, y lo que
// tendria que quedar guardado en los dos costes.
$casos = [
  'el del material de pruebas' => [80, 0.40, 10000, '200.00', '0.020000'],
  'uno barato, que con dos decimales se perdia' => [80, 1, 10000, '80.00', '0.008000'],
  'uno con division infinita' => [100, 3, 7, '33.33', '4.761905'],
  'sin factor a consumo' => [80, 0.40, NULL, NULL, NULL],
  'sin coste de compra' => [NULL, 0.40, 10000, NULL, NULL],
];

$bien = 0;
$mal = 0;

foreach ($casos as $comoSeLlama => [$coste, $aInventario, $aConsumo, $esperadoUos, $esperadoConsumo]) {
  $material = NULL;
  try {
    $valores = [
      'vid' => 'tec_inventory',
      'name' => 'PRUEBA COSTES ' . substr(md5($comoSeLlama), 0, 6),
    ] + $unidades;
    if ($coste !== NULL) {
      $valores['field_tec_cost'] = $coste;
    }
    if ($aInventario !== NULL) {
      $valores['field_tec_units'] = $aInventario;
    }
    if ($aConsumo !== NULL) {
      $valores['field_tec_split_into'] = $aConsumo;
    }

    $material = Term::create($valores);
    $material->save();

    // Se vuelve a leer de la base, no del objeto en memoria, para estar seguros
    // de que lo que se comprueba es lo que quedo guardado.
    $almacen->resetCache([$material->id()]);
    $guardado = $almacen->load($material->id());

    $uos = $guardado->get('field_tec_price_uos')->isEmpty() ? NULL : $guardado->get('field_tec_price_uos')->value;
    $consumo = $guardado->get('field_tec_price')->isEmpty() ? NULL : $guardado->get('field_tec_price')->value;

    $acierta = $uos === $esperadoUos && $consumo === $esperadoConsumo;
    $acierta ? $bien++ : $mal++;

    printf("\n  %s %s\n", $acierta ? '[BIEN]' : '[MAL] ', $comoSeLlama);
    printf("      compra %s, a inventario %s, a consumo %s\n",
      $coste ?? 'vacio', $aInventario ?? 'vacio', $aConsumo ?? 'vacio');
    printf("      inventario: %-12s esperado %s\n", $uos ?? 'vacio', $esperadoUos ?? 'vacio');
    printf("      consumo:    %-12s esperado %s\n", $consumo ?? 'vacio', $esperadoConsumo ?? 'vacio');
  }
  finally {
    // Se borra pase lo que pase: un material de mentira que se queda dentro
    // ensucia el inventario y descuadra la comprobacion del ERP.
    if ($material !== NULL && !$material->isNew()) {
      $material->delete();
    }
  }
}

echo "\n" . str_repeat('-', 78) . "\n";
echo " Casos correctos: $bien. Fallados: $mal.\n";
echo $mal === 0
  ? " Los costes salen solos, sin navegador.\n"
  : " Hay casos que no salen como deberian.\n";
echo str_repeat('-', 78) . "\n\n";
