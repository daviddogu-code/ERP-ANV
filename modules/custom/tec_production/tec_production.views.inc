<?php

/**
 * @file
 * Views data for tec_production.
 */

/**
 * Implements hook_views_data_alter().
 */
function tec_production_views_data_alter(array &$data): void {
  // Hung off the order table itself and not off a field, because there is no
  // field to hang it off: how much of a purchase order has arrived is worked out
  // from its lines. Only meaningful on purchase orders; the handler returns
  // nothing for anything else, so it does no harm if someone adds it to a list
  // of sales orders.
  $data['tec_order_field_data']['tec_receipt_state'] = [
    'title' => t('Delivery'),
    'help' => t('How much of a purchase order has arrived: nothing, part of it, or all of it. Worked out from the lines, never stored.'),
    'field' => [
      'id' => 'tec_purchase_receipt_state',
      'click sortable' => FALSE,
    ],
  ];

  // On the global table rather than on the order, because it belongs at the
  // foot of a page and not in a row, and because the displays that use it list
  // line items: the order is the argument, not the subject of the rows.
  $data['views']['tec_purchase_vat_totals'] = [
    'title' => t('Purchase order totals with VAT'),
    'help' => t('Subtotal, VAT and the amount actually owed, at the foot of a purchase order. Shows nothing on anything that is not a purchase order.'),
    'area' => [
      'id' => 'tec_purchase_vat_totals',
    ],
  ];
}
