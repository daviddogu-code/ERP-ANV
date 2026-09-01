<?php

namespace Drupal\tec_portal;

use Drupal\Core\Cache\Cache;
use Drupal\tec_production\Company;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Packing list and commercial invoice of one shipment.
 *
 * Same merchandise() rows. Packing has no prices. Invoice has the frozen
 * line price and the VAT already stamped on the sales order. Letterhead
 * is Company::, like the proforma.
 */
final class ShipmentPrint {

  /**
   * Packing list render array.
   */
  public static function packing($shipment): array {
    return self::page($shipment, 'packing');
  }

  /**
   * Invoice render array.
   */
  public static function invoice($shipment): array {
    return self::page($shipment, 'invoice');
  }

  /**
   * @param string $kind
   *   packing or invoice.
   */
  private static function page($shipment, string $kind): array {
    if (!Shipment::isShipment($shipment)) {
      throw new NotFoundHttpException();
    }

    $sold = Shipment::party(Shipment::soldTo($shipment));
    $ship = Shipment::party(Shipment::shipTo($shipment));
    $sellerAddress = Company::address();
    $date = Shipment::dateValue($shipment);
    $timestamp = $date !== '' ? strtotime($date . ' 12:00:00') : (int) $shipment->getCreatedTime();
    $grid = ShipmentGrid::create();
    $lines = $kind === 'invoice' ? $grid->invoiceTable($shipment) : $grid->packingTable($shipment);

    $bank = [];
    if ($kind === 'invoice') {
      $bank = array_filter([
        'name' => Company::bankName(),
        'holder' => Company::bankHolder(),
        'account' => Company::bankAccount(),
        'swift' => Company::bankSwift(),
      ], static fn(string $value): bool => $value !== '');
    }

    $tags = Cache::mergeTags(
      $shipment->getCacheTags(),
      ['config:' . Company::CONFIG]
    );
    foreach ([$sold['entity'], $ship['entity']] as $party) {
      if ($party) {
        $tags = Cache::mergeTags($tags, $party->getCacheTags());
      }
    }
    foreach (Shipment::merchandise($shipment) as $row) {
      $tags = Cache::mergeTags($tags, $row['item']->getCacheTags());
      if ($row['line']) {
        $tags = Cache::mergeTags($tags, $row['line']->getCacheTags());
      }
      if ($row['order']) {
        $tags = Cache::mergeTags($tags, $row['order']->getCacheTags());
      }
    }

    return [
      '#theme' => 'tec_portal_shipment_doc',
      '#doc_label' => $kind === 'invoice' ? 'INVOICE' : 'PACKING LIST',
      '#doc_number' => (string) $shipment->label(),
      '#date' => \Drupal::service('date.formatter')->format($timestamp, 'custom', 'j M Y'),
      '#incoterms' => Shipment::incoterms($shipment),
      '#seller_name' => Company::legalName(),
      '#seller_lines' => Proforma::addressLines($sellerAddress),
      '#seller_country' => Proforma::countryName((string) ($sellerAddress['country_code'] ?? '')),
      '#seller_phone' => Company::phone(),
      '#seller_email' => Company::email(),
      '#seller_tax_id' => Company::taxId(),
      '#sold_to_name' => $sold['name'],
      '#sold_to_lines' => $sold['lines'],
      '#sold_to_country' => $sold['country'],
      '#sold_to_tax_id' => $sold['tax_id'],
      '#ship_to_name' => $ship['name'],
      '#ship_to_lines' => $ship['lines'],
      '#ship_to_country' => $ship['country'],
      '#bank' => $bank,
      '#lines' => $lines,
      '#attached' => [
        'library' => ['tec_portal/proforma'],
      ],
      '#cache' => [
        'contexts' => ['user', 'route'],
        'tags' => $tags,
      ],
    ];
  }

}
