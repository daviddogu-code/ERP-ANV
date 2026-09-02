<?php

namespace Drupal\tec_portal;

use Drupal\Core\Cache\Cache;
use Drupal\tec_production\Vat;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Printed TAX INVOICE / RECEIPT from a frozen issued ficha.
 *
 * Thailand: one Boxing equipment line. Export: the same product table as the proforma.
 * Print does not issue a number.
 */
final class TaxInvoicePrint {

  /**
   * Render array for /o/inv/{number}/print.
   */
  public static function page($invoice): array {
    if (!TaxInvoice::isIssued($invoice)) {
      throw new NotFoundHttpException();
    }

    $snap = TaxInvoice::snapshot($invoice);
    $buyer = is_array($snap['buyer'] ?? NULL) ? $snap['buyer'] : [];
    $seller = is_array($snap['seller'] ?? NULL) ? $snap['seller'] : [];
    $bank = is_array($snap['bank'] ?? NULL) ? $snap['bank'] : [];
    $date = TaxInvoice::dateValue($invoice);
    $timestamp = $date !== '' ? strtotime($date . ' 12:00:00') : (int) $invoice->getCreatedTime();

    $tags = Cache::mergeTags(
      $invoice->getCacheTags(),
      TaxInvoice::listCacheTags()
    );

    return [
      '#theme' => 'tec_portal_tax_invoice',
      '#number' => TaxInvoice::formatNumber(TaxInvoice::number($invoice)),
      '#date' => \Drupal::service('date.formatter')->format($timestamp, 'custom', 'j M Y'),
      '#seller_name' => (string) ($seller['name'] ?? ''),
      '#seller_lines' => $seller['lines'] ?? [],
      '#seller_country' => (string) ($seller['country'] ?? ''),
      '#seller_phone' => (string) ($seller['phone'] ?? ''),
      '#seller_email' => (string) ($seller['email'] ?? ''),
      '#seller_tax_id' => (string) ($seller['tax_id'] ?? ''),
      '#buyer_name' => (string) ($buyer['name'] ?? ''),
      '#buyer_lines' => $buyer['lines'] ?? [],
      '#buyer_country' => (string) ($buyer['country'] ?? ''),
      '#buyer_tax_id' => (string) ($buyer['tax_id'] ?? ''),
      '#buyer_phone' => (string) ($buyer['phone'] ?? ''),
      '#bank' => $bank,
      '#lines' => self::table($invoice),
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
   * Thailand keeps the summary line. Export uses the proforma product table.
   */
  private static function table($invoice): array {
    if (!TaxInvoice::isThailandCustomer($invoice) && !TaxInvoice::isDeposit($invoice)) {
      $shipment = TaxInvoice::shipmentOf($invoice);
      if ($shipment && Shipment::merchandise($shipment)) {
        return ShipmentGrid::create()->taxInvoiceTable($shipment, $invoice);
      }
    }
    return self::summaryTable($invoice);
  }

  /**
   * One generic line plus subtotal / VAT / total from the frozen amounts.
   */
  private static function summaryTable($invoice): array {
    $net = TaxInvoice::netOf($invoice);
    $vat = TaxInvoice::vatOf($invoice);
    $gross = TaxInvoice::grossOf($invoice);
    $rate = TaxInvoice::rateOf($invoice);
    $qty = TaxInvoice::qtyOf($invoice);
    $details = [];
    $details[] = TaxInvoice::lineLabelOf($invoice);
    $desc = TaxInvoice::descriptionOf($invoice);
    if ($desc !== '') {
      $details[] = $desc;
    }
    $ref = TaxInvoice::orderRefOf($invoice);
    if ($ref !== '') {
      $details[] = 'Order no. ' . $ref;
    }
    $table = [
      '#type' => 'table',
      '#header' => [
        ['data' => t('Description'), 'class' => ['tec-tax__col-desc']],
        ['data' => t('Qty'), 'class' => ['tec-portal__num', 'tec-tax__col-qty']],
        ['data' => t('Amount'), 'class' => ['tec-portal__num', 'tec-tax__col-amt']],
      ],
      '#attributes' => ['class' => ['tec-portal__table']],
      '#prefix' => '<div class="tec-portal__group">',
      '#suffix' => '</div>',
    ];
    $table[] = [
      'description' => [
        '#markup' => implode('<br />', array_map('Drupal\Component\Utility\Html::escape', $details)),
        '#allowed_tags' => ['br'],
      ],
      'qty' => [
        '#plain_text' => number_format($qty, 0),
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-tax__col-qty']],
      ],
      'amount' => [
        '#plain_text' => TaxInvoice::money($net),
        '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-tax__col-amt']],
      ],
    ];
    $table['#footer'] = [
      self::foot(t('Subtotal'), $net, FALSE),
      self::foot(t('VAT @rate%', ['@rate' => Vat::formatRate($rate)]), $vat, FALSE),
      self::foot(t('Total'), $gross, TRUE),
    ];
    return $table;
  }

  /**
   * @return array<string, mixed>
   */
  private static function foot($label, float $amount, bool $total): array {
    $classes = ['tec-portal__vat-row'];
    if ($total) {
      $classes[] = 'tec-portal__vat-row--total';
    }
    return [
      'class' => $classes,
      'data' => [
        ['data' => $label, 'class' => ['tec-tax__col-desc', 'tec-portal__foot-label']],
        ['data' => '', 'class' => ['tec-tax__col-qty']],
        [
          'data' => TaxInvoice::money($amount),
          'class' => ['tec-portal__num', 'tec-tax__col-amt'],
        ],
      ],
    ];
  }

}
