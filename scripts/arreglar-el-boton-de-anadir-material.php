<?php

/**
 * @file
 * Arregla el boton 'Add new material' de la pestana del proveedor.
 *
 *   php vendor\bin\drush.php scr scripts/arreglar-el-boton-de-anadir-material.php
 *   php vendor\bin\drush.php scr scripts/arreglar-el-boton-de-anadir-material.php -- --de-verdad
 *
 * Sin --de-verdad no cambia nada: cuenta lo que haria.
 *
 * Son dos arreglos que van juntos, porque cada uno solo sirve con el otro:
 *
 * 1. La cabecera de la pantalla block_2 de la vista tec_inventory escribe el
 *    boton con {{ id }}. Eso es una marca de columna de fila, y una cabecera de
 *    vista no ve las columnas de las filas, asi que sale un hueco: el enlace
 *    queda en 'target_id=&destination=/tec_crm/'. Se cambia por
 *    {{ raw_arguments.id }}, que es la marca del argumento de la pantalla y es
 *    la que la vista si deja preparada para las areas.
 *
 *    Se elige raw_arguments y no arguments porque arguments es el TITULO del
 *    argumento: hoy vale lo mismo, pero el dia que alguien encienda un validador
 *    o un titulo en el argumento pasaria a valer la etiqueta de la ficha ('KJ')
 *    y el enlace se romperia por dentro sin dejar de parecer correcto.
 *
 *    El destino se queda en /tec_crm/<id>, el camino interno, y no en el alias
 *    /supplier/<id>: los dos acaban en la ficha del proveedor, pero el camino
 *    interno lo garantiza la ruta y sigue funcionando aunque el alias cambie,
 *    porque el propio sitio redirige del camino al alias.
 *
 * 2. El relleno automatico del proveedor lo hace el modulo epp con un ajuste de
 *    terceros en el campo, y ese ajuste esta en field_tec_suppliers, el campo
 *    viejo, que se va a retirar. Se pasa a field_tec_vendor, que es el que se
 *    usa de verdad. Antes no se notaba que estuviese en el campo equivocado
 *    porque el boton nunca llegaba a mandar el target_id.
 *
 * Va con red: antes de guardar la vista dibuja la cabecera nueva con un
 * proveedor de verdad y, si el enlace no sale con su numero, no guarda nada.
 */

const VISTA = 'tec_inventory';
const PANTALLA = 'block_2';
const CAMPO_VIEJO = 'taxonomy_term.tec_inventory.field_tec_suppliers';
const CAMPO_BUENO = 'taxonomy_term.tec_inventory.field_tec_vendor';

$deVerdad = FALSE;
foreach ((array) ($extra ?? []) as $argumento) {
  if (in_array($argumento, ['--de-verdad', 'de-verdad'], TRUE)) {
    $deVerdad = TRUE;
  }
}

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

echo "\n" . str_repeat('=', 78) . "\n";
echo $deVerdad
  ? " DE VERDAD: se guarda\n"
  : " ENSAYO: solo se cuenta lo que haria. Anade -- --de-verdad para hacerlo\n";
echo str_repeat('=', 78) . "\n";

$almacenVistas = \Drupal::entityTypeManager()->getStorage('view');
$almacenCampos = \Drupal::entityTypeManager()->getStorage('field_config');

// ---------------------------------------------------------------------------
// 1. La cabecera del boton.
// ---------------------------------------------------------------------------
$vista = $almacenVistas->load(VISTA);
if (!$vista) {
  echo "\n  No existe la vista " . VISTA . ". Nada que hacer.\n\n";
  return;
}

$pantallas = $vista->get('display');
$cabecera = &$pantallas[PANTALLA]['display_options']['header']['area_text_custom'];
if (!isset($cabecera['content'])) {
  echo "\n  La pantalla " . PANTALLA . " no tiene area de texto en la cabecera. Nada que hacer.\n\n";
  return;
}

$antes = $cabecera['content'];
// Solo la marca: el resto del texto de la cabecera se queda como esta, para no
// tocar clases ni estructura que dependan del tema.
$despues = str_replace('{{ id }}', '{{ raw_arguments.id }}', $antes);

$titulo('La cabecera de ' . PANTALLA);
echo "  antes:   " . str_replace(["\r", "\n"], '', $antes) . "\n";
echo "  despues: " . str_replace(["\r", "\n"], '', $despues) . "\n";
if ($antes === $despues) {
  echo "  ya estaba puesta la marca buena, no hay nada que cambiar en la cabecera\n";
}

// El manejador tiene que estar en modo de sustituir marcas o no rellena nada.
printf("  sustituye marcas: %s\n", !empty($cabecera['tokenize']) ? 'si' : 'NO, habria que encenderlo');

