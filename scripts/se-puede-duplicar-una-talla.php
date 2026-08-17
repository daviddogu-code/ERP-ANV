<?php

/**
 * @file
 * Duplicar una talla copia la talla y su BoM, y baja la bandera.
 *
 *   php vendor\bin\drush.php scr scripts/se-puede-duplicar-una-talla.php
 *
 * Duplicar no es un boton, es una bandera: el icono de la copia pone la bandera
 * y un proceso de ECA la escucha, fabrica la copia y baja la bandera el mismo.
 * Bajarla es el paso seis de una cadena de veinte, asi que cuando algo se
 * tuerce antes, la bandera se queda puesta y el icono se convierte en el texto
 * de fabrica "Unflag this item", que no explica nada.
 *
 * Eso paso el 17 de agosto de 2026, dos veces y por el mismo sitio. Los pasos
 * dos y tres de la cadena cargan el producto y el color de la talla leyendo
 * field_tec_product y field_tec_color_variation, y las dos tallas del unico
 * producto del sitio tenian los dos campos vacios. Cuando la accion de cargar
 * una referencia no encuentra nada responde acceso denegado -no una excepcion,
 * no un aviso- y ECA corta la rama sin escribir una linea. Cuatro intentos,
 * cuatro lineas en el registro y nada mas: ni copia, ni error, ni bandera
 * bajada. Se arreglo el producto, se volvio a pulsar, y fallo igual por el
 * color, que estaba un paso mas alla.
 *
 * Asi que esta prueba vale por dos: comprueba que duplicar hace lo que dice, y
 * de paso vigila una cadena que se rompe en silencio.
 *
 * Se fabrica su propio producto, con su color, su talla y dos lineas de
 * BoM, y lo borra todo al terminar. Se puede lanzar dos veces.
 */

$gestor = \Drupal::entityTypeManager();
$gestor->getStorage('user')->load(1);
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$problemas = 0;

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

/**
 * Los numeros que lista un campo de referencia multiple.
 */
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

// Dos materiales cualesquiera de los que ya hay, que aqui no se crean.
$materiales = array_values($gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->range(0, 2)
  ->execute());
if (count($materiales) < 2) {
  print "\n  No hay dos materiales con los que montar un BoM. No se puede probar.\n\n";
  return;
}

print "\n";
print "Montando un producto de prueba\n";
print str_repeat('-', 70) . "\n";

// Se nacen en el mismo orden que las de verdad, que es el del formulario
// anidado: primero el hijo, suelto y sin saber de quien cuelga, y despues el
// padre, que es quien lo mete en su lista. Nadie escribe a mano el camino de
// vuelta, y esa es justamente la parte que se rompia.
$producto = $almacenProducto->create(['type' => 'tec_product', 'title' => 'PRUEBA duplicar talla']);
$producto->save();

$color = $almacenProducto->create(['type' => 'tec_color_variation', 'title' => 'PRUEBA color']);
$color->save();
$producto->set('field_tec_color_variations', [$color->id()]);
$producto->save();

$talla = $almacenProducto->create([
  'type' => 'tec_size_variation',
  'title' => 'PRUEBA talla',
  'field_tec_price' => 123.45,
  'field_tec_barcode_nr' => 'PRUEBA-8888',
]);
$talla->save();
$color = $almacenProducto->loadUnchanged($color->id());
$color->set('field_tec_size_variations', [$talla->id()]);
$color->save();

$color = $almacenProducto->loadUnchanged($color->id());
$talla = $almacenProducto->loadUnchanged($talla->id());
$mirar('el color encuentra su producto sin que nadie se lo diga',
  (int) ($color->get('field_tec_product')->target_id ?? 0) === (int) $producto->id(),
  (string) ($color->get('field_tec_product')->target_id ?? 'vacio'));
$mirar('y la talla su color',
  (int) ($talla->get('field_tec_color_variation')->target_id ?? 0) === (int) $color->id(),
  (string) ($talla->get('field_tec_color_variation')->target_id ?? 'vacio'));
$mirar('y su producto',
  (int) ($talla->get('field_tec_product')->target_id ?? 0) === (int) $producto->id(),
  (string) ($talla->get('field_tec_product')->target_id ?? 'vacio'));

foreach ([['0.25', 0.25], ['1/12', 0.0833]] as $indice => [$escrito, $decimal]) {
  $linea = $almacenInventario->create([
    'type' => 'tec_bom_item',
    'title' => '',
    'field_tec_inventory' => ['target_id' => $materiales[$indice]],
    'field_tec_quantity' => $decimal,
    'field_tec_quantity_input' => $escrito,
    'field_tec_size_variation' => ['target_id' => $talla->id()],
  ]);
  $linea->save();
}

$talla = $almacenProducto->loadUnchanged($talla->id());
$bomOriginal = $lista($talla, 'field_tec_bom');
printf("  producto %s, color %s, talla %s, con %d lineas de BoM\n",
  $producto->id(), $color->id(), $talla->id(), count($bomOriginal));
$mirar('la talla de prueba tiene su BoM', count($bomOriginal) === 2, count($bomOriginal) . ' lineas');

