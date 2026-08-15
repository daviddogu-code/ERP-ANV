<?php

/**
 * @file
 * Abre los cuatro huecos de datos que necesita la recepcion de mercancia.
 *
 *   php vendor\bin\drush.php scr scripts/crear-los-campos-de-la-recepcion.php
 *
 * Hasta hoy un pedido de compra no podia dejar de estar en camino: el campo de
 * estado solo admitia un valor, y ninguna linea guardaba lo que habia llegado.
 * Asi que la cantidad pedida se quedaba sumando en "Ordered (UoP)" para siempre,
 * aunque la mercancia estuviese ya en la estanteria.
 *
 * Se aplican los tres principios que comparten Odoo y SAP, y cada campo es uno
 * de ellos:
 *
 *   1. Lo pendiente se deduce, no se escribe. De ahi el recibido por linea:
 *      pendiente = pedido - recibido, y no hay ninguna casilla donde teclear
 *      "lo que queda por llegar".
 *   2. Todo movimiento dice de donde viene. De ahi la referencia del movimiento
 *      de inventario a su linea de pedido, que es lo que Odoo guarda en sus
 *      movimientos de stock.
 *   3. El pedido corto se cierra a proposito. De ahi la marca de que no viene
 *      mas, con su motivo, que es la entrega completada de SAP.
 *
 * Y el segundo valor de estado, para que un pedido terminado salga de las
 * listas de pendientes. Son dos y solo dos: si llego todo, parte o nada se
 * deduce comparando las lineas, y guardarlo aparte seria una segunda version de
 * la verdad que acabaria discrepando de la primera.
 *
 * El recibido es entero porque lo pedido es entero. Con uno decimal y el otro
 * no, lo pendiente saldria con arrastres que nadie sabria explicar.
 *
 * Se puede lanzar dos veces: lo que ya existe se deja como esta.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$hechos = 0;
$saltados = 0;

/**
 * Crea un almacen de campo si no lo hay.
 */
$almacen = function (string $entity_type, string $field_name, string $type, array $settings = []) use (&$hechos, &$saltados): void {
  if (FieldStorageConfig::loadByName($entity_type, $field_name)) {
    print "[ya  ] almacen $entity_type.$field_name\n";
    $saltados++;
    return;
  }
  FieldStorageConfig::create([
    'field_name' => $field_name,
    'entity_type' => $entity_type,
    'type' => $type,
    'settings' => $settings,
    'cardinality' => 1,
  ])->save();
  print "[hecho] almacen $entity_type.$field_name ($type)\n";
  $hechos++;
};

/**
 * Cuelga el campo de un tipo de ficha si no esta colgado.
 */
$campo = function (string $entity_type, string $bundle, string $field_name, string $label, string $description, array $extra = []) use (&$hechos, &$saltados): void {
  if (FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
    print "[ya  ] campo $bundle.$field_name\n";
    $saltados++;
    return;
  }
  FieldConfig::create([
    'field_name' => $field_name,
    'entity_type' => $entity_type,
    'bundle' => $bundle,
    'label' => $label,
    'description' => $description,
    'required' => FALSE,
  ] + $extra)->save();
  print "[hecho] campo $bundle.$field_name '$label'\n";
  $hechos++;
};

print "\n1. Lo recibido, la marca de cierre y su motivo, en la linea de pedido\n";
print "--------------------------------------------------------------------\n";

$almacen('tec_line_item', 'field_tec_quantity_received', 'integer', ['unsigned' => FALSE, 'size' => 'normal']);
$campo(
  'tec_line_item',
  'tec_po_line_item',
  'field_tec_quantity_received',
  'Received (UoP)',
  'How much of this line has already arrived, in purchase units. The Receive form maintains it; change it by hand only to correct a mistake.',
  ['default_value' => [['value' => 0]]]
);

$almacen('tec_line_item', 'field_tec_no_more_expected', 'boolean');
$campo(
  'tec_line_item',
  'tec_po_line_item',
  'field_tec_no_more_expected',
  'Nothing more expected',
  'Ticked when the rest of this line is never coming. What is left stops counting as ordered, so the material can be bought again.',
  ['default_value' => [['value' => 0]]]
);

$almacen('tec_line_item', 'field_tec_close_reason', 'string', ['max_length' => 255, 'case_sensitive' => FALSE]);
$campo(
  'tec_line_item',
  'tec_po_line_item',
  'field_tec_close_reason',
  'Reason for closing',
  'Why the rest is not coming. Optional, but line items keep no author or revisions, so without it that decision leaves no trace at all.'
);

print "\n2. De donde viene cada entrada de stock\n";
print "--------------------------------------\n";

$almacen('tec_inventory', 'field_tec_po_line', 'entity_reference', ['target_type' => 'tec_line_item']);
$campo(
  'tec_inventory',
  'tec_inventory_transaction',
  'field_tec_po_line',
  'Purchase order line',
  'The purchase order line this stock came in on, when it came from a delivery. Empty for a hand-made +/- mutation.',
  [
    'settings' => [
      'handler' => 'default:tec_line_item',
      'handler_settings' => [
        'target_bundles' => ['tec_po_line_item' => 'tec_po_line_item'],
      ],
    ],
  ]
);

print "\n3. El segundo estado del pedido\n";
print "-------------------------------\n";

// Aqui hay una trampa que cuesta media hora si no se sabe. En el disco la lista
// se guarda como parejas -value y label-, pero getSetting() y setSetting()
// hablan en 'clave => etiqueta' y Drupal traduce entre las dos formas al
// guardar. Si se le pasa al setter la forma del disco, el modulo options compara
// las claves de antes con las de ahora, ve que 'open' ya no esta entre ellas, y
// como hay pedidos usandolo se niega en redondo: "a list field with existing
// data cannot have its keys changed".
$storage = FieldStorageConfig::loadByName('tec_order', 'field_tec_po_status');
$valores = $storage->getSetting('allowed_values');
if (isset($valores['closed'])) {
  print "[ya  ] el estado 'closed' ya existia\n";
  $saltados++;
}
else {
  $valores['closed'] = 'Closed';
  $storage->setSetting('allowed_values', $valores)->save();
  print "[hecho] estado 'closed' anadido, ahora hay " . count($valores) . " estados\n";
  $hechos++;
}

print "\n4. Que se puedan ver y arreglar a mano en la ficha de la linea\n";
print "-------------------------------------------------------------\n";

$formulario = \Drupal::service('entity_display.repository')
  ->getFormDisplay('tec_line_item', 'tec_po_line_item');
$colocados = 0;
$peso = 6;
foreach (
  [
    'field_tec_quantity_received' => 'number',
    'field_tec_no_more_expected' => 'boolean_checkbox',
    'field_tec_close_reason' => 'string_textfield',
  ] as $nombre => $tipo
) {
  if ($formulario->getComponent($nombre)) {
    print "[ya  ] $nombre ya estaba en el formulario\n";
    continue;
  }
  $formulario->setComponent($nombre, [
    'type' => $tipo,
    'weight' => $peso++,
    'region' => 'content',
  ]);
  $colocados++;
}
if ($colocados) {
  $formulario->save();
  print "[hecho] $colocados campos puestos en el formulario de la linea\n";
  $hechos++;
}

print "\n";
print "Hechos: $hechos. Ya estaban: $saltados.\n";
print "\nAhora hay que exportar la configuracion para que esto quede en el disco.\n";
