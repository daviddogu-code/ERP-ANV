<?php

/**
 * @file
 * Comprueba el icono de Supplier Orders: nodo, destino, imagen y /start.
 *
 *   php vendor\bin\drush.php scr scripts/comprobar-icono-supplier-orders.php
 */

use Drupal\tec_production\EventSubscriber\QueueTileRedirectSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$problemas = 0;
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas) {
  if (!$bien) {
    $problemas++;
  }
  print ($bien ? '  ok    ' : '  WRONG ') . $que . ($detalle === '' ? '' : '   [' . $detalle . ']') . "\n";
};

echo "\n";

$mirar('TILES nombra la vista de Supplier Orders',
  (QueueTileRedirectSubscriber::TILES['supplier_orders_tile_nid'] ?? '') === 'view.tec_supplier_orders.page_1');

$ajustes = \Drupal::config('tec_production.settings');
$nid = (int) $ajustes->get('supplier_orders_tile_nid');
$mirar('el ajuste apunta a un nodo', $nid > 0, (string) $nid);

$nodo = $nid ? \Drupal::entityTypeManager()->getStorage('node')->load($nid) : NULL;
$mirar('ese nodo existe y es una portada', $nodo && $nodo->bundle() === 'tec_landing_page', $nodo ? $nodo->bundle() : 'no');
$mirar('se llama Supplier Orders', $nodo && $nodo->label() === 'Supplier Orders', $nodo ? $nodo->label() : '');

$destino = '';
if ($nodo && $nodo->hasField('field_tec_target') && !$nodo->get('field_tec_target')->isEmpty()) {
  $destino = $nodo->get('field_tec_target')->first()->getUrl()->toString();
}
$mirar('apunta a /supplier-orders', $destino === '/supplier-orders', $destino ?: 'sin destino');

$fichero = $nodo && !$nodo->get('field_tec_icon')->isEmpty() ? $nodo->get('field_tec_icon')->entity : NULL;
$uri = $fichero ? $fichero->getFileUri() : '';
$disco = $uri ? (\Drupal::service('file_system')->realpath($uri) ?: '') : '';
$mirar('tiene imagen en disco', $disco !== '' && file_exists($disco), $uri ?: 'sin imagen');

$cuenta = \Drupal::entityTypeManager()->getStorage('user')->load(1);
\Drupal::currentUser()->setAccount($cuenta);
$kernel = \Drupal::service('http_kernel');
$peticion = Request::create('/start', 'GET');
try {
  $peticion->setSession(\Drupal::service('session'));
}
catch (\Throwable $e) {
}
$respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
$html = (string) $respuesta->getContent();
$mirar('/start responde 200', $respuesta->getStatusCode() === 200, (string) $respuesta->getStatusCode());
$mirar('/start enlaza a /supplier-orders', str_contains($html, 'href="/supplier-orders"') || str_contains($html, 'href="/supplier-orders"'));
$mirar('/start nombra Supplier Orders', str_contains($html, 'Supplier Orders'));

$ruta = \Drupal::service('router.route_provider')->getRouteByName('view.tec_supplier_orders.page_1');
$mirar('la vista existe en /supplier-orders', $ruta->getPath() === '/supplier-orders', $ruta->getPath());

echo "\n";
if ($problemas) {
  printf("  %d fallos\n\n", $problemas);
}
else {
  print "  todo bien\n\n";
}
