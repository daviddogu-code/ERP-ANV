<?php

namespace Drupal\tec_portal;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tec_production\OrderNumber;
use Drupal\tec_production\SalesStatus;
use Drupal\tec_production\Vat;

/**
 * One EXW pickup, and the quantities of this shipment.
 *
 * Remaining lives here: ordered qty on the sales line minus the sum of
 * ship-item quantities for that line. A shipment may pull lines from several
 * sales orders of the same customer. Packing and invoice both read
 * merchandise(), so the pairs and quantities cannot drift apart.
 */
final class Shipment {

  public const TYPE = 'tec_shipment';

  public const BUNDLE = 'tec_shipment';

  public const ITEM_TYPE = 'tec_ship_item';

  public const ITEM_BUNDLE = 'tec_ship_item';

  public const CUSTOMER_FIELD = 'field_tec_customer';

  public const DATE_FIELD = 'field_tec_ship_date';

  public const INCOTERMS_FIELD = 'field_tec_incoterms';

  public const SOLD_TO_FIELD = 'field_tec_sold_to';

  public const SHIP_TO_FIELD = 'field_tec_ship_to';

  public const SHIPMENT_FIELD = 'field_tec_shipment';

  public const ORDER_FIELD = 'field_tec_order';

  public const SOURCE_LINE_FIELD = 'field_tec_source_line';

  public const QTY_FIELD = 'field_tec_quantity';

  public const INCOTERM = 'EXW';

  /**
   * Highest SHIP yy-NNN handed out, per calendar year. Never exported.
   */
  public const NUMBER_LEDGER = 'tec_portal.shipment_number';

  /**
   * Factory number at the start of the title: "SHIP 26-001".
   */
  private const NUMBER_SHAPE = '/^(SHIP \d{2}-\d{3,})(?:\s|$)/';

  /**
   * ECK title column. Number + code stay; order list is what gets cut.
   */
  private const TITLE_MAX = 255;

  /**
   * Whether this is a shipment entity.
   */
  public static function isShipment($entity): bool {
    return $entity instanceof EntityInterface
      && $entity->getEntityTypeId() === self::TYPE
      && $entity->bundle() === self::BUNDLE;
  }

  /**
   * Browser paths for the four factory screens.
   */
  public static function newPath(int $company_id): string {
    return '/customer/' . $company_id . '/ship/new';
  }

  public static function viewPath($shipment): string {
    return '/o/ship/' . (int) $shipment->id();
  }

  public static function packingPath($shipment): string {
    return self::viewPath($shipment) . '/pl/print';
  }

  public static function invoicePath($shipment): string {
    return self::viewPath($shipment) . '/inv/print';
  }

  /**
   * SHIP 26-001 CODE (26-001, 26-002) after the first save.
   *
   * The factory number is issued once (2027 starts at SHIP 27-001). The
   * short code and the order tails refresh when quantities are saved.
   * Leftover titles like SHIP-1 are left alone.
   */
  public static function stampTitle(EntityInterface $shipment): void {
    if (!self::isShipment($shipment) || !$shipment->id()) {
      return;
    }
    $number = self::factoryNumber($shipment->label());
    if ($number === NULL) {
      $number = self::nextNumber();
    }
    self::writeTitle($shipment, $number);
  }

  /**
   * Rebuild CODE and (orders) without spending a new factory number.
   *
   * No-op on SHIP-1 leftovers that never received SHIP yy-NNN.
   */
  public static function refreshTitle(EntityInterface $shipment): void {
    if (!self::isShipment($shipment) || !$shipment->id()) {
      return;
    }
    $number = self::factoryNumber($shipment->label());
    if ($number === NULL) {
      return;
    }
    self::writeTitle($shipment, $number);
  }

  /**
   * The "SHIP 26-001" prefix, or NULL if this title was never numbered.
   */
  public static function factoryNumber(?string $title): ?string {
    if (preg_match(self::NUMBER_SHAPE, trim((string) $title), $match)) {
      return $match[1];
    }
    return NULL;
  }

