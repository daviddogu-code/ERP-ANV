<?php

/**
 * @file
 * Duplicar un producto copia el arbol entero, y no comparte nada con el viejo.
 *
 *   php vendor\bin\drush.php scr scripts/se-puede-duplicar-un-producto.php
 *
 * Es el tercero y el mayor de los tres botones de duplicar. Duplicar una talla
 * recorre un bucle, su BoM. Duplicar un color recorre dos, sus tallas y el BoM
 * de cada una. Duplicar un producto recorre tres, y por tanto tiene tres sitios
 * donde quedarse a medias sin decir nada: ECA le pide permiso a la accion que
 * carga una referencia, y esa accion responde acceso denegado cuando no hay
 * nada que cargar, no una excepcion. La rama se corta y el registro no dice
 * nada. Por eso aqui se mira la bandera: si sigue puesta al terminar, el
 * proceso empezo y no llego al final, y en pantalla el icono se convierte en el
 * texto de fabrica "Unflag this item".
 *
 * Lo que se vigila de fondo no es que la copia se parezca al original, que eso
 * se ve en pantalla, sino que no compartan piezas. Dos productos que apunten a
 * las mismas lineas de BoM se ven identicos y correctos, y el dia que alguien
 * cambie una cantidad en uno la cambia en el otro sin enterarse.
 *
 * Se fabrica su propio arbol -un producto, dos colores, cuatro tallas y ocho
 * lineas de BoM- y lo borra todo al terminar. Se puede lanzar dos veces.
 */

$gestor = \Drupal::entityTypeManager();
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
print "Montando un producto de prueba con dos colores y cuatro tallas\n";
print str_repeat('-', 70) . "\n";

$producto = $almacenProducto->create(['type' => 'tec_product', 'title' => 'PRUEBA duplicar producto']);
$producto->save();

$colores = [];
$tallasPorColor = [];
$bomOriginal = [];

foreach (['PRUEBA color uno', 'PRUEBA color dos'] as $indiceColor => $tituloColor) {
  $color = $almacenProducto->create(['type' => 'tec_color_variation', 'title' => $tituloColor]);
  $color->save();

  $tallas = [];
  foreach (['PRUEBA talla A', 'PRUEBA talla B'] as $indiceTalla => $tituloTalla) {
    $talla = $almacenProducto->create([
      'type' => 'tec_size_variation',
      'title' => $tituloTalla,
      'field_tec_price' => 100 + $indiceColor * 10 + $indiceTalla,
      'field_tec_barcode_nr' => 'PRUEBA-' . $indiceColor . $indiceTalla,
    ]);
    $talla->save();
    $tallas[] = (int) $talla->id();

    foreach ([['0.25', 0.25], ['1/12', 0.0833]] as $indiceLinea => [$escrito, $decimal]) {
      $linea = $almacenInventario->create([
        'type' => 'tec_bom_item',
        'title' => '',
        'field_tec_inventory' => ['target_id' => $materiales[$indiceLinea]],
        'field_tec_quantity' => $decimal,
        'field_tec_quantity_input' => $escrito,
        'field_tec_size_variation' => ['target_id' => $talla->id()],
      ]);
      $linea->save();
    }
  }

  $color = $almacenProducto->loadUnchanged($color->id());
  $color->set('field_tec_size_variations', $tallas);
  $color->save();

  $colores[] = (int) $color->id();
  $tallasPorColor[(int) $color->id()] = $tallas;
}

$producto->set('field_tec_color_variations', $colores);
$producto->save();

foreach ($tallasPorColor as $tallas) {
  foreach ($tallas as $idTalla) {
    $bomOriginal = array_merge($bomOriginal, $lista($almacenProducto->loadUnchanged($idTalla), 'field_tec_bom'));
  }
}

printf("  producto %s, colores %s, %d tallas, %d lineas de BoM\n",
  $producto->id(), implode(' y ', $colores), count($bomOriginal) / 2, count($bomOriginal));
$mirar('el arbol de prueba esta entero', count($bomOriginal) === 8, count($bomOriginal) . ' lineas');

print "\n";
print "Duplicando\n";
print str_repeat('-', 70) . "\n";

$productosAntes = array_map('intval', array_values($almacenProducto->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_product')
  ->execute()));

$servicioBanderas = \Drupal::service('flag');
$bandera = $servicioBanderas->getFlagById('tec_product_duplicate');
$mirar('la bandera de duplicar producto existe', $bandera !== NULL);

if ($bandera) {
  try {
    $servicioBanderas->flag($bandera, $almacenProducto->loadUnchanged($producto->id()), $gestor->getStorage('user')->load(1));
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO: %s\n      %s\n      en %s:%d\n", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
    $problemas++;
  }
}

$productosDespues = array_map('intval', array_values($almacenProducto->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_product')
  ->execute()));
$nuevos = array_values(array_diff($productosDespues, $productosAntes));

$mirar('ha nacido un producto', count($nuevos) === 1,
  count($productosAntes) . ' antes, ' . count($productosDespues) . ' despues');

$banderasPuestas = $gestor->getStorage('flagging')->getQuery()
  ->accessCheck(FALSE)
  ->condition('flag_id', 'tec_product_duplicate')
  ->condition('entity_id', $producto->id())
  ->execute();
