<?php

namespace Drupal\tec_production;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;

/**
 * Purchasing arithmetic shared by the Stock Control board and the Purchase List.
 *
 * Both screens have to answer the same two questions -- how much of a material
 * is already coming in, and how many inventory units fit in a purchase unit --
 * and they have to answer them the same way. When /stock and /purchase disagree
 * about what is on order, one of them tells you to buy something twice.
 *
 * Since receiving exists (15 August 2026) "coming in" means what is still
 * outstanding, not what was ordered, and it is derived from the lines every
 * time it is asked for. There is deliberately no field anywhere holding it.
 */
final class Purchasing {

  /**
   * The purchase order status that still brings stock in.
   *
   * A draft that nobody has sent to the supplier should not count as incoming
   * stock either, which is why this is a whitelist of one and not "anything
   * that is not closed".
   */
  public const INCOMING_STATUS = 'open';

  /**
   * The purchase order status that brings nothing else in.
   *
   * Set when no line has anything outstanding, whether because everything
   * arrived or because what was missing got written off. Which of the two it
   * was is read off the lines; see receiptState().
   */
  public const CLOSED_STATUS = 'closed';

  /**
   * What one purchase order line still has to bring, in purchase units.
   *
   * This is the whole "on order" idea in one place, and it is deliberately a
   * subtraction rather than a stored number: an ERP that lets a human type how
   * much is still coming ends up with a figure nobody can reconcile against the
   * orders. Odoo and SAP both derive it the same way.
   *
   * Zero when the line was closed short. Never negative: an over-delivery means
   * the supplier sent more than was ordered, not that the warehouse owes them
   * the difference back.
   */
  public static function outstanding($line): float {
    if ($line->hasField('field_tec_no_more_expected') && !empty($line->get('field_tec_no_more_expected')->value)) {
      return 0.0;
    }
    $ordered = $line->hasField('field_tec_quantity') && !$line->get('field_tec_quantity')->isEmpty()
      ? (float) $line->get('field_tec_quantity')->value
      : 0.0;
    $received = $line->hasField('field_tec_quantity_received') && !$line->get('field_tec_quantity_received')->isEmpty()
      ? (float) $line->get('field_tec_quantity_received')->value
      : 0.0;

    return max(0.0, $ordered - $received);
  }

  /**
   * How far along a purchase order is, worked out from its lines.
   *
   * Not stored anywhere on purpose. Two statuses live in the database, open and
   * closed, and this is the nuance on top: storing it as a third status would
   * be a second version of the truth, and the two would drift the first time
   * someone fixed a quantity by hand.
   *
   * @return string
   *   One of 'none' (open, nothing has arrived), 'partial' (open, something
   *   has), 'full' (nothing outstanding and something arrived) or 'cancelled'
   *   (nothing outstanding and nothing ever arrived).
   */
  public static function receiptState($order): string {
    $outstanding = 0.0;
    $received = 0.0;
    foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
      $outstanding += self::outstanding($line);
      $received += $line->hasField('field_tec_quantity_received') && !$line->get('field_tec_quantity_received')->isEmpty()
        ? (float) $line->get('field_tec_quantity_received')->value
        : 0.0;
    }

