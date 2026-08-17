<?php

/**
 * @file
 * Que ninguna variacion pierda a su producto, y que el BoM diga un solo
 * numero cuando solo hay un numero que decir.
 *
 *   php vendor\bin\drush.php scr scripts/la-talla-no-pierde-su-producto.php
 *
 * Dos fallos que salieron el mismo dia probando la cadena de ventas a mano, y
 * que no se parecen en nada salvo en la causa: algo que solo se hacia una vez,
 * al crear la ficha, y que nadie volvia a mirar.
 *
 *   1. El atajo al producto. Cada color y cada talla guardan, ademas del enlace
 *      a su padre, un enlace directo al producto. Lo rellenaban dos ECA que
 *      escuchan solo el insert y solo actuan si el enlace al padre ya esta
 *      puesto en ese instante, asi que una talla nacida dentro del formulario
 *      anidado se quedaba sin el para siempre. La fila seguia saliendo, pero su
 *      lapiz de editar fabricaba /tec_product/ sin numero detras: se guardaba
 *      bien y se aterrizaba en un 404.
 *
 *   2. La columna "Production requires" del BoM. Ensena el numero
 *      calculado y, cuando aporta algo, el texto original. Decidia si aportaba
 *      comparando letra por letra, asi que escribir 0.25 salia como
 *      "0.2500 · 0.25". La pregunta buena no es si se parecen sino si lo
 *      escrito es un numero o es una formula.
 *
 * Deshace todo lo que crea. Se puede lanzar dos veces.
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
 * Lo que pinta la columna del BoM para un texto escrito a mano.
 */
$columna = function (string $escrito) use ($gestor): string {
  $linea = $gestor->getStorage('tec_inventory')->create([
    'type' => 'tec_bom_item',
    'title' => 'prueba',
    'field_tec_quantity_input' => $escrito,
  ]);
  $pintado = tec_inventory_ief_production_requires_column($linea);
  return (string) ($pintado['#markup'] ?? '');
};

$basura = [];

print "\n";
print "La columna del BoM\n";
print str_repeat('-', 70) . "\n";

// Un numero es un numero, tenga los ceros que tenga detras. El de la izquierda
// ya lo dice.
$mirar('un 0.25 escrito a mano sale una sola vez', $columna('0.25') === '0.2500', $columna('0.25'));
$mirar('y un 1 tambien', $columna('1') === '1.0000', $columna('1'));
$mirar('y da igual como se escriban los ceros', $columna('0.2500') === '0.2500', $columna('0.2500'));

// Una formula si aporta: dice de donde sale el numero, y ademas es la que usan
// los documentos de corte para no perder precision al invertirla.
$mirar('una formula se sigue viendo al lado de su resultado', $columna('1/12') === '0.0833 · 1/12', $columna('1/12'));

// Texto que el interprete no sabe masticar. No hay numero que ensenar, asi que
// lo que se escribio tiene que quedar a la vista, no desaparecer sin avisar.
$mirar('lo que no es un numero ni una formula no se esconde', $columna('dos vueltas') === 'dos vueltas', $columna('dos vueltas'));

print "\n";
print "El atajo al producto\n";
print str_repeat('-', 70) . "\n";

$almacen = $gestor->getStorage('tec_product');

$producto = $almacen->create(['type' => 'tec_product', 'title' => 'PRUEBA hilo del producto']);
$producto->save();
$basura[] = $producto;

$color = $almacen->create([
  'type' => 'tec_color_variation',
  'title' => 'PRUEBA color',
  'field_tec_product' => $producto->id(),
]);
$color->save();
$basura[] = $color;

// El caso corriente: la talla nace con su color puesto.
$talla = $almacen->create([
  'type' => 'tec_size_variation',
  'title' => 'PRUEBA talla',
  'field_tec_color_variation' => $color->id(),
]);
$talla->save();
$basura[] = $talla;
$mirar('una talla creada con su color sale ya atada al producto',
  (int) ($talla->get('field_tec_product')->target_id ?? 0) === (int) $producto->id(),
  (string) ($talla->get('field_tec_product')->target_id ?? 'vacio'));

// El caso que rompia: la talla nace huerfana, como dentro del formulario
// anidado, y el color le llega despues. Antes esa segunda oportunidad no
// existia, porque solo se miraba en el insert.
$tardia = $almacen->create(['type' => 'tec_size_variation', 'title' => 'PRUEBA talla tardia']);
$tardia->save();
$basura[] = $tardia;
$mirar('una talla sin color todavia no puede saberlo, y no se lo inventa',
  $tardia->get('field_tec_product')->isEmpty(),
  (string) ($tardia->get('field_tec_product')->target_id ?? 'vacio'));

$tardia->set('field_tec_color_variation', $color->id());
$tardia->save();
$mirar('pero en cuanto le llega el color queda atada',
  (int) ($tardia->get('field_tec_product')->target_id ?? 0) === (int) $producto->id(),
  (string) ($tardia->get('field_tec_product')->target_id ?? 'vacio'));

// Y el color que tampoco lo sabe: se deduce de quien lo tenga en su lista.
$perdido = $almacen->create(['type' => 'tec_color_variation', 'title' => 'PRUEBA color perdido']);
$perdido->save();
$basura[] = $perdido;

$lista = array_column($producto->get('field_tec_color_variations')->getValue(), 'target_id');
$lista[] = $perdido->id();
$producto->set('field_tec_color_variations', $lista);
$producto->save();

$perdido->save();
$mirar('un color sin atajo lo saca de quien lo tiene en su lista',
  (int) ($perdido->get('field_tec_product')->target_id ?? 0) === (int) $producto->id(),
  (string) ($perdido->get('field_tec_product')->target_id ?? 'vacio'));

// Recoger.
foreach (array_reverse($basura) as $ficha) {
  try {
    $ficha->delete();
  }
  catch (\Throwable $e) {
    printf("  aviso: no se ha podido borrar %s %s: %s\n", $ficha->bundle(), $ficha->id(), $e->getMessage());
  }
}

print "\n";
print str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Lo creado se ha borrado.\n\n";
}
else {
  printf("  %d cosas mal.\n\n", $problemas);
}