$mirar('y la bandera ha vuelto a bajar sola', $banderasPuestas === [],
  $banderasPuestas ? 'sigue puesta' : 'bajada');

$copia = $nuevos ? $almacenProducto->loadUnchanged(reset($nuevos)) : NULL;
$coloresCopia = [];
$tallasCopia = [];
$bomCopia = [];

if ($copia) {
  $coloresCopia = $lista($copia, 'field_tec_color_variations');
  $mirar('con sus dos colores', count($coloresCopia) === 2, count($coloresCopia) . ' de 2');

  foreach ($coloresCopia as $idColor) {
    $color = $almacenProducto->loadUnchanged($idColor);
    $mirar('el color ' . $idColor . ' cuelga del producto nuevo',
      (int) ($color->get('field_tec_product')->target_id ?? 0) === (int) $copia->id(),
      (string) ($color->get('field_tec_product')->target_id ?? 'vacio'));

    $tallas = $lista($color, 'field_tec_size_variations');
    $tallasCopia = array_merge($tallasCopia, $tallas);
    $mirar('y se lleva sus dos tallas', count($tallas) === 2, count($tallas) . ' de 2');

    foreach ($tallas as $idTalla) {
      $talla = $almacenProducto->loadUnchanged($idTalla);
      $mirar('la talla ' . $idTalla . ' apunta a su color y a su producto nuevos',
        (int) ($talla->get('field_tec_color_variation')->target_id ?? 0) === $idColor
        && (int) ($talla->get('field_tec_product')->target_id ?? 0) === (int) $copia->id(),
        'color ' . ($talla->get('field_tec_color_variation')->target_id ?? 'vacio')
        . ', producto ' . ($talla->get('field_tec_product')->target_id ?? 'vacio'));
      $bomCopia = array_merge($bomCopia, $lista($talla, 'field_tec_bom'));
    }
  }

  $mirar('las cuatro tallas estan', count($tallasCopia) === 4, count($tallasCopia) . ' de 4');
  $mirar('y las ocho lineas de BoM', count($bomCopia) === 8, count($bomCopia) . ' de 8');

  // Lo que la pantalla no distingue: una copia de verdad de un decorado que
  // apunta a las piezas del original.
  $mirar('ningun color es el mismo que uno del original',
    array_intersect($coloresCopia, $colores) === []);
  $mirar('ninguna talla es la misma que una del original',
    array_intersect($tallasCopia, array_merge(...array_values($tallasPorColor))) === []);
  $mirar('y ninguna linea de BoM es la misma',
    array_intersect($bomCopia, $bomOriginal) === []);

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
  $mirar('las cantidades llegan iguales, con su texto original',
    $retrato($bomCopia) === $retrato($bomOriginal),
    implode(' | ', array_slice($retrato($bomCopia), 0, 2)) . ' ...');

  $precios = [];
  $codigos = [];
  foreach ($almacenProducto->loadMultiple($tallasCopia) as $talla) {
    $precios[] = (string) ($talla->get('field_tec_price')->value ?? '');
    $codigo = (string) ($talla->get('field_tec_barcode_nr')->value ?? '');
    if ($codigo !== '') {
      $codigos[] = $codigo;
    }
  }
  sort($precios);
  $mirar('los precios se copian', $precios === ['100.00', '101.00', '110.00', '111.00'],
    implode(', ', $precios));
  $mirar('y ningun codigo de barras viaja, que identifica a una sola talla',
    $codigos === [], $codigos ? implode(', ', $codigos) : 'las cuatro vacias');
}

print "\n";
print "Al borrar\n";
print str_repeat('-', 70) . "\n";

$debenIrse = array_merge($bomOriginal, $bomCopia);
$fichasDebenIrse = array_merge(
  [(int) $producto->id()],
  $colores,
  array_merge(...array_values($tallasPorColor)),
  $copia ? [(int) $copia->id()] : [],
  $coloresCopia,
  $tallasCopia
);

foreach ([$copia ? (int) $copia->id() : NULL, (int) $producto->id()] as $id) {
  if ($id) {
    try {
      $almacenProducto->loadUnchanged($id)?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar %s: %s\n", $id, $e->getMessage());
    }
  }
}

$fichasVivas = array_keys($almacenProducto->loadMultiple($fichasDebenIrse));
$mirar('el producto se lleva colores y tallas, los dos arboles', $fichasVivas === [],
  $fichasVivas ? 'quedan ' . implode(', ', $fichasVivas) : count($fichasDebenIrse) . ' fichas retiradas');

$lineasVivas = array_keys($almacenInventario->loadMultiple($debenIrse));
$mirar('y las dieciseis lineas de BoM se van con sus tallas', $lineasVivas === [],
  $lineasVivas ? 'quedan ' . implode(', ', $lineasVivas) : count($debenIrse) . ' lineas retiradas');

foreach ($almacenProducto->loadMultiple($fichasVivas) as $ficha) {
  $ficha->delete();
}
foreach ($almacenInventario->loadMultiple($lineasVivas) as $linea) {
  $linea->delete();
}

print str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Lo creado se ha borrado.\n\n";
}
else {
  printf("  %d cosas mal.\n\n", $problemas);
}
