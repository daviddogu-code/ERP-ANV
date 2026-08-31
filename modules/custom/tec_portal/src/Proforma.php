<?php

namespace Drupal\tec_portal;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\tec_production\Company;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The printed proforma: letterhead, the /o/order table, bank if filled.
 *
 * Same path Oscar used (/o/pf/{id}/print) so every Print Proforma icon keeps
 * working. Open and Cancelled have no document.
 */
final class Proforma {

  /**
   * Render array for the print page.
   */
  public static function page($order): array {
    if (!$order || $order->getEntityTypeId() !== 'tec_order' || $order->bundle() !== 'tec_sales_order') {
      throw new NotFoundHttpException();
    }
    if (!PortalOrder::hasProforma($order)) {
      throw new NotFoundHttpException();
    }

    $buyer = self::buyer($order);
    $tags = Cache::mergeTags(
      $order->getCacheTags(),
      ['config:' . Company::CONFIG]
    );
    if ($buyer['entity']) {
      $tags = Cache::mergeTags($tags, $buyer['entity']->getCacheTags());
    }

    $sellerAddress = Company::address();
    $bank = array_filter([
      'name' => Company::bankName(),
      'holder' => Company::bankHolder(),
      'account' => Company::bankAccount(),
      'swift' => Company::bankSwift(),
    ], static fn(string $value): bool => $value !== '');

    return [
      '#theme' => 'tec_portal_proforma',
      '#order_number' => (string) $order->label(),
      '#date' => \Drupal::service('date.formatter')->format((int) $order->getCreatedTime(), 'custom', 'j M Y'),
      '#seller_name' => Company::legalName(),
      '#seller_lines' => self::addressLines($sellerAddress),
      '#seller_country' => self::countryName((string) ($sellerAddress['country_code'] ?? '')),
      '#seller_phone' => Company::phone(),
      '#seller_email' => Company::email(),
      '#seller_tax_id' => Company::taxId(),
      '#buyer_name' => $buyer['name'],
      '#buyer_lines' => $buyer['lines'],
      '#buyer_country' => $buyer['country'],
      '#buyer_tax_id' => $buyer['tax_id'],
      '#buyer_phone' => $buyer['phone'],
      '#bank' => $bank,
      '#lines' => OrderLineGrid::create()->table($order, TRUE),
      '#attached' => [
        'library' => ['tec_portal/proforma'],
      ],
      '#cache' => [
        'contexts' => ['user', 'route'],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * Browser tab and Save-as-PDF filename: PROFORMA_RISE_26-001.
   *
   * Chrome names the PDF from <title>. Spaces and the site name would
   * become "Pro Forma RISE 26-001 _ Acta Total Enterprise Control".
   */
  public static function fileTitle($order): string {
    $number = $order ? trim((string) $order->label()) : '';
    if ($number === '') {
      return 'PROFORMA';
    }
    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $number) ?? '';
    $safe = trim($safe, '_');
    return $safe !== '' ? 'PROFORMA_' . $safe : 'PROFORMA';
  }

  /**
   * Customer stamped on the order.
   *
   * @return array{entity: ?EntityInterface, name: string, lines: string[], country: string, tax_id: string, phone: string}
   */
  public static function buyer($order): array {
    $empty = [
      'entity' => NULL,
      'name' => '',
      'lines' => [],
      'country' => '',
      'tax_id' => '',
      'phone' => '',
    ];
    $ids = \Drupal::service('tec_portal.company')->orderCompanyIds($order);
    $id = (int) ($ids[0] ?? 0);
    if ($id < 1) {
      return $empty;
    }
    $company = \Drupal::entityTypeManager()->getStorage('tec_crm')->load($id);
    if (!$company) {
      return $empty;
    }
    $address = [];
    if ($company->hasField('field_tec_address') && !$company->get('field_tec_address')->isEmpty()) {
      $address = $company->get('field_tec_address')->first()->getValue();
    }
    $lines = self::addressLines($address);
    $country = self::countryName((string) ($address['country_code'] ?? ''));
    return [
      'entity' => $company,
      'name' => (string) $company->label(),
      'lines' => $lines,
      'country' => $country,
      'tax_id' => self::plain($company, 'field_tec_tax_nr'),
      'phone' => self::plain($company, 'field_tec_phone'),
    ];
  }

  /**
   * Postal lines, no country (country is its own cell).
   */
  public static function addressLines(array $address): array {
    $lines = [];
    foreach (['address_line1', 'address_line2'] as $key) {
      $value = trim((string) ($address[$key] ?? ''));
      if ($value !== '') {
        $lines[] = $value;
      }
    }
    $admin = self::subdivisionName(
      (string) ($address['country_code'] ?? ''),
      (string) ($address['administrative_area'] ?? '')
    );
    $city = trim(implode(' ', array_filter([
      trim((string) ($address['postal_code'] ?? '')),
      trim((string) ($address['locality'] ?? '')),
      $admin,
    ])));
    if ($city !== '') {
      $lines[] = $city;
    }
    return $lines;
  }

  public static function countryName(string $code): string {
    $code = strtoupper(trim($code));
    if ($code === '') {
      return '';
    }
    try {
      $country = \Drupal::service('address.country_repository')->get($code);
      return $country ? (string) $country->getName() : $code;
    }
    catch (\Throwable) {
      return $code;
    }
  }

  public static function subdivisionName(string $country, string $code): string {
    $country = strtoupper(trim($country));
    $code = trim($code);
    if ($country === '' || $code === '') {
      return '';
    }
    try {
      $sub = \Drupal::service('address.subdivision_repository')->get($code, [$country]);
      return $sub ? (string) $sub->getName() : '';
    }
    catch (\Throwable) {
      return '';
    }
  }

  protected static function plain($entity, string $field): string {
    if (!$entity || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) $entity->get($field)->value);
  }

}