  /**
   * Whether this title already carries a factory shipment number.
   */
  public static function isNumber(?string $title): bool {
    return self::factoryNumber($title) !== NULL;
  }

  /**
   * Next free "SHIP yy-NNN" for this calendar year.
   */
  public static function nextNumber(): string {
    $prefix = self::numberPrefix();
    $ledger = \Drupal::keyValue(self::NUMBER_LEDGER);
    $key = $prefix;

    $issued = (int) $ledger->get($key, 0);
    $number = max($issued, self::highestNumberInUse($prefix)) + 1;

    while (TRUE) {
      $name = $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
      if (!self::numberIsTaken($name)) {
        $ledger->set($key, max((int) $ledger->get($key, 0), $number));
        return $name;
      }
      $number++;
    }
  }

  /**
   * "SHIP 26-" — the counter comes after. Year from the clock, like orders.
   */
  public static function numberPrefix(): string {
    return 'SHIP ' . date('y') . '-';
  }

  /**
   * Highest counter already on a shipment with this prefix, or 0.
   */
  private static function highestNumberInUse(string $prefix): int {
    $storage = \Drupal::entityTypeManager()->getStorage(self::TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition('title', $prefix, 'STARTS_WITH')
      ->execute();
    if (!$ids) {
      return 0;
    }
    $max = 0;
    foreach ($storage->loadMultiple($ids) as $shipment) {
      $number = self::factoryNumber($shipment->label());
      if ($number === NULL || !str_starts_with($number, $prefix)) {
        continue;
      }
      $max = max($max, (int) substr($number, strlen($prefix)));
    }
    return $max;
  }

  /**
   * Whether SHIP 26-001 is already the factory number of a shipment.
   *
   * Titles are "SHIP 26-001" plus optional " RISE (…)", so an exact title
   * match would miss them. STARTS_WITH the number alone would also catch
   * SHIP 26-0010. Match exact, or the number followed by a space.
   */
  private static function numberIsTaken(string $name): bool {
    $storage = \Drupal::entityTypeManager()->getStorage(self::TYPE);
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE);
    $group = $query->orConditionGroup()
      ->condition('title', $name)
      ->condition('title', $name . ' ', 'STARTS_WITH');
    return (int) $query->condition($group)->count()->execute() > 0;
  }

  /**
   * Write SHIP 26-001 CODE (order tails). Same factory number as $number.
   */
  private static function writeTitle(EntityInterface $shipment, string $number): void {
    $wanted = self::composeTitle($number, $shipment);
    if ((string) $shipment->label() === $wanted) {
      return;
    }
    $shipment->set('title', $wanted);
    $shipment->save();
  }

  /**
   * SHIP 26-001 RISE (26-001, 26-002). Empty code or empty ship omit those bits.
   */
  private static function composeTitle(string $number, EntityInterface $shipment): string {
    $code = OrderNumber::shortCode(self::customer($shipment));
    $head = $code === '' ? $number : $number . ' ' . $code;
    $tails = self::orderTailsOnShipment($shipment);
    if (!$tails) {
      return $head;
    }
    $kept = [];
    $hidden = 0;
    foreach ($tails as $index => $tail) {
      $trial = $kept;
      $trial[] = $tail;
      $candidate = $head . ' (' . implode(', ', $trial) . ')';
      if (mb_strlen($candidate) <= self::TITLE_MAX) {
        $kept = $trial;
        continue;
      }
      $hidden = count($tails) - $index;
      break;
    }
    if (!$kept) {
      return $head;
    }
    if ($hidden > 0) {
      $with_more = $head . ' (' . implode(', ', $kept) . ' +' . $hidden . ')';
      if (mb_strlen($with_more) <= self::TITLE_MAX) {
        return $with_more;
      }
    }
    return $head . ' (' . implode(', ', $kept) . ')';
  }

