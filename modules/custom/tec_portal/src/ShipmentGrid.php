<?php

namespace Drupal\tec_portal;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tec_production\LineItemDisplay;
use Drupal\tec_production\Vat;

/**
 * Packing and invoice tables from Shipment::merchandise().
 *
 * One list of pairs. Packing hides the money. Invoice uses the frozen
 * sales-line price and the VAT stamped on that line's order.
 */
final class ShipmentGrid {

  use PortalLineTable;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(?EntityTypeManagerInterface $manager = NULL): self {
    return new self($manager ?? \Drupal::entityTypeManager());
  }

  /**
   * Packing list: no prices. Piece count in the foot.
   */
  public function packingTable($shipment): array {
    $rows = Shipment::merchandise($shipment);
    $table = [
      '#type' => 'table',
      '#header' => $this->packingHeader(),
      '#empty' => $this->t('This shipment has no quantities yet.'),
      '#attributes' => ['class' => ['tec-portal__table']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];
    if (!$rows) {
      return $table;
    }
    $sum_qty = 0;
    foreach ($rows as $row) {
      $sum_qty += $row['qty'];
      $table[] = $this->goodsRow($row, FALSE);
    }
    $table['#footer'] = [$this->packingFooter($sum_qty)];
    return $table;
  }

  /**
   * Invoice: same rows as packing, plus frozen price and VAT of the orders.
   */
  public function invoiceTable($shipment): array {
    $rows = Shipment::merchandise($shipment);
    $table = [
      '#type' => 'table',
      '#header' => $this->printLineTableHeader(),
      '#empty' => $this->t('This shipment has no quantities yet.'),
      '#attributes' => ['class' => ['tec-portal__table']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];
    if (!$rows) {
      return $table;
    }
    $sum_qty = 0;
    foreach ($rows as $row) {
      $sum_qty += $row['qty'];
      $table[] = $this->goodsRow($row, TRUE);
    }
    $table['#footer'] = $this->invoiceFooter($sum_qty, Shipment::goodsTotals($shipment));
    return $table;
  }

  /**
   * Image | Product | Material | Colour | Size | Qty.
   */
  protected function packingHeader(): array {
    $num = ['tec-portal__num'];
    return [
      'image' => ['data' => '', 'class' => ['tec-portal__col-image']],
      'product' => ['data' => $this->t('Product'), 'class' => ['tec-portal__col-name']],
      'material' => ['data' => $this->t('Material'), 'class' => ['tec-portal__col-material']],
      'colour' => ['data' => $this->t('Colour'), 'class' => ['tec-portal__col-colour']],
      'size' => ['data' => $this->t('Size'), 'class' => ['tec-portal__col-size']],
      'qty' => ['data' => $this->t('Qty'), 'class' => array_merge($num, ['tec-portal__col-qty'])],
    ];
  }

  /**
   * One merchandise row. $with_price is the invoice.
   */
  protected function goodsRow(array $row, bool $with_price): array {
    $line = $row['line'];
    $qty = (int) $row['qty'];
    $product = $this->productOf($line);
    $size = $this->sizeOf($line);
    $colour = $this->colourOf($line);
    $cells = [
      'image' => $this->imageCell($colour, TRUE),
      'product' => ['#plain_text' => $this->productName($product) ?: ($line ? (string) $line->label() : '')],
      'material' => ['#plain_text' => LineItemDisplay::materialLabel($product)],
      'colour' => ['#plain_text' => LineItemDisplay::colorLabel($colour)],
      'size' => ['#plain_text' => LineItemDisplay::sizeLabel($size)],
      'qty' => [
        '#plain_text' => number_format($qty, 0),
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-qty']],
      ],
    ];
    if ($with_price) {
      $price = (float) $row['price'];
      $total = (float) $row['amount'];
      $cells['price'] = [
        '#markup' => Html::escape($this->money($price)),
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-price']],
      ];
      $cells['item_total'] = [
        '#markup' => '<span class="tec-portal__item-total">' . Html::escape($this->money($total)) . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-total']],
        '#allowed_tags' => ['span'],
      ];
    }
    return $cells;
  }

  /**
   * Piece count only. Six cells, same as the packing header.
   */
  protected function packingFooter(int $qty): array {
    $num = ['tec-portal__num'];
    return [
      'class' => ['tec-portal__vat-row', 'tec-portal__vat-row--total'],
      'data' => [
        ['data' => '', 'class' => ['tec-portal__col-image']],
        ['data' => $this->t('Total pieces'), 'class' => ['tec-portal__col-name', 'tec-portal__foot-label']],
        ['data' => '', 'class' => ['tec-portal__col-material']],
        ['data' => '', 'class' => ['tec-portal__col-colour']],
        ['data' => '', 'class' => ['tec-portal__col-size']],
        [
          'data' => ['#markup' => '<span class="tec-portal__sum-qty">' . Html::escape(number_format($qty, 0)) . '</span>'],
          'class' => array_merge($num, ['tec-portal__col-qty']),
        ],
      ],
    ];
  }

  /**
   * Goods + VAT of the orders. No deposit, no charges.
   */
  protected function invoiceFooter(int $qty, array $totals): array {
    $stamped = [];
    foreach ($totals['by_rate'] as $group) {
      if ($group['rate'] !== NULL) {
        $stamped[] = $group;
      }
    }
    if (!$stamped) {
      return [$this->totalsFooter($this->t('Total'), $totals['net'], $qty, 'tec-portal__sum-amount')];
    }
    $rows = [
      $this->totalsFooter($this->t('Subtotal'), $totals['net'], $qty, 'tec-portal__sum-net'),
    ];
    foreach ($stamped as $group) {
      $rows[] = $this->totalsFooter(
        $this->t('VAT @rate%', ['@rate' => Vat::formatRate((float) $group['rate'])]),
        (float) $group['vat'],
        NULL,
        'tec-portal__sum-vat'
      );
    }
    $rows[] = $this->totalsFooter($this->t('Total'), $totals['gross'], NULL, 'tec-portal__sum-gross', TRUE);
    return $rows;
  }

  /**
   * Export tax invoice: same columns and totals as the proforma.
   *
   * No Less deposits: the deposit is not a tax receipt, and this paper is
   * the only invoice, for the full load.
   */
  public function taxInvoiceTable($shipment, $invoice): array {
    $rows = Shipment::merchandise($shipment);
    $table = [
      '#type' => 'table',
      '#header' => $this->printLineTableHeader(),
      '#empty' => $this->t('This shipment has no quantities yet.'),
      '#attributes' => ['class' => ['tec-portal__table']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];
    if (!$rows) {
      return $table;
    }
    $sum_qty = 0;
    foreach ($rows as $row) {
      $sum_qty += $row['qty'];
      $table[] = $this->goodsRow($row, TRUE);
    }
    $table['#footer'] = $this->invoiceFooter($sum_qty, Shipment::goodsTotals($shipment));
    return $table;
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