print "\n";
print "Duplicando\n";
print str_repeat('-', 70) . "\n";

$antes = $lista($almacenProducto->loadUnchanged($color->id()), 'field_tec_size_variations');

$servicioBanderas = \Drupal::service('flag');
$bandera = $servicioBanderas->getFlagById('tec_product_duplicate_size');
$mirar('la bandera de duplicar talla existe', $bandera !== NULL);

if ($bandera) {
  try {
    $servicioBanderas->flag($bandera, $talla, $gestor->getStorage('user')->load(1));
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO: %s\n      %s\n      en %s:%d\n", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
    $problemas++;
  }
}

$despues = $lista($almacenProducto->loadUnchanged($color->id()), 'field_tec_size_variations');
$nuevas = array_values(array_diff($despues, $antes));

$mirar('el color ha ganado una talla', count($nuevas) === 1,
  count($antes) . ' antes, ' . count($despues) . ' despues');

// La bandera la baja el propio proceso, y es lo que devuelve el icono a su
// sitio. Si se queda puesta, la pantalla ensena "Unflag this item".
$banderasPuestas = $gestor->getStorage('flagging')->getQuery()
  ->accessCheck(FALSE)
  ->condition('flag_id', 'tec_product_duplicate_size')
  ->condition('entity_id', $talla->id())
  ->execute();
$mirar('y la bandera ha vuelto a bajar sola', $banderasPuestas === [],
  $banderasPuestas ? 'sigue puesta' : 'bajada');

$copia = $nuevas ? $almacenProducto->loadUnchanged(reset($nuevas)) : NULL;
if ($copia) {
  $mirar('la copia cuelga del mismo color',
    (int) ($copia->get('field_tec_color_variation')->target_id ?? 0) === (int) $color->id());
  $mirar('y del mismo producto',
    (int) ($copia->get('field_tec_product')->target_id ?? 0) === (int) $producto->id(),
    (string) ($copia->get('field_tec_product')->target_id ?? 'vacio'));
  $mirar('se lleva el precio',
    (float) ($copia->get('field_tec_price')->value ?? 0) === 123.45,
    (string) ($copia->get('field_tec_price')->value ?? 'vacio'));
  // El codigo de barras es lo unico que no se copia, y a proposito: identifica
  // una cosa, asi que dos cosas con el mismo no identifican ninguna. La copia
  // nace sin el y se le pone a mano.
  $mirar('pero no el codigo de barras, que identifica a una sola talla',
    (string) ($copia->get('field_tec_barcode_nr')->value ?? '') === '',
    (string) ($copia->get('field_tec_barcode_nr')->value ?? '') ?: 'vacio');

  // Lo que de verdad se viene a copiar: el BoM entero, con sus
  // cantidades y con el texto original de cada una.
  $bomCopia = $lista($copia, 'field_tec_bom');
  $mirar('el BoM se copia entero', count($bomCopia) === count($bomOriginal),
    count($bomCopia) . ' de ' . count($bomOriginal));

  $retrato = function (array $ids) use ($almacenInventario): array {
    $filas = [];
    foreach ($almacenInventario->loadMultiple($ids) as $linea) {
      $filas[] = ($linea->get('field_tec_inventory')->target_id ?? '?')
        . ' ' . rtrim(rtrim((string) ($linea->get('field_tec_quantity')->value ?? ''), '0'), '.')
        . ' ' . (string) ($linea->get('field_tec_quantity_input')->value ?? '');
    }
    sort($filas);
    return $filas;
  };
  $mirar('con los mismos materiales y las mismas cantidades',
    $retrato($bomCopia) === $retrato($bomOriginal),
    implode(' | ', $retrato($bomCopia)));

  $mirar('y las lineas copiadas son nuevas, no las mismas de la talla vieja',
    array_intersect($bomCopia, $bomOriginal) === []);
}

// Recoger, y de paso mirar que recoger sea de verdad recoger: las lineas de
// BoM solo se alcanzan a traves de su talla, asi que una que sobreviva a
// su talla no la vuelve a ver nadie.
print "\n";
print "Al borrar\n";
print str_repeat('-', 70) . "\n";

$lineasQueDebenIrse = $bomOriginal;
if ($copia) {
  $lineasQueDebenIrse = array_merge($lineasQueDebenIrse, $lista($copia, 'field_tec_bom'));
}
foreach ([$copia, $talla, $color, $producto] as $ficha) {
  if ($ficha) {
    try {
      $almacenProducto->loadUnchanged($ficha->id())?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar %s: %s\n", $ficha->id(), $e->getMessage());
    }
  }
}
$sobrevivientes = array_keys($almacenInventario->loadMultiple($lineasQueDebenIrse));
$mirar('el BoM se va con su talla', $sobrevivientes === [],
  $sobrevivientes ? 'se han quedado ' . implode(', ', $sobrevivientes) : count($lineasQueDebenIrse) . ' lineas retiradas');
foreach ($almacenInventario->loadMultiple($sobrevivientes) as $linea) {
  $linea->delete();
}

print str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Lo creado se ha borrado.\n\n";
}
else {
  printf("  %d cosas mal.\n\n", $problemas);
}
