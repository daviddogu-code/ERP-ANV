<?php

/**
 * @file
 * Guarda de que esta hecha cada pantalla, para poder compararlo despues.
 *
 *   php vendor/bin/drush scr scripts/foto-de-las-pantallas.php -- antes
 *   ...cambiar el tema...
 *   php vendor/bin/drush scr scripts/foto-de-las-pantallas.php -- despues
 *
 * Y luego comparar las dos carpetas.
 *
 * Un cambio de tema no rompe datos ni devuelve errores: la pagina responde 200
 * y se ve mal. Eso no lo cazan ni `comprobacion.php`, que mira datos, ni
 * `cargan-las-paginas.php`, que mira codigos de respuesta.
 *
 * Lo que si se puede medir sin ojos es de que esta hecha la pagina: que hojas
 * de estilo y que scripts carga, y que bloques y regiones aparecen. Si tras la
 * subida falta una hoja de estilo o desaparece una region, eso sale aqui y
 * explica lo que se vera en pantalla. No sustituye a mirarlo, pero senala donde.
 *
 * Solo lee.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$etiqueta = $extra[0] ?? 'foto';
$destino = dirname(DRUPAL_ROOT) . '/fotos-pantallas/' . $etiqueta;
if (!is_dir($destino)) {
  mkdir($destino, 0777, TRUE);
}
array_map('unlink', glob("$destino/*.txt"));

$paginas = [
  'inicio' => '/start',
  'cola' => '/o/queue',
  'almacen' => '/stock',
  'registro' => '/production/log',
  'pedido' => NULL,
  'producto' => NULL,
];

$gestor = \Drupal::entityTypeManager();
foreach (['pedido' => 'tec_order', 'producto' => 'tec_product'] as $clave => $tipo) {
  $ids = $gestor->getStorage($tipo)->getQuery()
    ->accessCheck(FALSE)->sort('id', 'DESC')->range(0, 1)->execute();
  if ($ids) {
    $paginas[$clave] = '/' . $tipo . '/' . reset($ids);
  }
}

$kernel = \Drupal::service('http_kernel');
$sesion = \Drupal::service('session');
// Las pantallas de la fabrica exigen permisos; como anonimo solo se recoge la
// pagina de acceso denegado, que es igual antes y despues y no compara nada.
\Drupal::currentUser()->setAccount(
  \Drupal::entityTypeManager()->getStorage('user')->load(1)
);

echo "\n";
foreach (array_filter($paginas) as $clave => $ruta) {
  $peticion = Request::create($ruta, 'GET');
  $peticion->setSession($sesion);
  \Drupal::requestStack()->push($peticion);

  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    $html = $respuesta->getContent();
  }
  catch (\Throwable $e) {
    $html = '';
    printf("  %-12s %s\n", $clave, 'EXCEPCION: ' . get_class($e));
  }
  finally {
    \Drupal::requestStack()->pop();
  }

  $lineas = [];

  preg_match_all('/href="([^"]*\.css[^"]*)"/i', $html, $css);
  $lineas[] = '--- hojas de estilo ---';
  $lineas = array_merge($lineas, limpia($css[1]));

  preg_match_all('/src="([^"]*\.js[^"]*)"/i', $html, $js);
  $lineas[] = '';
  $lineas[] = '--- scripts ---';
  $lineas = array_merge($lineas, limpia($js[1]));

  preg_match_all('/(?:id|class)="([^"]*(?:region|block)[^"]*)"/i', $html, $bloques);
  $lineas[] = '';
  $lineas[] = '--- regiones y bloques ---';
  $lineas = array_merge($lineas, limpia($bloques[1]));

  file_put_contents("$destino/$clave.txt", implode("\n", $lineas) . "\n");
  printf("  %-12s %-26s %6d bytes de html, %3d lineas\n", $clave, $ruta, strlen($html), count($lineas));
}

printf("\n  guardado en %s\n\n", $destino);

/**
 * Quita la parte que cambia sola y ordena, para que comparar tenga sentido.
 */
function limpia(array $valores): array {
  $limpios = [];
  foreach ($valores as $v) {
    // El sufijo de cache cambia en cada reconstruccion y no dice nada.
    $v = preg_replace('/\?[a-z0-9]+$/i', '', $v);
    $v = trim(preg_replace('/\s+/', ' ', $v));
    if ($v !== '') {
      $limpios[$v] = TRUE;
    }
  }
  $limpios = array_keys($limpios);
  sort($limpios);
  return $limpios;
}
