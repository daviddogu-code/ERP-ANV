<?php

/**
 * @file
 * Borrar una talla, un color o un producto no deja nada colgando.
 *
 *   php vendor\bin\drush.php scr scripts/borrar-no-deja-restos.php
 *
 * El arbol tiene cuatro pisos -producto, color, talla, lineas de BoM- y hasta
 * el 18 de agosto de 2026 el borrado solo se llevaba el del medio. La rutina de
 * ECA que lo hacia tiene dos acciones de borrar y las dos dicen "borrar la
 * talla", asi que al borrar un producto sus colores se quedaban de pie, sin
 * tallas y apuntando a un producto que ya no contesta. Las lineas de BoM no se
 * las llevaba nadie nunca, y esas son peores: a una linea solo se llega a
 * traves de su talla, asi que cuando la talla desaparece la linea no la vuelve
 * a ver nadie desde ninguna pantalla.
 *
 * De un solo producto de prueba borrado el 14 de agosto quedaron los dos
 * restos: el color 888 sobrevivio al producto 887, y las lineas 29700 y 29701
 * sobrevivieron a la talla 889.
 *
 * Y hay un tercer resto, mas callado: Drupal no quita el numero del hijo de la
 * lista del padre. Un numero muerto en medio de la lista no se ve, porque la
 * pantalla se salta lo que no puede cargar, pero la rutina de ECA recorre esa
 * lista y se para en cuanto una entrada no carga, asi que basta uno para que
 * las tallas que van detras se salven del borrado.
 *
 * Aqui se monta un arbol entero y se va desmontando de abajo arriba, mirando
 * despues de cada golpe que no quede nada de lo que colgaba y que siga en pie
 * lo que no tenia por que caerse.
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

$almacenProducto = $gestor->getStorage('tec_product');
$almacenInventario = $gestor->getStorage('tec_inventory');

/**
 * Cuantas de estas fichas siguen existiendo.
 */
$vivas = function (string $almacen, array $ids) use ($gestor): array {
  $ids = array_filter($ids);
  return $ids ? array_keys($gestor->getStorage($almacen)->loadMultiple($ids)) : [];
};

/**
 * Los numeros que lista un campo de referencia multiple.
 */
$lista = function ($ficha, string $campo): array {
  if (!$ficha || !$ficha->hasField($campo) || $ficha->get($campo)->isEmpty()) {
    return [];
  }
  return array_map(static fn(array $v): int => (int) $v['target_id'], $ficha->get($campo)->getValue());
};

$materiales = array_values($gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->range(0, 2)
  ->execute());
if (count($materiales) < 2) {
  print "\n  No hay dos materiales con los que montar un BoM. No se puede probar.\n\n";
  return;
}

/**
 * Una talla colgada de su color, con dos lineas de BoM.
 */
$nuevaTalla = function (int $color, bool $enLaLista) use ($almacenProducto, $almacenInventario, $materiales): array {
  $talla = $almacenProducto->create([
    'type' => 'tec_size_variation',
    'title' => 'PRUEBA talla',
    'field_tec_color_variation' => $color,
  ]);
  $talla->save();
  foreach ($materiales as $indice => $material) {
    $almacenInventario->create([
      'type' => 'tec_bom_item',
      'title' => '',
      'field_tec_inventory' => ['target_id' => $material],
      'field_tec_quantity' => 1 + $indice,
      'field_tec_size_variation' => ['target_id' => $talla->id()],
    ])->save();
  }
  // Nacer ya la mete en la lista del color, asi que la talla suelta hay que
  // sacarla a mano: es un estado al que se llega borrando mal, no creando.
  $ficha = $almacenProducto->loadUnchanged($color);
  $ya = $ficha->get('field_tec_size_variations')->isEmpty()
    ? []
    : array_map(static fn(array $v): int => (int) $v['target_id'], $ficha->get('field_tec_size_variations')->getValue());
  $ya = array_values(array_diff($ya, [(int) $talla->id()]));
  if ($enLaLista) {
    $ya[] = (int) $talla->id();
  }
  $ficha->set('field_tec_size_variations', $ya);
  $ficha->save();
  $talla = $almacenProducto->loadUnchanged($talla->id());
  $bom = $talla->get('field_tec_bom')->isEmpty()
    ? []
    : array_map(static fn(array $v): int => (int) $v['target_id'], $talla->get('field_tec_bom')->getValue());
  return [(int) $talla->id(), $bom];
};

print "\n";
print "Borrar una talla\n";
print str_repeat('-', 70) . "\n";

$producto = $almacenProducto->create(['type' => 'tec_product', 'title' => 'PRUEBA borrado']);
$producto->save();
$color = $almacenProducto->create(['type' => 'tec_color_variation', 'title' => 'PRUEBA color']);
$color->save();
$producto->set('field_tec_color_variations', [$color->id()]);
$producto->save();

