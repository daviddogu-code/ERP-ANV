<?php

/**
 * @file
 * Un producto a medias no desaparece de la marca: se nombra debajo del catalogo.
 *
 *   php vendor\bin\drush.php scr scripts/el-catalogo-nombra-lo-que-no-puede-ensenar.php
 *
 * El catalogo de una marca es una lista de tallas. Un producto sin color no
 * tiene ninguna fila, y un color sin talla tampoco. Hasta el 19 de agosto de
 * 2026 el boton + Product de esa pantalla mandaba de vuelta a la marca, asi que
 * al guardar un producto nuevo caias en una lista donde no estabas, sin otra
 * puerta que el enlace del mensaje de estado. El dueno se lo encontro creando
 * RISE THAI KICK PAD 1 STRAP desde Rise Fight Gear.
 *
 * Ahora el destination se ha ido, la cabecera lleva Organize, y lo que la
 * tabla no puede pintar se nombra debajo: BrandGaps. Organize marca las mismas
 * filas, con la misma pregunta.
 *
 * Monta su propio juego -- una marca, cuatro productos: pelado, solo color,
 * color y talla, y uno mixto -- y lo borra al final.
 */

use Drupal\tec_production\BrandGaps;
use Drupal\tec_production\Form\BrandProductOrderForm;
use Drupal\views\Views;

const RASTRO = 'PRUEBA producto a medias';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');

$problemas = 0;
$creadas = [];

$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$seccion = function (string $que): void {
  print "\n" . $que . "\n" . str_repeat('-', 70) . "\n";
};

$barrer = function () use ($terminos, $productos): int {
  $barridas = 0;
  $ids = $productos->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_product_name', RASTRO, 'STARTS_WITH')
    ->execute();
  foreach ($productos->loadMultiple($ids) as $pieza) {
    $pieza->delete();
    $barridas++;
  }
  // Los colores y las tallas no llevan el nombre del producto: se buscan por
  // titulo, que ECA rellena a partir del nombre al guardar.
  $ids = $productos->getQuery()
    ->accessCheck(FALSE)
    ->condition('title', RASTRO, 'CONTAINS')
    ->execute();
  foreach ($productos->loadMultiple($ids) as $pieza) {
    $pieza->delete();
    $barridas++;
  }
  $ids = $terminos->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'tec_brands')
    ->condition('name', RASTRO, 'STARTS_WITH')
    ->execute();
  foreach ($terminos->loadMultiple($ids) as $marca) {
    $marca->delete();
    $barridas++;
  }
  return $barridas;
};

$pintar = static function (string $vista, string $pantalla, array $args): string {
  $build = views_embed_view($vista, $pantalla, ...$args);
  if (!is_array($build)) {
    $build = ['#markup' => 'NADA'];
  }
  return (string) \Drupal::service('renderer')->renderPlain($build);
};

$corto = static function ($producto): string {
  if (!$producto) {
    return '?';
  }
  $nombre = (string) ($producto->get('field_product_name')->value ?? $producto->label());
  return mb_strtolower(trim(str_ireplace(RASTRO, '', $nombre)));
};

$barridas = $barrer();

print "\n" . str_repeat('=', 70) . "\n";
print "  El catalogo nombra lo que no puede ensenar\n";
print str_repeat('=', 70) . "\n";
if ($barridas) {
  printf("  antes barro %d fichas que dejo una carrera anterior\n", $barridas);
}

$seccion('Montando el juego');

$marca = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' marca']);
$marca->save();
$creadas[] = $marca;

$pelado = $productos->create([
  'type' => 'tec_product',
  'field_product_name' => RASTRO . ' pelado',
  'field_tec_brand' => [['target_id' => $marca->id()]],
]);
$pelado->save();
$creadas[] = $pelado;

$soloColor = $productos->create([
  'type' => 'tec_product',
  'field_product_name' => RASTRO . ' solo color',
  'field_tec_brand' => [['target_id' => $marca->id()]],
]);
$soloColor->save();
$creadas[] = $soloColor;

$colorHuero = $productos->create([
  'type' => 'tec_color_variation',
  'title' => RASTRO . ' solo color negro',
]);
$colorHuero->save();
$creadas[] = $colorHuero;
$soloColor = $productos->loadUnchanged($soloColor->id());
$soloColor->set(BrandGaps::COLOURS, [$colorHuero->id()]);
$soloColor->save();

