<?php

/**
 * Pide la portada y dice a donde apunta cada icono.
 *
 * Es la comprobacion de lo unico que un humano ve y una maquina no: el enlace que
 * hay detras de cada icono del inicio. Sirvio para descubrir que cuatro no
 * llevaban a ninguna parte, y sirve para verificar que ya llevan.
 *
 * Uso: php vendor/bin/drush.php scr scripts/a-donde-apuntan-los-iconos.php
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$kernel = \Drupal::service('http_kernel');
$cuenta = \Drupal::entityTypeManager()->getStorage('user')->load(1);
\Drupal::currentUser()->setAccount($cuenta);

$peticion = Request::create('/start', 'GET');
try {
  $peticion->setSession(\Drupal::service('session'));
}
catch (\Throwable $e) {
}

$respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
$html = $respuesta->getContent();

printf("\n  /start responde %s\n\n", $respuesta->getStatusCode());

// La rejilla de iconos. Se acota al bloque de la vista para no recoger la barra
// de administracion ni el menu.
$trozo = $html;
if (($i = strpos($html, 'tec-grid')) !== FALSE) {
  $trozo = substr($html, $i, 20000);
}

preg_match_all('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', $trozo, $coincidencias, PREG_SET_ORDER);

$validador = \Drupal::service('path.validator');
$vistos = [];
$malos = 0;

printf("  %-26s %-24s %s\n", 'icono', 'apunta a', 'la ruta existe');
print '  ' . str_repeat('-', 66) . "\n";

foreach ($coincidencias as $c) {
  $destino = $c[1];
  $texto = trim(preg_replace('/\s+/', ' ', strip_tags($c[2])));

  if (!str_starts_with($destino, '/')) {
    continue;
  }

  // La imagen y el titulo de la misma ficha son dos enlaces al mismo sitio; se
  // ensena una vez, pero contando los dos para saber que los dos estan puestos.
  $clave = $destino;
  if (isset($vistos[$clave])) {
    $vistos[$clave]['veces']++;
    if ($texto !== '') {
      $vistos[$clave]['texto'] = $texto;
    }
    continue;
  }
  $vistos[$clave] = ['texto' => $texto, 'veces' => 1];
}

foreach ($vistos as $destino => $datos) {
  $existe = $validador->getUrlIfValidWithoutAccessCheck($destino) ? 'si' : 'NO';
  if ($existe === 'NO') {
    $malos++;
  }
  $aviso = $datos['veces'] === 2 ? '' : '  (' . $datos['veces'] . ' enlace, no dos)';
  printf("  %-26s %-24s %s%s\n",
    $datos['texto'] !== '' ? $datos['texto'] : '(solo imagen)',
    $destino,
    $existe,
    $aviso);
}

printf("\n  %d iconos, %d apuntando a una ruta que no existe\n\n", count($vistos), $malos);

$nodos = 0;
foreach (array_keys($vistos) as $destino) {
  if (preg_match('#^/node/\d+$#', $destino)) {
    $nodos++;
  }
}

print $nodos
  ? "  Quedan $nodos iconos enlazando al nodo en vez de a la pantalla.\n\n"
  : "  Ninguno enlaza al nodo: todos apuntan a su pantalla.\n\n";
