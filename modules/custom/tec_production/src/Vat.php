<?php

namespace Drupal\tec_production;

use Drupal\Core\Entity\EntityInterface;

/**
 * Thai VAT on a purchase or a sale, and the rate stamped on the order.
 *
 * Purchases ask the supplier card how they are treated (registered, small Thai,
 * or foreign). Sales ask only the destination: Thailand is the standard rate,
 * anywhere else is zero. The two must not share the supplier list -- the same
 * company can be a customer and a supplier, and the answers mean different
 * things.
 *
 * The rate itself is not on the contact. It sits in one setting, so the day
 * the country changes it, it is changed once and not on every card. What does
 * get written onto each order is the rate that applied when the order was
 * raised -- see the presave hook in tec_production.module. An order printed
 * in March must still say in December what it said in March, whatever happened
 * to the rate or to the contact in between.
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
   * Country on a contact's address, or empty.
   *
   * Sales VAT is destination: Thailand is 7%, anywhere else is 0%. The
   * supplier list on the same card is a different question and is not read.
   */
  public static function customerCountry(?EntityInterface $contact = NULL): string {
    if (!$contact || !$contact->hasField('field_tec_address') || $contact->get('field_tec_address')->isEmpty()) {
      return '';
    }
    return strtoupper(trim((string) $contact->get('field_tec_address')->country_code));
  }

  /**
   * The rate to charge on a sale to this contact, as a percentage.
   *
   * ANV is VAT-registered. Every sale whose destination is Thailand is 7%,
   * whether or not the customer is registered. Export is 0%. No country yet
   * is also 0% on the arithmetic; the form refuses to confirm until there is
   * one, so a sale is not silently treated as an export.
   */
  public static function forCustomer(?EntityInterface $contact = NULL): float {
    return self::customerCountry($contact) === 'TH' ? self::standardRate() : 0.0;
  }

  /**
   * The rate as it is written next to VAT, without trailing zeros.
   */
  public static function formatRate(float $rate): string {
    return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
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
   * The money lines at the foot of an order.
   *
   * Returns net, rate, vat and gross, or NULL if the order is not one this
   * applies to. It lives here and not in the screens because the order page,
   * the printed order and the guardian all ask the same question, and three
   * answers free to drift apart is the bug worth not having.
   *
   * A rate of NULL is not the same as a rate of zero. Zero is an answer: this
   * sale is an export, or this supplier is abroad. NULL means the order was
   * raised before any of this existed, and those are left with the plain total
   * they have always shown -- printing a VAT line on a paper already sent
   * would be worse than printing none.
   */
  public static function breakdown(EntityInterface $order): ?array {
    if ($order->getEntityTypeId() !== 'tec_order'
      || !in_array($order->bundle(), ['tec_purchase_order', 'tec_sales_order'], TRUE)) {
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
