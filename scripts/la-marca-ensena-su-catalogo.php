<?php

/**
 * @file
 * La ficha de una marca ensena sus productos, y solo los suyos.
 *
 *   php vendor\bin\drush.php scr scripts/la-marca-ensena-su-catalogo.php
 *
 * Comprueba las tres puertas del catalogo:
 *
 * - La pantalla `brand_catalogue` de la vista de productos devuelve los
 *   productos de la marca que se le pasa, ni uno mas ni uno menos.
 * - La ficha del termino de la marca los ensena, o sea que el campo que la
 *   embebe esta puesto y el argumento le llega.
 * - En /b la baldosa lleva a la ficha y el lapiz al formulario de edicion, que
 *   antes eran la misma cosa.
 *
 * Crea dos productos en dos marcas para poder distinguir, y los borra al final.
 * Si no hay dos marcas, lo dice y no prueba nada.
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

$mio = $almacen->create([
  'type' => 'tec_product',
  'field_product_name' => 'PRUEBA catalogo de la marca',
  'field_tec_brand' => [['target_id' => $laNuestra]],
]);
$mio->save();

$ajeno = $almacen->create([
  'type' => 'tec_product',
  'field_product_name' => 'PRUEBA catalogo de la otra',
  'field_tec_brand' => [['target_id' => $laOtra]],
]);
$ajeno->save();

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

$mirar('sale el producto de esta marca', in_array((int) $mio->id(), $salen, TRUE));
$mirar('y no sale el de la otra', !in_array((int) $ajeno->id(), $salen, TRUE));

$conMarca = array_values($almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_product')
  ->condition('field_tec_brand', $laNuestra)
  ->execute());
$mirar('salen todos los que tiene la marca', count($salen) === count($conMarca),
  count($salen) . ' en pantalla, ' . count($conMarca) . ' en la base');

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

$almacen->loadUnchanged($mio->id())?->delete();
$almacen->loadUnchanged($ajeno->id())?->delete();

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Los dos productos de prueba se han borrado.\n\n";
}
else {
  printf("  %d cosas mal. Los dos productos de prueba se han borrado.\n\n", $problemas);
}
