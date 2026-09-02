<?php

namespace Drupal\tec_portal;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tec_portal\OtherCharges;
use Drupal\tec_production\LineItemDisplay;

/**
 * Read-only line table: the same grid /o/order shows after Confirm.
 *
 * Used on the sales-order card, /o/order after Confirm, and the printed proforma.
 */
final class OrderLineGrid {

  use PortalLineTable;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(?EntityTypeManagerInterface $manager = NULL): self {
    return new self($manager ?? \Drupal::entityTypeManager());
  }

  /**
   * Wrapped table plus portal CSS, for a block on the factory card.
   */
  public function build($order): array {
    if (!$order || $order->getEntityTypeId() !== 'tec_order') {
      return [
        '#markup' => '<p>' . $this->t('Open a sales order to view its lines.') . '</p>',
        '#cache' => ['contexts' => ['route']],
      ];
    }

    $tags = $order->getCacheTags();
    foreach ($this->linesOf($order) as $line) {
      $tags = Cache::mergeTags($tags, $line->getCacheTags());
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tec-portal', 'tec-portal--place', 'tec-portal--card-lines'],
      ],
      '#attached' => ['library' => ['tec_portal/portal']],
      'lines' => $this->table($order),
      '#cache' => [
        'contexts' => ['route'],
        'tags' => $tags,
        'max-age' => Cache::PERMANENT,
      ],
    ];
  }

  /**
   * The table only. OrderForm already wraps it in the portal form.
   *
   * $print: short headers and a reserved image slot so every row is the
   * same height on A4. /o/order and the card leave this false.
   */
  public function table($order, bool $print = FALSE): array {
    $visible = [];
    $sum_qty = 0.0;
    $sum_amount = 0.0;
    foreach ($this->linesOf($order) as $line) {
      $qty = $this->qtyOf($line);
      if ($qty < 1) {
        continue;
      }
      $visible[] = $line;
      $sum_qty += $qty;
      $sum_amount += $this->totalOf($line, $qty, $this->priceOf($line));
    }

    if (OtherCharges::isOrder($order)) {
      return $this->chargeTable($order, $visible, $sum_qty, $sum_amount, $print);
    }

    $table = [
      '#type' => 'table',
      '#header' => $print ? $this->printLineTableHeader() : $this->lineTableHeader(),
      '#empty' => $this->t('This order has no lines.'),
      '#attributes' => ['class' => ['tec-portal__table']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];
    if (!$visible) {
      return $table;
    }

    $table['#footer'] = $this->vatFooter($sum_qty, $sum_amount, $this->vatRateFromOrder($order));

    foreach ($visible as $line) {
      $lid = (int) $line->id();
      $line_qty = $this->qtyOf($line);
      $price = $this->priceOf($line);
      $total = $this->totalOf($line, $line_qty, $price);
      $product = $this->productOf($line);
      $size = $this->sizeOf($line);
      $colour = $this->colourOf($line);
      $table[$lid] = [
        '#attributes' => [
          'data-price' => number_format($price, 2, '.', ''),
          'data-qty' => (string) (int) $line_qty,
        ],
        'image' => $this->imageCell($colour, $print),
        'product' => ['#plain_text' => $this->productName($product) ?: (string) $line->label()],
        'material' => ['#plain_text' => LineItemDisplay::materialLabel($product)],
        'colour' => ['#plain_text' => LineItemDisplay::colorLabel($colour)],
        'size' => ['#plain_text' => LineItemDisplay::sizeLabel($size)],
        'qty' => [
          '#plain_text' => number_format($line_qty, 0),
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-qty']],
        ],
        'price' => [
          '#markup' => Html::escape($this->money($price)),
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-price']],
        ],
        'item_total' => [
          '#markup' => '<span class="tec-portal__item-total">' . Html::escape($this->money($total)) . '</span>',
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-total']],
          '#allowed_tags' => ['span'],
        ],
      ];
    }

    return $table;
  }

  /**
   * Description | Qty | Price | Total. No colour, size or photo.
   *
   * @param object[] $visible
   */
  protected function chargeTable($order, array $visible, float $sum_qty, float $sum_amount, bool $print): array {
    $num = ['tec-portal__num'];
    $table = [
      '#type' => 'table',
      '#header' => [
        'description' => ['data' => $this->t('Description'), 'class' => ['tec-portal__col-name']],
        'qty' => ['data' => $this->t('Qty'), 'class' => array_merge($num, ['tec-portal__col-qty'])],
        'price' => ['data' => $this->t('Price'), 'class' => array_merge($num, ['tec-portal__col-price'])],
        'item_total' => ['data' => $print ? $this->t('Total') : $this->t('Item total'), 'class' => array_merge($num, ['tec-portal__col-total'])],
      ],
      '#empty' => $this->t('This order has no lines.'),
      '#attributes' => ['class' => ['tec-portal__table', 'tec-portal__table--charges']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];
    if (!$visible) {
      return $table;
    }
    $table['#footer'] = $this->chargeVatFooter($sum_qty, $sum_amount, $this->vatRateFromOrder($order));
    foreach ($visible as $line) {
      $lid = (int) $line->id();
      $line_qty = $this->qtyOf($line);
      $price = $this->priceOf($line);
      $total = $this->totalOf($line, $line_qty, $price);
      $table[$lid] = [
        '#attributes' => [
          'data-price' => number_format($price, 2, '.', ''),
          'data-qty' => (string) (int) $line_qty,
        ],
        'description' => ['#plain_text' => (string) $line->label()],
        'qty' => [
          '#plain_text' => number_format($line_qty, 0),
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-qty']],
        ],
        'price' => [
          '#markup' => Html::escape($this->money($price)),
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-price']],
        ],
        'item_total' => [
          '#markup' => '<span class="tec-portal__item-total">' . Html::escape($this->money($total)) . '</span>',
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-total']],
          '#allowed_tags' => ['span'],
        ],
      ];
    }
    return $table;
  }

  /**
   * Lines on an order, in the order they were added.
   */
  public function linesOf($order): array {
    if (!$order || !$order->hasField('field_tec_line_items') || $order->get('field_tec_line_items')->isEmpty()) {
      return [];
    }
    return $order->get('field_tec_line_items')->referencedEntities();
  }

  protected function qtyOf($line): float {
    if (!$line->hasField('field_tec_quantity') || $line->get('field_tec_quantity')->isEmpty()) {
      return 0;
    }
    return (float) $line->get('field_tec_quantity')->value;
  }

  protected function priceOf($line): float {
    if (!$line->hasField('field_tec_price') || $line->get('field_tec_price')->isEmpty()) {
      return 0;
    }
    return (float) $line->get('field_tec_price')->value;
  }

  protected function totalOf($line, float $qty, float $price): float {
    if ($line->hasField('field_tec_line_item_total_number') && !$line->get('field_tec_line_item_total_number')->isEmpty()) {
      return (float) $line->get('field_tec_line_item_total_number')->value;
    }
    return $qty * $price;
  }

  protected function productOf($line): ?EntityInterface {
    return $this->loadProductRef($line, 'field_tec_product');
  }

  protected function sizeOf($line): ?EntityInterface {
    return $this->loadProductRef($line, 'field_tec_size_variation');
  }

  protected function colourOf($line): ?EntityInterface {
    $colour = $this->loadProductRef($line, 'field_tec_color_variation');
    if ($colour) {
      return $colour;
    }
    $size = $this->sizeOf($line);
    return $size ? $this->loadProductRef($size, 'field_tec_color_variation') : NULL;
  }

  protected function loadProductRef($entity, string $field): ?EntityInterface {
    if (!$entity || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return NULL;
    }
    $id = (int) $entity->get($field)->target_id;
    if ($id < 1) {
      return NULL;
    }
    $got = $this->entityTypeManager->getStorage('tec_product')->load($id);
    return $got ?: NULL;
  }

  protected function productName($product): string {
    if (!$product) {
      return '';
    }
    if ($product->hasField('field_product_name')) {
      $name = trim((string) $product->get('field_product_name')->value);
      if ($name !== '') {
        return $name;
      }
    }
    return (string) $product->label();
  }

}
