<?php

/**
 * @file
 * Leaves the purchase order draft with the seven columns of the paper form.
 *
 * Two columns go: the material description, which the paper form does not have
 * because the material name already carries it, and a second copy of the line
 * total rendered as an editable box. The box was a trap rather than a feature:
 * whatever was typed into it was overwritten on save by the process that works
 * the line total out from quantity and cost, so it accepted numbers and threw
 * them away. The line total that stays is the read-only one.
 *
 * The grand total script is fixed in the same go, because it cannot survive the
 * columns changing underneath it. It was written for the sales draft and has
 * been attached to the purchase draft ever since without ever having worked
 * there: it looks for a price in .views-field-field-tec-price and a quantity in
 * .views-field-form-field-field-tec-quantity, and on the purchase draft the
 * price lives in .views-field-field-tec-cost and the quantity box is the second
 * one in the view, so its class ends in -1. Neither selector matches, so every
 * row was being valued at nothing. It also drew its footer with a hardcoded
 * span of eight columns, which stops being true the moment a column goes.
 *
 * Run: php vendor\bin\drush.php scr scripts/quitar-las-dos-columnas-del-borrador.php
 */

use Drupal\views\Entity\View;

/**
 * The columns to take off the purchase order draft.
 */
const FUERA = [
  'description__value',
  'form_field_field_tec_line_item_total_number',
];

/**
 * What the seven remaining columns should be, in order, as a safety net.
 */
const QUEDAN = [
  'counter',
  'simple_popup_views_field',
  'name',
  'form_field_field_tec_quantity_1',
  'field_tec_unit_purchase_1',
  'field_tec_cost',
  'field_tec_line_item_total_number',
];

// ---------------------------------------------------------------------------
// The two columns.
// ---------------------------------------------------------------------------

$view = View::load('tec_order_sales_order_line_items');
$displays = $view->get('display');
$fields = $displays['page_1']['display_options']['fields'] ?? [];

$ausentes = array_diff(FUERA, array_keys($fields));
if (count($ausentes) === count(FUERA)) {
  print "Both columns are already gone from the screen.\n";
}
else {
  foreach (FUERA as $id) {
    unset($fields[$id]);
  }
  $displays['page_1']['display_options']['fields'] = $fields;

  // The table style keeps its own list of which field is drawn in which column
  // and how each one is aligned. Views ignores entries for fields that are no
  // longer there, so leaving them behind would do no visible harm, but it would
  // leave the next person reading a list of columns that includes two that do
  // not exist.
  foreach (['columns', 'info'] as $mapa) {
    foreach (FUERA as $id) {
      unset($displays['page_1']['display_options']['style']['options'][$mapa][$id]);
    }
  }

  $view->set('display', $displays);
  $view->save();
}

$ahora = array_keys(array_filter(
  View::load('tec_order_sales_order_line_items')->getDisplay('page_1')['display_options']['fields'],
  static fn ($f) => empty($f['exclude'])
));

print "\nVisible columns now: " . count($ahora) . "\n";
foreach ($ahora as $i => $id) {
  print '  ' . ($i + 1) . '. ' . $id . "\n";
}
print "\n" . ($ahora === QUEDAN ? "These are the seven of the paper form." : "NOT the seven expected. Look at it.") . "\n";

// ---------------------------------------------------------------------------
// The grand total script.
// ---------------------------------------------------------------------------

// The baht sign is written as an escape so that this file can be saved in any
// encoding without the symbol turning into rubbish on the page.
$js = <<<'JS'
jQuery(document).ready(function () {
    // The column every figure here is about: the line total. It is what the row
    // shows, what the grand total adds up and what ends up on the paper, so it
    // is the one that has to be right.
    var TOTAL = '.views-field-field-tec-line-item-total-number';

    // Two draft screens share this script and they do not name their columns
    // alike. A sales line carries its own price and its quantity box is the
    // first in the view; a purchase line is priced from the material's purchase
    // cost and its quantity box is the second, so Views suffixes the class with
    // -1. Rather than work it out from the address, each row is asked which of
    // the two it happens to have.
    var PRICE = '.views-field-field-tec-price, .views-field-field-tec-cost';
    var QTY = '.views-field-form-field-field-tec-quantity input, .views-field-form-field-field-tec-quantity-1 input';

    function money(amount) {
        return '\u0E3F ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Writes the line total into its cell and hands it back.
    function updateRowTotal(row) {
        // The cell holds the figure in a strong on some screens and bare on
        // others. Whichever it is, it is the thing to write into.
        var cell = row.find(TOTAL).find('strong');
        if (!cell.length) {
            cell = row.find(TOTAL);
        }
        if (!cell.length) {
            return 0;
        }

        var price = parseFloat(row.find(PRICE).first().text().replace(/[^\d.-]/g, ''));
        var quantity = parseFloat(row.find(QTY).first().val());

        // A line with no quantity typed in yet is worth nothing, not an error.
        if (isNaN(price) || isNaN(quantity)) {
            cell.text(money(0));
            return 0;
        }

        var total = price * quantity;
        cell.text(money(total));
        return total;
    }

    function updateGrandTotal() {
        jQuery('.views-table').each(function () {
            var table = jQuery(this);
            var grandTotal = 0;

            table.find('tbody tr').not('.grand-total-row').each(function () {
                grandTotal += updateRowTotal(jQuery(this));
            });

            var row = table.find('.grand-total-row');
            if (!row.length) {
                row = jQuery('<tr class="grand-total-row"><td class="views-align-right"><strong>Grand Total</strong></td><td class="views-align-right"><strong class="grand-total-value"></strong></td></tr>');
                table.find('tbody').append(row);
            }

            // Counted off the table rather than hardcoded. The number of columns
            // is exactly the sort of thing that changes, and when it did, the
            // old fixed span of eight left this row hanging off the edge.
            var columns = table.find('thead th').length || table.find('tbody tr').first().find('td').length;
            row.find('td').first().attr('colspan', Math.max(1, columns - 1));
            row.find('.grand-total-value').text(money(grandTotal));
        });
    }

    // input covers mouse-wheel on number fields; keyup/change cover keyboard.
    jQuery(document).on('input keyup change', QTY, function () {
        updateGrandTotal();
    });

    updateGrandTotal();
});
JS;

$almacen = \Drupal::entityTypeManager()->getStorage('asset_injector_js');
$inyector = $almacen->load('draft_for_ace_company_view');
if (!$inyector) {
  print "\nThe grand total script is not there, so it has been left alone.\n";
  return;
}

if (trim($inyector->get('code')) === trim($js)) {
  print "\nThe grand total script is already the fixed one.\n";
  return;
}

$inyector->set('code', $js);
$inyector->save();

print "\nGrand total script rewritten:\n";
print "  reads the price from either column, so both drafts work\n";
print "  reads the quantity box whichever of the two it is\n";
print "  works out the footer span from the table instead of assuming eight\n";
