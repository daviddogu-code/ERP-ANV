<?php

/**
 * @file
 * Anade el ajuste `skip_on_nan` que falta en los tamper de matematicas.
 *
 *   php vendor/bin/drush scr scripts/anadir-skip-on-nan.php --simulacro
 *   php vendor/bin/drush scr scripts/anadir-skip-on-nan.php
 *
 * Tamper 8.x-1.0 anadio a su plugin de matematicas la opcion de saltarse el
 * calculo cuando el resultado no es un numero. Los ajustes guardados de antes no
 * la tienen, y Drupal no rellena los que faltan al leer una configuracion vieja,
 * asi que cada vez que se ejecuta uno de esos calculos suelta un aviso.
 *
 * Se pone en `false`, que es lo que el modulo usa por defecto y lo que estaba
 * pasando hasta ahora: si el resultado no es un numero, se guarda igual. Nada
 * cambia de comportamiento; solo deja de avisar.
 *
 * Importa mas de lo que parece. Este ERP calcula el total de cada linea de
 * pedido con este plugin, y el registro es lo unico que hay para enterarse de
 * que algo va mal. Un aviso que sale siempre y no significa nada acaba tapando
 * al que si significa algo.
 */

$simulacro = in_array('--simulacro', $extra ?? [], TRUE);
$factory = \Drupal::configFactory();
$tocados = 0;
$plugins = 0;

echo "\n";
foreach ($factory->listAll('eca.eca.') as $nombre) {
  $config = $factory->getEditable($nombre);
  $acciones = $config->get('actions') ?? [];
  $cambio = FALSE;

  foreach ($acciones as $id => $accion) {
    if (($accion['plugin'] ?? '') !== 'eca_tamper:math') {
      continue;
    }
    if (array_key_exists('skip_on_nan', $accion['configuration'] ?? [])) {
      continue;
    }
    $acciones[$id]['configuration']['skip_on_nan'] = FALSE;
    $cambio = TRUE;
    $plugins++;
    printf("  %-30s %-20s %s\n", str_replace('eca.eca.', '', $nombre), $id, $accion['label'] ?? '');
  }

  if (!$cambio) {
    continue;
  }
  $tocados++;
  if (!$simulacro) {
    $config->set('actions', $acciones)->save();
  }
}

printf(
  "\n  %d calculos en %d procesos%s\n\n",
  $plugins,
  $tocados,
  $simulacro ? ' (simulacro, no se ha guardado nada)' : ' actualizados'
);