  /**
   * YY-NNN of each sales order with qty on this ship. Once each, sorted.
   *
   * @return string[]
   */
  private static function orderTailsOnShipment(EntityInterface $shipment): array {
    $order_ids = [];
    foreach (self::itemEntities($shipment) as $item) {
      if (self::itemQty($item) < 1) {
        continue;
      }
      $oid = self::itemOrderId($item);
      if ($oid > 0) {
        $order_ids[$oid] = $oid;
      }
    }
    if (!$order_ids) {
      return [];
    }
    $tails = [];
    $orders = \Drupal::entityTypeManager()->getStorage('tec_order')->loadMultiple($order_ids);
    foreach ($orders as $order) {
      $label = trim((string) $order->label());
      if (preg_match('/(\d{2}-\d{3,})\s*$/', $label, $match)) {
        $tails[$match[1]] = $match[1];
      }
    }
    $keys = array_keys($tails);
    natsort($keys);
    $out = [];
    foreach ($keys as $key) {
      $out[] = $tails[$key];
    }
    return $out;
  }

  /**
   * Browser tab and Save-as-PDF filename, same idea as Proforma::fileTitle.
   *
   * $kind is packing or invoice. Filename uses the factory number only, so
   * INVOICE_SHIP_26-001 stays short when the on-screen title lists orders.
   */
  public static function fileTitle($shipment, string $kind): string {
    $prefix = $kind === 'invoice' ? 'INVOICE' : 'PACKING';
    $label = $shipment ? trim((string) $shipment->label()) : '';
    $number = self::factoryNumber($label) ?: $label;
    if ($number === '') {
      return $prefix;
    }
    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $number) ?? '';
    $safe = trim($safe, '_');
    return $safe !== '' ? $prefix . '_' . $safe : $prefix;
  }

  public static function customerId($shipment): int {
    if (!$shipment || !$shipment->hasField(self::CUSTOMER_FIELD) || $shipment->get(self::CUSTOMER_FIELD)->isEmpty()) {
      return 0;
    }
    return (int) $shipment->get(self::CUSTOMER_FIELD)->target_id;
  }

  public static function customer($shipment): ?EntityInterface {
    return self::crm($shipment, self::CUSTOMER_FIELD);
  }

  public static function soldTo($shipment): ?EntityInterface {
    return self::crm($shipment, self::SOLD_TO_FIELD) ?: self::customer($shipment);
  }

  public static function shipTo($shipment): ?EntityInterface {
    return self::crm($shipment, self::SHIP_TO_FIELD) ?: self::customer($shipment);
  }

  /**
   * Y-m-d, or empty.
   */
  public static function dateValue($shipment): string {
    if (!$shipment || !$shipment->hasField(self::DATE_FIELD) || $shipment->get(self::DATE_FIELD)->isEmpty()) {
      return '';
    }
    $raw = (string) $shipment->get(self::DATE_FIELD)->value;
    return substr($raw, 0, 10);
  }

  public static function incoterms($shipment): string {
    if ($shipment && $shipment->hasField(self::INCOTERMS_FIELD) && !$shipment->get(self::INCOTERMS_FIELD)->isEmpty()) {
      $value = trim((string) $shipment->get(self::INCOTERMS_FIELD)->value);
      if ($value !== '') {
        return $value;
      }
    }
    return self::INCOTERM;
  }

  /**
   * Address block for a CRM organisation, same shape as Proforma::buyer.
   *
   * @return array{entity: ?EntityInterface, name: string, lines: string[], country: string, tax_id: string, phone: string}
   */
  public static function party(?EntityInterface $company): array {
    $empty = [
      'entity' => NULL,
      'name' => '',
      'lines' => [],
      'country' => '',
      'tax_id' => '',
      'phone' => '',
    ];
    if (!$company || $company->getEntityTypeId() !== 'tec_crm') {
      return $empty;
    }
    $address = [];
    if ($company->hasField('field_tec_address') && !$company->get('field_tec_address')->isEmpty()) {
      $address = $company->get('field_tec_address')->first()->getValue();
    }
    return [
      'entity' => $company,
      'name' => (string) $company->label(),
      'lines' => Proforma::addressLines($address),
      'country' => Proforma::countryName((string) ($address['country_code'] ?? '')),
      'tax_id' => self::plain($company, 'field_tec_tax_nr'),
      'phone' => self::plain($company, 'field_tec_phone'),
    ];
  }

  /**
   * Qty on this shipment for a sales line. Zero if this shipment has no row yet.
   */
  public static function qtyOnShipment($shipment, int $line_id): int {
    foreach (self::itemEntities($shipment) as $item) {
      if (self::sourceLineId($item) === $line_id) {
        return self::itemQty($item);
      }
    }
    return 0;
  }

  /**
   * Ordered quantity on a sales line.
   */
  public static function orderedQty($line): int {
    if (!$line || !$line->hasField(self::QTY_FIELD) || $line->get(self::QTY_FIELD)->isEmpty()) {
      return 0;
    }
    return max(0, (int) $line->get(self::QTY_FIELD)->value);
  }

  /**
   * Sum already on other shipments for this sales line.
   *
   * $except_shipment_id leaves this shipment out, so the load screen can show
   * what is still free to put here.
   */
  public static function shippedQty(int $line_id, ?int $except_shipment_id = NULL): int {
    $map = self::shippedByLine([$line_id], $except_shipment_id);
    return $map[$line_id] ?? 0;
  }

  /**
   * Ordered minus already shipped. Never below zero.
   */
  public static function remainingQty($line, ?int $except_shipment_id = NULL): int {
    $ordered = self::orderedQty($line);
    $line_id = $line ? (int) $line->id() : 0;
    if ($line_id < 1) {
      return 0;
    }
    return max(0, $ordered - self::shippedQty($line_id, $except_shipment_id));
  }

  /**
   * Shipped qty keyed by sales-line id.
   *
   * @param int[] $line_ids
   *
   * @return array<int, int>
   */
  public static function shippedByLine(array $line_ids, ?int $except_shipment_id = NULL): array {
    $line_ids = array_values(array_filter(array_map('intval', $line_ids)));
    $out = array_fill_keys($line_ids, 0);
    if (!$line_ids || !self::typesExist()) {
      return $out;
    }
    $storage = \Drupal::entityTypeManager()->getStorage(self::ITEM_TYPE);
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::ITEM_BUNDLE)
      ->condition(self::SOURCE_LINE_FIELD, $line_ids, 'IN');
    if ($except_shipment_id) {
      $query->condition(self::SHIPMENT_FIELD, $except_shipment_id, '<>');
    }
    $ids = $query->execute();
    if (!$ids) {
      return $out;
    }
    foreach ($storage->loadMultiple($ids) as $item) {
      $lid = self::sourceLineId($item);
      if ($lid && array_key_exists($lid, $out)) {
        $out[$lid] += self::itemQty($item);
      }
    }
    return $out;
  }

  /**
   * Confirmed sales lines of this customer with a quantity, for the load screen.
   *
   * Several orders of the same company appear together. Open, Cancelled
   * and Closed orders are left out.
   *
   * @return list<array{order: EntityInterface, line: EntityInterface, ordered: int, shipped: int, remaining: int}>
   */
  public static function loadRows(int $company_id, ?int $except_shipment_id = NULL): array {
    if ($company_id < 1 || !self::typesExist()) {
      return [];
    }
    $orders = self::salesOrdersOf($company_id);
    if (!$orders) {
      return [];
    }
    $order_ids = array_map(static fn($order) => (int) $order->id(), $orders);
    $line_storage = \Drupal::entityTypeManager()->getStorage('tec_line_item');
    $line_ids = $line_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_sales_order_line_item')
      ->condition('field_tec_order', $order_ids, 'IN')
      ->execute();
    if (!$line_ids) {
      return [];
    }
    $lines = $line_storage->loadMultiple($line_ids);
    $source_ids = [];
    foreach ($lines as $line) {
      $ordered = self::orderedQty($line);
      if ($ordered < 1) {
        continue;
      }
      $source_ids[] = (int) $line->id();
    }
    $shipped = self::shippedByLine($source_ids, $except_shipment_id);
    $rows = [];
    foreach ($orders as $order) {
      $oid = (int) $order->id();
      foreach ($lines as $line) {
        $line_order = self::lineOrderId($line);
        if ($line_order !== $oid) {
          continue;
        }
        $ordered = self::orderedQty($line);
        if ($ordered < 1) {
          continue;
        }
        $lid = (int) $line->id();
        $already = $shipped[$lid] ?? 0;
        $rows[] = [
          'order' => $order,
          'line' => $line,
          'ordered' => $ordered,
          'shipped' => $already,
          'remaining' => max(0, $ordered - $already),
        ];
      }
    }
    return $rows;
  }

  /**
   * Goods on this shipment, one row per ship item with qty > 0.
   *
   * Packing list and invoice must iterate this same list. Price is the
   * frozen sales-line price. VAT rate is the one stamped on that line's order.
   *
   * @return list<array{item: EntityInterface, line: ?EntityInterface, order: ?EntityInterface, qty: int, price: float, amount: float, vat_rate: ?float}>
   */
  public static function merchandise($shipment): array {
    $rows = [];
    if (!self::isShipment($shipment)) {
      return $rows;
    }
    $items = self::itemEntities($shipment);
    usort($items, static function ($a, $b) {
      $oa = self::itemOrderId($a);
      $ob = self::itemOrderId($b);
      if ($oa !== $ob) {
        return $oa <=> $ob;
      }
      return self::sourceLineId($a) <=> self::sourceLineId($b);
    });
    $line_storage = \Drupal::entityTypeManager()->getStorage('tec_line_item');
    $order_storage = \Drupal::entityTypeManager()->getStorage('tec_order');
    foreach ($items as $item) {
      $qty = self::itemQty($item);
      if ($qty < 1) {
        continue;
      }
      $line = NULL;
      $lid = self::sourceLineId($item);
      if ($lid) {
        $line = $line_storage->load($lid);
      }
      $order = NULL;
      $oid = self::itemOrderId($item);
      if ($oid) {
        $order = $order_storage->load($oid);
      }
      if (!$order && $line) {
        $order = $line->hasField('field_tec_order') ? $line->get('field_tec_order')->entity : NULL;
      }
      $price = self::linePrice($line);
      $rows[] = [
        'item' => $item,
        'line' => $line,
        'order' => $order,
        'qty' => $qty,
        'price' => $price,
        'amount' => round($qty * $price, 2),
        'vat_rate' => self::orderVatRate($order),
      ];
    }
    return $rows;
  }

  /**
   * Goods net, VAT of the orders on this shipment, gross. No deposit, no charges.
   *
   * @return array{net: float, vat: float, gross: float, by_rate: array<string, array{rate: ?float, net: float, vat: float}>}
   */
  public static function goodsTotals($shipment): array {
    $by = [];
    $net = 0.0;
    foreach (self::merchandise($shipment) as $row) {
      $amount = $row['amount'];
      $net += $amount;
      $key = $row['vat_rate'] === NULL ? 'none' : (string) $row['vat_rate'];
      if (!isset($by[$key])) {
        $by[$key] = ['rate' => $row['vat_rate'], 'net' => 0.0, 'vat' => 0.0];
      }
      $by[$key]['net'] += $amount;
    }
    $vat = 0.0;
    foreach ($by as &$group) {
      $group['net'] = round($group['net'], 2);
      $group['vat'] = $group['rate'] === NULL ? 0.0 : Vat::on($group['net'], $group['rate']);
      $vat += $group['vat'];
    }
    unset($group);
    $net = round($net, 2);
    $vat = round($vat, 2);
    return [
      'net' => $net,
      'vat' => $vat,
      'gross' => round($net + $vat, 2),
      'by_rate' => $by,
    ];
  }

  /**
   * Distinct VAT rates on the merchandise of this shipment. Empty if none stamped.
   *
   * @return float[]
   */
  public static function vatRatesOn($shipment): array {
    $rates = [];
    foreach (self::merchandise($shipment) as $row) {
      if ($row['vat_rate'] === NULL) {
        continue;
      }
      $rates[(string) $row['vat_rate']] = (float) $row['vat_rate'];
    }
    return array_values($rates);
  }

  /**
   * Whether two confirmed sales orders of this customer have qty > 0.
   */
  public static function customerHasMultipleOrders(int $company_id): bool {
    return count(self::salesOrdersOf($company_id)) > 1;
  }

  /**
   * Ship-item entities on this shipment, including qty 0 leftovers.
   *
   * @return EntityInterface[]
   */
  public static function itemEntities($shipment): array {
    if (!self::isShipment($shipment) || !self::typesExist()) {
      return [];
    }
    $storage = \Drupal::entityTypeManager()->getStorage(self::ITEM_TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::ITEM_BUNDLE)
      ->condition(self::SHIPMENT_FIELD, (int) $shipment->id())
      ->sort('id', 'ASC')
      ->execute();
    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  public static function typesExist(): bool {
    $manager = \Drupal::entityTypeManager();
    return $manager->hasDefinition(self::TYPE) && $manager->hasDefinition(self::ITEM_TYPE);
  }

  /**
   * Confirmed sales orders of this company, oldest first.
   *
   * @return EntityInterface[]
   */
  private static function salesOrdersOf(int $company_id): array {
    $storage = \Drupal::entityTypeManager()->getStorage('tec_order');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_sales_order')
      ->condition(self::CUSTOMER_FIELD, $company_id)
      ->sort('id', 'ASC')
      ->execute();
    if (!$ids) {
      return [];
    }
    $orders = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      if (PortalOrder::hasProforma($order) && SalesStatus::of($order) !== SalesStatus::CLOSED) {
        $orders[] = $order;
      }
    }
    return $orders;
  }

  private static function crm($shipment, string $field): ?EntityInterface {
    if (!$shipment || !$shipment->hasField($field) || $shipment->get($field)->isEmpty()) {
      return NULL;
    }
    $entity = $shipment->get($field)->entity;
    return $entity && $entity->getEntityTypeId() === 'tec_crm' ? $entity : NULL;
  }

  private static function sourceLineId($item): int {
    if (!$item || !$item->hasField(self::SOURCE_LINE_FIELD) || $item->get(self::SOURCE_LINE_FIELD)->isEmpty()) {
      return 0;
    }
    return (int) $item->get(self::SOURCE_LINE_FIELD)->target_id;
  }

  private static function itemOrderId($item): int {
    if (!$item || !$item->hasField(self::ORDER_FIELD) || $item->get(self::ORDER_FIELD)->isEmpty()) {
      return 0;
    }
    return (int) $item->get(self::ORDER_FIELD)->target_id;
  }

  private static function lineOrderId($line): int {
    if (!$line || !$line->hasField('field_tec_order') || $line->get('field_tec_order')->isEmpty()) {
      return 0;
    }
    return (int) $line->get('field_tec_order')->target_id;
  }

  private static function itemQty($item): int {
    if (!$item || !$item->hasField(self::QTY_FIELD) || $item->get(self::QTY_FIELD)->isEmpty()) {
      return 0;
    }
    return max(0, (int) $item->get(self::QTY_FIELD)->value);
  }

  private static function linePrice($line): float {
    if (!$line || !$line->hasField('field_tec_price') || $line->get('field_tec_price')->isEmpty()) {
      return 0.0;
    }
    return (float) $line->get('field_tec_price')->value;
  }

  private static function orderVatRate($order): ?float {
    if (!$order || !$order->hasField(Vat::RATE_FIELD) || $order->get(Vat::RATE_FIELD)->isEmpty()) {
      return NULL;
    }
    return (float) $order->get(Vat::RATE_FIELD)->value;
  }

  private static function plain($entity, string $field): string {
    if (!$entity || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) $entity->get($field)->value);
  }

}
