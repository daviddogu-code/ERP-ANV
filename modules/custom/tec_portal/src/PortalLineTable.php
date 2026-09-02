<?php

namespace Drupal\tec_portal;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\tec_production\LineItemDisplay;
use Drupal\tec_production\Vat;

/**
 * Shared catalogue grid: columns, hover thumbnail, footer totals.
 */
trait PortalLineTable {

  /**
   * Image | Product | Material | Colour | Size | Qty | Price | Item total.
   *
   * Caps are CSS on thead. These strings stay sentence case for t().
   */
  protected function lineTableHeader(): array {
    $num = ['tec-portal__num'];
    return [
      'image' => ['data' => $this->t('Image'), 'class' => ['tec-portal__col-image']],
      'product' => ['data' => $this->t('Product'), 'class' => ['tec-portal__col-name']],
      'material' => ['data' => $this->t('Material'), 'class' => ['tec-portal__col-material']],
      'colour' => ['data' => $this->t('Colour'), 'class' => ['tec-portal__col-colour']],
      'size' => ['data' => $this->t('Size'), 'class' => ['tec-portal__col-size']],
      'qty' => ['data' => $this->t('Qty'), 'class' => array_merge($num, ['tec-portal__col-qty'])],
      'price' => ['data' => $this->t('Price'), 'class' => array_merge($num, ['tec-portal__col-price'])],
      'item_total' => ['data' => $this->t('Item total'), 'class' => array_merge($num, ['tec-portal__col-total'])],
    ];
  }

  /**
   * Same eight columns on A4. No Image label: the column is photos.
   */
  protected function printLineTableHeader(): array {
    $num = ['tec-portal__num'];
    return [
      'image' => ['data' => '', 'class' => ['tec-portal__col-image']],
      'product' => ['data' => $this->t('Product'), 'class' => ['tec-portal__col-name']],
      'material' => ['data' => $this->t('Material'), 'class' => ['tec-portal__col-material']],
      'colour' => ['data' => $this->t('Colour'), 'class' => ['tec-portal__col-colour']],
      'size' => ['data' => $this->t('Size'), 'class' => ['tec-portal__col-size']],
      'qty' => ['data' => $this->t('Qty'), 'class' => array_merge($num, ['tec-portal__col-qty'])],
      'price' => ['data' => $this->t('Price'), 'class' => array_merge($num, ['tec-portal__col-price'])],
      'item_total' => ['data' => $this->t('Total'), 'class' => array_merge($num, ['tec-portal__col-total'])],
    ];
  }

  /**
   * Footer: piece count under Qty, money under Item total.
   *
   * With a VAT rate: Subtotal, VAT, Total. Without: the single Total the
   * screen has always shown. Eight cells, same as the header. colspan on a
   * form table does not keep Qty and Item total under those columns.
   *
   * @return array
   *   Rows for #footer.
   */
  protected function vatFooter(float $qty, float $net, ?float $rate): array {
    if ($rate === NULL) {
      return [$this->totalsFooter($this->t('Total'), $net, $qty, 'tec-portal__sum-amount')];
    }
    $vat = Vat::on($net, $rate);
    $gross = round($net + $vat, 2);
    return [
      $this->totalsFooter($this->t('Subtotal'), $net, $qty, 'tec-portal__sum-net'),
      $this->totalsFooter($this->t('VAT @rate%', ['@rate' => Vat::formatRate($rate)]), $vat, NULL, 'tec-portal__sum-vat'),
      $this->totalsFooter($this->t('Total'), $gross, NULL, 'tec-portal__sum-gross', TRUE),
    ];
  }

  /**
   * One footer row: label, optional piece count, money.
   */
  protected function totalsFooter($label, float $amount, ?float $qty, string $amount_class, bool $total = FALSE): array {
    $num = ['tec-portal__num'];
    $row = [
      'class' => $total ? ['tec-portal__vat-row', 'tec-portal__vat-row--total'] : ['tec-portal__vat-row'],
      'data' => [
        ['data' => '', 'class' => ['tec-portal__col-image']],
        ['data' => $label, 'class' => ['tec-portal__col-name', 'tec-portal__foot-label']],
        ['data' => '', 'class' => ['tec-portal__col-material']],
        ['data' => '', 'class' => ['tec-portal__col-colour']],
        ['data' => '', 'class' => ['tec-portal__col-size']],
        [
          'data' => $qty === NULL
            ? ''
            : ['#markup' => '<span class="tec-portal__sum-qty">' . Html::escape(number_format($qty, 0)) . '</span>'],
          'class' => array_merge($num, ['tec-portal__col-qty']),
        ],
        ['data' => '', 'class' => array_merge($num, ['tec-portal__col-price'])],
        [
          'data' => ['#markup' => '<span class="' . Html::escape($amount_class) . '">' . Html::escape($this->money($amount)) . '</span>'],
          'class' => array_merge($num, ['tec-portal__col-total']),
        ],
      ],
    ];
    return $row;
  }

