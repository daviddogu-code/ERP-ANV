<?php

/**
 * @file
 * El pie del borrador de ventas cuenta piezas, debajo de su columna.
 *
 *   php vendor\bin\drush.php scr scripts/el-borrador-dice-cuantas-piezas-va-pidiendo.php
 *
 * Es la misma pregunta que el pedido ya guardado contesta al pie -- cuantas
 * piezas son -- hecha en la pantalla donde todavia se esta decidiendo. Y no se
 * contesta igual, a proposito: aqui las cifras se mueven mientras se teclea y
 * no hay nada guardado que sumar, asi que la suma la hace el navegador. En el
 * pedido guardado la hace el servidor, porque alli ya no se mueve nada.
 *
 * El pie de estas dos pantallas lo dibuja el guion inyectado
 * `asset_injector.js.draft_for_ace_company_view`, y hasta hoy montaba una fila
 * de dos celdas: una etiqueta ancha que ocupaba todas las columnas menos la
 * ultima, y el dinero en esa ultima. Una fila asi no puede poner una cifra
 * debajo de Quantity, que en ventas es la **segunda** columna de nueve. Por eso
 * la fila pasa a montarse por columnas: el hueco de delante, la cuenta de
 * piezas bajo su columna, la etiqueta rellenando hasta el dinero, y el dinero.
 * La etiqueta sigue justo a la izquierda del total, que es donde estaba.
 *
 * **Solo en ventas.** Compras se queda como estaba, sin cuenta de piezas, que
 * es lo que se pidio. Se distingue por lo mismo que ya distinguia este guion
 * para encontrar la casilla: Views le pone un `-1` detras a la segunda copia de
 * un campo, y la pantalla de compras es la segunda, asi que una columna de
 * cantidad **sin** sufijo es la de ventas.
 *
 * Tampoco se toca el pie de tres lineas de compras -- Subtotal, IVA, Total --
 * ni la coma de los millares, ni el redondeo al satang.
 *
 * Se puede lanzar dos veces: deja el guion igual.
 */

const INYECTOR = 'asset_injector.js.draft_for_ace_company_view';

$guion = \Drupal::configFactory()->getEditable(INYECTOR);
if ($guion->isNew()) {
  print "\n  No existe " . INYECTOR . ".\n\n";
  return;
}

