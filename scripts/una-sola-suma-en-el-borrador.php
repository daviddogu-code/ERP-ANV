<?php

/**
 * @file
 * El borrador de compra tenia dos calculadoras y ya solo tiene una.
 *
 * La que sobraba vivia en admin_form_styles y se colgaba solo de /po/draft/.
 * Escribia en las mismas celdas que el script inyectado, asi que mientras las
 * dos decian lo mismo nadie se entero. Dejaron de decir lo mismo el dia que el
 * borrador cambio la columna del coste del material por el precio de la propia
 * linea: aquella seguia buscando td.views-field-field-tec-cost, no encontraba
 * nada, multiplicaba por cero y escribia "0,00" encima de un subtotal que la
 * otra acababa de calcular bien. Se ha borrado con su libreria y su enganche.
 *
 * Aqui se remata la faena en el script que queda: tambien nombraba la columna
 * del coste, de reserva, y ya no la nombra ninguna pantalla. Se quita el
 * selector muerto y se corrige el comentario, que decia que las dos pantallas
 * de borrador nombran distinto el dinero. Ya no: las dos leen field_tec_price
 * y lo unico que sigue diferenciandolas es cual de las dos casillas de cantidad
 * les toca, que es justo lo que el selector de la cantidad resuelve.
 */

const INYECTOR = 'asset_injector.js.draft_for_ace_company_view';

const VIEJO = <<<'JS'
    // Two draft screens share this script and they do not name their columns
    // alike. A sales line carries its own price and its quantity box is the
    // first in the view; a purchase line is priced from the material's purchase
    // cost and its quantity box is the second, so Views suffixes the class with
    // -1. Rather than work it out from the address, each row is asked which of
    // the two it happens to have.
    var PRICE = '.views-field-field-tec-price, .views-field-field-tec-cost';
JS;

const NUEVO = <<<'JS'
    // Both draft screens price a line the same way, from the line's own price,
    // so there is one column to read. What they do not share is the quantity
    // box: it is the first in the sales view and the second in the purchase
    // one, and Views suffixes the class of the second with -1. Rather than work
    // it out from the address, each row is asked which of the two it has.
    var PRICE = '.views-field-field-tec-price';
JS;

$vista = \Drupal::config('views.view.tec_order_sales_order_line_items');
$culpables = [];
foreach ($vista->get('display') as $id => $pantalla) {
  foreach ($pantalla['display_options']['fields'] ?? [] as $clave => $campo) {
    if (($campo['field'] ?? '') === 'field_tec_cost') {
      $culpables[] = $id . ':' . $clave;
    }
  }
}
if ($culpables) {
  printf("[MAL] todavia hay pantallas que ensenan field_tec_cost (%s). No se toca nada.\n", implode(', ', $culpables));
  return;
}
print "Ninguna pantalla de linea ensena ya field_tec_cost.\n";

$inyector = \Drupal::configFactory()->getEditable(INYECTOR);
$codigo = $inyector->get('code');

if (!str_contains($codigo, 'views-field-field-tec-cost')) {
  print "El script inyectado ya no la nombra. Nada que hacer.\n";
  return;
}

if (!str_contains($codigo, VIEJO)) {
  print "[MAL] el trozo del precio no esta como se esperaba. No se toca nada.\n";
  return;
}

$inyector->set('code', str_replace(VIEJO, NUEVO, $codigo))->save();
print "Quitado el selector muerto del coste y corregido el comentario.\n";
