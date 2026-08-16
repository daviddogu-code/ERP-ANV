<?php

namespace Drupal\tec_production;

use Drupal\Core\Entity\EntityInterface;

/**
 * The name an order gets: the short code, the year, and a counter. "KJ 26-001".
 *
 * The title of an order in this ERP is not a description, it is the order
 * number. It is what gets read down the phone, written on the delivery note and
 * looked for in the folder, so it has to be short, unique, and say at a glance
 * whose order it is. Hence three parts: the other party's short code, the
 * two-digit year, and a counter that starts again each year.
 *
 * The counter runs per contact, not across the whole company. "Your order 001"
 * only means something to a supplier who has exactly one 001, and a customer who
 * placed three orders this year expects to see 001, 002, 003 -- not 007, 019 and
 * 044 because other customers were busy in between.
 *
 * This lives in one class because it used to live in four: two ECA processes
 * each counted rows in a view, and the purchase list counted orders its own way.
 * The three disagreed. Both ECA counts only saw published orders, and every
 * order is born unpublished, so a second order for the same contact was handed
 * the number the first one already had. Neither checked that the name was free.
 * Two orders sharing a number is not a numbering scheme.
 */
final class OrderNumber {

  /**
   * The contact field holding the short code that goes in an order number.
   *
   * One field for both sides since 16 August 2026, when the separate supplier
   * code was retired. It keeps the customer name for historical reasons only:
   * the same company is often both, and two fields meant typing the code twice
   * and keeping them in step by hand.
   */
  public const SHORT_CODE_FIELD = 'field_tec_customer_code';

  /**
   * Where the highest number handed out per contact and year is remembered.
   *
   * A key-value store rather than configuration: this is a fact about what has
   * happened on this site, not a setting, and exporting it would mean carrying
   * one site's counters to another. It survives a cache rebuild, which is the
   * whole point of it.
   */
  public const LEDGER = 'tec_production.order_number';

  /**
   * Which field holds the other party, per kind of order.
   *
   * Purchase orders carry a customer field too, left over from when they were
   * copied from sales orders. It is the supplier that belongs in their number,
   * so the vendor field is the one named here.
   *
   * Any bundle missing from this list is left alone. Draft orders are the reason
   * it is a list and not a guess: they are a staging area, they get thrown away,
   * and burning a number on one would leave gaps in a customer's sequence.
   */
  private const CONTACT_FIELD = [
    'tec_sales_order' => 'field_tec_customer',
    'tec_purchase_order' => 'field_tec_vendor',
  ];

  /**
   * Whether orders of this kind get a number from here.
   */
  public static function handles(string $bundle): bool {
    return isset(self::CONTACT_FIELD[$bundle]);
  }

  /**
   * The field holding the other party on this kind of order, or NULL.
   */
  public static function contactField(string $bundle): ?string {
    return self::CONTACT_FIELD[$bundle] ?? NULL;
  }

  /**
   * The name this order should carry.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   The order. Its contact field is read for the short code, so it has to be
   *   filled in by the time this is called -- which is why both ECA processes
   *   now attach the contact before the first save and not after it.
   *
   * @return string
   *   The name, or '' when this kind of order is not numbered from here.
   */
  public static function forOrder(EntityInterface $order): string {
    $bundle = $order->bundle();
    if (!self::handles($bundle)) {
      return '';
    }

    $field = self::CONTACT_FIELD[$bundle];
    $contact = $order->hasField($field) && !$order->get($field)->isEmpty()
      ? $order->get($field)->entity
      : NULL;

    return self::next($bundle, $contact);
  }

