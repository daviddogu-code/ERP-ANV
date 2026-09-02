<?php

namespace Drupal\tec_portal;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tec_production\OrderNumber;

/**
 * Sales orders that are not catalogue pairs: stickers, labels, development.
 *
 * Same tec_order and the same proforma print. Lines have a typed description
 * and a price, no product, and they never go on a shipment. The portal /my
 * does not list or open them.
 */
final class OtherCharges {

  /**
   * Boolean on tec_sales_order. Set in PHP, not on the Drupal form.
   */
  public const FIELD = 'field_tec_other_charges';

  /**
   * Whether the marker field exists on sales orders.
   */
  public static function fieldExists(): bool {
    $storage = \Drupal::entityTypeManager()->getStorage('field_storage_config');
    return (bool) $storage->load('tec_order.' . self::FIELD);
  }

  /**
   * Factory path to type the lines for this company.
   */
  public static function newPath(int $company_id): string {
    return '/customer/' . $company_id . '/charge/new';
  }

  /**
   * A sales order stamped as Other charges.
   */
  public static function isOrder($order): bool {
    if (!$order instanceof EntityInterface
      || $order->getEntityTypeId() !== 'tec_order'
      || $order->bundle() !== 'tec_sales_order') {
      return FALSE;
    }
    if ($order->hasField(self::FIELD) && !$order->get(self::FIELD)->isEmpty()) {
      return (bool) $order->get(self::FIELD)->value;
    }
    $lines = self::linesOf($order);
    if (!$lines) {
      return FALSE;
    }
    foreach ($lines as $line) {
      if (!self::isLine($line)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * A sales line with no catalogue product. No talla, no BoM, no caja.
   */
  public static function isLine($line): bool {
    if (!$line instanceof EntityInterface || $line->bundle() !== 'tec_sales_order_line_item') {
      return FALSE;
    }
    if (!$line->hasField('field_tec_product') || $line->get('field_tec_product')->isEmpty()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Stamp the order so /o/order never opens the catalogue grid.
   */
  public static function mark(EntityInterface $order): void {
    if (!$order->hasField(self::FIELD)) {
      return;
    }
    $order->set(self::FIELD, TRUE);
  }

  /**
   * Open sales order with the typed lines. Numbered like any other sale.
   *
   * @param list<array{description: string, qty: int, price: float}> $rows
   */
  public static function createOrder(EntityInterface $company, array $rows, int $uid): EntityInterface {
    $line_storage = \Drupal::entityTypeManager()->getStorage('tec_line_item');
    $lines = [];
    foreach ($rows as $row) {
      $line = self::makeLine($line_storage, $row['description'], $row['qty'], $row['price'], $uid);
      $line->save();
      $lines[] = $line;
    }
    if (!$lines) {
      throw new \InvalidArgumentException('Other charges needs at least one line.');
    }

    $order_storage = \Drupal::entityTypeManager()->getStorage('tec_order');
    $order = $order_storage->create([
      'type' => 'tec_sales_order',
      'field_tec_customer' => (int) $company->id(),
      'field_tec_line_items' => array_map(static fn($line) => $line->id(), $lines),
      'field_tec_order_status' => PortalOrder::OPEN,
      'status' => 1,
      'uid' => $uid,
    ]);
    self::mark($order);
    OrderNumber::earn($order);
    $order->save();

    foreach ($lines as $line) {
      $line->set('field_tec_order', $order->id());
      $line->save();
    }

    return $order;
  }

  /**
   * One typed line. Product, colour and size stay empty.
   */
  public static function makeLine($storage, string $description, int $qty, float $price, int $uid) {
    $qty = max(1, $qty);
    $price = round($price, 2);
    return $storage->create([
      'type' => 'tec_sales_order_line_item',
      'title' => $description,
      'field_tec_quantity' => $qty,
      'field_tec_price' => number_format($price, 2, '.', ''),
      'field_tec_custom_sales_price' => TRUE,
      'field_tec_line_item_total_number' => number_format($qty * $price, 2, '.', ''),
      'uid' => $uid,
    ]);
  }

  /**
   * Write description, qty and price onto an existing line.
   */
  public static function writeLine($line, string $description, int $qty, float $price): void {
    $qty = max(1, $qty);
    $price = round($price, 2);
    $line->set('title', $description);
    $line->set('field_tec_quantity', $qty);
    $line->set('field_tec_price', number_format($price, 2, '.', ''));
    $line->set('field_tec_custom_sales_price', TRUE);
    $line->set('field_tec_line_item_total_number', number_format($qty * $price, 2, '.', ''));
    $line->save();
  }

  /**
   * @return EntityInterface[]
   */
  public static function linesOf($order): array {
    if (!$order instanceof EntityInterface
      || !$order->hasField('field_tec_line_items')
      || $order->get('field_tec_line_items')->isEmpty()) {
      return [];
    }
    return $order->get('field_tec_line_items')->referencedEntities();
  }

}
