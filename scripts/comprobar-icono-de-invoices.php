<?php

/**
 * @file
 * Comprueba el icono de Invoices: nodo, destino, imagen y /start.
 *
 *   php vendor\bin\drush.php scr scripts/comprobar-icono-de-invoices.php
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

$mirar('TILES nombra la lista de facturas',
  (QueueTileRedirectSubscriber::TILES['invoices_tile_nid'] ?? '') === 'tec_portal.invoice_list');

$ajustes = \Drupal::config('tec_production.settings');
$nid = (int) $ajustes->get('invoices_tile_nid');
$mirar('el ajuste apunta a un nodo', $nid > 0, (string) $nid);

$nodo = $nid ? \Drupal::entityTypeManager()->getStorage('node')->load($nid) : NULL;
$mirar('ese nodo existe y es una portada', $nodo && $nodo->bundle() === 'tec_landing_page', $nodo ? $nodo->bundle() : 'no');
$mirar('se llama Invoices', $nodo && $nodo->label() === 'Invoices', $nodo ? $nodo->label() : '');

$destino = '';
if ($nodo && $nodo->hasField('field_tec_target') && !$nodo->get('field_tec_target')->isEmpty()) {
  $destino = $nodo->get('field_tec_target')->first()->getUrl()->toString();
}
$mirar('apunta a /o/inv', $destino === '/o/inv', $destino ?: 'sin destino');

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
$mirar('/start enlaza a /o/inv', str_contains($html, 'href="/o/inv"'));
$mirar('/start nombra Invoices', str_contains($html, 'Invoices'));

$ruta = \Drupal::service('router.route_provider')->getRouteByName('tec_portal.invoice_list');
$mirar('la pantalla existe en /o/inv', $ruta->getPath() === '/o/inv', $ruta->getPath());

echo "\n";
if ($problemas) {
  printf("  %d fallos\n\n", $problemas);
}
else {
  print "  todo bien\n\n";
}
