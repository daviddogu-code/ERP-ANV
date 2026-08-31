<?php

/**
 * @file
 * Duplicar un color no ensucia el registro, y la copia nace entera.
 *
 *   php vendor/bin/drush.php scr scripts/el-duplicado-no-ensucia-el-registro.php
 *
 * Un clic disparaba una veintena de Logs de depuracion (tokens rotos,
 * titulos copiados de otro proceso). Esta prueba fabrica un producto con un
 * color, dos tallas y BoM, duplica el color por la misma bandera que el boton,
 * y comprueba que la copia es independiente y que el canal eca no escribe esas
 * frases.
 */

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$problemas = 0;
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$lista = function ($ficha, string $campo): array {
  if (!$ficha || !$ficha->hasField($campo) || $ficha->get($campo)->isEmpty()) {
    return [];
  }
  return array_map(
    static fn(array $valor): int => (int) $valor['target_id'],
    $ficha->get($campo)->getValue()
  );
};

$almacenProducto = $gestor->getStorage('tec_product');
$almacenInventario = $gestor->getStorage('tec_inventory');
$materiales = array_values($gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->range(0, 2)
  ->execute());
$tallasVocab = array_values($gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_sizes')
  ->range(0, 2)
  ->execute());
if (count($materiales) < 2 || count($tallasVocab) < 2) {
  print "\n  Faltan materiales o tallas de vocabulario. No se puede probar.\n\n";
  return;
}

print "\nMontando un producto de prueba\n";
print str_repeat('-', 70) . "\n";

$producto = $almacenProducto->create(['type' => 'tec_product', 'title' => 'PRUEBA duplicar color log']);
$producto->save();

$color = $almacenProducto->create(['type' => 'tec_color_variation', 'title' => 'PRUEBA color origen']);
$color->save();
$producto = $almacenProducto->loadUnchanged($producto->id());
$producto->set('field_tec_color_variations', [$color->id()]);
$producto->save();

$tallaIds = [];
foreach ($tallasVocab as $i => $tid) {
  $talla = $almacenProducto->create([
    'type' => 'tec_size_variation',
    'title' => 'PRUEBA talla ' . $i,
    'field_tec_price' => 10 + $i,
    'field_tec_barcode_nr' => 'PRUEBA-C-' . $i,
    'field_tec_size' => ['target_id' => $tid],
  ]);
  $talla->save();
  $tallaIds[] = (int) $talla->id();
  foreach ([['0.25', 0.25], ['1', 1]] as [$escrito, $decimal]) {
    $linea = $almacenInventario->create([
      'type' => 'tec_bom_item',
      'title' => '',
      'field_tec_inventory' => ['target_id' => $materiales[$i % 2]],
      'field_tec_quantity' => $decimal,
      'field_tec_quantity_input' => $escrito,
    ]);
    $linea->save();
    $talla = $almacenProducto->loadUnchanged($talla->id());
    $bom = $lista($talla, 'field_tec_bom');
    $bom[] = (int) $linea->id();
    $talla->set('field_tec_bom', $bom);
    $talla->save();
  }
}
$color = $almacenProducto->loadUnchanged($color->id());
$color->set('field_tec_size_variations', $tallaIds);
$color->save();

$color = $almacenProducto->loadUnchanged($color->id());
printf("  producto %s, color %s, %d tallas\n", $producto->id(), $color->id(), count($tallaIds));

$desde = (int) \Drupal::database()->query('SELECT MAX(wid) FROM {watchdog}')->fetchField();

print "\nDuplicando el color\n";
print str_repeat('-', 70) . "\n";

$antes = $lista($almacenProducto->loadUnchanged($producto->id()), 'field_tec_color_variations');
$servicioBanderas = \Drupal::service('flag');
$bandera = $servicioBanderas->getFlagById('tec_product_duplicate_color');
$mirar('la bandera de duplicar color existe', $bandera !== NULL);

if ($bandera) {
  try {
    $servicioBanderas->flag($bandera, $color, $gestor->getStorage('user')->load(1));
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO: %s\n      %s\n", get_class($e), $e->getMessage());
    $problemas++;
  }
}