  /**
   * Four-column foot for Other charges: Description | Qty | Price | Total.
   */
  protected function chargeVatFooter(float $qty, float $net, ?float $rate): array {
    if ($rate === NULL) {
      return [$this->chargeTotalsFooter($this->t('Total'), $net, $qty, 'tec-portal__sum-amount')];
    }
    $vat = Vat::on($net, $rate);
    $gross = round($net + $vat, 2);
    return [
      $this->chargeTotalsFooter($this->t('Subtotal'), $net, $qty, 'tec-portal__sum-net'),
      $this->chargeTotalsFooter($this->t('VAT @rate%', ['@rate' => Vat::formatRate($rate)]), $vat, NULL, 'tec-portal__sum-vat'),
      $this->chargeTotalsFooter($this->t('Total'), $gross, NULL, 'tec-portal__sum-gross', TRUE),
    ];
  }

  /**
   * One Other charges footer row.
   */
  protected function chargeTotalsFooter($label, float $amount, ?float $qty, string $amount_class, bool $total = FALSE): array {
    $num = ['tec-portal__num'];
    return [
      'class' => $total ? ['tec-portal__vat-row', 'tec-portal__vat-row--total'] : ['tec-portal__vat-row'],
      'data' => [
        ['data' => $label, 'class' => ['tec-portal__col-name', 'tec-portal__foot-label']],
        [
          'data' => $qty === NULL
            ? ''
            : ['#markup' => '<span class="tec-portal__sum-qty">' . Html::escape(number_format($qty, 0)) . '</span>'],
          'class' => array_merge($num, ['tec-portal__col-qty']),
        ],
        ['data' => '', 'class' => array_merge($num, ['tec-portal__col-price'])],
        [
          'data' => ['#markup' => '<span class="' . Html::escape($amount_class) . '">' . Html::escape($this->money($amount)) . '</span>'],
          'class' => array_merge($num, ['tec-portal__col-total']),
        ],
      ],
    ];
  }

  /**
   * VAT % for a live grid, from the customer's country. NULL if none yet.
   */
  protected function vatRateFromContact(?EntityInterface $company): ?float {
    if (!$company || Vat::customerCountry($company) === '') {
      return NULL;
    }
    return Vat::forCustomer($company);
  }

  /**
   * VAT % stamped on an order, or NULL for orders raised before this existed.
   */
  protected function vatRateFromOrder($order): ?float {
    if (!$order) {
      return NULL;
    }
    $sums = Vat::breakdown($order);
    return ($sums && $sums['rate'] !== NULL) ? (float) $sums['rate'] : NULL;
  }

  /**
   * Hands the rate to place.js so the foot moves as quantities are typed.
   */
  protected function attachVatSettings(array &$form, ?float $rate): void {
    if ($rate === NULL) {
      return;
    }
    $form['#attached']['drupalSettings']['tecPortalVat'] = ['rate' => $rate];
  }

  /**
   * Colour thumbnail, same 40x40 as the factory order, large on hover.
   *
   * Markup, not a nested render array: a form table cell swallows #theme.
   * Hover is CSS, the same trick as the factory (Simple Popup Views JS
   * never binds in time).
   */
  protected function imageCell(?EntityInterface $colour, bool $reserve_slot = FALSE): array {
    $cell = ['#wrapper_attributes' => ['class' => ['tec-portal__col-image']]];
    $small = $this->imageUrl($colour, 'small_40x40');
    if ($small === NULL) {
      if ($reserve_slot) {
        $cell['#markup'] = '<span class="tec-portal__thumb tec-portal__thumb--empty"></span>';
        $cell['#allowed_tags'] = ['span'];
      }
      else {
        $cell['#markup'] = '';
      }
      return $cell;
    }
    $large = $this->imageUrl($colour, 'large') ?: $small;
    $cell['#markup'] = '<span class="tec-portal__thumb" tabindex="0">'
      . '<img src="' . Html::escape($small) . '" alt="" width="40" height="40" class="tec-portal__img" />'
      . '<span class="tec-portal__thumb-pop"><img src="' . Html::escape($large) . '" alt="" loading="lazy" /></span>'
      . '</span>';
    $cell['#allowed_tags'] = ['span', 'img'];
    return $cell;
  }

