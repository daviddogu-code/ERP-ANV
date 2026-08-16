<?php

/**
 * @file
 * Puts the columns of the purchase order draft in the order of the paper form.
 *
 * The order is the one the company has been using in Google Sheets for years:
 *
 *   Item | Picture | Material name | Qty | Unit | Purchase Cost | Total Cost
 *
 * Only the order changes here. No column is renamed, hidden, added or removed,
 * and nothing is touched outside the po/draft/% screen, because the point of
 * this step is to be able to hold the screen next to the paper form and compare
 * one single thing.
 *
 * Two columns on the screen are not on the paper form: the material description
 * and a second, editable copy of the line total. They are parked after the
 * seven rather than removed, so that this script cannot lose anything.
 *
 * Run: php vendor\bin\drush.php scr scripts/ordenar-las-columnas-del-borrador.php
 */

use Drupal\views\Entity\View;

/**
 * The columns in the order the paper form has them, plus the two strays.
 *
 * The hidden fields in between are not decoration. The Picture column is drawn
 * by a popup plugin that reads {{ field_tec_inventory_image }} for the
 * thumbnail and {{ field_tec_inventory_image_1 }} for the enlargement, and
 * Views only lets a field read the ones declared before it, so both have to
 * stay ahead of it or the column comes out blank. The hidden quantity form
 * field keeps its place ahead of the visible one for the same reason.
 */
const ORDEN = [
  // The seven of the paper form.
  'counter',                                     // Item.
  'form_field_field_tec_quantity',               // Hidden, feeds nothing but must precede its twin.
  'field_tec_inventory_image',                   // Hidden, thumbnail for Picture.
  'field_tec_inventory_image_1',                 // Hidden, enlargement for Picture.
  'simple_popup_views_field',                    // Picture.
  'name',                                        // Material name.
  'form_field_field_tec_quantity_1',             // Qty, the editable one.
  'field_tec_unit_purchase_1',                   // Unit.
  'field_tec_cost',                              // Purchase Cost.
  'field_tec_line_item_total_number',            // Total Cost.

  // On the screen but not on the paper form. Left visible, parked at the end.
  'description__value',
  'form_field_field_tec_line_item_total_number',

  // Hidden, in the order they were already in. view_tec_order has to stay ahead
  // of the edit and delete links, which build their URLs out of it.
  'id',
  'field_tec_quantity',
  'field_tec_units',
  'field_tec_price',
  'field_tec_unit_purchase',
  'field_tec_moq_quantity',
  'view_tec_order',
  'edit_tec_line_item',
  'delete_tec_line_item',
  'field_tec_vendor',
  'field_tec_uos',
  'field_views_simple_math_field_1',
  'field_tec_split_into',
  'field_tec_line_item_total_number_1',
  'field_tec_line_item_total',
];

$view = View::load('tec_order_sales_order_line_items');
if (!$view) {
  print "The view is not there. Nothing done.\n";
  return;
}

$display = $view->getDisplay('page_1');
$fields = $display['display_options']['fields'] ?? [];

// Refuse rather than guess. If the screen has gained or lost a column since
// this list was written, reordering it against a stale list would silently drop
// whatever is not named here.
$sobran = array_diff(array_keys($fields), ORDEN);
$faltan = array_diff(ORDEN, array_keys($fields));
if ($sobran || $faltan) {
  print "The screen no longer matches this script, so nothing has been changed.\n";
  if ($sobran) {
    print "  on screen but not in the list: " . implode(', ', $sobran) . "\n";
  }
  if ($faltan) {
    print "  in the list but not on screen: " . implode(', ', $faltan) . "\n";
  }
  return;
}

$antes = array_keys($fields);
if ($antes === ORDEN) {
  print "Already in this order. Nothing to do.\n";
  return;
}

$ordenados = [];
foreach (ORDEN as $id) {
  $ordenados[$id] = $fields[$id];
}

// Reach into the one display and put it back. Going through the whole display
// array rather than the loaded display keeps every other screen in this file
// byte for byte as it was; there are eleven more of them in here.
$displays = $view->get('display');
$displays['page_1']['display_options']['fields'] = $ordenados;
$view->set('display', $displays);
$view->save();

// Read it back rather than trust the save, and show only what someone looking
// at the screen would see.
$vuelto = array_keys(View::load('tec_order_sales_order_line_items')->getDisplay('page_1')['display_options']['fields']);
print ($vuelto === ORDEN ? "Done." : "Saved, but the order did not stick. Look at it.") . "\n\n";

print "Visible columns, left to right:\n";
$n = 0;
foreach ($vuelto as $id) {
  if (!empty($fields[$id]['exclude'])) {
    continue;
  }
  $n++;
  print '  ' . $n . '. ' . ($fields[$id]['label'] ?: '(no heading)') . "\n";
}