$despues = $lista($almacenProducto->loadUnchanged($producto->id()), 'field_tec_color_variations');
$nuevas = array_values(array_diff($despues, $antes));
$mirar('el producto ha ganado un color', count($nuevas) === 1,
  count($antes) . ' antes, ' . count($despues) . ' despues');

$banderasPuestas = $gestor->getStorage('flagging')->getQuery()
  ->accessCheck(FALSE)
  ->condition('flag_id', 'tec_product_duplicate_color')
  ->condition('entity_id', $color->id())
  ->execute();
$mirar('y la bandera ha vuelto a bajar sola', $banderasPuestas === [],
  $banderasPuestas ? 'sigue puesta' : 'bajada');

$copia = $nuevas ? $almacenProducto->loadUnchanged(reset($nuevas)) : NULL;
if ($copia) {
  $tallasCopia = $lista($copia, 'field_tec_size_variations');
  $mirar('la copia cuelga del mismo producto',
    (int) ($copia->get('field_tec_product')->target_id ?? 0) === (int) $producto->id());
  $mirar('trae las mismas tallas en numero',
    count($tallasCopia) === count($tallaIds),
    count($tallasCopia) . ' de ' . count($tallaIds));
  $mirar('y no comparte las fichas de talla',
    array_intersect($tallasCopia, $tallaIds) === []);

  $bomOrigen = [];
  foreach ($tallaIds as $tid) {
    $bomOrigen = array_merge($bomOrigen, $lista($almacenProducto->load($tid), 'field_tec_bom'));
  }
  $bomCopia = [];
  foreach ($tallasCopia as $tid) {
    $bomCopia = array_merge($bomCopia, $lista($almacenProducto->load($tid), 'field_tec_bom'));
  }
  $mirar('el BoM se copia entero', count($bomCopia) === count($bomOrigen),
    count($bomCopia) . ' de ' . count($bomOrigen));
  $mirar('y no comparte lineas de BoM',
    array_intersect($bomCopia, $bomOrigen) === []);
}

$frases = [
  '%token__',
  'token__entity',
  'token__bomOriginal',
  'Running TEC Product: Duplicate',
  'Running: "TEC Color variation',
  'Running: "TEC Product: Update Size',
  'Running "TEC Product: Insert Size',
  'Set field data on sub-entities',
  'Insert Color entity',
  'BoM list is not yet empty',
  'Duplicated color',
  'Duplicated size',
  'The size name is:',
  'Quanitty value is',
  'Finished running: "TEC Color variation',
];
$nuevos = \Drupal::database()->select('watchdog', 'w')
  ->fields('w', ['wid', 'type', 'message', 'variables'])
  ->condition('wid', $desde, '>')
  ->execute()
  ->fetchAll();
$sucios = [];
$ecaMsgs = [];
foreach ($nuevos as $fila) {
  $texto = $fila->message . ' ' . (is_string($fila->variables) ? $fila->variables : '');
  if ($fila->type === 'eca') {
    $ecaMsgs[] = preg_replace('/\s+/', ' ', strip_tags($fila->message));
  }
  foreach ($frases as $aguja) {
    if (str_contains($texto, $aguja)) {
      $sucios[] = $aguja;
      break;
    }
  }
}
$eca = count($ecaMsgs);
$mirar('el registro no trae las frases de depuracion', $sucios === [],
  $sucios ? implode(', ', array_unique($sucios)) : count($nuevos) . ' lineas nuevas, ' . $eca . ' de eca');
$mirar('y el canal eca queda en silencio', $eca === 0);
if ($ecaMsgs) {
  foreach (array_unique($ecaMsgs) as $msg) {
    printf("         eca: %s\n", substr($msg, 0, 120));
  }
}

print "\nBorrando la prueba\n";
print str_repeat('-', 70) . "\n";
if ($copia) {
  $copia->delete();
}
$color = $almacenProducto->load($color->id());
if ($color) {
  $color->delete();
}
$producto = $almacenProducto->load($producto->id());
if ($producto) {
  $producto->delete();
}

print "\n";
if ($problemas) {
  print "  $problemas fallos.\n\n";
  throw new \RuntimeException("el duplicado de color no quedo limpio ($problemas)");
}
print "  Todo bien.\n\n";
