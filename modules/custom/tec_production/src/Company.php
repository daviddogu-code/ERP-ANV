<?php

namespace Drupal\tec_production;

use Drupal\address\Element\Address;
use Drupal\file\FileInterface;

/**
 * Who we are, for printed orders and anything else that has to say it.
 *
 * Customers and suppliers live in the CRM. This is the other record: the
 * legal identity of Acta Non Verba, edited at /admin/config/tec/company.
 * Print reads it from here so the letterhead is not pasted into a Views
 * header, which is where it used to live and why a change of address meant
 * editing HTML.
 *
 * Missing keys fall back to the factory address that was already on the
 * purchase-order printout, so a proforma can be issued before anyone opens
 * the settings screen. Tax ID, bank and logo have no such fallback: those
 * were never on the paper, and inventing them would be worse than a blank.
 */
final class Company {

  public const CONFIG = 'tec_production.settings';

  /**
   * The values written when a key has never been saved.
   *
   * Phone and street are what the purchase-order print already showed.
   * The website is the public one. Empty strings are deliberate: there is
   * no tax number or bank account in the codebase to copy.
   */
  public static function defaults(): array {
    return [
      'legal_name' => 'Acta Non Verba Co., LTD.',
      'tax_id' => '',
      'phone' => '+66 (0) 910 699 203',
      'email' => '',
      'website' => 'https://anvfightgear.com',
      'logo' => 0,
      'address' => self::defaultAddress(),
      'bank_name' => '',
      'bank_holder' => '',
      'bank_account' => '',
      'bank_swift' => '',
    ];
  }

  /**
   * Pattaya, as the purchase-order print already had it.
   *
   * Province is Chon Buri's subdivision code, not the English name: that is
   * what the Address widget stores. Locality is the city; Bang Lamung sits
   * on the second line.
   */
  public static function defaultAddress(): array {
    return Address::applyDefaults([
      'langcode' => 'en',
      'country_code' => 'TH',
      'administrative_area' => '20',
      'locality' => 'Pattaya',
      'postal_code' => '20150',
      'address_line1' => '67, 29 Nongmaikaen Rd.',
      'address_line2' => 'Bang Lamung District',
    ]);
  }

  public static function legalName(): string {
    return self::text('legal_name');
  }

  public static function taxId(): string {
    return self::text('tax_id', FALSE);
  }

  public static function phone(): string {
    return self::text('phone');
  }

  public static function email(): string {
    return self::text('email', FALSE);
  }

  public static function website(): string {
    return self::text('website');
  }

  /**
   * The stored address, or the factory one if nobody has saved it yet.
   */
  public static function address(): array {
    $address = \Drupal::config(self::CONFIG)->get('address');
    if (!is_array($address)) {
      return self::defaultAddress();
    }
    return Address::applyDefaults($address);
  }

  public static function logoId(): int {
    $id = \Drupal::config(self::CONFIG)->get('logo');
    return $id === NULL ? 0 : (int) $id;
  }

  public static function logo(): ?FileInterface {
    $id = self::logoId();
    if ($id < 1) {
      return NULL;
    }
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($id);
    return $file instanceof FileInterface ? $file : NULL;
  }

  public static function bankName(): string {
    return self::text('bank_name', FALSE);
  }

  public static function bankHolder(): string {
    return self::text('bank_holder', FALSE);
  }

  public static function bankAccount(): string {
    return self::text('bank_account', FALSE);
  }

  public static function bankSwift(): string {
    return self::text('bank_swift', FALSE);
  }

  /**
   * A stored string, or the default when the key has never been written.
   *
   * @param bool $fallback
   *   When FALSE, a missing key is an empty string rather than the default.
   *   Tax ID and bank have no default worth printing.
   */
  private static function text(string $key, bool $fallback = TRUE): string {
    $value = \Drupal::config(self::CONFIG)->get($key);
    if ($value === NULL) {
      if (!$fallback) {
        return '';
      }
      $defaults = self::defaults();
      return (string) ($defaults[$key] ?? '');
    }
    return (string) $value;
  }

}