  /**
   * The next free name for an order of this kind and this contact.
   *
   * The number is one above the highest ever handed out for this contact and
   * year, and that height is remembered rather than worked out from what is
   * lying around. Until 17 August 2026 it was worked out, by counting the orders
   * that existed, and that made a number a thing you could get back: delete the
   * last purchase order and the count dropped, so the next one was handed the
   * same number that was already on a document sent to the supplier. Counting
   * also had a stranger habit, because a count is not a maximum -- with holes in
   * a sequence the count could land inside one and reissue a number from the
   * middle of the year.
   *
   * Two things are read and the higher wins. The remembered height, and the
   * highest number actually in use. The second is not redundant: it seeds the
   * first the first time a contact is numbered, so nothing had to be migrated,
   * and it heals the counter if orders are ever renamed or imported behind its
   * back. Neither can make the counter go backwards.
   *
   * The climb afterwards stays. The counter answers "has this been handed out",
   * the climb answers "is this name free right now", and those are different
   * questions -- a title typed by hand can occupy a number the counter never
   * issued.
   *
   * Two orders created in the very same instant can still read the same height.
   * The climb keeps them from sharing a name, and whichever writes last writes
   * the higher of the two, so the ledger ends up right; if it did not, the scan
   * for the highest in use would put it right on the next order. That is why no
   * lock is taken for something one person clicking one button cannot cause.
   *
   * Asking spends. There is no way to look at the next number without taking it,
   * on purpose: a peek that did not spend would be handed out twice the moment
   * two screens showed it. Anything that wants to display a number should create
   * the order and read its title.
   *
   * @param string $bundle
   *   The kind of order.
   * @param \Drupal\Core\Entity\EntityInterface|null $contact
   *   The customer or supplier. Without one there is no short code and the name
   *   comes out as just "26-001", with no space left dangling at the front.
   */
  public static function next(string $bundle, ?EntityInterface $contact = NULL): string {
    $storage = \Drupal::entityTypeManager()->getStorage('tec_order');
    $prefix = self::prefix($contact);
    $ledger = \Drupal::keyValue(self::LEDGER);
    $key = self::ledgerKey($bundle, $prefix);

    $issued = (int) $ledger->get($key, 0);
    $number = max($issued, self::highestInUse($bundle, $prefix)) + 1;

    while (TRUE) {
      $name = $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
      $taken = (int) $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->condition('title', $name)
        ->count()
        ->execute();
      if (!$taken) {
        $ledger->set($key, max((int) $ledger->get($key, 0), $number));
        return $name;
      }
      $number++;
    }
  }

  /**
   * Where this contact's counter is filed. Public so tests can put it back.
   *
   * The bundle is part of the key because one company is often both customer
   * and supplier, and since the two short codes became one field they produce
   * the same prefix. Their sequences are still separate: a supplier's 26-004 and
   * a customer's 26-004 are two different documents.
   */
  public static function ledgerKey(string $bundle, string $prefix): string {
    return $bundle . '|' . $prefix;
  }

  /**
   * The highest number currently on an order with this prefix, or 0.
   *
   * Sorting by title works because the counter is padded to three digits, so it
   * sorts the same as a number does. Past 999 it would not, and the answer would
   * come back too low -- which is harmless here, because it is only ever used as
   * the floor under a remembered counter that by then would be well past it.
   */
  private static function highestInUse(string $bundle, string $prefix): int {
    $ids = \Drupal::entityTypeManager()->getStorage('tec_order')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('title', $prefix, 'STARTS_WITH')
      ->sort('title', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return 0;
    }

    $order = \Drupal::entityTypeManager()->getStorage('tec_order')->load(reset($ids));
    $tail = substr((string) $order->label(), strlen($prefix));

    return preg_match('/^\d+/', $tail, $digits) ? (int) $digits[0] : 0;
  }

  /**
   * Everything in the name before the counter: "KJ 26-", or "26-" with no code.
   *
   * The space between the code and the year is what keeps two codes apart when
   * one is the start of the other: without it, "A" and "AB" would both count
   * "AB26-004" as one of theirs.
   */
  public static function prefix(?EntityInterface $contact = NULL): string {
    $code = self::shortCode($contact);
    return ($code === '' ? '' : $code . ' ') . date('y') . '-';
  }

  /**
   * The short code on a contact, trimmed, or ''.
   */
  public static function shortCode(?EntityInterface $contact = NULL): string {
    if (!$contact || !$contact->hasField(self::SHORT_CODE_FIELD) || $contact->get(self::SHORT_CODE_FIELD)->isEmpty()) {
      return '';
    }
    return trim((string) $contact->get(self::SHORT_CODE_FIELD)->value);
  }

}
