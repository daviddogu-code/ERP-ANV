<?php

/**
 * @file
 * Ensena los colores del tema y de donde salen.
 *
 *   php vendor/bin/drush scr scripts/colores-del-tema.php
 *
 * Hasta la version 5 el tema guardaba los colores en el modulo `color` del
 * nucleo. Drupal los saco del nucleo, y dxpr_theme 8 los trajo a sus propios
 * ajustes y desinstalo el modulo. Esto sirve para comprobar que la mudanza no
 * se dejo nada por el camino.
 *
 * Solo lee.
 */

$ajustes = \Drupal::configFactory()->get('dxpr_theme.settings')->getRawData();

$colores = array_filter(
  $ajustes,
  fn($clave) => str_contains($clave, 'color') || str_contains($clave, 'scheme') || str_contains($clave, 'palette'),
  ARRAY_FILTER_USE_KEY
);

echo "\n";
foreach ($colores as $clave => $valor) {
  printf("  %-46s %s\n", $clave, is_scalar($valor) ? $valor : json_encode($valor));
}

printf("\n  %d ajustes de color, %d ajustes en total en el tema\n", count($colores), count($ajustes));

$restos = \Drupal::configFactory()->listAll('color.');
printf("  %d restos del modulo color en la configuracion\n\n", count($restos));
