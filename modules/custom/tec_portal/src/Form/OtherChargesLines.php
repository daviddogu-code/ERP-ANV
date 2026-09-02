<?php

namespace Drupal\tec_portal\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tec_portal\OtherCharges;

/**
 * Typed Other charges rows: description, qty, price. Shared by the create
 * screen and the Open factory order.
 */
trait OtherChargesLines {

  /**
   * Form table. Existing lines first, then blank rows.
   *
   * @param object[] $existing
   */
  protected function chargeLinesTable(array $existing, int $blanks, ?float $rate): array {
    $sum_qty = 0.0;
    $sum_amount = 0.0;
    $table = [
      '#type' => 'table',
      '#header' => [
        'description' => ['data' => $this->t('Description'), 'class' => ['tec-portal__col-name']],
        'qty' => ['data' => $this->t('Qty'), 'class' => ['tec-portal__num', 'tec-portal__col-qty']],
        'price' => ['data' => $this->t('Price'), 'class' => ['tec-portal__num', 'tec-portal__col-price']],
        'item_total' => ['data' => $this->t('Item total'), 'class' => ['tec-portal__num', 'tec-portal__col-total']],
      ],
      '#empty' => $this->t('This order has no lines.'),
      '#attributes' => ['class' => ['tec-portal__table', 'tec-portal__table--charges']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];

    $i = 0;
    foreach ($existing as $line) {
      $qty = $this->chargeQtyOf($line);
      $price = $this->chargePriceOf($line);
      $sum_qty += $qty;
      $sum_amount += $qty * $price;
      $table[$i] = $this->chargeLineRow((int) $line->id(), (string) $line->label(), $qty, $price);
      $i++;
    }
    $blanks = max(1, $blanks);
    for ($n = 0; $n < $blanks; $n++) {
      $table[$i] = $this->chargeLineRow(0, '', 0, 0);
      $i++;
    }

    $table['#footer'] = $this->chargeVatFooter($sum_qty, $sum_amount, $rate);
    return $table;
  }

  /**
   * One editable row. Empty id means a line that has not been saved yet.
   */
  protected function chargeLineRow(int $id, string $description, float $qty, float $price): array {
    $total = $qty * $price;
    return [
      '#attributes' => [
        'data-price' => number_format($price, 2, '.', ''),
        'data-qty' => (string) (int) $qty,
      ],
      'description' => [
        '#wrapper_attributes' => ['class' => ['tec-portal__col-name', 'tec-portal__charge-desc-cell']],
        'id' => [
          '#type' => 'hidden',
          '#default_value' => $id,
        ],
        'text' => [
          '#type' => 'textfield',
          '#title' => $this->t('Description'),
          '#title_display' => 'invisible',
          '#default_value' => $description,
          '#maxlength' => 255,
          '#attributes' => ['class' => ['tec-portal__charge-desc']],
        ],
      ],
      'qty' => [
        '#type' => 'number',
        '#title' => $this->t('Quantity'),
        '#title_display' => 'invisible',
        '#min' => 0,
        '#step' => 1,
        '#default_value' => $qty > 0 ? (int) $qty : '',
        '#attributes' => ['class' => ['tec-portal__qty-box', 'tec-portal__charge-qty']],
        '#wrapper_attributes' => ['class' => ['tec-portal__qty', 'tec-portal__num', 'tec-portal__col-qty']],
      ],
      'price' => [
        '#type' => 'number',
        '#title' => $this->t('Price'),
        '#title_display' => 'invisible',
        '#min' => 0,
        '#step' => 0.01,
        '#default_value' => $price > 0 ? number_format($price, 2, '.', '') : '',
        '#attributes' => ['class' => ['tec-portal__charge-price']],
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-price', 'tec-portal__charge-price-cell']],
      ],
      'item_total' => [
        '#markup' => '<span class="tec-portal__item-total">' . Html::escape($this->money($total)) . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-total']],
        '#allowed_tags' => ['span'],
      ],
    ];
  }

  /**
   * Rows that have a description, a quantity and a price.
   *
   * @return list<array{id: int, description: string, qty: int, price: float}>
   */
  protected function completeChargeRows(FormStateInterface $form_state): array {
    $out = [];
    foreach ($form_state->getValue('lines') ?: [] as $row) {
      if (!is_array($row)) {
        continue;
      }
      $cell = $row['description'] ?? [];
      $id = is_array($cell) ? (int) ($cell['id'] ?? 0) : 0;
      $description = trim((string) (is_array($cell) ? ($cell['text'] ?? '') : $cell));
      $qty = (int) ($row['qty'] ?? 0);
      $price = round((float) ($row['price'] ?? 0), 2);
      if ($description === '' || $qty < 1 || $price <= 0) {
        continue;
      }
      $out[] = [
        'id' => $id,
        'description' => mb_substr($description, 0, 255),
        'qty' => $qty,
        'price' => $price,
      ];
    }
    return $out;
  }

  protected function chargeQtyOf($line): float {
    if (!$line->hasField('field_tec_quantity') || $line->get('field_tec_quantity')->isEmpty()) {
      return 0;
    }
    return (float) $line->get('field_tec_quantity')->value;
  }

  protected function chargePriceOf($line): float {
    if (!$line->hasField('field_tec_price') || $line->get('field_tec_price')->isEmpty()) {
      return 0;
    }
    return (float) $line->get('field_tec_price')->value;
  }

}