// ---------------------------------------------------------------------------
// La red: se dibuja la cabecera nueva con un proveedor de verdad antes de
// guardar. Se hace sobre el manejador ya construido, en memoria, sin guardar.
// ---------------------------------------------------------------------------
\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));
$proveedor = (string) \Drupal::database()->select('taxonomy_term__field_tec_vendor', 'v')
  ->fields('v', ['field_tec_vendor_target_id'])
  ->range(0, 1)
  ->execute()
  ->fetchField();

$titulo('Prueba de la cabecera nueva antes de guardarla');
$vale = FALSE;
if ($proveedor === '') {
  echo "  ningun material tiene proveedor: no hay con que probar el argumento\n";
}
else {
  $ejecutable = $almacenVistas->load(VISTA)->getExecutable();
  $ejecutable->setDisplay(PANTALLA);
  $ejecutable->setArguments([$proveedor]);
  $ejecutable->preExecute();
  $ejecutable->build(PANTALLA);
  $area = $ejecutable->header['area_text_custom'] ?? NULL;
  if (!$area) {
    echo "  no se ha podido construir la cabecera\n";
  }
  else {
    $area->options['content'] = $despues;
    $html = (string) \Drupal::service('renderer')->renderInIsolation($area->render(FALSE));
    $enlace = preg_match('~href="([^"]*)"~', $html, $coincide)
      ? html_entity_decode($coincide[1])
      : '(no sale enlace)';
    printf("  con el proveedor %s el boton apunta a:\n    %s\n", $proveedor, $enlace);
    $vale = (bool) preg_match('~target_id=' . preg_quote($proveedor, '~') . '(&|$)~', $enlace)
      && str_contains($enlace, 'destination=/tec_crm/' . $proveedor);
    printf("  lleva el proveedor y vuelve a su ficha: %s\n", $vale ? 'SI' : 'NO');
  }
}

// ---------------------------------------------------------------------------
// 2. El relleno automatico de epp.
// ---------------------------------------------------------------------------
$titulo('El relleno automatico del proveedor');
$viejo = $almacenCampos->load(CAMPO_VIEJO);
$bueno = $almacenCampos->load(CAMPO_BUENO);

$relleno = $viejo ? (string) $viejo->getThirdPartySetting('epp', 'value', '') : '';
$tambienAlEditar = $viejo ? $viejo->getThirdPartySetting('epp', 'on_update', 0) : 0;

if (!$bueno) {
  echo "  no existe " . CAMPO_BUENO . ". No se puede mover el relleno.\n";
}
elseif ($relleno === '' && (string) $bueno->getThirdPartySetting('epp', 'value', '') !== '') {
  echo "  ya estaba movido: lo lleva " . CAMPO_BUENO . "\n";
  $relleno = (string) $bueno->getThirdPartySetting('epp', 'value', '');
}
elseif ($relleno === '') {
  echo "  no hay ningun relleno que mover\n";
}
else {
  printf("  se quita de   field_tec_suppliers  (%s)\n", $relleno);
  printf("  se pone en    field_tec_vendor     (%s, tambien al editar: %s)\n", $relleno, $tambienAlEditar ? 'si' : 'no');
}

if (!$deVerdad) {
  echo "\n  Esto era un ensayo. Para hacerlo:\n";
  echo "  php vendor\\bin\\drush.php scr scripts/arreglar-el-boton-de-anadir-material.php -- --de-verdad\n\n";
  return;
}

// Con la red puesta: si la cabecera nueva no sale bien, no se guarda nada.
if ($antes !== $despues && !$vale) {
  echo "\n  NO SE GUARDA NADA: la cabecera nueva no sale con el proveedor.\n";
  echo "  Revisa la marca antes de insistir.\n\n";
  return;
}

$titulo('Guardando');
if ($antes !== $despues) {
  $cabecera['content'] = $despues;
  $vista->set('display', $pantallas);
  $vista->save();
  printf("  views.view.%s  cabecera de %s cambiada\n", VISTA, PANTALLA);
}
else {
  printf("  views.view.%s  sin cambios\n", VISTA);
}

if ($bueno && $relleno !== '') {
  $bueno->setThirdPartySetting('epp', 'value', $relleno);
  $bueno->setThirdPartySetting('epp', 'on_update', $tambienAlEditar);
  $bueno->save();
  echo "  field.field." . CAMPO_BUENO . "  relleno puesto\n";
}

if ($viejo && (string) $viejo->getThirdPartySetting('epp', 'value', '') !== '') {
  $viejo->unsetThirdPartySetting('epp', 'value');
  $viejo->unsetThirdPartySetting('epp', 'on_update');
  $viejo->save();
  echo "  field.field." . CAMPO_VIEJO . "  relleno quitado\n";
}

echo "\n  Hecho. Comprueba con:\n";
echo "  php vendor\\bin\\drush.php scr scripts/funciona-la-pestana-de-materiales-del-proveedor.php\n";
echo "  php vendor\\bin\\drush.php scr scripts/abre-el-formulario-de-material-con-el-proveedor.php\n\n";
