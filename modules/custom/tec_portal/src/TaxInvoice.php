<?php

namespace Drupal\tec_portal;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\tec_production\Company;
use Drupal\tec_production\LineItemDisplay;
use Drupal\tec_production\SalesStatus;
use Drupal\tec_production\Vat;

/**
 * One tax invoice / receipt: a deposit or this dispatch, frozen when issued.
 *
 * Recorded deposits live on the order before they have a 036x. Issue invoice
 * spends the next number and freezes buyer, seller, bank and amounts. Print
 * reads that snapshot. Save quantities on a shipment never spends a number.
 */
final class TaxInvoice {

  public const TYPE = 'tec_invoice';

  public const BUNDLE = 'tec_invoice';

  public const KIND_FIELD = 'field_tec_kind';

  public const STATUS_FIELD = 'field_tec_status';

  public const NUMBER_FIELD = 'field_tec_tax_no';

  public const CUSTOMER_FIELD = 'field_tec_customer';

  public const ORDER_FIELD = 'field_tec_order';

  public const SHIPMENT_FIELD = 'field_tec_shipment';

  public const DATE_FIELD = 'field_tec_date';

  public const NOTE_FIELD = 'field_tec_note';

  public const DESCRIPTION_FIELD = 'field_tec_description';

  public const LINE_LABEL_FIELD = 'field_tec_line_label';

  public const ORDER_REF_FIELD = 'field_tec_order_ref';

  public const QTY_FIELD = 'field_tec_qty';

  public const RATE_FIELD = 'field_tec_vat_rate';

  public const NET_FIELD = 'field_tec_net';

  public const VAT_FIELD = 'field_tec_vat';

  public const GROSS_FIELD = 'field_tec_gross';

  public const GOODS_GROSS_FIELD = 'field_tec_goods_gross';

  public const DEPOSIT_APPLIED_FIELD = 'field_tec_deposit_applied';

  public const APPLIED_FIELD = 'field_tec_applied_gross';

  public const SNAPSHOT_FIELD = 'field_tec_snapshot';

  public const SHIPMENT_INVOICE_FIELD = 'field_tec_tax_invoice';

  public const KIND_DEPOSIT = 'deposit';

  public const KIND_GOODS = 'goods';

  public const RECORDED = 'recorded';

  public const ISSUED = 'issued';

  public const LINE_LABEL = 'Boxing equipment';

  /**
   * Highest 036x handed out by this ERP. Never exported.
   */
  public const NUMBER_LEDGER = 'tec_portal.invoice_number';

  private const TITLE_MAX = 255;

  /**
   * Whether this is a tax-invoice entity.
   */
  public static function isInvoice($entity): bool {
    return $entity instanceof EntityInterface
      && $entity->getEntityTypeId() === self::TYPE
      && $entity->bundle() === self::BUNDLE;
  }

  public static function typesExist(): bool {
    return \Drupal::entityTypeManager()->hasDefinition(self::TYPE);
  }

  public static function listPath(): string {
    return '/o/inv';
  }

  public static function depositPath($order): string {
    return '/o/order/' . (int) $order->id() . '/deposit';
  }

  public static function depositEditPath($order, $invoice): string {
    return self::depositPath($order) . '/' . (int) $invoice->id();
  }

  /**
   * Print URL of an issued invoice. Empty when it has no number yet.
   */
  public static function printPath($invoice): string {
    $n = self::number($invoice);
    return $n > 0 ? '/o/inv/' . self::formatNumber($n) . '/print' : '';
  }

  public static function formatNumber(int $number): string {
    return str_pad((string) max(0, $number), 4, '0', STR_PAD_LEFT);
  }

  public static function number($invoice): int {
    if (!$invoice || !$invoice->hasField(self::NUMBER_FIELD) || $invoice->get(self::NUMBER_FIELD)->isEmpty()) {
      return 0;
    }
    return (int) $invoice->get(self::NUMBER_FIELD)->value;
  }

  public static function isIssued($invoice): bool {
    return self::isInvoice($invoice)
      && self::statusOf($invoice) === self::ISSUED
      && self::number($invoice) > 0;
  }

  public static function isDeposit($invoice): bool {
    return self::kindOf($invoice) === self::KIND_DEPOSIT;
  }

  public static function kindOf($invoice): string {
    if (!$invoice || !$invoice->hasField(self::KIND_FIELD) || $invoice->get(self::KIND_FIELD)->isEmpty()) {
      return '';
    }
    return (string) $invoice->get(self::KIND_FIELD)->value;
  }

