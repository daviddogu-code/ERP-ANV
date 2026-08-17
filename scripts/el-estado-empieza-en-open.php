<?php

/**
 * @file
 * Un pedido nuevo empieza en Open, no en "On process".
 *
 *   php vendor\bin\drush.php scr scripts/el-estado-empieza-en-open.php
 *   php vendor\bin\drush.php scr scripts/el-estado-empieza-en-open.php -- --aplicar
 *
 * Sin --aplicar solo cuenta lo que haria.
 *
 * El campo nacio ayer con "On process" de valor por defecto, y el guion de
 * encendido lo sembro ademas en los once pedidos que ya existian. Las dos cosas
 * decian lo mismo y las dos estaban mal: "On process" es una respuesta, y un
 * pedido recien hecho no tiene respuesta todavia. Nadie ha llamado al
 * proveedor, nadie ha preguntado nada, y una pantalla que arranca con una
 * casilla rellena hace que el dia que alguien la rellene de verdad no se note.
 *
 * Open no es un cuarto valor guardado, es la ausencia de los otros tres. Por
 * eso esto no anade nada al campo: le quita el defecto y borra lo sembrado.
 * Purchasing::progress() ya sabe leer el vacio como Open.
 *
 * Lo que se borra se recupera con un clic, que es justo el clic que faltaba.
 *
 * Se puede lanzar dos veces.
 */

use Drupal\field\Entity\FieldConfig;

$aplicar = in_array('--aplicar', $extra ?? [], TRUE) || in_array('--aplicar', $_SERVER['argv'] ?? [], TRUE);

const DESCRIPCION = 'Where the goods are while they are on their way, which nothing in the ERP can work out on its own. Moved by hand on the purchase control screen, and empty until somebody moves it: an order nobody has chased yet reads Open. The three states that finish an order -- Delivered, Closed and Cancelled -- are not in here on purpose. They are read off the receipts, the invoice and the open/closed flag, so nobody can claim one of them without the facts behind it.';

print "\n1. El campo deja de venir relleno de fabrica\n";
print "-------------------------------------------\n";

$campo = FieldConfig::loadByName('tec_order', 'tec_purchase_order', 'field_tec_po_queue_status');
if (!$campo) {
  print "[MAL ] no existe field_tec_po_queue_status. Nada que hacer.\n";
  return;
}

$defecto = $campo->getDefaultValueLiteral();
$toca_descripcion = $campo->getDescription() !== DESCRIPCION;

if (!$defecto && !$toca_descripcion) {
  print "[ya  ] el campo ya nace vacio y la explicacion ya es la buena\n";
}
else {
  if ($defecto) {
    print sprintf("%s quitar el defecto '%s'\n", $aplicar ? '[hecho]' : '[haria]', $defecto[0]['value'] ?? '?');
  }
  if ($toca_descripcion) {
    // La explicacion vieja prometia que el formulario de recibir escribiria
    // "Delivered" en este campo. Nunca lo hizo y nunca debio hacerlo.
    print sprintf("%s reescribir la explicacion, que prometia un Delivered que no existe\n", $aplicar ? '[hecho]' : '[haria]');
  }
  if ($aplicar) {
    $campo->setDefaultValue([])->setDescription(DESCRIPCION)->save();
  }
}

print "\n2. Los pedidos que ya llevan el 'On process' sembrado\n";
print "----------------------------------------------------\n";

$storage = \Drupal::entityTypeManager()->getStorage('tec_order');
$ids = $storage->getQuery()
  ->condition('type', 'tec_purchase_order')
  ->condition('field_tec_po_queue_status', 'on_process')
  ->accessCheck(FALSE)
  ->execute();

// Se vacian todos los que estan en el primer escalon, sin distinguir cual
// sembro el guion y cual eligio una persona. Distinguirlos haria falta si
// perder el dato costase algo, y no cuesta: "On process" es el primer valor de
// la lista y se vuelve a poner con un clic. Quedarse corto seria peor, porque
// once pedidos afirmando que alguien los esta gestionando es exactamente la
// mentira que este cambio viene a quitar.
$vaciados = 0;
foreach ($storage->loadMultiple($ids) as $order) {
  if ($aplicar) {
    $order->set('field_tec_po_queue_status', NULL)->save();
  }
  $vaciados++;
  print sprintf("%s %s vuelve a Open\n", $aplicar ? '[hecho]' : '[haria]', $order->label());
}

if (!$vaciados) {
  print "[ya  ] ninguno esta en el primer escalon\n";
}

print "\nVaciados: $vaciados.\n";
if (!$aplicar) {
  print "\nEsto ha sido en seco. Con --aplicar se escribe.\n";
}
