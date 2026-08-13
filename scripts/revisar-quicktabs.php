<?php

/**
 * @file
 * Comprueba si la configuracion de quicktabs encaja con el esquema de la 4.3.1.
 *
 *   php vendor/bin/drush scr scripts/revisar-quicktabs.php
 *
 * Hace falta porque el salto del nucleo dejo quicktabs con la version de
 * esquema 8000 mientras el modulo en disco es la 4.3.1, que declara haber
 * borrado todas las actualizaciones hasta la 103001. Drupal se planta y no deja
 * ejecutar *ninguna* actualizacion de *ningun* modulo mientras eso siga asi.
 *
 * La unica actualizacion borrada instalaba el modulo js_cookie, que la rama 4
 * ya no usa porque la memoria de pestañas se guarda en el navegador. Si js_cookie
 * no esta instalado y la configuracion tiene las claves que el esquema pide,
 * entonces no falta nada y el desfase es solo un numero mal puesto.
 *
 * Solo lee.
 */

$esperadas = [
  'id', 'label', 'uuid', 'status', 'renderer', 'hide_empty_tabs',
  'remember_last_clicked_tab', 'default_tab', 'dependencies', 'options',
  'configuration_data',
];

$factory = \Drupal::configFactory();
$instancias = $factory->listAll('quicktabs.quicktabs_instance.');

echo "\n=== las " . count($instancias) . " instancias de quicktabs ===\n\n";

$problemas = 0;
foreach ($instancias as $nombre) {
  $datos = $factory->get($nombre)->getRawData();
  $faltan = array_diff($esperadas, array_keys($datos));
  $sobran = array_diff(array_keys($datos), $esperadas, ['langcode']);

  printf("  %-52s %d pestañas\n", str_replace('quicktabs.quicktabs_instance.', '', $nombre), count($datos['configuration_data'] ?? []));
  if ($faltan) {
    printf("      FALTAN claves: %s\n", implode(', ', $faltan));
    $problemas++;
  }
  if ($sobran) {
    printf("      sobran claves: %s\n", implode(', ', $sobran));
  }
}

echo "\n=== esta js_cookie instalado? ===\n";
$jsCookie = \Drupal::moduleHandler()->moduleExists('js_cookie');
printf("  %s\n", $jsCookie
  ? 'SI. La rama 4 ya no lo usa; habria que revisarlo.'
  : 'No, que es lo que la rama 4 espera.');

echo "\n";
echo ($problemas === 0 && !$jsCookie)
  ? "No falta nada. El desfase de version de esquema es solo un numero.\n\n"
  : "HAY $problemas instancias incompletas. No tocar la version de esquema.\n\n";
