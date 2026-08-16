<?php

/**
 * @file
 * Abre los tres huecos de datos que necesita la pantalla de control de compras.
 *
 *   php vendor\bin\drush.php scr scripts/crear-los-campos-de-la-cola-de-compras.php
 *
 * Quien vigila los pedidos de compra lo lleva hoy en una hoja de Google, con
 * cuatro estados de colores, una fecha esperada, la fecha en que llego y un
 * enlace al PDF de la factura. Nada de eso existe en el ERP, asi que la hoja se
 * rellena a mano despues de crear cada pedido y se borra a mano cuando la
 * mercancia entra en la fabrica.
 *
 * Tres campos, y solo tres, porque el resto de la hoja ya lo sabe el ERP: el
 * numero del pedido, el proveedor, quien lo creo y cuando. Y la fecha de llegada
 * tampoco se guarda: sale de los movimientos de almacen que creo la recepcion,
 * que ya apuntan a la linea de la que vinieron y llevan su fecha.
 *
 * El detalle que mas importa de los tres:
 *
 *   El estado de la pantalla NO es el estado del pedido. "Open" y "Closed" son
 *   lo que le importa al almacen -si viene mercancia o no-, y los decide la
 *   recepcion. Esto otro es donde esta la mercancia mientras viene, que el ERP
 *   no puede adivinar y por eso lo mueve una persona. Son dos preguntas
 *   distintas y por eso son dos campos: si el cierre esperase a la factura, un
 *   material que ya esta en la estanteria seguiria contando como "en camino"
 *   durante semanas y la lista de la compra se negaria a volver a pedirlo.
 *
 * La hoja tiene cuatro estados y aqui solo se guardan tres. El cuarto,
 * "Delivered", no cabe en este campo: la mercancia ha llegado cuando hay un
 * movimiento de almacen que lo dice, y eso ya se sabe mirando las lineas. Un
 * desplegable donde alguien pueda escribir "entregado" sin que entre una sola
 * unidad en el stock es exactamente la mentira que este ERP existe para no
 * contar, asi que la pantalla lo deduce y no lo ofrece.
 *
 * La factura va en el almacen privado, no en el publico. Es un documento
 * comercial con precios y datos fiscales de un tercero, y en el publico
 * cualquiera con la direccion se la descarga sin estar identificado.
 *
 * Se puede lanzar dos veces: lo que ya existe se deja como esta.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

const TIPO = 'tec_purchase_order';

$hechos = 0;
$saltados = 0;

/**
 * Crea un almacen de campo si no lo hay.
 */
$almacen = function (string $field_name, string $type, array $settings = []) use (&$hechos, &$saltados): void {
  if (FieldStorageConfig::loadByName('tec_order', $field_name)) {
    print "[ya  ] almacen tec_order.$field_name\n";
    $saltados++;
    return;
  }
  FieldStorageConfig::create([
    'field_name' => $field_name,
    'entity_type' => 'tec_order',
    'type' => $type,
    'settings' => $settings,
    'cardinality' => 1,
  ])->save();
  print "[hecho] almacen tec_order.$field_name ($type)\n";
  $hechos++;
};

/**
 * Cuelga el campo del pedido de compra si no esta colgado.
 */
$campo = function (string $field_name, string $label, string $description, array $extra = []) use (&$hechos, &$saltados): void {
  if (FieldConfig::loadByName('tec_order', TIPO, $field_name)) {
    print "[ya  ] campo " . TIPO . ".$field_name\n";
    $saltados++;
    return;
  }
  FieldConfig::create([
    'field_name' => $field_name,
    'entity_type' => 'tec_order',
    'bundle' => TIPO,
    'label' => $label,
    'description' => $description,
    'required' => FALSE,
  ] + $extra)->save();
  print "[hecho] campo $field_name '$label'\n";
  $hechos++;
};

print "\n1. Donde esta la mercancia mientras viene\n";
print "-----------------------------------------\n";