  public static function statusOf($invoice): string {
    if (!$invoice || !$invoice->hasField(self::STATUS_FIELD) || $invoice->get(self::STATUS_FIELD)->isEmpty()) {
      return self::RECORDED;
    }
    return (string) $invoice->get(self::STATUS_FIELD)->value;
  }

  /**
   * Browser tab and Save-as-PDF filename: TAX_INVOICE_0361.
   */
  public static function fileTitle($invoice): string {
    $n = self::number($invoice);
    return $n > 0 ? 'TAX_INVOICE_' . self::formatNumber($n) : 'TAX_INVOICE';
  }

  /**
   * List cache tags so /o/inv rebuilds when one is issued.
   *
   * @return string[]
   */
  public static function listCacheTags(): array {
    if (!self::typesExist()) {
      return [];
    }
    return \Drupal::entityTypeManager()->getDefinition(self::TYPE)->getListCacheTags();
  }

  /**
   * Whether this order should get a 036x when the deposit is issued.
   *
   * Thailand (stamped 7%) yes. Export (0%) records the money only.
   */
  public static function orderNeedsTaxInvoice($order): bool {
    return self::orderVatRate($order) > 0;
  }

  /**
   * Stamped sales VAT, or 0 when the order was raised before the field.
   */
  public static function orderVatRate($order): float {
    if (!$order) {
      return 0.0;
    }
    $sums = Vat::breakdown($order);
    return ($sums && $sums['rate'] !== NULL) ? (float) $sums['rate'] : 0.0;
  }

  /**
   * YY-NNN tail of a sales-order title, or the whole title.
   */
  public static function orderTail($order): string {
    $label = $order ? trim((string) $order->label()) : '';
    if ($label !== '' && preg_match('/(\d{2}-\d{3,})\s*$/', $label, $match)) {
      return $match[1];
    }
    return $label;
  }

  /**
   * Next deposit label on this order: Deposit 1, Deposit 2, …
   */
  public static function nextDepositLabel($order): string {
    $n = 0;
    foreach (self::ofOrder($order) as $invoice) {
      if (self::isDeposit($invoice)) {
        $n++;
      }
    }
    return 'Deposit ' . ($n + 1);
  }

  /**
   * Issued tax invoices, newest number first.
   *
   * @return EntityInterface[]
   */
  public static function issuedList(int $limit = 0, int $offset = 0): array {
    if (!self::typesExist()) {
      return [];
    }
    $query = self::issuedQuery()
      ->sort(self::NUMBER_FIELD, 'DESC')
      ->sort('id', 'DESC');
    if ($limit > 0) {
      $query->range($offset, $limit);
    }
    $ids = $query->execute();
    if (!$ids) {
      return [];
    }
    return array_values(\Drupal::entityTypeManager()->getStorage(self::TYPE)->loadMultiple($ids));
  }

  public static function issuedCount(): int {
    if (!self::typesExist()) {
      return 0;
    }
    return (int) self::issuedQuery()->count()->execute();
  }

