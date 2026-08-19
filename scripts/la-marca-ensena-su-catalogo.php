<?php

/**
 * @file
 * La ficha de una marca ensena sus productos, y solo los suyos.
 *
 *   php vendor\bin\drush.php scr scripts/la-marca-ensena-su-catalogo.php
 *
 * Comprueba las tres puertas del catalogo:
 *
 * - La pantalla `brand_catalogue` de la vista de productos devuelve las tallas de
 *   la marca que se le pasa, ni una mas ni una menos.
 * - La ficha del termino de la marca las ensena, o sea que el campo que la
 *   embebe esta puesto y el argumento le llega.
 * - En /b la baldosa lleva a la ficha y el lapiz al formulario de edicion, que
 *   antes eran la misma cosa.
 *
 * El catalogo era una fila por producto y desde el 19 de agosto de 2026 es una
 * fila por talla, porque el precio de venta y el coste viven en la talla. Por eso
 * el juego de prueba de aqui no es un producto suelto: un producto sin colores ni
 * tallas no tiene ninguna fila que ensenar. Lo que cada fila dice -precio
 * editable y coste de materiales- se prueba aparte, en
 * `el-catalogo-de-la-marca-dice-precio-y-coste.php`.
 *
 * Crea el arbol entero -producto, color y talla- en dos marcas para poder
 * distinguir, y lo borra al final. Si no hay dos marcas, lo dice y no prueba nada.
 */

use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

$marcas = array_values($gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_brands')
  ->sort('tid')
  ->range(0, 2)
  ->execute());

if (count($marcas) < 2) {
  print "\n  Hacen falta dos marcas para poder distinguir. Hay " . count($marcas) . ".\n\n";
  return;
}

[$laNuestra, $laOtra] = $marcas;
$almacen = $gestor->getStorage('tec_product');

/**
 * Un producto de una marca, con su color y su talla, que es lo que hace fila.
 *
 * Devuelve el producto y la talla. El color queda enganchado por los dos lados
 * porque el catalogo sube de la talla al color y del color al producto por la
 * lista del padre, igual que la ficha del producto.
 *
 * La talla se deja a proposito sin termino de talla: asi esta prueba tambien
 * vigila que una talla a medio crear siga saliendo en el catalogo, con la casilla
 * de talla vacia, en vez de desaparecer sin decir nada.
 */
$arbol = static function (string $nombre, $marca) use ($almacen): array {
  $producto = $almacen->create([
    'type' => 'tec_product',
    'field_product_name' => $nombre,
    'field_tec_brand' => [['target_id' => $marca]],
  ]);
  $producto->save();

  $color = $almacen->create(['type' => 'tec_color_variation', 'title' => $nombre . ' color']);
  $color->save();

  $talla = $almacen->create(['type' => 'tec_size_variation', 'title' => $nombre . ' talla']);
  $talla->save();

  $color = $almacen->loadUnchanged($color->id());
  $color->set('field_tec_size_variations', [$talla->id()]);
  $color->save();

  $producto->set('field_tec_color_variations', [$color->id()]);
  $producto->save();

  return [$almacen->loadUnchanged($producto->id()), $talla, $color];
};

[$mio, $miTalla, $miColor] = $arbol('PRUEBA catalogo de la marca', $laNuestra);
[$ajeno, $tallaAjena, $colorAjeno] = $arbol('PRUEBA catalogo de la otra', $laOtra);

// Se preguntan los nombres en vez de darlos por sabidos: al guardar, el ERP
// pone en mayuscula la inicial de cada palabra, asi que el nombre que sale en
// pantalla no es el que se acaba de escribir.
$seLlama = $almacen->loadUnchanged($mio->id())->label();
$seLlamaLaOtra = $almacen->loadUnchanged($ajeno->id())->label();

print "\n";
print "La pantalla del catalogo\n";
print str_repeat('-', 70) . "\n";

$vista = Views::getView('tec_products');
$mirar('la pantalla existe', $vista && $vista->setDisplay('brand_catalogue'));

$vista->setArguments([$laNuestra]);
$vista->preExecute();
$vista->execute();

$salen = [];
foreach ($vista->result as $fila) {
  $salen[] = (int) $fila->_entity->id();
}

$mirar('sale la talla de esta marca', in_array((int) $miTalla->id(), $salen, TRUE));
$mirar('y no sale la de la otra', !in_array((int) $tallaAjena->id(), $salen, TRUE));

// Todas las tallas de la marca: por cada producto suyo, por cada color, sus
// tallas. Se cuentan bajando el arbol, que es el mismo camino que sube la vista.
$suyas = [];
foreach ($almacen->loadMultiple($almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_product')
  ->condition('field_tec_brand', $laNuestra)
  ->execute()) as $producto) {
  foreach ($producto->get('field_tec_color_variations')->referencedEntities() as $color) {
    foreach ($color->get('field_tec_size_variations')->referencedEntities() as $talla) {
      $suyas[(int) $talla->id()] = TRUE;
    }
  }
}
$mirar('salen todas las tallas que tiene la marca', count($salen) === count($suyas),
  count($salen) . ' en pantalla, ' . count($suyas) . ' en la base');

$vista->destroy();

print "\n";
print "Las paginas\n";
print str_repeat('-', 70) . "\n";

$kernel = \Drupal::service('http_kernel');
$sesion = \Drupal::service('session');

/**
 * Pide una pagina y devuelve el codigo y el html.
 */
$pedir = function (string $ruta) use ($kernel, $sesion): array {
  $peticion = Request::create($ruta, 'GET');
  $peticion->setSession($sesion);
  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    return [$respuesta->getStatusCode(), (string) $respuesta->getContent()];
  }
  catch (\Throwable $e) {
    return [0, get_class($e) . ': ' . $e->getMessage()];
  }
};

[$codigo, $html] = $pedir('/taxonomy/term/' . $laNuestra);
$mirar('la ficha de la marca carga', $codigo === 200, 'codigo ' . $codigo);
$mirar('y ensena su producto', str_contains($html, $seLlama), $seLlama);
$mirar('sin ensenar el de la otra', !str_contains($html, $seLlamaLaOtra), $seLlamaLaOtra);

[$codigo, $html] = $pedir('/b');
$mirar('la pantalla de marcas carga', $codigo === 200, 'codigo ' . $codigo);
$mirar('la baldosa lleva a la ficha',
  str_contains($html, 'href="/taxonomy/term/' . $laNuestra . '"'));
$mirar('y el lapiz al formulario de edicion',
  str_contains($html, '/taxonomy/term/' . $laNuestra . '/edit'));

// De abajo arriba: borrar un producto no se lleva a sus colores ni a sus tallas.
foreach ([$miTalla, $tallaAjena, $miColor, $colorAjeno, $mio, $ajeno] as $pieza) {
  $almacen->loadUnchanged($pieza->id())?->delete();
}

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Los dos arboles de prueba se han borrado.\n\n";
}
else {
  printf("  %d cosas mal. Los dos arboles de prueba se han borrado.\n\n", $problemas);
}
