<?php

/**
 * @file
 * Desatasca quicktabs para que se puedan volver a ejecutar actualizaciones.
 *
 *   php vendor/bin/drush scr scripts/arreglar-quicktabs.php
 *
 * El salto del nucleo de agosto subio quicktabs de la rama 3 de desarrollo a la
 * 4.3.1 y dejo dos cosas a medias:
 *
 * 1. La version de esquema se quedo en 8000. La 4.3.1 declara haber borrado
 *    todas las actualizaciones hasta la 103001, asi que Drupal se planta y no
 *    deja ejecutar ninguna actualizacion de ningun modulo. La unica borrada
 *    instalaba el modulo js_cookie, que la rama 4 ya no usa porque la memoria
 *    de pestañas se guarda en el navegador; js_cookie no esta instalado, que es
 *    justo el estado que la 4 espera, asi que no falta nada por hacer y el
 *    numero se puede poner al dia.
 *
 * 2. La actualizacion posterior que añade el ajuste remember_last_clicked_tab
 *    quedo anotada como ejecutada, pero no llego a aplicarse: las cinco
 *    instancias siguen sin la clave. Se sabe que no es un fallo de anotacion en
 *    bloque porque la *otra* actualizacion posterior de quicktabs, la de
 *    direct_linking, si dejo su marca en la configuracion. Aqui se desanota
 *    para que vuelva a ejecutarse, y es el codigo del propio modulo el que hace
 *    el trabajo, no nosotros a mano.
 *
 * Despues de esto hay que lanzar scripts/actualizar-bd.php.
 */

echo "\n=== 1. version de esquema de quicktabs ===\n";

$almacen = \Drupal::keyValue('system.schema');
$actual = $almacen->get('quicktabs');
printf("  ahora: %s\n", var_export($actual, TRUE));

if ($actual === NULL) {
  echo "  quicktabs no consta como instalado. No se toca nada.\n";
}
elseif ($actual >= 103001) {
  echo "  ya esta al dia, no hace falta tocarla\n";
}
else {
  // Comprobacion de seguridad: solo se salta si de verdad no hay rastro de
  // js_cookie, que era lo unico que hacia la actualizacion borrada.
  if (\Drupal::moduleHandler()->moduleExists('js_cookie')) {
    echo "  js_cookie SI esta instalado. Parar y mirarlo a mano.\n";
    return;
  }
  $almacen->set('quicktabs', 103001);
  printf("  puesta a 103001\n");
}

echo "\n=== 2. la actualizacion posterior que no llego a aplicarse ===\n";

$funcion = 'quicktabs_post_update_remember_last_clicked_tab_setting';
$registro = \Drupal::keyValue('post_update');
$hechas = $registro->get('existing_updates', []);

$sinAplicar = 0;
foreach (\Drupal::configFactory()->listAll('quicktabs.quicktabs_instance.') as $nombre) {
  if (\Drupal::configFactory()->get($nombre)->get('remember_last_clicked_tab') === NULL) {
    $sinAplicar++;
  }
}

printf("  instancias sin el ajuste: %d\n", $sinAplicar);
printf("  anotada como ejecutada:   %s\n", in_array($funcion, $hechas, TRUE) ? 'si' : 'no');

if ($sinAplicar === 0) {
  echo "  no hay nada que rehacer\n";
}
elseif (!in_array($funcion, $hechas, TRUE)) {
  echo "  ya consta como pendiente; la ejecutara actualizar-bd.php\n";
}
else {
  $registro->set('existing_updates', array_values(array_diff($hechas, [$funcion])));
  echo "  desanotada. Volvera a salir como pendiente.\n";
}

echo "\nAhora: php vendor/bin/drush scr scripts/actualizar-bd.php\n\n";