[$talla, $bom] = $nuevaTalla((int) $color->id(), TRUE);
$mirar('la talla de prueba nace con sus dos lineas', count($bom) === 2, count($bom) . ' lineas');

$almacenProducto->loadUnchanged($talla)->delete();

$mirar('la talla se va', $vivas('tec_product', [$talla]) === []);
$mirar('y se lleva sus lineas de BoM', $vivas('tec_inventory', $bom) === [],
  implode(', ', $vivas('tec_inventory', $bom)));
$mirar('el color no se queda con el numero muerto en su lista',
  !in_array($talla, $lista($almacenProducto->loadUnchanged($color->id()), 'field_tec_size_variations'), TRUE),
  implode(', ', $lista($almacenProducto->loadUnchanged($color->id()), 'field_tec_size_variations')) ?: 'lista vacia');
$mirar('y ni el color ni el producto se caen con ella',
  count($vivas('tec_product', [(int) $color->id(), (int) $producto->id()])) === 2);

print "\n";
print "Borrar un color\n";
print str_repeat('-', 70) . "\n";

[$talla2, $bom2] = $nuevaTalla((int) $color->id(), TRUE);
[$talla3, $bom3] = $nuevaTalla((int) $color->id(), TRUE);
$almacenProducto->loadUnchanged($color->id())->delete();

$mirar('el color se va', $vivas('tec_product', [(int) $color->id()]) === []);
$mirar('y se lleva sus dos tallas', $vivas('tec_product', [$talla2, $talla3]) === [],
  implode(', ', $vivas('tec_product', [$talla2, $talla3])));
$mirar('y las lineas de BoM de las dos', $vivas('tec_inventory', array_merge($bom2, $bom3)) === [],
  implode(', ', $vivas('tec_inventory', array_merge($bom2, $bom3))));
$mirar('el producto no se queda con el numero muerto en su lista',
  $lista($almacenProducto->loadUnchanged($producto->id()), 'field_tec_color_variations') === [],
  implode(', ', $lista($almacenProducto->loadUnchanged($producto->id()), 'field_tec_color_variations')) ?: 'lista vacia');
$mirar('y el producto sigue en pie', $vivas('tec_product', [(int) $producto->id()]) !== []);

print "\n";
print "Borrar un producto\n";
print str_repeat('-', 70) . "\n";

$color2 = $almacenProducto->create([
  'type' => 'tec_color_variation',
  'title' => 'PRUEBA color dos',
  'field_tec_product' => $producto->id(),
]);
$color2->save();
$producto = $almacenProducto->loadUnchanged($producto->id());
$producto->set('field_tec_color_variations', [$color2->id()]);
$producto->save();

[$talla4, $bom4] = $nuevaTalla((int) $color2->id(), TRUE);
// Y una talla que cuelga del color pero que el color no tiene apuntada, que es
// como quedan las que nacen en mal orden. Antes se salvaban del borrado.
[$talla5, $bom5] = $nuevaTalla((int) $color2->id(), FALSE);
$mirar('hay una talla que su color no tiene en la lista',
  !in_array($talla5, $lista($almacenProducto->loadUnchanged($color2->id()), 'field_tec_size_variations'), TRUE));

$almacenProducto->loadUnchanged($producto->id())->delete();

$mirar('el producto se va', $vivas('tec_product', [(int) $producto->id()]) === []);
$mirar('y esta vez tambien su color', $vivas('tec_product', [(int) $color2->id()]) === [],
  implode(', ', $vivas('tec_product', [(int) $color2->id()])));
$mirar('las dos tallas, la apuntada y la suelta', $vivas('tec_product', [$talla4, $talla5]) === [],
  implode(', ', $vivas('tec_product', [$talla4, $talla5])));
$mirar('y todas sus lineas de BoM', $vivas('tec_inventory', array_merge($bom4, $bom5)) === [],
  implode(', ', $vivas('tec_inventory', array_merge($bom4, $bom5))));

// Recoger lo que hubiera sobrevivido a la prueba, que es justo lo que la prueba
// esta buscando y no deberia existir.
foreach ([
  ['tec_inventory', array_merge($bom, $bom2, $bom3, $bom4, $bom5)],
  ['tec_product', [$talla, $talla2, $talla3, $talla4, $talla5, (int) $color->id(), (int) $color2->id(), (int) $producto->id()]],
] as [$almacen, $ids]) {
  foreach ($gestor->getStorage($almacen)->loadMultiple(array_filter($ids)) as $sobra) {
    $sobra->delete();
  }
}

print "\n";
print str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. No queda nada de lo creado.\n\n";
}
else {
  printf("  %d cosas mal.\n\n", $problemas);
}
