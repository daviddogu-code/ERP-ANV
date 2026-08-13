<?php

/**
 * @file
 * Exporta a config/sync solo los objetos de configuracion que se le indiquen.
 *
 *   php vendor/bin/drush scr scripts/exportar-config.php -- eca.settings
 *   php vendor/bin/drush scr scripts/exportar-config.php -- "quicktabs.*"
 *
 * `drush config:export` exporta todo, y mientras duro la subida eso era un error:
 * estaban encendidos update y upgrade_status para diagnosticar, y una exportacion
 * completa los habria metido en core.extension y de ahi al servidor. Desde el 14
 * de agosto de 2026 ya no hay nada que esconder: upgrade_status esta desinstalado,
 * update se queda a proposito y sus ajustes estan exportados, y `config:status`
 * dice que no hay ninguna diferencia.
 *
 * Sigue mereciendo la pena para dos cosas: exportar un objeto suelto sin arrastrar
 * lo que este a medias, y la proxima vez que se encienda una herramienta de
 * diagnostico, que volvera a pasar.
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
$aBorrar = [];
foreach ($patrones as $patron) {
  if (str_contains($patron, '*')) {
    $regex = '/^' . str_replace('\*', '.*', preg_quote($patron, '/')) . '$/';
    $elegidos = array_merge($elegidos, preg_grep($regex, $todos));
    // Lo que sigue en config/sync pero ya no existe activo: una actualizacion
    // lo ha retirado y hay que quitarlo tambien de la copia, o volvera a
    // aparecer en la proxima importacion.
    $aBorrar = array_merge($aBorrar, array_diff(preg_grep($regex, $sync->listAll()), $todos));
  }
  elseif (in_array($patron, $todos, TRUE)) {
    $elegidos[] = $patron;
  }
  elseif ($sync->exists($patron)) {
    $aBorrar[] = $patron;
  }
  else {
    printf("  %-56s no existe ni activo ni en config/sync\n", $patron);
  }
}
$elegidos = array_unique($elegidos);
$aBorrar = array_unique($aBorrar);
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

foreach ($aBorrar as $nombre) {
  $sync->delete($nombre);
  printf("  %-56s BORRADO, ya no existe activo\n", $nombre);
}

printf("\n  %d escritos, %d borrados en config/sync\n\n", $escritos, count($aBorrar));
