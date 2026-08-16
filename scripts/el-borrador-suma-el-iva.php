<?php

/**
 * @file
 * El borrador de compra suma el IVA mientras se teclea.
 *
 * El pie de las otras dos pantallas de compra lo dibuja el servidor. El del
 * borrador no puede: es un formulario, y la gracia es que las cifras se muevan
 * segun se escriben las cantidades, antes de guardar nada. Asi que ahi la suma
 * la hace el navegador, y el porcentaje se lo pasa el gancho
 * tec_production_views_pre_render().
 *
 * El mismo script corre en el borrador de ventas. Si no llega porcentaje, la
 * pantalla se comporta exactamente como hasta hoy, con su Grand Total y nada
 * mas, que es lo que ventas debe seguir viendo hasta que su IVA se piense.
 *
 * Run: drush scr scripts/el-borrador-suma-el-iva.php
 */

$config = \Drupal::configFactory()->getEditable('asset_injector.js.draft_for_ace_company_view');
if ($config->isNew()) {
  print "No existe el inyector draft_for_ace_company_view.\n";
  return;
}

$codigo = <<<'JS'
jQuery(document).ready(function () {
    // The column every figure here is about: the line total. It is what the row
    // shows, what the foot adds up and what ends up on the paper, so it is the
    // one that has to be right.
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

    // The rate the server stamped on this order, or null when there is none.
    // Only the purchase draft is handed one. The sales draft gets nothing on
    // purpose and so keeps the single total it has always had.
    function vatRate() {
        var given = (window.drupalSettings || {}).tecPurchaseVat;
        return given && typeof given.rate === 'number' ? given.rate : null;
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

    // What goes at the foot of the table: one line without a rate, three with
    // one. The VAT is rounded to the satang before being added, which is how
    // the server does it, or the draft and the printed order would end up
    // disagreeing by a coin on the way to the supplier.
    function footLines(subtotal) {
        var rate = vatRate();
        if (rate === null) {
            return [['Grand Total', subtotal, true]];
        }

        var vat = Math.round(subtotal * rate) / 100;
        return [
            ['Subtotal', subtotal, false],
            ['VAT ' + String(parseFloat(rate.toFixed(2))) + '%', vat, false],
            ['Total', subtotal + vat, true]
        ];
    }

    function updateFoot(table, subtotal) {
        table.find('.tec-foot-row').remove();

        // Counted off the table rather than hardcoded. The number of columns is
        // exactly the sort of thing that changes, and when it did, the old fixed
        // span of eight left this row hanging off the edge.
        var columns = table.find('thead th').length || table.find('tbody tr').first().find('td').length;
        var body = table.find('tbody');

        footLines(subtotal).forEach(function (line) {
            var row = jQuery('<tr class="tec-foot-row"><td class="views-align-right"></td><td class="views-align-right"></td></tr>');
            var label = jQuery('<span>').text(line[0]);
            var value = jQuery('<span>').text(money(line[1]));

            if (line[2]) {
                // The figure that gets paid, told apart from the two above it.
                row.addClass('grand-total-row');
                label = jQuery('<strong>').append(label);
                value = jQuery('<strong>').addClass('grand-total-value').append(value);
            }

            row.find('td').first().attr('colspan', Math.max(1, columns - 1)).append(label);
            row.find('td').last().append(value);
            body.append(row);
        });
    }

    function updateGrandTotal() {
        jQuery('.views-table').each(function () {
            var table = jQuery(this);
            var subtotal = 0;

            table.find('tbody tr').not('.tec-foot-row').not('.grand-total-row').each(function () {
                subtotal += updateRowTotal(jQuery(this));
            });

            updateFoot(table, subtotal);
        });
    }

    // input covers mouse-wheel on number fields; keyup/change cover keyboard.
    jQuery(document).on('input keyup change', QTY, function () {
        updateGrandTotal();
    });

    updateGrandTotal();
});
JS;

$config->set('code', $codigo)->save();

print "  El inyector ya sabe de IVA.\n";
print "  Con porcentaje: Subtotal, VAT y Total. Sin el: Grand Total, como siempre.\n";
