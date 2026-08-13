<?php

/**
 * @file
 * Exporta a config/sync solo los objetos de configuracion que se le indiquen.
 *
 *   php vendor/bin/drush scr scripts/exportar-config.php -- eca.settings
 *   php vendor/bin/drush scr scripts/exportar-config.php -- "quicktabs.*"
 *
 * `drush config:export` exporta todo, y ahora mismo eso seria un error: tenemos
 * encendidos update y upgrade_status para diagnosticar la subida, asi que una
 * exportacion completa metaria esos dos modulos en core.extension y sus ajustes
 * en config/sync, y de ahi al servidor. Esto exporta lo que se le pide y nada
 * mas.
 *
 * Acepta comodines con *.
 */

$patrones = $extra ?? [];
if (!$patrones) {
  echo "Uso: ... scripts/exportar-config.php -- <nombre|patron> [...]\n";
  return;
}

/** @var \Drupal\Core\Config\StorageInterface $activa */
$activa = \Drupal::service('config.storage');
/** @var \Drupal\Core\Config\StorageInterface $sync */
$sync = \Drupal::service('config.storage.sync');

$todos = $activa->listAll();
$elegidos = [];
foreach ($patrones as $patron) {
  if (str_contains($patron, '*')) {
    $regex = '/^' . str_replace('\*', '.*', preg_quote($patron, '/')) . '$/';
    $elegidos = array_merge($elegidos, preg_grep($regex, $todos));
  }
  elseif (in_array($patron, $todos, TRUE)) {
    $elegidos[] = $patron;
  }
  else {
    printf("  %-56s no existe en la configuracion activa\n", $patron);
  }
}
$elegidos = array_unique($elegidos);
sort($elegidos);

echo "\n";
$escritos = 0;
foreach ($elegidos as $nombre) {
  $datos = $activa->read($nombre);
  $anterior = $sync->exists($nombre) ? $sync->read($nombre) : NULL;
  if ($anterior === $datos) {
    printf("  %-56s igual, no se toca\n", $nombre);
    continue;
  }
  $sync->write($nombre, $datos);
  printf("  %-56s %s\n", $nombre, $anterior === NULL ? 'NUEVO' : 'actualizado');
  $escritos++;
}

printf("\n  %d objetos escritos en config/sync\n\n", $escritos);
