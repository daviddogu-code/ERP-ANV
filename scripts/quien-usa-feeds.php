<?php

/**
 * @file
 * Quien usa Feeds y Feeds Tamper. Se ejecuta con:
 *   drush scr scripts/quien-usa-feeds.php
 *
 * Feeds es el motor del importador de materiales, el que hay pendiente de
 * arreglar. Feeds Tamper es un anadido suyo que transforma los valores por el
 * camino, y ahora mismo es una de las tres cosas que impiden saltar a la 11.
 *
 * Antes de pelearse con el, conviene saber si de verdad hace algo: cuantos
 * tipos de importacion hay, cuales llevan transformaciones y cuantas
 * importaciones se han llegado a ejecutar.
 */

$factory = \Drupal::configFactory();
$tipos = $factory->listAll('feeds.feed_type.');

printf("\n  %d tipos de importacion definidos\n\n", count($tipos));

$conTamper = 0;
$totalTampers = 0;

foreach ($tipos as $nombre) {
  $datos = $factory->get($nombre)->getRawData();

  // Feeds Tamper no guarda las transformaciones en la raiz del tipo, sino
  // colgando de third_party_settings, que es donde un modulo deja lo suyo
  // dentro de la configuracion de otro.
  $tampers = $datos['third_party_settings']['feeds_tamper']['tampers'] ?? [];

  if ($tampers) {
    $conTamper++;
    $totalTampers += count($tampers);
  }

  printf(
    "    %-44s %2d transformaciones\n",
    str_replace('feeds.feed_type.', '', $nombre),
    count($tampers)
  );

  foreach ($tampers as $t) {
    printf(
      "      %-20s sobre %-16s %s\n",
      $t['plugin'] ?? '?',
      $t['source'] ?? '?',
      $t['text'] ?? ''
    );
  }
}

$almacen = \Drupal::entityTypeManager()->getStorage('feeds_feed');
$feeds = $almacen->getQuery()->accessCheck(FALSE)->execute();

printf("\n  %d importaciones creadas\n", count($feeds));

foreach ($feeds as $id) {
  $feed = $almacen->load($id);
  printf(
    "    %-44s tipo %-16s %s importados\n",
    $feed->label(),
    $feed->bundle(),
    $feed->getItemCount()
  );
}

printf("\n  %d tipos con transformaciones, %d en total\n\n", $conTamper, $totalTampers);
