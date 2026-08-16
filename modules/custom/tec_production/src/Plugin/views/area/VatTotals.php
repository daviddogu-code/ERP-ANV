<?php

namespace Drupal\tec_production\Plugin\views\area;

use Drupal\tec_production\Vat;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * The foot of a purchase order: subtotal, VAT and what is actually owed.
 *
 * Until this existed the screens showed one figure, the sum of the lines, and
 * called it Total. On a Thai supplier that is not what gets paid. The 7% was
 * being written onto every order and read by nobody, so the number on the
 * screen and the number on the invoice were always going to differ.
 *
 * It is an area and not a field because it says something about the order, not
 * about a line, and it should print once at the bottom whatever the rows did.
 *
 * The sales displays share the attachment this replaced, so the guard on the
 * bundle is not decoration: a proforma must not grow a VAT line until sales has
 * been thought through, which is a different question with a different answer.
 */
#[ViewsArea("tec_purchase_vat_totals")]
class VatTotals extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $order = $this->order();
    if (!$order) {
      return [];
    }

    $sums = Vat::breakdown($order);
    if (!$sums) {
      return [];
    }

    // An order raised before any of this had a rate keeps the single total it
    // has always printed, rather than losing its foot altogether.
    $lines = $sums['rate'] === NULL
      ? [[$this->t('Total'), $sums['net'], TRUE]]
      : [
        [$this->t('Subtotal'), $sums['net'], FALSE],
        [$this->t('VAT @rate%', ['@rate' => rtrim(rtrim(number_format($sums['rate'], 2, '.', ''), '0'), '.')]), $sums['vat'], FALSE],
        [$this->t('Total'), $sums['gross'], TRUE],
      ];

    $rows = [];
    foreach ($lines as [$label, $amount, $strong]) {
      $rows[] = [
        'class' => $strong ? ['tec-vat-totals__row--total'] : [],
        'data' => [
          ['data' => $label, 'class' => ['tec-vat-totals__label']],
          // Escaped rather than typed, because this file has no business
          // depending on surviving a trip through an editor that guesses at
          // encodings, and several in this project have not.
          ['data' => "\u{0E3F}" . number_format($amount, 2), 'class' => ['tec-vat-totals__amount']],
        ],
      ];
    }

    // A line changing has to be able to throw this out of the render cache. The
    // order's own tag does not cover it: saving a line invalidates the line.
    $tags = $order->getCacheTags();
    foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
      $tags = array_merge($tags, $line->getCacheTags());
    }

    return [
      '#theme' => 'table',
      '#rows' => $rows,
      '#attributes' => ['class' => ['tec-vat-totals']],
      '#attached' => ['library' => ['tec_production/purchase_totals']],
      '#cache' => ['tags' => array_unique($tags)],
    ];
  }

  /**
   * The order this page is about.
   *
   * Both screens that use this take the order id as their only argument, so
   * there is nothing cleverer to do and nothing to guess.
   */
  private function order() {
    $id = (int) ($this->view->args[0] ?? 0);
    if ($id <= 0) {
      return NULL;
    }
    return \Drupal::entityTypeManager()->getStorage('tec_order')->load($id);
  }

}
