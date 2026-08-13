<?php

/**
 * @file
 * Quien bloquea el salto a Drupal 11. Se ejecuta con:
 *   drush scr scripts/quien-bloquea-la-11.php
 *
 * Hace la misma pregunta que hara el nucleo al arrancar: para cada extension
 * encendida, mira si su core_version_requirement acepta la version 11 que
 * queremos poner. Es la comprobacion que de verdad decide si el sitio arranca
 * o se queda a oscuras, y no depende de lo que diga drupal.org.
 *
 * Lo que salga en la lista de bloqueos hay que arreglarlo antes de tocar
 * composer.json. Lo que salga como sin etiqueta es un modulo tan viejo que ni
 * declara version; el nucleo lo rechaza igual.
 */

use Composer\Semver\Semver;

const DESTINO = '11.4.0';

$listas = [
  'module' => \Drupal::service('extension.list.module'),
  'theme' => \Drupal::service('extension.list.theme'),
];

$bloquean = [];
$sinEtiqueta = [];
$total = 0;

foreach ($listas as $tipo => $lista) {
  foreach ($lista->getAllInstalledInfo() as $nombre => $info) {
    // El propio nucleo y lo que vive dentro de core/ va con el, no cuenta.
    $ruta = $lista->getPath($nombre);
    if (str_starts_with($ruta, 'core/')) {
      continue;
    }

    $total++;
    $regla = $info['core_version_requirement'] ?? NULL;

    if (!$regla) {
      $sinEtiqueta[$nombre] = $tipo;
      continue;
    }

    try {
      if (!Semver::satisfies(DESTINO, $regla)) {
        $bloquean[$nombre] = ['tipo' => $tipo, 'regla' => $regla, 'ruta' => $ruta];
      }
    }
    catch (\Throwable $e) {
      $bloquean[$nombre] = ['tipo' => $tipo, 'regla' => $regla . ' (ilegible)', 'ruta' => $ruta];
    }
  }
}

printf("\n  Se pregunta por Drupal %s a %d extensiones encendidas de fuera del nucleo.\n\n", DESTINO, $total);

if ($bloquean) {
  printf("  %d BLOQUEAN el salto:\n\n", count($bloquean));
  foreach ($bloquean as $nombre => $d) {
    printf("    %-34s %-7s %s\n", $nombre, $d['tipo'], $d['regla']);
    printf("    %-34s %s\n", '', $d['ruta']);
  }
  print "\n";
}

if ($sinEtiqueta) {
  printf("  %d sin etiqueta, el nucleo los rechaza igual:\n\n", count($sinEtiqueta));
  foreach ($sinEtiqueta as $nombre => $tipo) {
    printf("    %-34s %s\n", $nombre, $tipo);
  }
  print "\n";
}

if (!$bloquean && !$sinEtiqueta) {
  print "  Ninguna bloquea. Las " . $total . " aceptan la 11.\n\n";
}