$entero = $productos->create([
  'type' => 'tec_product',
  'field_product_name' => RASTRO . ' entero',
  'field_tec_brand' => [['target_id' => $marca->id()]],
]);
$entero->save();
$creadas[] = $entero;

$colorEntero = $productos->create([
  'type' => 'tec_color_variation',
  'title' => RASTRO . ' entero negro',
]);
$colorEntero->save();
$creadas[] = $colorEntero;

$tallaEntera = $productos->create([
  'type' => 'tec_size_variation',
  'title' => RASTRO . ' entero talla',
]);
$tallaEntera->save();
$creadas[] = $tallaEntera;

$colorEntero = $productos->loadUnchanged($colorEntero->id());
$colorEntero->set(BrandGaps::SIZES, [$tallaEntera->id()]);
$colorEntero->save();
$entero = $productos->loadUnchanged($entero->id());
$entero->set(BrandGaps::COLOURS, [$colorEntero->id()]);
$entero->save();

// Un producto con un color que si tiene talla y otro que no: el catalogo
// ensena las tallas del primero y el aviso tiene que nombrar el segundo.
$mixto = $productos->create([
  'type' => 'tec_product',
  'field_product_name' => RASTRO . ' mixto',
  'field_tec_brand' => [['target_id' => $marca->id()]],
]);
$mixto->save();
$creadas[] = $mixto;

$colorMixtoOk = $productos->create(['type' => 'tec_color_variation', 'title' => RASTRO . ' mixto negro']);
$colorMixtoOk->save();
$creadas[] = $colorMixtoOk;
$colorMixtoHuero = $productos->create(['type' => 'tec_color_variation', 'title' => RASTRO . ' mixto oro']);
$colorMixtoHuero->save();
$creadas[] = $colorMixtoHuero;
$tallaMixta = $productos->create(['type' => 'tec_size_variation', 'title' => RASTRO . ' mixto talla']);
$tallaMixta->save();
$creadas[] = $tallaMixta;

$colorMixtoOk = $productos->loadUnchanged($colorMixtoOk->id());
$colorMixtoOk->set(BrandGaps::SIZES, [$tallaMixta->id()]);
$colorMixtoOk->save();
$mixto = $productos->loadUnchanged($mixto->id());
$mixto->set(BrandGaps::COLOURS, [$colorMixtoOk->id(), $colorMixtoHuero->id()]);
$mixto->save();

$productos->resetCache();
$pelado = $productos->loadUnchanged($pelado->id());
$soloColor = $productos->loadUnchanged($soloColor->id());
$entero = $productos->loadUnchanged($entero->id());
$mixto = $productos->loadUnchanged($mixto->id());

printf("  marca %s, cuatro productos\n", $marca->id());

$seccion('BrandGaps sabe quien falta y por que');

$mirar('el pelado no tiene color', !empty(BrandGaps::of($pelado)['blank']));
$mirar('el de solo color nombra ese color',
  count(BrandGaps::of($soloColor)['colours'] ?? []) === 1,
  BrandGaps::reason(BrandGaps::of($soloColor)));
$mirar('el entero no es un hueco', BrandGaps::of($entero) === []);
$mirar('el mixto nombra el color sin talla',
  count(BrandGaps::of($mixto)['colours'] ?? []) === 1
  && (string) BrandGaps::of($mixto)['colours'][0]->id() === (string) $colorMixtoHuero->id());
$mirar('y cuenta las tallas que si tiene', BrandGaps::sizeCount($mixto) === 1,
  (string) BrandGaps::sizeCount($mixto));

$enLaMarca = BrandGaps::inBrand((int) $marca->id());
$mirar('la marca tiene tres huecos, el entero no esta',
  count($enLaMarca) === 3
  && isset($enLaMarca[$pelado->id()], $enLaMarca[$soloColor->id()], $enLaMarca[$mixto->id()])
  && !isset($enLaMarca[$entero->id()]),
  implode(', ', array_map($corto, array_column($enLaMarca, 'product'))));
$mirar('y el mas nuevo va primero',
  (int) array_key_first($enLaMarca) === (int) $mixto->id(),
  (string) (array_key_first($enLaMarca) ?: 'vacio'));

$seccion('El catalogo no los pinta, y los nombra debajo');

$html = $pintar('tec_products', 'brand_catalogue', [(string) $marca->id()]);

