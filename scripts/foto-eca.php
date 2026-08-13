<?php

/**
 * @file
 * Vuelca toda la configuracion de ECA a una carpeta, un fichero YAML por
 * objeto, para poder compararla antes y despues de una actualizacion.
 *
 *   php vendor/bin/drush scr scripts/foto-eca.php -- antes
 *   ... actualizar ...
 *   php vendor/bin/drush scr scripts/foto-eca.php -- despues
 *   diff -r ../fotos-eca/antes ../fotos-eca/despues
 *
 * Los ganchos de actualizacion de ECA 2 reescriben los modelos: renombran
 * tokens y cambian la forma de la configuracion. Eso es justo lo que hay que
 * mirar con lupa, porque un modelo que se migra mal no da error, simplemente
 * deja de dispararse, y eso no se nota hasta que alguien echa en falta un
 * calculo semanas despues.
 *
 * Solo lee de la base de datos; escribe fuera del proyecto.
 */

$etiqueta = $extra[0] ?? 'foto';
$destino = dirname(DRUPAL_ROOT) . '/fotos-eca/' . $etiqueta;

if (!is_dir($destino)) {
  mkdir($destino, 0777, TRUE);
}
array_map('unlink', glob("$destino/*.yml"));

$factory = \Drupal::configFactory();
$nombres = $factory->listAll('eca.');

foreach ($nombres as $nombre) {
  $datos = $factory->get($nombre)->getRawData();
  file_put_contents(
    "$destino/$nombre.yml",
    \Symfony\Component\Yaml\Yaml::dump($datos, 20, 2)
  );
}

printf("  %d objetos de configuracion de ECA guardados en %s\n", count($nombres), $destino);
