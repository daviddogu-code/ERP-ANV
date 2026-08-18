<?php

/**
 * @file
 * Las marcas de un contacto: en su ficha, y en el formulario solo si es cliente.
 *
 *   php vendor\bin\drush.php scr scripts/las-marcas-solo-salen-si-es-cliente.php
 *
 * QUE ES ESTO.
 *
 * La lista de marcas que compra un contacto se estreno en su formulario y en ningun
 * sitio mas, y eso dejaba dos cosas a medias.
 *
 * Una, la ficha no las ensenaba. Y con la pestana Products agrupando por marca, una
 * marca recien asignada y todavia sin productos no salia por ninguna parte: la
 * pestana no pinta grupo si no hay filas. El cliente parecia no comprar esa marca.
 *
 * Dos, la lista salia siempre, tambien en un contacto que es solo proveedor, donde
 * no significa nada. Los campos de compra ya se esconden asi desde agosto, con
 * #states y la casilla del tipo de contacto; esto es el mismo truco del otro lado.
 *
 * Lo que se vigila: que la ficha del cliente ensene sus marcas enlazadas, que la de
 * un contacto sin marcas no ensene nada, que el formulario lleve la condicion de
 * «solo si es cliente» en la lista de marcas, y -por si acaso- que la condicion de
 * los campos de proveedor siga en su sitio.
 *
 * Se monta su propio juego y lo barre al empezar y al acabar. Todo lo suyo se llama
 * «PRUEBA marcas si cliente algo»: un rastro corto como «PRUEBA» se lleva por
 * delante el juego de pruebas permanente de la casa.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const RASTRO = 'PRUEBA marcas si cliente';
const CLIENTE = 1395;
const PROVEEDOR = 1396;

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$contactos = $gestor->getStorage('tec_crm');
$kernel = \Drupal::service('http_kernel');

$problemas = 0;

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = static function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s\n", $bien ? 'BIEN' : 'MAL', $que);
  if (!$bien) {
    $problemas++;
    if ($pista !== '') {
      print '         ' . $pista . "\n";
    }
  }
};

/**
 * Pide una pagina por dentro y devuelve el codigo y el html.
 */
$pedir = static function (string $ruta) use ($kernel): array {
  $peticion = Request::create($ruta, 'GET');
  $peticion->setSession(\Drupal::service('session'));
  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    return [$respuesta->getStatusCode(), (string) $respuesta->getContent()];
  }
  catch (\Throwable $e) {
    return [0, 'EXCEPCION: ' . $e->getMessage()];
  }
};

/**
 * El formulario de un contacto, ya pintado.
 */
$formularioDe = static function ($contacto): string {
  $formulario = \Drupal::service('entity.form_builder')->getForm($contacto, 'default');

  return (string) \Drupal::service('renderer')->renderPlain($formulario);
};

/**
 * Barre lo de esta prueba y nada mas.
 */
$barrer = static function () use ($terminos, $contactos): int {
  $barridas = 0;
  foreach ([[$contactos, 'title'], [$terminos, 'name']] as [$almacen, $campo]) {
    $suyas = $almacen->getQuery()->accessCheck(FALSE)->condition($campo, RASTRO, 'STARTS_WITH')->execute();
    foreach ($almacen->loadMultiple($suyas) as $ficha) {
      $ficha->delete();
      $barridas++;
    }
  }

  return $barridas;
};

print "\n";
print "Montando el juego\n";
print str_repeat('-', 70) . "\n";

$barridas = $barrer();
if ($barridas > 0) {
  printf("  antes barro %d fichas que dejo una prueba anterior\n", $barridas);
}

$marca = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' marca']);
$marca->save();

$elCliente = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' cliente',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_brands' => [['target_id' => $marca->id()]],
]);
$elCliente->save();

// Solo proveedor y sin marcas: es el que no tiene que ensenar nada de marcas.
$elProveedor = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' proveedor',
  'field_tec_contact_type' => [['target_id' => PROVEEDOR]],
]);
$elProveedor->save();

