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
   * The counter starts from how many names of this exact shape have been handed
   * out already, and then climbs until it finds one that is free. Counting alone
   * would not be enough: delete an order and the count drops, so the next one
   * would be handed a number that is still on a printed purchase order. The
   * climb is what makes the answer safe rather than merely likely.
   *
   * Counting names rather than orders is deliberate. It ties the number to what
   * has actually been handed out, so it survives an order changing hands or
   * having its date corrected, and it restarts by itself in January because the
   * year is part of what is being counted.
   *
   * Two orders created in the very same instant could still race to one name.
   * Serialising that would mean a lock on every order in the system, to protect
   * against something one person clicking one button cannot cause. The sales
   * orders have lived with those odds for years.
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

    $number = 1 + (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('title', $prefix, 'STARTS_WITH')
      ->count()
      ->execute();

    while (TRUE) {
      $name = $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
      $taken = (int) $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->condition('title', $name)
        ->count()
        ->execute();
      if (!$taken) {
        return $name;
      }
      $number++;
    }
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
