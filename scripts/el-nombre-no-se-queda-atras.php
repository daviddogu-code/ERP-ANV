<?php

/**
 * @file
 * Renombrar una ficha del arbol del producto le cambia el nombre de verdad.
 *
 *   php vendor\bin\drush.php scr scripts/el-nombre-no-se-queda-atras.php
 *
 * En los tres pisos el nombre no se escribe, se deduce: el del producto de
 * field_product_name, el del color de su termino de color y el de la talla de
 * su termino de talla. Y en los tres el campo `title` esta escondido en el
 * formulario, asi que nadie ve si se queda atras.
 *
 * Que se quede atras no es cosmetico. El titulo de una linea de pedido se
 * estampa desde `[productLoaded:title]` por un camino y desde
 * `[productLoaded:field_product_name]` por otro, asi que un producto con los
 * dos nombres distintos sale con un nombre en un documento y con otro en el
 * siguiente, sin que nada avise.
 *
 * Cada piso llego aqui por su lado. La talla siempre estuvo bien, porque su
 * proceso escucha el update con la condicion "ha cambiado el termino". El color
 * estuvo mal durante meses -la variacion 816 tenia termino White y titulo
 * Black- porque su proceso solo rellena el titulo cuando esta vacio, y una
 * copia nace con el titulo del original ya puesto; se arreglo el 17 de agosto
 * de 2026 recalculandolo en cada guardado.
 *
 * Se fabrica sus propias fichas y las borra al terminar.
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

$almacen = $gestor->getStorage('tec_product');
$terminos = $gestor->getStorage('taxonomy_term');

/**
 * Dos terminos cualesquiera de un vocabulario, para poder cambiar de uno a otro.
 */
$dosTerminos = function (string $vocabulario) use ($terminos): array {
  $ids = array_values($terminos->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->range(0, 2)
    ->execute());
  return count($ids) === 2 ? $ids : [];
};

$creadas = [];

print "\n";
print "El producto\n";
print str_repeat('-', 70) . "\n";

$producto = $almacen->create([
  'type' => 'tec_product',
  'field_product_name' => 'PRUEBA nombre viejo',
]);
$producto->save();
$creadas[] = $producto;

$producto = $almacen->loadUnchanged($producto->id());
$mirar('al nacer, el nombre visible sale del campo',
  trim((string) $producto->label()) === trim((string) $producto->get('field_product_name')->value),
  'se llama "' . $producto->label() . '", el campo dice "' . $producto->get('field_product_name')->value . '"');

$producto->set('field_product_name', 'PRUEBA nombre nuevo');
$producto->save();

$producto = $almacen->loadUnchanged($producto->id());
$mirar('y al renombrarlo, el nombre visible le sigue',
  trim((string) $producto->label()) === trim((string) $producto->get('field_product_name')->value),
  'se llama "' . $producto->label() . '", el campo dice "' . $producto->get('field_product_name')->value . '"');

print "\n";
print "El color\n";
print str_repeat('-', 70) . "\n";

$colores = $dosTerminos('tec_colors');
if (count($colores) !== 2) {
  print "  No hay dos colores con los que probar.\n";
}
else {
  $color = $almacen->create([
    'type' => 'tec_color_variation',
    'title' => 'PRUEBA color',
    'field_tec_colors' => [['target_id' => $colores[0]]],
  ]);
  $color->save();
  $creadas[] = $color;

  $color = $almacen->loadUnchanged($color->id());
  $mirar('al nacer, se llama como su termino de color',
    $color->label() === $terminos->load($colores[0])->label(),
    'se llama "' . $color->label() . '", el termino dice "' . $terminos->load($colores[0])->label() . '"');

  $color->set('field_tec_colors', [['target_id' => $colores[1]]]);
  $color->save();

  $color = $almacen->loadUnchanged($color->id());
  $mirar('y al cambiarle el color, el nombre le sigue',
    $color->label() === $terminos->load($colores[1])->label(),
    'se llama "' . $color->label() . '", el termino dice "' . $terminos->load($colores[1])->label() . '"');
}

print "\n";
print "La talla\n";
print str_repeat('-', 70) . "\n";

$tallas = $dosTerminos('tec_sizes');
if (count($tallas) !== 2) {
  print "  No hay dos tallas con las que probar.\n";
}
else {
  $talla = $almacen->create([
    'type' => 'tec_size_variation',
    'title' => 'PRUEBA talla',
    'field_tec_size' => [['target_id' => $tallas[0]]],
  ]);
  $talla->save();
  $creadas[] = $talla;

  $talla = $almacen->loadUnchanged($talla->id());
  $mirar('al nacer, se llama como su termino de talla',
    $talla->label() === $terminos->load($tallas[0])->label(),
    'se llama "' . $talla->label() . '", el termino dice "' . $terminos->load($tallas[0])->label() . '"');

  $talla->set('field_tec_size', [['target_id' => $tallas[1]]]);
  $talla->save();

  $talla = $almacen->loadUnchanged($talla->id());
  $mirar('y al cambiarle la talla, el nombre le sigue',
    $talla->label() === $terminos->load($tallas[1])->label(),
    'se llama "' . $talla->label() . '", el termino dice "' . $terminos->load($tallas[1])->label() . '"');
}

foreach ($creadas as $ficha) {
  $almacen->loadUnchanged($ficha->id())?->delete();
}

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Lo creado se ha borrado.\n\n";
}
else {
  printf("  %d cosas mal. Lo creado se ha borrado.\n\n", $problemas);
}
