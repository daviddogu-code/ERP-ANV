<?php

namespace Drupal\tec_production;

use Drupal\Core\Entity\EntityInterface;

/**
 * Whether a purchase carries Thai VAT, and at what rate.
 *
 * Three situations look different and are the same question. A supplier abroad
 * does not charge Thai VAT. A small Thai supplier who is not registered for VAT
 * does not charge it either. A registered Thai supplier does. So there is no
 * rule about countries with exceptions bolted on; there is one fact per
 * supplier, and it is whether they are registered and charging.
 *
 * That fact is a short list rather than a yes/no box on purpose. Two of the
 * three answers mean the same zero, but for different reasons, and the reason
 * is worth keeping: a small supplier may register next year, a foreign one
 * never will, and a year from now nobody could tell them apart from a ticked or
 * unticked box. It also means the small ones can be listed and asked.
 *
 * The rate itself is not on the supplier. It sits in one setting, so the day
 * the country changes it, it is changed once and not on every card. What does
 * get written onto each purchase order is the rate that applied when the order
 * was raised -- see the presave hook in tec_production.module. An order printed
 * in March must still say in December what it said in March, whatever happened
 * to the rate or to the supplier's registration in between.
 *
 * Deriving all this from the tax number was tempting, since a Thai business
 * registered for VAT has one and an unregistered one does not, and the field is
 * already on the card. It was dropped because a foreign supplier may well have
 * their own country's number sitting in it, which would invent a 7% that nobody
 * is charging, and because a registered supplier whose number was never typed
 * in would quietly lose the VAT. For money, a fact somebody stated beats a fact
 * inferred.
 */
final class Vat {

  /**
   * The field on a contact saying how it is treated for VAT.
   */
  public const TREATMENT_FIELD = 'field_tec_vat_treatment';

  /**
   * The field on a purchase order holding the rate it was raised under.
   */
  public const RATE_FIELD = 'field_tec_vat_rate';

  /**
   * Supplier is registered and charges VAT at the standard rate.
   */
  public const STANDARD = 'standard';

  /**
   * Thai supplier, below the registration threshold, charges nothing.
   */
  public const LOCAL_EXEMPT = 'local_exempt';

  /**
   * Supplier is outside Thailand, so no Thai VAT on the order.
   *
   * Import VAT may well be paid at customs, but that belongs to the customs
   * entry and not to what is agreed with the supplier, so the order says zero.
   */
  public const FOREIGN = 'foreign';

  /**
   * The rate used when the setting is missing.
   *
   * Thailand's standard rate. Chosen over zero because most suppliers here are
   * Thai and registered, so a missing setting shows up as a number that is too
   * often right rather than as VAT quietly vanishing from every order.
   */
  private const FALLBACK_RATE = 7.0;

  /**
   * The three answers, as they read on the card.
   */
  public static function treatments(): array {
    return [
      self::STANDARD => 'Registered for VAT, charges it',
      self::LOCAL_EXEMPT => 'Thai, not registered, charges no VAT',
      self::FOREIGN => 'Outside Thailand, no Thai VAT',
    ];
  }

  /**
   * The standard rate in force today, as a percentage.
   */
  public static function standardRate(): float {
    $rate = \Drupal::config('tec_production.settings')->get('vat_rate');
    return $rate === NULL ? self::FALLBACK_RATE : (float) $rate;
  }

  /**
   * The rate to charge on a purchase from this contact, as a percentage.
   *
   * A contact with nothing chosen yet is treated as registered rather than as
   * exempt. Getting it wrong in that direction shows up: somebody sees VAT on a
   * purchase order that should not have it and says so. The other way round it
   * is a number missing from a document, which nobody notices until the
   * accountant does, months later.
   */
  public static function forContact(?EntityInterface $contact = NULL): float {
    if (!$contact || !$contact->hasField(self::TREATMENT_FIELD)) {
      return self::standardRate();
    }
    $treatment = $contact->get(self::TREATMENT_FIELD)->value;

    return match ($treatment) {
      self::LOCAL_EXEMPT, self::FOREIGN => 0.0,
      default => self::standardRate(),
    };
  }

  /**
   * The rate written on a purchase order, as a percentage.
   *
   * Read from the order and not worked out again from the supplier, because the
   * whole point of stamping it is that the order keeps what it was raised
   * under. Orders raised before this field existed have nothing in it, and are
   * answered with zero rather than with today's rate: inventing VAT on a
   * document already sent to a supplier would be worse than showing none.
   */
  public static function forOrder(EntityInterface $order): float {
    if (!$order->hasField(self::RATE_FIELD) || $order->get(self::RATE_FIELD)->isEmpty()) {
      return 0.0;
    }
    return (float) $order->get(self::RATE_FIELD)->value;
  }

  /**
   * The VAT on a net amount, rounded to the currency.
   *
   * Rounded here and only here, so that a screen, a printed order and whatever
   * is added later cannot each round it their own way and disagree by a satang.
   */
  public static function on(float $net, float $rate): float {
    return round($net * $rate / 100, 2);
  }

  /**
   * The money lines at the foot of a purchase order.
   *
   * Returns net, rate, vat and gross, or NULL if the order is not one this
   * applies to. It lives here and not in the screens because the order page,
   * the printed order and the guardian all ask the same question, and three
   * answers free to drift apart is the bug worth not having.
   *
   * A rate of NULL is not the same as a rate of zero. Zero is an answer: this
   * supplier is abroad or unregistered, and the document says so. NULL means
   * the order was raised before any of this existed, and those are left with
   * the plain total they have always shown -- printing a VAT line on a paper
   * already sent to a supplier would be worse than printing none.
   */
  public static function breakdown(EntityInterface $order): ?array {
    if ($order->getEntityTypeId() !== 'tec_order' || $order->bundle() !== 'tec_purchase_order') {
      return NULL;
    }

    $net = self::netOf($order);
    $stamped = $order->hasField(self::RATE_FIELD) && !$order->get(self::RATE_FIELD)->isEmpty();
    if (!$stamped) {
      return ['net' => $net, 'rate' => NULL, 'vat' => NULL, 'gross' => $net];
    }

    $rate = (float) $order->get(self::RATE_FIELD)->value;
    $vat = self::on($net, $rate);

    return ['net' => $net, 'rate' => $rate, 'vat' => $vat, 'gross' => round($net + $vat, 2)];
  }

  /**
   * What the lines of an order add up to before tax.
   *
   * Added from the line totals that ECA already keeps, and not recalculated
   * from cost times quantity, so that the foot of the page cannot disagree with
   * the rows printed right above it.
   */
  private static function netOf(EntityInterface $order): float {
    if (!$order->hasField('field_tec_line_items')) {
      return 0.0;
    }

    $net = 0.0;
    foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
      if ($line->hasField('field_tec_line_item_total_number')) {
        $net += (float) $line->get('field_tec_line_item_total_number')->value;
      }
    }
    return round($net, 2);
  }

}