printf("  marca %s, cliente %s, proveedor %s\n", $marca->id(), $elCliente->id(), $elProveedor->id());

print "\nLa ficha del cliente ensena sus marcas\n";
print str_repeat('-', 70) . "\n";

[$codigo, $html] = $pedir('/tec_crm/' . $elCliente->id());
$mirar('la ficha del cliente responde', $codigo === 200, 'ha devuelto ' . $codigo);
$mirar('sale el nombre de la marca que compra',
  str_contains($html, RASTRO . ' marca'),
  'no aparece «' . RASTRO . ' marca» en la ficha');

// Enlazada, que es lo que la hace util: la pagina de la marca es donde se le crean
// productos, y por ahi se sale del agujero de la marca sin productos.
$enlace = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')
  ->load($marca->id())->toUrl()->toString();
$mirar('y enlaza a la pagina de la marca',
  str_contains($html, 'href="' . $enlace . '"'),
  'no sale href="' . $enlace . '"');

[$codigo, $htmlProveedor] = $pedir('/tec_crm/' . $elProveedor->id());
$mirar('la ficha del que no compra marcas responde', $codigo === 200, 'ha devuelto ' . $codigo);
$mirar('y no ensena nada de marcas',
  !str_contains($htmlProveedor, RASTRO . ' marca'),
  'sale una marca en la ficha de quien no compra ninguna');

print "\nEl formulario esconde las marcas si no es cliente\n";
print str_repeat('-', 70) . "\n";

$html = $formularioDe($elCliente);

// #states viaja al navegador dentro de data-drupal-states, en json y con las
// comillas escapadas por el pintado. Se busca la casilla del tipo de contacto que
// manda: la de cliente.
$condicion = 'field_tec_contact_type[' . CLIENTE . ']';
$mirar('el formulario lleva la lista de marcas',
  str_contains($html, 'field_tec_brands'),
  'no sale el campo en el formulario');

$estados = [];
if (preg_match_all('~data-drupal-states="([^"]*)"~', $html, $encontrados)) {
  $estados = array_map(static fn (string $uno) => html_entity_decode($uno, ENT_QUOTES), $encontrados[1]);
}

$mirar('y alguna condicion mira la casilla de cliente',
  (bool) array_filter($estados, static fn (string $uno) => str_contains($uno, $condicion)),
  count($estados) . ' condiciones en el formulario, ninguna con ' . $condicion);

// La condicion tiene que estar en el envoltorio de las marcas, no en cualquier
// sitio: si estuviera en otro campo, las marcas seguirian saliendo siempre.
$suya = FALSE;
if (preg_match('~<div[^>]*field--name-field-tec-brands[^>]*>~', $html, $trozo)) {
  $suya = str_contains(html_entity_decode($trozo[0], ENT_QUOTES), $condicion);
}
if (!$suya && preg_match_all('~<div[^>]*data-drupal-states="[^"]*"[^>]*>~', $html, $trozos)) {
  foreach ($trozos[0] as $trozo) {
    $limpio = html_entity_decode($trozo, ENT_QUOTES);
    if (str_contains($limpio, $condicion) && str_contains($limpio, 'field-tec-brands')) {
      $suya = TRUE;
      break;
    }
  }
}
$mirar('y la condicion es la del envoltorio de las marcas', $suya,
  'la condicion existe pero no cuelga del campo de marcas');

// Y el otro lado, que ya funcionaba: los campos de compra siguen escondidos hasta
// que se marca Proveedor. Esta prueba esta aqui para que nadie los desenchufe al
// tocar lo de al lado.
$deProveedor = 'field_tec_contact_type[' . PROVEEDOR . ']';
$mirar('los campos de proveedor siguen con su condicion',
  (bool) array_filter($estados, static fn (string $uno) => str_contains($uno, $deProveedor)),
  'ninguna condicion mira ' . $deProveedor);

$barrer();

print "\n" . str_repeat('=', 70) . "\n";
printf("  %s El juego de prueba se ha borrado.\n",
  $problemas === 0 ? 'Todo bien.' : $problemas . ' cosas mal.');
print "\n";
