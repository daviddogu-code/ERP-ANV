<?php

/**
 * @file
 * Lista las direcciones publicas de las vistas del ERP.
 *
 *   php vendor/bin/drush scr scripts/rutas-de-las-vistas.php
 *
 * Sirve para alimentar a scripts/cargan-las-paginas.php sin adivinar rutas.
 *
 * Solo lee.
 */

$factory = \Drupal::configFactory();
$rutas = [];

foreach ($factory->listAll('views.view.') as $nombre) {
  $vista = $factory->get($nombre);
  if (!$vista->get('status')) {
    continue;
  }
  foreach ($vista->get('display') ?? [] as $id => $display) {
    if (($display['display_plugin'] ?? '') !== 'page') {
      continue;
    }
    $ruta = $display['display_options']['path'] ?? NULL;
    if (!$ruta || str_contains($ruta, '%')) {
      continue;
    }
    $rutas['/' . ltrim($ruta, '/')] = sprintf(
      '%s (%s)',
      str_replace('views.view.', '', $nombre),
      $id
    );
  }
}

ksort($rutas);
echo "\n";
foreach ($rutas as $ruta => $de) {
  printf("  %-46s %s\n", $ruta, $de);
}
printf("\n  %d paginas de vista\n\n", count($rutas));
echo "  Para pasarlas todas:\n";
echo '  php vendor/bin/drush scr scripts/cargan-las-paginas.php -- ' . implode(' ', array_keys($rutas)) . "\n\n";
