<?php

/**
 * @file
 * Comprueba el icono de Patterns: nodo, destino, imagen y /start.
 *
 *   php vendor\bin\drush.php scr scripts/comprobar-icono-de-patterns.php
 */

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

$nodos = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_landing_page')
  ->condition('field_tec_target.uri', 'internal:/pattern')
  ->execute();
$nid = $nodos ? (int) reset($nodos) : 0;
$mirar('hay un icono que apunta a /pattern', $nid > 0, (string) $nid);

$nodo = $nid ? \Drupal::entityTypeManager()->getStorage('node')->load($nid) : NULL;
$mirar('ese nodo es una portada', $nodo && $nodo->bundle() === 'tec_landing_page', $nodo ? $nodo->bundle() : 'no');
$mirar('se llama Patterns', $nodo && $nodo->label() === 'Patterns', $nodo ? $nodo->label() : '');

$destino = '';
$uri_destino = '';
if ($nodo && $nodo->hasField('field_tec_target') && !$nodo->get('field_tec_target')->isEmpty()) {
  $uri_destino = (string) $nodo->get('field_tec_target')->uri;
  $destino = $nodo->get('field_tec_target')->first()->getUrl()->toString();
}
$mirar('apunta a /pattern',
  $uri_destino === 'internal:/pattern' && in_array($destino, ['/pattern', '/pt'], TRUE),
  $destino ?: $uri_destino ?: 'sin destino');

$fichero = $nodo && !$nodo->get('field_tec_icon')->isEmpty() ? $nodo->get('field_tec_icon')->entity : NULL;
$uri = $fichero ? $fichero->getFileUri() : '';
$disco = $uri ? (\Drupal::service('file_system')->realpath($uri) ?: '') : '';
$mirar('tiene imagen en disco', $disco !== '' && file_exists($disco), $uri ?: 'sin imagen');
$mirar('la imagen es isometric-patterns-100', str_contains($uri, 'isometric-patterns-100'), $uri);

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
$mirar('/start enlaza a /pattern', str_contains($html, 'href="/pattern"') || str_contains($html, 'href="/pt"'));
$mirar('/start nombra Patterns', str_contains($html, 'Patterns'));
$mirar('/start pinta el dibujo', str_contains($html, 'isometric-patterns-100'));

$ruta = \Drupal::service('router.route_provider')->getRouteByName('entity.tec_pattern.collection');
$mirar('la pantalla existe en /pattern', $ruta->getPath() === '/pattern', $ruta->getPath());

echo "\n";
if ($problemas) {
  printf("  %d fallos\n\n", $problemas);
}
else {
  print "  todo bien\n\n";
}
