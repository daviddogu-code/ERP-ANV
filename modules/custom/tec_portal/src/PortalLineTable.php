<?php

namespace Drupal\tec_portal;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\tec_production\LineItemDisplay;

/**
 * Shared catalogue grid: columns, hover thumbnail, footer totals.
 */
trait PortalLineTable {

  /**
   * Image | Product name | Material | Colour | Size | Qty | Price | Item total.
   */
  protected function lineTableHeader(bool $blank = FALSE): array {
    $num = ['tec-portal__num'];
    $header = [
      'image' => ['data' => $blank ? '' : $this->t('Image'), 'class' => ['tec-portal__col-image']],
      'product' => ['data' => $blank ? '' : $this->t('Product name'), 'class' => ['tec-portal__col-name']],
      'material' => ['data' => $blank ? '' : $this->t('Product material'), 'class' => ['tec-portal__col-material']],
      'colour' => ['data' => $blank ? '' : $this->t('Colour'), 'class' => ['tec-portal__col-colour']],
      'size' => ['data' => $blank ? '' : $this->t('Size'), 'class' => ['tec-portal__col-size']],
      'qty' => ['data' => $blank ? '' : $this->t('Qty'), 'class' => array_merge($num, ['tec-portal__col-qty'])],
      'price' => ['data' => $blank ? '' : $this->t('Price'), 'class' => array_merge($num, ['tec-portal__col-price'])],
      'item_total' => ['data' => $blank ? '' : $this->t('Item total'), 'class' => array_merge($num, ['tec-portal__col-total'])],
    ];
    return $header;
  }

  /**
   * Footer row: piece count under Qty, money under Item total.
   *
   * Eight cells, same as the header. colspan on a form table does not keep
   * Qty and Item total under those columns.
   */
  protected function totalsFooter(bool $grand = FALSE, float $qty = 0, float $amount = 0): array {
    $num = ['tec-portal__num'];
    $qty_class = $grand ? 'tec-portal__grand-qty' : 'tec-portal__sum-qty';
    $amount_class = $grand ? 'tec-portal__grand-amount' : 'tec-portal__sum-amount';
    $label = $grand ? $this->t('Grand total') : $this->t('Total');
    return [
      ['data' => '', 'class' => ['tec-portal__col-image']],
      ['data' => $label, 'class' => ['tec-portal__col-name', 'tec-portal__foot-label']],
      ['data' => '', 'class' => ['tec-portal__col-material']],
      ['data' => '', 'class' => ['tec-portal__col-colour']],
      ['data' => '', 'class' => ['tec-portal__col-size']],
      [
        'data' => ['#markup' => '<span class="' . $qty_class . '">' . Html::escape(number_format($qty, 0)) . '</span>'],
        'class' => array_merge($num, ['tec-portal__col-qty']),
      ],
      ['data' => '', 'class' => array_merge($num, ['tec-portal__col-price'])],
      [
        'data' => ['#markup' => '<span class="' . $amount_class . '">' . Html::escape($this->money($amount)) . '</span>'],
        'class' => array_merge($num, ['tec-portal__col-total']),
      ],
    ];
  }

  /**
   * Colour thumbnail, same 40x40 as the factory order, large on hover.
   *
   * Markup, not a nested render array: a form table cell swallows #theme.
   * Hover is CSS, the same trick as the factory (Simple Popup Views JS
   * never binds in time).
   */
  protected function imageCell(?EntityInterface $colour): array {
    $cell = ['#wrapper_attributes' => ['class' => ['tec-portal__col-image']]];
    $small = $this->imageUrl($colour, 'small_40x40');
    if ($small === NULL) {
      $cell['#markup'] = '';
      return $cell;
    }
    $large = $this->imageUrl($colour, 'large') ?: $small;
    $cell['#markup'] = '<span class="tec-portal__thumb" tabindex="0">'
      . '<img src="' . Html::escape($small) . '" alt="" width="40" height="40" class="tec-portal__img" loading="lazy" />'
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
   * One brand fieldset: catalogue rows, quantities keyed by size id.
   *
   * @param array<int, int> $qty_by_size
   */
  protected function catalogueBrandGroup(array $brand, array $qty_by_size = []): array {
    $sum_qty = 0.0;
    $sum_amount = 0.0;
    $element = [
      '#type' => 'fieldset',
      '#title' => $brand['name'],
      '#attributes' => ['class' => ['tec-portal__group']],
    ];
    $element['lines'] = [
      '#type' => 'table',
      '#header' => $this->lineTableHeader(),
      '#attributes' => ['class' => ['tec-portal__table']],
    ];

    foreach ($brand['rows'] as $row) {
      $sid = $row['size_id'];
      $product = $row['product_entity'] ?? NULL;
      $colour = $row['colour_entity'] ?? NULL;
      $qty = max(0, (int) ($qty_by_size[$sid] ?? 0));
      $price = (float) $row['price'];
      $sum_qty += $qty;
      $sum_amount += $qty * $price;
      $element['lines'][$sid] = [
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

    $element['lines']['#footer'] = [$this->totalsFooter(FALSE, $sum_qty, $sum_amount)];
    return $element;
  }

  /**
   * Grand total table, same width as a brand fieldset.
   */
  protected function grandTotalTable(float $qty = 0, float $amount = 0): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-portal__group', 'tec-portal__group--grand']],
      'grid' => [
        '#type' => 'table',
        '#header' => $this->lineTableHeader(TRUE),
        '#attributes' => ['class' => ['tec-portal__table', 'tec-portal__grand']],
        '#rows' => [$this->totalsFooter(TRUE, $qty, $amount)],
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
