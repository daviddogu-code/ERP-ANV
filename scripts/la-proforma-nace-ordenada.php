<?php

/**
 * @file
 * La proforma nace en el orden del catalogo, y el papel lo respeta.
 *
 *   php vendor\bin\drush.php scr scripts/la-proforma-nace-ordenada.php
 *
 * El boton "+ Order" crea el borrador entero de golpe: ECA le pide a la vista
 * tec_order_eca_create_draft_sales todas las tallas de todos los productos de
 * las marcas del cliente y crea una linea por fila, en el orden en que la vista
 * las devuelve. Esa vista no tenia ningun orden -sorts: {  }-, asi que las
 * lineas salian como se las diera la base de datos: mezcladas.
 *
 * Ahora las ordena CatalogueOrder por los cuatro niveles, marca, producto,
 * color y talla, justo cuando vuelve la consulta. Y como cada linea se guarda en
 * ese orden, su numero de linea lo hereda: el orden queda **congelado** en el
 * momento de crearlas, igual que el precio. Volver a arrastrar la marca el mes
 * que viene cambia la proforma siguiente, no las que ya se enviaron.
 *
 * Para que eso se cumpla hasta el final, cada pantalla que lista las lineas de
 * un pedido tiene que ordenar por el numero de linea. Casi todas lo hacian ya
 * desde el 18 de agosto; faltaban cuatro, y la que mas dolia era la impresion de
 * la Pro Forma: el documento que se envia al cliente era el unico sitio donde
 * las lineas podian salir en cualquier orden, y sin ORDER BY una consulta puede
 * devolverlas hoy de una manera y manana de otra.
 *
 * Se puede lanzar dos veces: la segunda no encuentra nada que cambiar.
 */

const VISTA = 'tec_order_sales_order_line_items';

/**
 * Las pantallas que listan lineas para que las lea una persona.
 *
 * Son las cuatro que no ordenaban por nada. Las demas de la vista ya lo hacian:
 * el bloque del pedido, el borrador de venta y las dos que estan sin usar.
 */
const LISTAN = [
  'page_1' => 'Purchase order draft page (po/draft/%)',
  'page_3' => 'PO Print Page (po/%/print)',
  'page_4' => 'Pro Forma Print Page (o/pf/%/print)',
  'block_2' => 'Purchase order block',
];

/**
 * Las pantallas que no pueden ordenar, aunque parezca que les vendria bien.
 *
 * Cuatro de esta vista agregan -suman importes o dibujan un boton por pedido- y
 * ninguna tiene orden propio, asi que heredan el de la principal. Y una pantalla
 * que agrupa no puede ordenar por una columna que no esta agregada: Views la
 * mete en el GROUP BY, y entonces la suma deja de ser por pedido y pasa a ser
 * por linea.
 *
 * Medido, no supuesto: al poner el orden en la principal, el bloque de totales
 * del borrador (block_5) devolvio siete filas en vez de una, y los botones del
 * borrador (attachment_3) tambien. Por eso la principal se queda sin orden y
 * cada pantalla que lo necesita lo pide por su cuenta.
 */
const SIN_ORDEN = [
  'default' => 'Default, de la que heredan las que agregan',
  'attachment_2' => 'Sales order totals attachment (unused), agrega',
  'attachment_3' => 'Sales order draft buttons attachment, agrega',
  'attachment_4' => 'Order buttons attachment, no lista nada',
  'attachment_8' => 'Purchase order draft buttons attachment, agrega',
  'block_5' => 'Sales order draft totals block, agrega',
];

/**
 * Ordenar por el numero de linea, que es el orden en que se crearon.
 */
function ordenPorLinea(): array {
  return [
    'id' => 'id',
    'table' => 'tec_line_item_field_data',
    'field' => 'id',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'tec_line_item',
    'entity_field' => 'id',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'expose' => ['label' => '', 'field_identifier' => ''],
    'exposed' => FALSE,
  ];
}

print "\n";
print "=====================================================================\n";
print "  La proforma nace ordenada\n";
print "=====================================================================\n\n";

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  print "  No existe la vista " . VISTA . ".\n\n";
  return;
}

print "1. Quien lista lineas y no las ordenaba\n";
print "---------------------------------------\n";

$pantallas = $vista->get('display');
$cambios = 0;