const ESTADOS = [
  'on_process' => 'On process',
  'ready_to_pick_up' => 'Ready to pick up',
  'on_the_way' => 'On the way',
];

$almacen('field_tec_po_queue_status', 'list_string', ['allowed_values' => ESTADOS]);
$campo(
  'field_tec_po_queue_status',
  'Delivery progress',
  'Where the goods are while they are on their way, which nothing in the ERP can work out on its own. Moved by hand on the purchase control screen. There is no "Delivered" here on purpose: that one is read off the receipts, so it cannot be claimed without the stock to back it. And it is not the same as Order status, which is what the warehouse reads to know whether anything is still coming.',
  ['default_value' => [['value' => 'on_process']]]
);

// Por si una version anterior del guion dejo el cuarto valor puesto. Quitarlo
// solo es seguro mientras no lo use ningun pedido, y no lo usa ninguno porque
// la pantalla nunca lo ha ofrecido.
$almacen_estados = FieldStorageConfig::loadByName('tec_order', 'field_tec_po_queue_status');
if (array_key_exists('delivered', $almacen_estados->getSetting('allowed_values') ?: [])) {
  $usados = \Drupal::entityQuery('tec_order')
    ->condition('field_tec_po_queue_status', 'delivered')
    ->accessCheck(FALSE)
    ->count()
    ->execute();
  if ($usados) {
    print "[MAL ] hay $usados pedidos con 'delivered' guardado. No se toca: mirar de donde salieron.\n";
  }
  else {
    $almacen_estados->setSetting('allowed_values', ESTADOS)->save();
    print "[hecho] quitado el valor 'delivered', que ahora se deduce\n";
    $hechos++;
  }
}

print "\n2. Cuando dijo el proveedor que llegaria\n";
print "---------------------------------------\n";

$almacen('field_tec_expected_delivery', 'datetime', ['datetime_type' => 'date']);
$campo(
  'field_tec_expected_delivery',
  'Expected delivery day',
  'The day the supplier said it would arrive. Written by hand, and corrected on the purchase control screen when the supplier changes their mind, which is where it gets looked at.'
);

print "\n3. La factura del proveedor\n";
print "---------------------------\n";

$almacen('field_tec_supplier_invoice', 'file', ['uri_scheme' => 'private', 'target_type' => 'file', 'display_field' => FALSE, 'display_default' => FALSE]);
$campo(
  'field_tec_supplier_invoice',
  'Invoice',
  "The supplier's invoice, scanned or photographed. Uploading it is what takes the order off the purchase control screen: the goods arrived weeks ago, but the paperwork is what finishes it.",
  [
    'settings' => [
      'file_directory' => 'supplier-invoices/[date:custom:Y]',
      'file_extensions' => 'pdf jpg jpeg png heic webp',
      'max_filesize' => '',
      'description_field' => FALSE,
    ],
  ]
);

print "\n4. Que se puedan ver y arreglar a mano en la ficha del pedido\n";
print "------------------------------------------------------------\n";

$formulario = \Drupal::service('entity_display.repository')->getFormDisplay('tec_order', TIPO);
$colocados = 0;
$peso = 20;
foreach (
  [
    'field_tec_po_queue_status' => 'options_select',
    'field_tec_expected_delivery' => 'datetime_default',
    'field_tec_supplier_invoice' => 'file_generic',
  ] as $nombre => $tipo
) {
  if ($formulario->getComponent($nombre)) {
    print "[ya  ] $nombre ya estaba en el formulario\n";
    continue;
  }
  $formulario->setComponent($nombre, ['type' => $tipo, 'weight' => $peso++, 'region' => 'content']);
  $colocados++;
}
if ($colocados) {
  $formulario->save();
  print "[hecho] $colocados campos puestos en el formulario del pedido\n";
  $hechos++;
}

print "\nHechos: $hechos. Ya estaban: $saltados.\n";
print "\nAhora hay que exportar la configuracion para que esto quede en el disco.\n";