  /**
   * Issued invoice with this 036x, or NULL.
   */
  public static function byNumber(int $number): ?EntityInterface {
    if ($number < 1 || !self::typesExist()) {
      return NULL;
    }
    $ids = \Drupal::entityTypeManager()->getStorage(self::TYPE)->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::STATUS_FIELD, self::ISSUED)
      ->condition(self::NUMBER_FIELD, $number)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $loaded = \Drupal::entityTypeManager()->getStorage(self::TYPE)->load(reset($ids));
    return self::isInvoice($loaded) ? $loaded : NULL;
  }

  /**
   * Deposits of this order, then goods invoices of shipments that include it.
   *
   * @return EntityInterface[]
   */
  public static function ofOrder($order): array {
    if (!$order || !self::typesExist()) {
      return [];
    }
    $oid = (int) $order->id();
    $storage = \Drupal::entityTypeManager()->getStorage(self::TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::ORDER_FIELD, $oid)
      ->sort('id', 'ASC')
      ->execute();
    $out = [];
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $invoice) {
      $out[(int) $invoice->id()] = $invoice;
    }
    if (Shipment::typesExist()) {
      $item_ids = \Drupal::entityTypeManager()->getStorage(Shipment::ITEM_TYPE)->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', Shipment::ITEM_BUNDLE)
        ->condition(Shipment::ORDER_FIELD, $oid)
        ->execute();
      $ship_ids = [];
      if ($item_ids) {
        foreach (\Drupal::entityTypeManager()->getStorage(Shipment::ITEM_TYPE)->loadMultiple($item_ids) as $item) {
          if ($item->hasField(Shipment::SHIPMENT_FIELD) && !$item->get(Shipment::SHIPMENT_FIELD)->isEmpty()) {
            $ship_ids[] = (int) $item->get(Shipment::SHIPMENT_FIELD)->target_id;
          }
        }
      }
      $ship_ids = array_values(array_unique(array_filter($ship_ids)));
      if ($ship_ids) {
        $goods_ids = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', self::BUNDLE)
          ->condition(self::KIND_FIELD, self::KIND_GOODS)
          ->condition(self::SHIPMENT_FIELD, $ship_ids, 'IN')
          ->sort('id', 'ASC')
          ->execute();
        foreach ($goods_ids ? $storage->loadMultiple($goods_ids) : [] as $invoice) {
          $out[(int) $invoice->id()] = $invoice;
        }
      }
    }
    return array_values($out);
  }

  /**
   * Whether this invoice's customer is in Thailand (summary paper).
   *
   * Anywhere else lists every product on the shipment.
   */
  public static function isThailandCustomer($invoice): bool {
    return Vat::customerCountry(self::customer($invoice)) === 'TH';
  }

  /**
   * Shipment this goods invoice belongs to, or NULL.
   */
  public static function shipmentOf($invoice): ?EntityInterface {
    if (!$invoice || !$invoice->hasField(self::SHIPMENT_FIELD) || $invoice->get(self::SHIPMENT_FIELD)->isEmpty()) {
      return NULL;
    }
    $entity = $invoice->get(self::SHIPMENT_FIELD)->entity;
    return Shipment::isShipment($entity) ? $entity : NULL;
  }

  /**
   * Product rows for an export tax invoice.
   *
   * Snapshot first (frozen at issue). Older papers fall back to the locked
   * shipment so a reprint of 0004 still lists the goods.
   *
   * @return list<array{product: string, material: string, colour: string, size: string, qty: int, price: float, amount: float, order: string}>
   */
  public static function goodsLines($invoice): array {
    $snap = self::snapshot($invoice);
    if (!empty($snap['goods_lines']) && is_array($snap['goods_lines'])) {
      return array_values($snap['goods_lines']);
    }
    $shipment = self::shipmentOf($invoice);
    if (!$shipment) {
      return [];
    }
    $lines = [];
    foreach (Shipment::merchandise($shipment) as $row) {
      $lines[] = self::snapshotGoodsLine($row);
    }
    return $lines;
  }

  /**
   * @param array{line: ?EntityInterface, order: ?EntityInterface, qty: int, price: float, amount: float} $row
   *
   * @return array{product: string, material: string, colour: string, size: string, qty: int, price: float, amount: float, order: string}
   */
  public static function snapshotGoodsLine(array $row): array {
    $line = $row['line'] ?? NULL;
    $product = ($line && $line->hasField('field_tec_product')) ? $line->get('field_tec_product')->entity : NULL;
    $size = ($line && $line->hasField('field_tec_size_variation')) ? $line->get('field_tec_size_variation')->entity : NULL;
    $colour = $line ? LineItemDisplay::colorVariation($line) : NULL;
    return [
      'product' => $product instanceof EntityInterface ? Catalogue::productName($product) : ($line ? (string) $line->label() : ''),
      'material' => $product ? LineItemDisplay::materialLabel($product) : '',
      'colour' => LineItemDisplay::colorLabel($colour),
      'size' => LineItemDisplay::sizeLabel($size),
      'qty' => (int) ($row['qty'] ?? 0),
      'price' => (float) ($row['price'] ?? 0),
      'amount' => (float) ($row['amount'] ?? 0),
      'order' => !empty($row['order']) ? trim((string) $row['order']->label()) : '',
    ];
  }

  /**
   * Issued goods invoice of this shipment, or NULL.
   */
  public static function ofShipment($shipment): ?EntityInterface {
    $invoice = self::goodsOfShipment($shipment);
    return $invoice && self::isIssued($invoice) ? $invoice : NULL;
  }

  /**
   * Whether quantities on this shipment may still be changed.
   */
  public static function shipmentIsLocked($shipment): bool {
    return self::ofShipment($shipment) !== NULL;
  }

  /**
   * Goods invoice of this shipment, issued or still a leftover draft.
   */
  private static function goodsOfShipment($shipment): ?EntityInterface {
    if (!Shipment::isShipment($shipment) || !self::typesExist()) {
      return NULL;
    }
    if ($shipment->hasField(self::SHIPMENT_INVOICE_FIELD) && !$shipment->get(self::SHIPMENT_INVOICE_FIELD)->isEmpty()) {
      $loaded = $shipment->get(self::SHIPMENT_INVOICE_FIELD)->entity;
      if (self::isInvoice($loaded) && self::kindOf($loaded) === self::KIND_GOODS) {
        return $loaded;
      }
    }
    $ids = \Drupal::entityTypeManager()->getStorage(self::TYPE)->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::KIND_FIELD, self::KIND_GOODS)
      ->condition(self::SHIPMENT_FIELD, (int) $shipment->id())
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $loaded = \Drupal::entityTypeManager()->getStorage(self::TYPE)->load(reset($ids));
    return self::isInvoice($loaded) ? $loaded : NULL;
  }

  /**
   * Record a deposit (no 036x). Amount is what arrived in the bank, VAT included.
   */
  public static function recordDeposit($order, string $date, float $gross, string $note, ?EntityInterface $existing = NULL): EntityInterface {
    $rate = self::orderVatRate($order);
    $split = Vat::fromGross($gross, $rate);
    $company_id = self::orderCompanyId($order);
    if ($company_id < 1) {
      throw new \InvalidArgumentException('This order has no customer.');
    }
    $label = $existing ? self::plain($existing, self::DESCRIPTION_FIELD) : self::nextDepositLabel($order);
    if ($label === '') {
      $label = self::nextDepositLabel($order);
    }
    $values = [
      'type' => self::BUNDLE,
      self::KIND_FIELD => self::KIND_DEPOSIT,
      self::STATUS_FIELD => self::RECORDED,
      self::CUSTOMER_FIELD => $company_id,
      self::ORDER_FIELD => (int) $order->id(),
      self::DATE_FIELD => $date,
      self::NOTE_FIELD => $note,
      self::DESCRIPTION_FIELD => $label,
      self::LINE_LABEL_FIELD => self::LINE_LABEL,
      self::ORDER_REF_FIELD => self::orderTail($order),
      self::QTY_FIELD => 1,
      self::RATE_FIELD => $split['rate'],
      self::NET_FIELD => self::moneyValue($split['net']),
      self::VAT_FIELD => self::moneyValue($split['vat']),
      self::GROSS_FIELD => self::moneyValue($split['gross']),
      self::GOODS_GROSS_FIELD => self::moneyValue(0),
      self::DEPOSIT_APPLIED_FIELD => self::moneyValue(0),
    ];
    if (self::isInvoice($existing) && !self::isIssued($existing)) {
      foreach ($values as $field => $value) {
        if ($field === 'type') {
          continue;
        }
        $existing->set($field, $value);
      }
      $invoice = $existing;
    }
    else {
      $values['title'] = self::draftTitle($order, $label);
      if (!$existing || !$existing->hasField(self::APPLIED_FIELD) || $existing->get(self::APPLIED_FIELD)->isEmpty()) {
        $values[self::APPLIED_FIELD] = self::moneyValue(0);
      }
      $invoice = \Drupal::entityTypeManager()->getStorage(self::TYPE)->create($values);
    }
    $invoice->save();
    self::markOrderPaid($order);
    return $invoice;
  }

  /**
   * Spend the next 036x on a recorded Thailand deposit.
   *
   * @throws \InvalidArgumentException
   */
  public static function issueDeposit($invoice): EntityInterface {
    if (!self::isInvoice($invoice) || !self::isDeposit($invoice)) {
      throw new \InvalidArgumentException('This is not a deposit.');
    }
    if (self::isIssued($invoice)) {
      return $invoice;
    }
    $order = self::orderOf($invoice);
    if (!$order || !self::orderNeedsTaxInvoice($order)) {
      throw new \InvalidArgumentException('Export deposits are recorded without a tax invoice.');
    }
    if (self::grossOf($invoice) <= 0) {
      throw new \InvalidArgumentException('A tax invoice needs an amount.');
    }
    return self::issue($invoice);
  }

  /**
   * Freeze this dispatch: one 036x. Thailand minus deposits. Export is the
   * full load: no tax receipt on the deposit, one invoice at the end.
   *
   * Thailand and export share the series. Export is 0% VAT.
   *
   * @throws \InvalidArgumentException
   */
  public static function issueForShipment($shipment): EntityInterface {
    if (!Shipment::isShipment($shipment)) {
      throw new \InvalidArgumentException('This is not a shipment.');
    }
    $existing = self::goodsOfShipment($shipment);
    if ($existing && self::isIssued($existing)) {
      return $existing;
    }
    $rows = Shipment::merchandise($shipment);
    if (!$rows) {
      throw new \InvalidArgumentException('Save quantities before issuing the invoice.');
    }
    $rates = Shipment::vatRatesOn($shipment);
    if (count($rates) > 1) {
      throw new \InvalidArgumentException('These orders do not share the same VAT rate. Split them onto two shipments.');
    }
    $rate = $rates ? (float) $rates[0] : 0.0;
    $totals = Shipment::goodsTotals($shipment);
    $goods_gross = (float) $totals['gross'];
    if ($goods_gross <= 0) {
      throw new \InvalidArgumentException('This dispatch has no goods amount to invoice.');
    }
    $order_ids = [];
    $orders = [];
    foreach ($rows as $row) {
      if (!empty($row['order'])) {
        $oid = (int) $row['order']->id();
        $order_ids[$oid] = $oid;
        $orders[$oid] = $row['order'];
      }
    }
    $first = $orders ? reset($orders) : NULL;
    $company_id = Shipment::customerId($shipment) ?: ($first ? self::orderCompanyId($first) : 0);
    if ($company_id < 1) {
      throw new \InvalidArgumentException('This shipment has no customer.');
    }
    $company = \Drupal::entityTypeManager()->getStorage('tec_crm')->load($company_id);
    $thailand = Vat::customerCountry($company) === 'TH';
    $applied = 0.0;
    $bill_gross = $goods_gross;
    if ($thailand) {
      $credit = self::allocateDeposits(array_values($order_ids), $goods_gross, FALSE);
      $applied = (float) $credit['applied'];
      $bill_gross = round($goods_gross - $applied, 2);
      if ($bill_gross <= 0) {
        throw new \InvalidArgumentException('Deposits already cover this dispatch. Nothing left to invoice.');
      }
    }
    $split = Vat::fromGross($bill_gross, $rate);
    $date = Shipment::dateValue($shipment) ?: date('Y-m-d');
    $values = [
      self::KIND_FIELD => self::KIND_GOODS,
      self::STATUS_FIELD => self::RECORDED,
      self::CUSTOMER_FIELD => $company_id,
      self::ORDER_FIELD => $first ? (int) $first->id() : NULL,
      self::SHIPMENT_FIELD => (int) $shipment->id(),
      self::DATE_FIELD => $date,
      self::NOTE_FIELD => '',
      self::DESCRIPTION_FIELD => 'This dispatch',
      self::LINE_LABEL_FIELD => self::LINE_LABEL,
      self::ORDER_REF_FIELD => self::orderTailsList($orders),
      self::QTY_FIELD => 1,
      self::RATE_FIELD => $split['rate'],
      self::NET_FIELD => self::moneyValue($split['net']),
      self::VAT_FIELD => self::moneyValue($split['vat']),
      self::GROSS_FIELD => self::moneyValue($split['gross']),
      self::GOODS_GROSS_FIELD => self::moneyValue($goods_gross),
      self::DEPOSIT_APPLIED_FIELD => self::moneyValue($applied),
    ];
    if ($existing) {
      foreach ($values as $field => $value) {
        $existing->set($field, $value);
      }
      $invoice = $existing;
    }
    else {
      $values['type'] = self::BUNDLE;
      $values['title'] = 'INV';
      $values[self::APPLIED_FIELD] = self::moneyValue(0);
      $invoice = \Drupal::entityTypeManager()->getStorage(self::TYPE)->create($values);
    }
    $invoice->save();
    $issued = self::issue($invoice);
    if ($thailand) {
      self::allocateDeposits(array_values($order_ids), $goods_gross, TRUE);
    }
    if ($shipment->hasField(self::SHIPMENT_INVOICE_FIELD)) {
      $shipment->set(self::SHIPMENT_INVOICE_FIELD, (int) $issued->id());
      $shipment->save();
    }
    return $issued;
  }

  /**
   * Y-m-d of the invoice date field.
   */
  public static function dateValue($invoice): string {
    if (!$invoice || !$invoice->hasField(self::DATE_FIELD) || $invoice->get(self::DATE_FIELD)->isEmpty()) {
      return '';
    }
    return substr((string) $invoice->get(self::DATE_FIELD)->value, 0, 10);
  }

  public static function grossOf($invoice): float {
    return self::decimal($invoice, self::GROSS_FIELD);
  }

  public static function netOf($invoice): float {
    return self::decimal($invoice, self::NET_FIELD);
  }

  public static function vatOf($invoice): float {
    return self::decimal($invoice, self::VAT_FIELD);
  }

  public static function rateOf($invoice): float {
    return self::decimal($invoice, self::RATE_FIELD);
  }

  public static function descriptionOf($invoice): string {
    return self::plain($invoice, self::DESCRIPTION_FIELD);
  }

  public static function lineLabelOf($invoice): string {
    $label = self::plain($invoice, self::LINE_LABEL_FIELD);
    return $label !== '' ? $label : self::LINE_LABEL;
  }

  public static function orderRefOf($invoice): string {
    return self::plain($invoice, self::ORDER_REF_FIELD);
  }

  public static function noteOf($invoice): string {
    return self::plain($invoice, self::NOTE_FIELD);
  }

  public static function qtyOf($invoice): int {
    if (!$invoice || !$invoice->hasField(self::QTY_FIELD) || $invoice->get(self::QTY_FIELD)->isEmpty()) {
      return 1;
    }
    return max(1, (int) $invoice->get(self::QTY_FIELD)->value);
  }

  public static function goodsGrossOf($invoice): float {
    return self::decimal($invoice, self::GOODS_GROSS_FIELD);
  }

  public static function depositAppliedOf($invoice): float {
    return self::decimal($invoice, self::DEPOSIT_APPLIED_FIELD);
  }

  public static function kindLabel($invoice): string {
    return self::isDeposit($invoice) ? 'Deposit' : 'This dispatch';
  }

  public static function customer($invoice): ?EntityInterface {
    if (!$invoice || !$invoice->hasField(self::CUSTOMER_FIELD) || $invoice->get(self::CUSTOMER_FIELD)->isEmpty()) {
      return NULL;
    }
    $entity = $invoice->get(self::CUSTOMER_FIELD)->entity;
    return $entity && $entity->getEntityTypeId() === 'tec_crm' ? $entity : NULL;
  }

  public static function orderOf($invoice): ?EntityInterface {
    if (!$invoice || !$invoice->hasField(self::ORDER_FIELD) || $invoice->get(self::ORDER_FIELD)->isEmpty()) {
      return NULL;
    }
    return $invoice->get(self::ORDER_FIELD)->entity;
  }

  /**
   * Frozen letterhead, buyer and bank. Empty until issued.
   *
   * @return array<string, mixed>
   */
  public static function snapshot($invoice): array {
    if (!$invoice || !$invoice->hasField(self::SNAPSHOT_FIELD) || $invoice->get(self::SNAPSHOT_FIELD)->isEmpty()) {
      return [];
    }
    $raw = (string) $invoice->get(self::SNAPSHOT_FIELD)->value;
    $data = Json::decode($raw);
    return is_array($data) ? $data : [];
  }

  /**
   * Baht, two decimals. Same shape as the portal tables.
   */
  public static function money(float $amount): string {
    return '฿ ' . number_format($amount, 2);
  }

  /**
   * Processing after the first recorded deposit. Does not block the queue arrow.
   */
  public static function markOrderPaid($order): void {
    if (!$order || $order->getEntityTypeId() !== 'tec_order' || $order->bundle() !== 'tec_sales_order') {
      return;
    }
    if (SalesStatus::of($order) !== SalesStatus::PENDING_PAYMENT) {
      return;
    }
    $order->set('field_tec_order_status', SalesStatus::PROCESSING);
    $order->save();
  }

  /**
   * Spend the next 036x and freeze the snapshot.
   */
  private static function issue(EntityInterface $invoice): EntityInterface {
    if (self::isIssued($invoice)) {
      return $invoice;
    }
    $lock = \Drupal::lock();
    if (!$lock->acquire('tec_portal.tax_invoice', 15)) {
      throw new \RuntimeException('Could not allocate a tax invoice number. Try again.');
    }
    try {
      $n = self::allocateNumber();
      $invoice->set(self::NUMBER_FIELD, $n);
      $invoice->set(self::STATUS_FIELD, self::ISSUED);
      $invoice->set(self::SNAPSHOT_FIELD, Json::encode(self::buildSnapshot($invoice)));
      $invoice->set('title', self::issuedTitle($invoice, $n));
      $invoice->save();
      $ledger = \Drupal::keyValue(self::NUMBER_LEDGER);
      $ledger->set('n', max((int) $ledger->get('n', 0), $n));
      return $invoice;
    }
    finally {
      $lock->release('tec_portal.tax_invoice');
    }
  }

  /**
   * Next free integer: max(setting, ledger, highest issued) + 1.
   */
  private static function allocateNumber(): int {
    $ledger = (int) \Drupal::keyValue(self::NUMBER_LEDGER)->get('n', 0);
    $n = max($ledger, Company::invoiceLastNumber(), self::highestNumberInUse()) + 1;
    while (self::numberIsTaken($n)) {
      $n++;
    }
    return $n;
  }

  private static function highestNumberInUse(): int {
    if (!self::typesExist()) {
      return 0;
    }
    $ids = \Drupal::entityTypeManager()->getStorage(self::TYPE)->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::NUMBER_FIELD, 0, '>')
      ->sort(self::NUMBER_FIELD, 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return 0;
    }
    $invoice = \Drupal::entityTypeManager()->getStorage(self::TYPE)->load(reset($ids));
    return self::number($invoice);
  }

  private static function numberIsTaken(int $number): bool {
    return (int) \Drupal::entityTypeManager()->getStorage(self::TYPE)->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::NUMBER_FIELD, $number)
      ->count()
      ->execute() > 0;
  }

  private static function issuedQuery() {
    return \Drupal::entityTypeManager()->getStorage(self::TYPE)->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::STATUS_FIELD, self::ISSUED)
      ->condition(self::NUMBER_FIELD, 0, '>');
  }

  /**
   * Credit recorded deposits of these orders against this dispatch, FIFO.
   *
   * $write actually marks the credit on the deposit rows. Call it only after
   * the goods invoice has been issued, so a failed Issue does not spend them.
   *
   * @param int[] $order_ids
   *
   * @return array{applied: float, takes: array<int, float>}
   */
  private static function allocateDeposits(array $order_ids, float $need, bool $write): array {
    $need = round($need, 2);
    $takes = [];
    $applied = 0.0;
    if ($need <= 0 || !$order_ids || !self::typesExist()) {
      return ['applied' => 0.0, 'takes' => $takes];
    }
    $storage = \Drupal::entityTypeManager()->getStorage(self::TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::KIND_FIELD, self::KIND_DEPOSIT)
      ->condition(self::ORDER_FIELD, $order_ids, 'IN')
      ->sort(self::DATE_FIELD, 'ASC')
      ->sort('id', 'ASC')
      ->execute();
    if (!$ids) {
      return ['applied' => 0.0, 'takes' => $takes];
    }
    foreach ($storage->loadMultiple($ids) as $deposit) {
      if ($applied >= $need) {
        break;
      }
      $gross = self::grossOf($deposit);
      $already = self::decimal($deposit, self::APPLIED_FIELD);
      $free = round($gross - $already, 2);
      if ($free <= 0) {
        continue;
      }
      $take = round(min($free, $need - $applied), 2);
      if ($take <= 0) {
        continue;
      }
      $takes[(int) $deposit->id()] = $take;
      $applied = round($applied + $take, 2);
      if ($write) {
        $deposit->set(self::APPLIED_FIELD, self::moneyValue($already + $take));
        $deposit->save();
      }
    }
    return ['applied' => $applied, 'takes' => $takes];
  }

  /**
   * @return array<string, mixed>
   */
  private static function buildSnapshot(EntityInterface $invoice): array {
    $order = self::orderOf($invoice);
    $shipment = NULL;
    if ($invoice->hasField(self::SHIPMENT_FIELD) && !$invoice->get(self::SHIPMENT_FIELD)->isEmpty()) {
      $shipment = $invoice->get(self::SHIPMENT_FIELD)->entity;
    }
    $company = self::customer($invoice);
    if (!$company && $order) {
      $cid = self::orderCompanyId($order);
      $company = $cid ? \Drupal::entityTypeManager()->getStorage('tec_crm')->load($cid) : NULL;
    }
    $buyer = $shipment ? Shipment::party(Shipment::soldTo($shipment) ?: $company) : self::buyerOfCompany($company);
    $seller_address = Company::address();
    $bank = array_filter([
      'name' => Company::bankName(),
      'holder' => Company::bankHolder(),
      'account' => Company::bankAccount(),
      'swift' => Company::bankSwift(),
    ], static fn(string $value): bool => $value !== '');
    if (Vat::customerCountry($company) === 'TH') {
      $name = trim((string) ($buyer['name'] ?? ''));
      if ($name !== '' && !str_contains($name, 'Head Office')) {
        $buyer['name'] = $name . ' (Head Office)';
      }
    }
    $snap = [
      'buyer' => [
        'name' => (string) ($buyer['name'] ?? ''),
        'lines' => $buyer['lines'] ?? [],
        'country' => (string) ($buyer['country'] ?? ''),
        'tax_id' => (string) ($buyer['tax_id'] ?? ''),
        'phone' => (string) ($buyer['phone'] ?? ''),
      ],
      'seller' => [
        'name' => Company::legalName(),
        'lines' => Proforma::addressLines($seller_address),
        'country' => Proforma::countryName((string) ($seller_address['country_code'] ?? '')),
        'tax_id' => Company::taxId(),
        'phone' => Company::phone(),
        'email' => Company::email(),
      ],
      'bank' => $bank,
      'shipment_label' => $shipment ? trim((string) $shipment->label()) : '',
      'order_label' => $order ? trim((string) $order->label()) : '',
    ];
    if ($shipment && self::kindOf($invoice) === self::KIND_GOODS && Vat::customerCountry($company) !== 'TH') {
      $goods_lines = [];
      foreach (Shipment::merchandise($shipment) as $row) {
        $goods_lines[] = self::snapshotGoodsLine($row);
      }
      $snap['goods_lines'] = $goods_lines;
    }
    return $snap;
  }

  /**
   * @return array{entity: ?EntityInterface, name: string, lines: string[], country: string, tax_id: string, phone: string}
   */
  private static function buyerOfCompany(?EntityInterface $company): array {
    if (!$company) {
      return [
        'entity' => NULL,
        'name' => '',
        'lines' => [],
        'country' => '',
        'tax_id' => '',
        'phone' => '',
      ];
    }
    return Shipment::party($company);
  }

  private static function issuedTitle(EntityInterface $invoice, int $number): string {
    $head = self::formatNumber($number);
    $code = '';
    $company = self::customer($invoice);
    if ($company) {
      $code = \Drupal\tec_production\OrderNumber::shortCode($company);
    }
    $mid = $code !== '' ? $head . ' ' . $code : $head;
    $kind = self::isDeposit($invoice) ? self::descriptionOf($invoice) : 'This dispatch';
    $ref = self::orderRefOf($invoice);
    $wanted = $kind !== '' ? $mid . ' ' . $kind : $mid;
    if ($ref !== '') {
      $wanted .= ' (' . $ref . ')';
    }
    if (mb_strlen($wanted) > self::TITLE_MAX) {
      return mb_substr($wanted, 0, self::TITLE_MAX);
    }
    return $wanted;
  }

  private static function draftTitle($order, string $label): string {
    $tail = self::orderTail($order);
    $wanted = trim('DEPOSIT ' . $tail . ' ' . $label);
    return mb_strlen($wanted) > self::TITLE_MAX ? mb_substr($wanted, 0, self::TITLE_MAX) : $wanted;
  }

  /**
   * @param EntityInterface[] $orders
   */
  private static function orderTailsList(array $orders): string {
    $tails = [];
    foreach ($orders as $order) {
      $tail = self::orderTail($order);
      if ($tail !== '') {
        $tails[$tail] = $tail;
      }
    }
    $keys = array_keys($tails);
    natsort($keys);
    return implode(', ', $keys);
  }

  private static function orderCompanyId($order): int {
    if (!$order) {
      return 0;
    }
    $ids = \Drupal::service('tec_portal.company')->orderCompanyIds($order);
    return (int) ($ids[0] ?? 0);
  }

  private static function decimal($entity, string $field): float {
    if (!$entity || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return 0.0;
    }
    return (float) $entity->get($field)->value;
  }

  private static function plain($entity, string $field): string {
    if (!$entity || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) $entity->get($field)->value);
  }

  private static function moneyValue(float $amount): string {
    return number_format(round($amount, 2), 2, '.', '');
  }

}
