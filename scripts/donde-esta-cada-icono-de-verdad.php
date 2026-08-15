<?php

/**
 * @file
 * La ruta real de cada icono del inicio.
 *
 * Buscando por el disco solo aparecen cuatro iconos isometricos, pero Drupal dice
 * que los once existen. Uno de los dos miente y hay que saber cual antes de dar
 * por bueno el icono de marcas: si el resto de iconos estuviera roto, la pantalla
 * de inicio estaria llena de imagenes fantasma y eso seria otro fallo.
 *
 * Uso: drush php:script scripts/donde-esta-cada-icono-de-verdad
 */

$almacen = \Drupal::entityTypeManager()->getStorage('node');
$ids = $almacen->getQuery()->accessCheck(FALSE)->sort('field_icon_order')->execute();

printf("\n  %-5s %-20s %-46s %s\n", 'nodo', 'titulo', 'ruta en el disco', 'esta');
echo '  ' . str_repeat('-', 92) . "\n";

foreach ($almacen->loadMultiple($ids) as $nodo) {
  if (!$nodo->hasField('field_tec_icon') || $nodo->get('field_tec_icon')->isEmpty()) {
    printf("  %-5s %-20s %-46s %s\n", $nodo->id(), $nodo->label(), '(sin icono)', '');
    continue;
  }
  $fichero = $nodo->get('field_tec_icon')->entity;
  if (!$fichero) {
    printf("  %-5s %-20s %-46s %s\n", $nodo->id(), $nodo->label(), '(ficha borrada)', 'NO');
    continue;
  }
  $uri = $fichero->getFileUri();
  $real = \Drupal::service('file_system')->realpath($uri);
  printf(
    "  %-5s %-20s %-46s %s\n",
    $nodo->id(),
    mb_strimwidth((string) $nodo->label(), 0, 20, '...'),
    $real ? str_replace(realpath('.') . DIRECTORY_SEPARATOR, '', $real) : $uri,
    file_exists($uri) ? 'si' : 'NO'
  );
}

echo "\n  Y el de marcas, que no esta enganchado a ningun nodo:\n";
$suelto = 'public://2024-03/isometric-brand-100.png';
printf(
  "      %-60s %s\n",
  $suelto,
  file_exists($suelto) ? 'esta en el disco' : 'no esta'
);

$ficheros = \Drupal::entityTypeManager()->getStorage('file');
$fichado = $ficheros->loadByProperties(['uri' => $suelto]);
printf(
  "      ficha en Drupal: %s\n",
  $fichado ? 'si, id ' . reset($fichado)->id() : 'no, hay que crearla'
);

echo "\n";
