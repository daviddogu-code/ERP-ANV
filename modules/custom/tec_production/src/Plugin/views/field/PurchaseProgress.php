<?php

namespace Drupal\tec_production\Plugin\views\field;

use Drupal\tec_production\Purchasing;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Where a purchase order has got to, for any listing that shows a status.
 *
 * Before this, /supplier-orders and the supplier's own tab printed the stored
 * open/closed flag while the purchase control screen worked the answer out from
 * the receipts and the invoice, so the same order could read Closed on one page
 * and On the way on another. Both now ask Purchasing::progress(), which is the
 * only place that decides.
 *
 * The stored flag has not gone anywhere and is still what the buttons read: an
 * order stops being editable when it is closed, and that is a different
 * question from what to tell the person chasing it.
 */
#[ViewsField("tec_purchase_progress")]
class PurchaseProgress extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Nothing to add. The order is already loaded for the row and its lines
    // come with it.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    // Sorting would need the value in SQL, and it is worked out in PHP.
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

    $progress = Purchasing::progress($order);

    // The word comes from the lines and from the invoice, so a line changing
    // has to be able to throw this row out of the render cache. The order's own
    // tag does not cover it: saving a line invalidates the line, not the order
    // that holds it.
    $tags = $order->getCacheTags();
    foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
      $tags = array_merge($tags, $line->getCacheTags());
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => ['class' => ['tec-po-status', 'tec-po-status--' . $progress]],
      '#value' => Purchasing::PROGRESS[$progress] ?? $progress,
      '#attached' => ['library' => ['tec_production/purchase_progress']],
      '#cache' => ['tags' => array_unique($tags)],
    ];
  }

}
