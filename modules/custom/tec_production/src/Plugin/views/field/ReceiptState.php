<?php

namespace Drupal\tec_production\Plugin\views\field;

use Drupal\tec_production\Purchasing;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * How much of a purchase order has actually arrived.
 *
 * The column exists because "open" on its own does not tell whoever chases
 * suppliers what to chase: an order where nothing has turned up and one where
 * half of it is already on the shelf read exactly the same.
 *
 * There is no field behind this and no column in the database. The answer is
 * worked out from the lines every time it is asked for, by the same function the
 * stock board and the guardian use, so the list cannot end up disagreeing with
 * them. See Purchasing::receiptState() for why that is deliberate.
 */
#[ViewsField("tec_purchase_receipt_state")]
class ReceiptState extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Nothing to add. The order is already loaded for the row and its lines come
    // with it, so there is nothing to ask the database for.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    // Sorting would need the value in SQL, and it is not there.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $order = $this->getEntity($values);
    if (!$order || $order->bundle() !== 'tec_purchase_order') {
      return '';
    }

    $words = [
      'none' => $this->t('Nothing received'),
      'partial' => $this->t('Partially received'),
      'full' => $this->t('Fully received'),
      'cancelled' => $this->t('Cancelled'),
    ];
    $state = Purchasing::receiptState($order);

    // The words come from the lines, so a line changing has to be able to throw
    // this row out of the render cache. The order's own tag does not cover that:
    // saving a line invalidates the line, not the order that holds it.
    $tags = $order->getCacheTags();
    foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
      $tags = array_merge($tags, $line->getCacheTags());
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => ['class' => ['tec-receipt', 'tec-receipt--' . $state]],
      '#value' => $words[$state] ?? $state,
      '#attached' => ['library' => ['tec_production/receipt_state']],
      '#cache' => ['tags' => array_unique($tags)],
    ];
  }

}
