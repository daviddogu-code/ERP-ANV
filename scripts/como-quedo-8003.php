<?php

/**
 * @file
 * Como quedo la actualizacion 8003 de dxpr_theme_helper. Se ejecuta con:
 *   drush scr scripts/como-quedo-8003.php
 *
 * Esa actualizacion hace dos cosas seguidas. Primero enciende el modulo
 * media_library_form_element, y despues pasa dos imagenes de fondo del tema,
 * que hasta ahora eran una ruta de fichero, a ser entidades de medios.
 *
 * La segunda parte se rompio porque pide un servicio que no existe hasta
 * Drupal 11.3. Esto mira que quedo hecho y que no, y sobre todo si la parte
 * que falta llega a importar: si las dos rutas estan vacias, no hay nada que
 * migrar y el fallo es inofensivo.
 */

$version = \Drupal::service('update.update_hook_registry')->getInstalledVersion('dxpr_theme_helper');
printf("\n  version de esquema de dxpr_theme_helper   %s\n", $version);

$encendido = \Drupal::moduleHandler()->moduleExists('media_library_form_element');
printf("  media_library_form_element               %s\n", $encendido ? 'encendido' : 'apagado');

$ajustes = \Drupal::configFactory()->get('dxpr_theme.settings');

printf("\n  lo que habria que migrar:\n\n");
foreach ([
  'background_image' => 'background_image_path',
  'page_title_image' => 'page_title_image_path',
] as $destino => $origen) {
  $valorDestino = $ajustes->get($destino);
  $valorOrigen = $ajustes->get($origen);
  printf(
    "    %-22s destino %-10s origen %s\n",
    $destino,
    $valorDestino === NULL ? '(no existe)' : ($valorDestino === '' ? "(vacio)" : $valorDestino),
    $valorOrigen === NULL ? '(no existe)' : ($valorOrigen === '' ? '(vacio)' : $valorOrigen)
  );
}

$modulos = count(\Drupal::service('extension.list.module')->getAllInstalledInfo());
printf("\n  %d modulos encendidos en total\n\n", $modulos);