$codigo = <<<'JS'
jQuery(document).ready(function () {
    // The column every figure here is about: the line total. It is what the row
    // shows, what the foot adds up and what ends up on the paper, so it is the
    // one that has to be right.
    var TOTAL = '.views-field-field-tec-line-item-total-number';

    // Both draft screens price a line the same way, from the line's own price,
    // so there is one column to read. What they do not share is the quantity
    // box: it is the first in the sales view and the second in the purchase
    // one, and Views suffixes the class of the second with -1. Rather than work
    // it out from the address, each row is asked which of the two it has.
    var PRICE = '.views-field-field-tec-price';
    var QTY = '.views-field-form-field-field-tec-quantity input, .views-field-form-field-field-tec-quantity-1 input';

    // The same tell read the other way round: a quantity column with no suffix
    // is the sales screen, and the sales screen is the only one that was asked
    // for a count of pieces at the foot.
    var SALES_QTY = '.views-field-form-field-field-tec-quantity';

    function money(amount) {
        return '\u0E3F ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Pieces are counted, not paid: no decimals of their own, but the same
    // thousands comma as the figure sitting next to them.
    function pieces(amount) {
        return String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // What a row is asking for. An empty box is nothing, not an error, which is
    // also why a line with nothing typed is worth zero and not blank.
    function quantityOf(row) {
        var typed = parseFloat(row.find(QTY).first().val());
        return isNaN(typed) ? 0 : typed;
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
        if (isNaN(price)) {
            cell.text(money(0));
            return 0;
        }

        var total = price * quantityOf(row);
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

    // Which column a figure belongs under. Views names every cell after the
    // field that fills it, so the column is found by name instead of counted:
    // Quantity is the second column of nine in sales and the fourth of seven in
    // purchases, and both move the day somebody adds a column.
    function columnOf(table, selector) {
        var at = -1;
        table.find('thead th').each(function (index) {
            if (at === -1 && jQuery(this).is(selector)) {
                at = index;
            }
        });
        return at;
    }

    // A cell of the foot leans the way its column leans, whatever the view says
    // that is, so the count sits over the boxes it counted.
    function alignmentOf(table, index) {
        var found = (table.find('thead th').eq(index).attr('class') || '').match(/(views|text)-align-\w+/g);
        return found ? found.join(' ') : '';
    }

    function updateFoot(table, subtotal, counted) {
        table.find('.tec-foot-row').remove();

        // Counted off the table rather than hardcoded. The number of columns is
        // exactly the sort of thing that changes, and when it did, the old fixed
        // span of eight left this row hanging off the edge.
        var columns = table.find('thead th').length || table.find('tbody tr').first().find('td').length;
        var body = table.find('tbody');
        var countAt = columnOf(table, SALES_QTY);

        footLines(subtotal).forEach(function (line) {
            var row = jQuery('<tr class="tec-foot-row"></tr>');
            var label = jQuery('<span>').text(line[0]);
            var value = jQuery('<span>').text(money(line[1]));
            var count = jQuery('<span>').text(pieces(counted));

            if (line[2]) {
                // The figure that gets paid, told apart from the two above it.
                row.addClass('grand-total-row');
                label = jQuery('<strong>').append(label);
                value = jQuery('<strong>').addClass('grand-total-value').append(value);
                count = jQuery('<strong>').addClass('grand-total-pieces').append(count);
            }

            // With a count to show, the run of columns the label used to span is
            // cut in two by the quantity column. Without one -- the purchase
            // screen, or a table whose quantity column is so far right that the
            // label would have nowhere left to go -- the row is the two cells it
            // always was, because a label with no room is worse than a count
            // that is not shown.
            var room = columns - countAt - 2;
            if (line[2] && countAt > -1 && room > 0) {
                if (countAt > 0) {
                    row.append(jQuery('<td>').attr('colspan', countAt));
                }
                row.append(jQuery('<td>').addClass(alignmentOf(table, countAt)).append(count));
                row.append(jQuery('<td class="views-align-right">').attr('colspan', room).append(label));
            }
            else {
                row.append(jQuery('<td class="views-align-right">').attr('colspan', Math.max(1, columns - 1)).append(label));
            }
            row.append(jQuery('<td class="views-align-right">').append(value));

            body.append(row);
        });
    }

    function updateGrandTotal() {
        jQuery('.views-table').each(function () {
            var table = jQuery(this);
            var subtotal = 0;
            var counted = 0;

            table.find('tbody tr').not('.tec-foot-row').not('.grand-total-row').each(function () {
                var row = jQuery(this);
                subtotal += updateRowTotal(row);
                counted += quantityOf(row);
            });

            updateFoot(table, subtotal, counted);
        });
    }

    // input covers mouse-wheel on number fields; keyup/change cover keyboard.
    jQuery(document).on('input keyup change', QTY, function () {
        updateGrandTotal();
    });

    updateGrandTotal();
});
JS;

print "\n";
print "=====================================================================\n";
print "  El borrador dice cuantas piezas va pidiendo\n";
print "=====================================================================\n\n";

$antes = (string) $guion->get('code');
if (trim($antes) === trim($codigo)) {
  print "  El guion ya es este. No se toca nada.\n\n";
}
else {
  $guion->set('code', $codigo)->save();
  printf("  Guion reescrito: %d lineas antes, %d ahora.\n\n",
    substr_count($antes, "\n") + 1,
    substr_count($codigo, "\n") + 1);
}

// ---------------------------------------------------------------------------
// Que sigue enganchado donde debe, y que dice lo que tiene que decir.
// ---------------------------------------------------------------------------
$paginas = $guion->get('conditions.request_path.pages') ?? '';
printf("  Se carga en: %s\n", str_replace(["\r\n", "\n"], ', ', trim($paginas)));

foreach ([
  'la cuenta de piezas' => 'grand-total-pieces',
  'la columna de ventas' => 'SALES_QTY',
  'las columnas se buscan por nombre' => 'function columnOf',
  'el pie de compras sigue con IVA' => "'VAT '",
] as $que => $aguja) {
  printf("  %-38s %s\n", $que, str_contains($codigo, $aguja) ? 'si' : 'NO');
}
print "\n";
