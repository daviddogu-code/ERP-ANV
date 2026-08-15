<?php

/**
 * Cambia la cabecera `Namecccc` de la columna del nombre del material por
 * `Material Name`, en la pantalla maestra del resumen de material.
 *
 * Es una errata de alguien que se apoyo en el teclado, y solo se ve al abrir la
 * vista para editarla: la pantalla maestra no se dibuja en ninguna parte. Aun asi se
 * toca con red, porque `block_1` -que si se usa, en la pestana "Material Summary" de
 * la ficha de pedido- cuelga de la misma vista. Lo que protege a la hija es que
 * tiene su propia lista de campos (`defaults.fields: false`), y eso se comprueba
 * aqui antes de guardar: si algun dia dejara de ser cierto, este guion se para.
 *
 * Ensayo por defecto. Actua con: drush php:script scripts/quitar-la-errata-de-la-cabecera -- --de-verdad
 */

const CAMPO = 'name';
const ANTES = 'Namecccc';
const DESPUES = 'Material Name';

$deVerdad = in_array('--de-verdad', $extra ?? [], TRUE);

$vista = \Drupal::entityTypeManager()
  ->getStorage('view')
  ->load('tec_order_material_calculation_summary');
if (!$vista) {
  echo 'No existe la vista. Nada que hacer.' . PHP_EOL;
  return;
}

$pantallas = $vista->get('display');

// La red: la hija tiene que tener su propia lista de campos.
$suyos = $pantallas['block_1']['display_options']['defaults']['fields'] ?? TRUE;
if ($suyos !== FALSE) {
  echo 'ALTO: block_1 ha pasado a heredar los campos de la maestra, asi que este' . PHP_EOL;
  echo 'cambio se le colaria a la pantalla que usa la fabrica. No se toca nada.' . PHP_EOL;
  return;
}

$maestra = $pantallas['default']['display_options']['fields'][CAMPO]['label'] ?? NULL;
$hija = $pantallas['block_1']['display_options']['fields'][CAMPO]['label'] ?? NULL;
echo 'Cabecera en la maestra: ' . var_export($maestra, TRUE) . PHP_EOL;
echo 'Cabecera en block_1:    ' . var_export($hija, TRUE) . ' (no se toca)' . PHP_EOL;

if ($maestra === DESPUES) {
  echo PHP_EOL . 'Ya estaba puesta. Nada que hacer.' . PHP_EOL;
  return;
}
if ($maestra !== ANTES) {
  echo PHP_EOL . 'ALTO: esperaba encontrar la errata y hay otra cosa. No se toca nada.' . PHP_EOL;
  return;
}

if (!$deVerdad) {
  echo PHP_EOL . 'Ensayo. Con --de-verdad pasaria a ' . DESPUES . '.' . PHP_EOL;
  return;
}

$pantallas['default']['display_options']['fields'][CAMPO]['label'] = DESPUES;
$vista->set('display', $pantallas);
$vista->save();

$recien = \Drupal::entityTypeManager()->getStorage('view')
  ->loadUnchanged('tec_order_material_calculation_summary')
  ->get('display');
echo PHP_EOL . 'Guardado. Maestra: ' . var_export($recien['default']['display_options']['fields'][CAMPO]['label'], TRUE)
  . ' | block_1: ' . var_export($recien['block_1']['display_options']['fields'][CAMPO]['label'] ?? NULL, TRUE) . PHP_EOL;