    if ($outstanding > 0.0) {
      return $received > 0.0 ? 'partial' : 'none';
    }
    return $received > 0.0 ? 'full' : 'cancelled';
  }

  /**
   * The seven readings of a purchase order, in the order they happen.
   *
   * This is the one answer to "what is going on with this order", and every
   * screen that shows a status shows this: the supplier list, the order card,
   * the supplier's own tab, the printout and the purchase control screen. Until
   * this existed each of them read a different thing and they contradicted each
   * other, which is how an order could be Closed on one page and Partially
   * received on the next.
   *
   * Only the three in the middle are stored. Open is the absence of them,
   * Delivered comes from the receipts, Closed from the invoice and Cancelled
   * from a written-off order. The order of the list is the order of the story,
   * and it is what the Status column sorts by, so sorting gathers everything
   * that has not left the supplier yet; alphabetical would put "On the way"
   * above "On process" and mean nothing.
   */
  public const PROGRESS = [
    'open' => 'Open',
    'on_process' => 'On process',
    'ready_to_pick_up' => 'Ready to pick up',
    'on_the_way' => 'On the way',
    'delivered' => 'Delivered',
    'closed' => 'Closed',
    'cancelled' => 'Cancelled',
  ];

  /**
   * The three a person is allowed to choose.
   *
   * Where the goods are between the supplier's yard and the factory door is the
   * one thing the ERP cannot work out on its own, so it is the one thing a
   * person types. The other four are facts and are read off the data: offering
   * Delivered in a dropdown would let someone mark an order arrived without a
   * single unit entering stock, and the purchase list would then refuse to
   * reorder material that never came.
   */
  public const PROGRESS_MANUAL = ['on_process', 'ready_to_pick_up', 'on_the_way'];

  /**
   * Where a purchase order has got to, in one word.
   *
   * Nothing here is stored beyond the three manual steps, so the reading cannot
   * drift from the orders it describes. The precedence is the whole design:
   *
   *   Cancelled  closed with nothing ever received, so it never happened.
   *   Closed     everything arrived and the invoice is filed. Both halves are
   *              required: an invoice that turns up before a part-delivery must
   *              not be able to finish an order that still owes material.
   *   Delivered  everything arrived, the paperwork has not.
   *   The three  whatever the person chasing it last said.
   *   Open       nobody has said anything yet.
   *
   * Note what Cancelled is not. An order with no quantities on any line reads
   * the same as a written-off one from the lines alone -- nothing outstanding,
   * nothing received -- but one is abandoned and the other is a draft somebody
   * started this morning. The stored open/closed flag is what tells them apart,
   * and it is the reason that flag is still worth having.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   A purchase order.
   *
   * @return string
   *   One of the keys of self::PROGRESS.
   */
  public static function progress($order): string {
    $state = self::receiptState($order);
    $closed = $order->hasField('field_tec_po_status')
      && $order->get('field_tec_po_status')->value === self::CLOSED_STATUS;

    if ($state === 'cancelled') {
      return $closed ? 'cancelled' : 'open';
    }
    if ($state === 'full') {
      $invoiced = $order->hasField('field_tec_supplier_invoice')
        && !$order->get('field_tec_supplier_invoice')->isEmpty();
      return $invoiced ? 'closed' : 'delivered';
    }

    $said = $order->hasField('field_tec_po_queue_status')
      ? (string) $order->get('field_tec_po_queue_status')->value
      : '';

    return in_array($said, self::PROGRESS_MANUAL, TRUE) ? $said : 'open';
  }

  /**
   * What is still to come per material, broken down by purchase order.
   *
   * The breakdown exists so the stock board can offer the order itself: seeing
   * that 10 are coming is only half the answer when what you want to do next is
   * say that they arrived.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param int[] $tids
   *   Material term ids to look up.
   *
   * @return array
   *   Keyed by material term id, then by order id, each entry holding the order
   *   entity under 'order' and its outstanding purchase units under 'quantity'.
   *   Materials with nothing coming are absent, and so are orders and lines
   *   with nothing outstanding.
   */
  public static function incoming(EntityTypeManagerInterface $entityTypeManager, array $tids): array {
    $map = [];
    if (!$tids) {
      return $map;
    }

    $storage = $entityTypeManager->getStorage('tec_line_item');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_po_line_item')
      ->condition('field_tec_inventory', $tids, 'IN')
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return $map;
    }

    foreach ($storage->loadMultiple($ids) as $line) {
      $order = $line->hasField('field_tec_order') ? $line->get('field_tec_order')->entity : NULL;
      // A line with no order behind it is an orphan, not an order in flight.
      if (!$order || $order->bundle() !== 'tec_purchase_order') {
        continue;
      }
      $status = $order->hasField('field_tec_po_status') ? $order->get('field_tec_po_status')->value : NULL;
      if ($status !== self::INCOMING_STATUS) {
        continue;
      }
      $pending = self::outstanding($line);
      // Nothing outstanding must leave the material out of the map rather than
      // put a zero in it, because callers read a present key as "something is
      // coming" and would show a 0 where a dash belongs.
      if ($pending <= 0.0) {
        continue;
      }
      $tid = (int) $line->get('field_tec_inventory')->target_id;
      $oid = (int) $order->id();
      if (!isset($map[$tid][$oid])) {
        $map[$tid][$oid] = ['order' => $order, 'quantity' => 0.0];
      }
      $map[$tid][$oid]['quantity'] += $pending;
    }

    return $map;
  }

  /**
   * Quantity still to come per material, in purchase units.
   *
   * Until 15 August 2026 this returned the quantity ordered, which was the same
   * number until the day a delivery arrived -- and there was no way to record
   * that a delivery had arrived, so it stayed the same number forever.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param int[] $tids
   *   Material term ids to look up.
   *
   * @return float[]
   *   Quantity outstanding keyed by material term id. Materials with nothing
   *   coming are absent, not zero.
   */
  public static function onOrder(EntityTypeManagerInterface $entityTypeManager, array $tids): array {
    $map = [];
    foreach (self::incoming($entityTypeManager, $tids) as $tid => $orders) {
      $map[$tid] = array_sum(array_column($orders, 'quantity'));
    }

    return $map;
  }

  /**
   * The day each purchase order's goods actually turned up.
   *
   * Not a field, and deliberately so. Receiving already writes a stock
   * transaction that points back at the line it came in on, and that
   * transaction is dated, so the arrival date is sitting in the warehouse's own
   * history. Writing it a second time onto the order would let the two
   * disagree, and the copy on the order is the one nobody could check.
   *
   * A delivery that came in two lorries leaves two dates. This returns the last
   * of them, which is the day the order stopped being something to chase.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param array $orders
   *   Purchase orders, keyed however the caller likes.
   *
   * @return int[]
   *   Unix timestamp of the last arrival, keyed by order id. Orders where
   *   nothing has arrived yet are absent, not zero.
   */
  public static function deliveredOn(EntityTypeManagerInterface $entityTypeManager, array $orders): array {
    $order_of_line = [];
    foreach ($orders as $order) {
      foreach ($order->get('field_tec_line_items') as $item) {
        if ($item->target_id) {
          $order_of_line[(int) $item->target_id] = (int) $order->id();
        }
      }
    }
    if (!$order_of_line) {
      return [];
    }

    $storage = $entityTypeManager->getStorage('tec_inventory');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_inventory_transaction')
      ->condition('field_tec_po_line', array_keys($order_of_line), 'IN')
      ->accessCheck(FALSE)
      ->execute();

    $dates = [];
    foreach ($storage->loadMultiple($ids) as $transaction) {
      $oid = $order_of_line[(int) $transaction->get('field_tec_po_line')->target_id] ?? NULL;
      if ($oid === NULL) {
        continue;
      }
      $dates[$oid] = max($dates[$oid] ?? 0, (int) $transaction->get('created')->value);
    }

    return $dates;
  }

  /**
   * The supplier invoice filed against a purchase order, if there is one.
   *
   * The file lives on the order, in the private filesystem. Returning null is
   * what lets a listing hide the download rather than print a broken icon.
   */
  public static function invoice($order): ?FileInterface {
    if (!$order instanceof EntityInterface
      || !$order->hasField('field_tec_supplier_invoice')
      || $order->get('field_tec_supplier_invoice')->isEmpty()) {
      return NULL;
    }
    $file = $order->get('field_tec_supplier_invoice')->entity;
    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * Bangkok date for invoice filenames: the factory's day, not Singapore's.
   */
  public static function invoiceClock(): \DateTimeImmutable {
    return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Bangkok'));
  }

  /**
   * The order title as it can live in a ZIP: spaces to _, no Windows poison.
   */
  public static function invoiceFileStem($order): string {
    $label = trim((string) ($order instanceof EntityInterface ? $order->label() : $order));
    $label = preg_replace('/\s+/u', '_', $label) ?? '';
    $label = preg_replace('/[^A-Za-z0-9._-]+/', '_', $label) ?? '';
    $label = preg_replace('/_+/', '_', $label) ?? '';
    $label = trim($label, '._');
    if ($label === '') {
      $label = 'PO';
    }
    if (strlen($label) > 80) {
      $label = rtrim(substr($label, 0, 80), '._');
    }
    return $label;
  }

  /**
   * INV_PO_30082026_POLYTE_26-013.pdf — upload day, then the order number.
   *
   * $with_id is for two orders that sanitize to the same stem the same day.
   */
  public static function invoiceFileName($order, FileInterface $file, bool $with_id = FALSE): string {
    $ext = strtolower(pathinfo((string) $file->getFilename(), PATHINFO_EXTENSION) ?: 'bin');
    $name = 'INV_PO_' . self::invoiceClock()->format('dmY') . '_' . self::invoiceFileStem($order);
    if ($with_id && $order instanceof EntityInterface) {
      $name .= '_' . $order->id();
    }
    return $name . '.' . $ext;
  }

  /**
   * Private URI that file should occupy after filing.
   *
   * Ignores $ignore (the scan being replaced) so a same-day replace keeps the
   * name. Another living file on that URI gets the order id on the end.
   */
  public static function invoiceDestination($order, FileInterface $file, ?FileInterface $ignore = NULL): string {
    $dir = 'private://supplier-invoices/' . self::invoiceClock()->format('Y');
    $dest = $dir . '/' . self::invoiceFileName($order, $file);
    if (self::invoiceUriTaken($dest, $file, $ignore)) {
      $dest = $dir . '/' . self::invoiceFileName($order, $file, TRUE);
    }
    return $dest;
  }

  /**
   * Move the scan onto that name. The scanner's 1893728.jpg is not kept.
   */
  public static function placeInvoice(FileInterface $file, $order, ?FileInterface $ignore = NULL): FileInterface {
    $dest = self::invoiceDestination($order, $file, $ignore);
    $dir = 'private://supplier-invoices/' . self::invoiceClock()->format('Y');
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
    if ($file->getFileUri() !== $dest) {
      $file = \Drupal::service('file.repository')->move($file, $dest, FileExists::Replace);
    }
    // Drupal's move keeps the scanner name on the file entity even after the
    // URI has changed. The ZIP for the accountant needs the INV_PO name.
    $file->setFilename(basename(str_replace('\\', '/', $dest)));
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Another file entity already sits on this URI, and it is not the new scan
   * or the one this filing is about to drop.
   */
  protected static function invoiceUriTaken(string $uri, FileInterface $incoming, ?FileInterface $ignore): bool {
    $ids = \Drupal::entityTypeManager()->getStorage('file')->getQuery()
      ->condition('uri', $uri)
      ->accessCheck(FALSE)
      ->execute();
    foreach ($ids as $id) {
      if ((int) $id === (int) $incoming->id()) {
        continue;
      }
      if ($ignore && (int) $id === (int) $ignore->id()) {
        continue;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * The URL that downloads that invoice for whoever can see the order.
   */
  public static function invoiceUrl($order): ?Url {
    $file = self::invoice($order);
    if (!$file) {
      return NULL;
    }
    $path = $file->createFileUrl();
    if (!$path) {
      return NULL;
    }
    return str_starts_with($path, '/')
      ? Url::fromUserInput($path)
      : Url::fromUri($path);
  }

  /**
   * Same file, as an attachment. The dialog Download uses this, not invoiceUrl():
   * that one is for the hover preview, which has to show the scan inline.
   */
  public static function invoiceDownloadUrl($order): ?Url {
    if (!self::invoice($order)) {
      return NULL;
    }
    $id = $order instanceof EntityInterface ? $order->id() : $order;
    return Url::fromRoute('tec_production.po_invoice_file', ['tec_order' => $id]);
  }

  /**
   * The File invoice dialog, with a way back to the screen that opened it.
   *
   * Purchase Control used to be the only door. Closed orders leave that list,
   * so Supplier Orders has to open the same form or there is nowhere to replace
   * a bad scan. Destination is whatever page rendered the link: after save the
   * staff land there, not always on Purchase Control.
   */
  public static function invoiceDialogUrl($order): Url {
    $id = $order instanceof EntityInterface ? $order->id() : $order;
    return Url::fromRoute('tec_production.po_invoice', ['tec_order' => $id], [
      'query' => \Drupal::destination()->getAsArray(),
    ]);
  }

  /**
   * Attributes that open that form in the same modal Purchase Control uses.
   */
  public static function invoiceDialogAttributes(array $classes = []): array {
    $classes[] = 'use-ajax';
    return [
      'class' => array_values(array_unique(array_filter($classes))),
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode(['width' => 520]),
    ];
  }

  /**
   * Marks the invoice link so a hover preview can open, photo or PDF.
   *
   * Views rewrites the icon through a token, and that path strips an iframe
   * out of the markup. The list icon now points at the replace dialog, so the
   * file URL is on data-preview-url rather than href; javascript fills the box
   * from that, and still falls back to href on the order card download.
   */
  public static function invoicePreview(FileInterface $file, array $trigger, ?Url $preview_url = NULL): array {
    $classes = $trigger['#attributes']['class'] ?? [];
    if (is_string($classes)) {
      $classes = preg_split('/\s+/', trim($classes)) ?: [];
    }
    $classes[] = 'tec-po-invoice-preview';
    $trigger['#attributes']['class'] = array_values(array_unique($classes));
    $kind = self::invoiceKind($file);
    if ($kind !== 'other') {
      $trigger['#attributes']['data-preview'] = $kind;
    }
    if ($preview_url) {
      $trigger['#attributes']['data-preview-url'] = $preview_url->toString();
    }
    $trigger['#attached']['library'][] = 'tec_production/supplier_orders';
    return $trigger;
  }

  /**
   * Photo, PDF from the scanner, or something we cannot preview.
   */
  public static function invoiceKind(FileInterface $file): string {
    $mime = strtolower((string) $file->getMimeType());
    $name = strtolower($file->getFilename());
    if (str_starts_with($mime, 'image/') || preg_match('/\.(jpe?g|png|webp|gif|heic|heif)$/', $name)) {
      return 'image';
    }
    if ($mime === 'application/pdf' || str_ends_with($name, '.pdf')) {
      return 'pdf';
    }
    return 'other';
  }

  /**
   * A conversion factor, falling back to 1.
   *
   * Every material is supposed to carry both factors since Oscar's optional
   * unit system was removed, and the factor is 1 when the unit does not change.
   * An empty or zero factor would divide by zero, so it is read as that 1.
   */
  public static function factor($entity, string $field): float {
    if ($entity->hasField($field) && !$entity->get($field)->isEmpty()) {
      $value = (float) $entity->get($field)->value;
      if ($value > 0.0) {
        return $value;
      }
    }
    return 1.0;
  }

}
