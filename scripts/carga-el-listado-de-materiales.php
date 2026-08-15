<?php

/**
 * @file
 * Si el listado de materiales carga y con sus columnas en su sitio.
 *
 *   php vendor\bin\drush.php scr scripts/carga-el-listado-de-materiales.php
 *
 * El listado de materiales es la pantalla mas usada del ERP y no tiene pagina
 * propia: se dibuja como bloque de la vista tec_inventory. Por eso no basta con
 * pedir una direccion, hay que dibujar las pantallas de la vista y contar lo que
 * sale.
 *
 * Se usa antes y despues de tocar la vista. Lo que tiene que salir igual es el
 * numero de materiales y las cabeceras de la tabla; lo que cambia a proposito al
 * retirar field_tec_suppliers es que desaparece su columna, que ademas estaba
 * excluida y no se dibujaba.
 *
 * No cambia nada.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const VISTA = 'tec_inventory';

\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  echo "\n  No existe la vista " . VISTA . ".\n\n";
  return;
}

$titulo('Las pantallas de la vista ' . VISTA);
foreach ($vista->get('display') as $idPantalla => $pantalla) {
  $encendida = ($pantalla['display_options']['enabled'] ?? TRUE) ? 'encendida' : 'APAGADA';
  printf(
    "  %-14s %-22s %-12s %s\n",
    $idPantalla,
    $pantalla['display_plugin'] ?? '?',
    $encendida,
    $pantalla['display_title'] ?? ''
  );
}

// Las pantallas que dibujan el listado. block_2 lleva argumento y se prueba en
// su propio arnes; aqui van las que se dibujan sin argumento.
$titulo('Lo que dibuja cada pantalla');
$dibujante = \Drupal::service('renderer');
foreach (['default', 'block_1'] as $idPantalla) {
  $ejecutable = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA)->getExecutable();
  try {
    $ejecutable->setDisplay($idPantalla);
    $ejecutable->preExecute();
    $ejecutable->execute();
    $filas = count($ejecutable->result);
    $html = (string) $dibujante->renderInIsolation($ejecutable->buildRenderable($idPantalla, []));
  }
  catch (\Throwable $e) {
    printf("  %-12s EXCEPCION %s: %s\n", $idPantalla, get_class($e), $e->getMessage());
    continue;
  }

  printf("  %-12s materiales: %d\n", $idPantalla, $filas);

  // Las cabeceras de la tabla, que es lo que se descoloca si una columna se
  // queda a medias entre la lista de campos y la lista de columnas del estilo.
  if (preg_match_all('~<th[^>]*>(.*?)</th>~s', $html, $celdas)) {
    $etiquetas = [];
    foreach ($celdas[1] as $celda) {
      $texto = trim(preg_replace('~\s+~', ' ', strip_tags($celda)));
      $etiquetas[] = $texto === '' ? '(sin titulo)' : $texto;
    }
    printf("  %-12s columnas (%d): %s\n", '', count($etiquetas), implode(' | ', $etiquetas));
  }
  else {
    printf("  %-12s no sale ninguna cabecera de tabla\n", '');
  }
  printf("  %-12s tamano del dibujo: %d caracteres\n\n", '', strlen($html));
}

// Y las paginas donde la fabrica lo abre de verdad, por si el bloque estuviese
// colocado en alguna que no responde.
$titulo('Las paginas donde se abre');
$kernel = \Drupal::service('http_kernel');
foreach (['/i', '/node/2', '/start'] as $ruta) {
  $peticion = Request::create($ruta, 'GET');
  try {
    $peticion->setSession(\Drupal::service('session'));
  }
  catch (\Throwable $e) {
    // Una peticion interna se aguanta sin sesion.
  }
  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    $cuerpo = (string) $respuesta->getContent();
    printf(
      "  %-12s codigo %-5s  %s\n",
      $ruta,
      $respuesta->getStatusCode(),
      str_contains($cuerpo, 'view-tec-inventory') || str_contains($cuerpo, 'views-view--tec-inventory')
        ? 'lleva el listado de materiales'
        : ''
    );
  }
  catch (\Throwable $e) {
    printf("  %-12s EXCEPCION %s: %s\n", $ruta, get_class($e), $e->getMessage());
  }
}

echo "\n";