  /**
   * Image style URL for the first photo on a colour, or NULL.
   */
  protected function imageUrl(?EntityInterface $colour, string $style_name = 'small_40x40'): ?string {
    if (!$colour || !$colour->hasField('field_tec_images') || $colour->get('field_tec_images')->isEmpty()) {
      return NULL;
    }
    $fid = (int) $colour->get('field_tec_images')->target_id;
    if ($fid < 1) {
      return NULL;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      return NULL;
    }
    $style = ImageStyle::load($style_name);
    if (!$style) {
      return NULL;
    }
    return $style->buildUrl($file->getFileUri());
  }

  /**
   * One catalogue table: brands consecutive, VAT foot. Same columns as /my.
   *
   * @param array $grid
   *   Catalogue::grid().
   * @param array<int, int> $qty_by_size
   * @param float|null $vat_rate
   *   Percentage stamped on the order, or from the customer's country on
   *   Place. NULL keeps the single Total of orders raised before VAT.
   */
  protected function catalogueLineTable(array $grid, array $qty_by_size = [], ?float $vat_rate = NULL): array {
    $sum_qty = 0.0;
    $sum_amount = 0.0;
    $table = [
      '#type' => 'table',
      '#header' => $this->lineTableHeader(),
      '#attributes' => ['class' => ['tec-portal__table']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];

    foreach ($grid as $brand) {
      foreach ($brand['rows'] as $row) {
        $sid = $row['size_id'];
        $qty = max(0, (int) ($qty_by_size[$sid] ?? 0));
        $price = (float) $row['price'];
        $sum_qty += $qty;
        $sum_amount += $qty * $price;
        $table[$sid] = $this->qtyLineRow($row, $qty, $price);
      }
    }

    $table['#footer'] = $this->vatFooter($sum_qty, $sum_amount, $vat_rate);
    return $table;
  }

  /**
   * One editable size row. Quantities keyed by size variation id.
   */
  protected function qtyLineRow(array $row, int $qty, float $price): array {
    $product = $row['product_entity'] ?? NULL;
    $colour = $row['colour_entity'] ?? NULL;
    return [
      '#attributes' => [
        'data-price' => number_format($price, 2, '.', ''),
        'data-qty' => (string) $qty,
      ],
      'image' => $this->imageCell($colour instanceof EntityInterface ? $colour : NULL),
      'product' => ['#plain_text' => $row['product']],
      'material' => ['#plain_text' => LineItemDisplay::materialLabel($product instanceof EntityInterface ? $product : NULL)],
      'colour' => ['#plain_text' => $row['colour']],
      'size' => ['#plain_text' => $row['size']],
      'qty' => [
        '#type' => 'number',
        '#title' => $this->t('Quantity of @product @size', [
          '@product' => $row['product'],
          '@size' => $row['size'],
        ]),
        '#title_display' => 'invisible',
        '#min' => 0,
        '#step' => 1,
        '#default_value' => $qty,
        '#attributes' => [
          'class' => ['tec-portal__qty-box'],
          'data-price' => number_format($price, 2, '.', ''),
        ],
        '#wrapper_attributes' => ['class' => ['tec-portal__qty', 'tec-portal__num', 'tec-portal__col-qty']],
      ],
      'price' => [
        '#markup' => Html::escape($this->money($price)),
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-price']],
      ],
      'item_total' => [
        '#markup' => '<span class="tec-portal__item-total">' . Html::escape($this->money($qty * $price)) . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-total']],
        '#allowed_tags' => ['span'],
      ],
    ];
  }

  /**
   * Baht, two decimals.
   */
  protected function money(float $amount): string {
    return '฿ ' . number_format($amount, 2);
  }

}