foreach (LISTAN as $nombre => $que) {
  if (!isset($pantallas[$nombre])) {
    printf("  %-14s no existe\n", $nombre);
    continue;
  }
  $tiene = $pantallas[$nombre]['display_options']['sorts'] ?? [];
  if ($tiene) {
    printf("  %-14s ya ordenaba por %s\n", $nombre, implode(' + ', array_keys($tiene)));
    continue;
  }

  $pantallas[$nombre]['display_options']['sorts'] = ['id' => ordenPorLinea()];
  printf("  %-14s sin orden -> numero de linea ASC   (%s)\n", $nombre, $que);
  $cambios++;
}

print "\n   Y las que no deben ordenar, porque agregan:\n";

foreach (SIN_ORDEN as $nombre => $que) {
  if (!isset($pantallas[$nombre])) {
    continue;
  }
  $tiene = $pantallas[$nombre]['display_options']['sorts'] ?? [];
  if (!$tiene) {
    printf("  %-14s sin orden, como debe   (%s)\n", $nombre, $que);
    continue;
  }

  $pantallas[$nombre]['display_options']['sorts'] = [];
  printf("  %-14s TENIA %s: fuera, que le partia la suma   (%s)\n", $nombre, implode(' + ', array_keys($tiene)), $que);
  $cambios++;
}

if ($cambios) {
  $vista->set('display', $pantallas);
  $vista->save();
  printf("\n  Guardada la vista con %d pantallas ordenadas.\n", $cambios);
}
else {
  print "\n  Todas ordenaban ya.\n";
}

print "\n2. Que orden lleva ahora la vista que crea el borrador\n";
print "-----------------------------------------------------\n";

// La vista de creacion sigue sin sorts a proposito: dos de los cuatro niveles
// son deltas de un campo de varios valores, y Views solo los alcanza volviendo a
// unir la tabla, lo que duplica filas. Lo dice CatalogueOrder, y aqui se
// comprueba que el enganche esta puesto.
$creacion = \Drupal::entityTypeManager()->getStorage('view')->load('tec_order_eca_create_draft_sales');
$sorts = $creacion ? ($creacion->get('display')['default']['display_options']['sorts'] ?? []) : [];
printf("  sorts de la vista de creacion: %s\n", $sorts ? implode(' + ', array_keys($sorts)) : 'ninguno, los pone PHP');
printf(
  "  enganche en el modulo: %s\n",
  function_exists('tec_production_views_post_execute') ? 'tec_production_views_post_execute()' : 'NO ESTA'
);
printf(
  "  pantallas que ordena CatalogueOrder: %s\n",
  json_encode(\Drupal\tec_production\CatalogueOrder::SCREENS)
);

print "\n3. Como sale un cliente de verdad\n";
print "---------------------------------\n";

// El primer cliente que tenga marcas, para ver el orden con datos reales.
$almacen_crm = \Drupal::entityTypeManager()->getStorage('tec_crm');
$ids = $almacen_crm->getQuery()
  ->exists('field_tec_brands')
  ->accessCheck(FALSE)
  ->sort('id')
  ->range(0, 1)
  ->execute();

if (!$ids) {
  print "  no hay ningun cliente con marcas en esta base\n";
}
else {
  $cliente = $almacen_crm->load(reset($ids));
  $marcas = [];
  foreach ($cliente->get('field_tec_brands') as $item) {
    $marcas[] = $item->entity ? $item->entity->label() : ('?' . $item->target_id);
  }
  printf("  cliente %s (%s), marcas en su orden: %s\n", $cliente->id(), $cliente->label(), implode(' | ', $marcas));

  $vista_creacion = \Drupal\views\Views::getView('tec_order_eca_create_draft_sales');
  $vista_creacion->setDisplay('default');
  $vista_creacion->setArguments([$cliente->id()]);
  $vista_creacion->preExecute();
  $vista_creacion->execute();

  printf("  %d filas, en este orden:\n", count($vista_creacion->result));
  foreach ($vista_creacion->result as $i => $fila) {
    $talla = $fila->_entity ?? NULL;
    if (!$talla) {
      continue;
    }
    $producto = !$talla->get('field_tec_product')->isEmpty() ? $talla->get('field_tec_product')->entity : NULL;
    $color = !$talla->get('field_tec_color_variation')->isEmpty() ? $talla->get('field_tec_color_variation')->entity : NULL;
    printf(
      "    %2d  %-30s sitio %-4s %-26s %s\n",
      $i + 1,
      $producto ? mb_substr((string) $producto->label(), 0, 30) : '(sin producto)',
      $producto ? \Drupal\tec_production\CataloguePosition::of($producto) : '-',
      $color ? mb_substr((string) $color->label(), 0, 26) : '(sin color)',
      $talla->label()
    );
  }
}

print "\n";
print "Ahora hay que exportar la configuracion para que esto quede en el disco.\n";
print "\n";