$mirar('el + Product ya no te tira a la marca',
  (bool) preg_match('/add\/tec_product\?brand_id=' . $marca->id() . '(?![^"]*&destination=)/', $html)
  && !str_contains($html, 'add/tec_product?brand_id=' . $marca->id() . '&destination=/taxonomy/term/'));
$mirar('y al lado esta Organize',
  str_contains($html, '/taxonomy/term/' . $marca->id() . '/organize'));

$mirar('el entero si sale en la tabla', str_contains($html, $corto($entero) === '' ? 'entero' : (string) $entero->label())
  || str_contains(mb_strtolower($html), 'entero'));
$mirar('el pelado no es una fila',
  !str_contains(mb_strtolower($html), 'pelado') || str_contains($html, 'tec-brand-gaps'));

$aviso = str_contains($html, 'tec-brand-gaps');
$mirar('hay un aviso debajo de la tabla', $aviso);
$mirar('nombra al pelado', $aviso && str_contains(mb_strtolower($html), 'pelado'));
$mirar('nombra al de solo color', $aviso && str_contains(mb_strtolower($html), 'solo color'));
$mirar('nombra al mixto', $aviso && str_contains(mb_strtolower($html), 'mixto'));
$mirar('y dice que son 3', $aviso && str_contains($html, '3 products are not on the list above yet'));

$seccion('Organize los lista a todos y marca los que faltan');

$formulario = \Drupal::formBuilder()->getForm(BrandProductOrderForm::class, $marca);
$buildForm = $formulario;
$pintaForm = (string) \Drupal::service('renderer')->renderPlain($buildForm);

$mirar('Organize tiene columna de tallas',
  isset($formulario['table']['#header'][5])
  && (string) $formulario['table']['#header'][5]['data'] === 'Sizes');

foreach ([
  $pelado->id() => TRUE,
  $soloColor->id() => TRUE,
  $mixto->id() => TRUE,
  $entero->id() => FALSE,
] as $pid => $falta) {
  $clases = $formulario['table'][$pid]['#attributes']['class'] ?? [];
  $marcado = in_array('tec-brand-order__row--gap', $clases, TRUE);
  $mirar(
    $corto($productos->load($pid)) . ($falta ? ' sale marcado' : ' no sale marcado'),
    $marcado === $falta
  );
}

$mirar('y el pelado esta en la tabla, aunque el catalogo no lo pinte',
  isset($formulario['table'][$pelado->id()]));

$seccion('Al completar un color, sale del aviso');

$colorHuero = $productos->loadUnchanged($colorHuero->id());
$tallaNueva = $productos->create([
  'type' => 'tec_size_variation',
  'title' => RASTRO . ' solo color talla',
]);
$tallaNueva->save();
$creadas[] = $tallaNueva;
$colorHuero->set(BrandGaps::SIZES, [$tallaNueva->id()]);
$colorHuero->save();
$productos->resetCache([$soloColor->id(), $colorHuero->id()]);

$mirar('el de solo color ya no es un hueco', BrandGaps::of($productos->loadUnchanged($soloColor->id())) === []);
$html = $pintar('tec_products', 'brand_catalogue', [(string) $marca->id()]);
$mirar('el aviso baja a 2', str_contains($html, '2 products are not on the list above yet'));
$aviso = '';
if (preg_match('/class="tec-brand-gaps[\s"][\s\S]*?<\/div>\s*<\/div>/', $html, $trozo)) {
  $aviso = $trozo[0];
}
$mirar('y ese producto ya no se nombra en el aviso', !str_contains(mb_strtolower($aviso), 'solo color'));
$mirar('pero si sale ya en la tabla', str_contains(mb_strtolower($html), 'solo color'));

$seccion('Una marca sin huecos no pone aviso');

$otra = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' otra']);
$otra->save();
$creadas[] = $otra;
$huecosDeOtra = BrandGaps::inBrand((int) $otra->id());
$mirar('una marca vacia no tiene huecos que nombrar', $huecosDeOtra === []);

foreach (array_reverse($creadas) as $pieza) {
  $pieza = $pieza->getEntityTypeId() === 'tec_product'
    ? $productos->loadUnchanged($pieza->id())
    : $terminos->loadUnchanged($pieza->id());
  $pieza?->delete();
}
$barrer();

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. El juego de prueba se ha borrado.\n\n";
}
else {
  printf("  %d cosas mal. El juego de prueba se ha borrado.\n\n", $problemas);
}
