<?php

/**
 * @file
 * Cierra a mano la actualizacion 8003 de dxpr_theme_helper. Se ejecuta con:
 *   drush scr scripts/cerrar-8003.php
 *
 * Por que se cierra a mano. La 8003 hace dos cosas. La primera, encender
 * media_library_form_element, ya se hizo. La segunda pide el servicio
 * Drupal\Core\Extension\ThemeSettingsProvider, que no existe hasta Drupal
 * 11.3, asi que revienta y deja la version de esquema en 8002. Mientras siga
 * en 8002, cada actualizacion futura la vuelve a intentar y vuelve a fallar.
 *
 * Y se puede cerrar sin miedo porque lo que le falta por hacer es pasar dos
 * imagenes de fondo del tema a entidades de medios, y comprobado el 13 de
 * agosto de 2026 las dos rutas de origen estan vacias: no hay ninguna imagen
 * que migrar. Se vuelve a comprobar aqui antes de tocar nada, y si alguna
 * tuviera valor, el script se para y no cierra nada.
 */

$ajustes = \Drupal::configFactory()->get('dxpr_theme.settings');
$pendientes = array_filter([
  'background_image_path' => $ajustes->get('background_image_path'),
  'page_title_image_path' => $ajustes->get('page_title_image_path'),
]);

if ($pendientes) {
  print "\n  ALTO. Hay imagenes de fondo puestas, si que habria que migrarlas:\n\n";
  foreach ($pendientes as $clave => $valor) {
    printf("    %-24s %s\n", $clave, $valor);
  }
  print "\n  No se cierra nada. Hay que migrarlas a mano o esperar a Drupal 11.3.\n\n";
  return;
}

$registro = \Drupal::service('update.update_hook_registry');
$antes = $registro->getInstalledVersion('dxpr_theme_helper');

if ($antes >= 8003) {
  printf("\n  Ya estaba en %s, no hay nada que hacer.\n\n", $antes);
  return;
}

$registro->setInstalledVersion('dxpr_theme_helper', 8003);

printf(
  "\n  Las dos rutas estan vacias, no habia nada que migrar.\n  dxpr_theme_helper pasa de %s a %s.\n\n",
  $antes,
  $registro->getInstalledVersion('dxpr_theme_helper')
);
