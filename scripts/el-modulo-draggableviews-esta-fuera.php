<?php

/**
 * @file
 * Confirms draggableviews is gone.
 *
 *   php vendor/bin/drush.php scr scripts/el-modulo-draggableviews-esta-fuera.php
 */

$ok = 0;
$fail = 0;
$mirar = function (string $nombre, bool $bien, string $detalle = '') use (&$ok, &$fail): void {
  printf("  [%s] %s%s\n", $bien ? 'BIEN' : 'FALLA', $nombre, $detalle !== '' ? " — $detalle" : '');
  $bien ? $ok++ : $fail++;
};

$mirar('el modulo no esta instalado',
  !\Drupal::moduleHandler()->moduleExists('draggableviews'));

$mirar('no queda en core.extension',
  !array_key_exists('draggableviews', (array) \Drupal::config('core.extension')->get('module')));

$mirar('no queda la carpeta en contrib',
  !is_dir(DRUPAL_ROOT . '/modules/contrib/draggableviews'));

$mirar('no queda la tabla de pesos',
  !\Drupal::database()->schema()->tableExists('draggableviews_structure'));

$citan = [];
foreach (\Drupal::configFactory()->listAll('views.view.') as $nombre) {
  if (str_contains(json_encode(\Drupal::config($nombre)->getRawData()), 'draggableviews')) {
    $citan[] = substr($nombre, strlen('views.view.'));
  }
}
$mirar('ninguna vista lo cita', $citan === [], implode(', ', $citan));

$modulos = count(array_keys(\Drupal::service('extension.list.module')->getAllInstalledInfo()));
printf("  [cifra] %d modulos instalados\n", $modulos);

printf("\n  %d bien, %d fallan\n", $ok, $fail);
if ($fail) {
  throw new \RuntimeException("draggableviews sigue ahi ($fail)");
}
