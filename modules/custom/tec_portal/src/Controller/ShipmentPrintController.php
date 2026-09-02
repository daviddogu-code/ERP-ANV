<?php

namespace Drupal\tec_portal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\Shipment;
use Drupal\tec_portal\ShipmentPrint;
use Drupal\tec_portal\TaxInvoice;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Printed packing list, and a redirect from the old live invoice URL.
 *
 * Factory only. Issue invoice on the shipment is what creates the 036x.
 * Print does not issue a number.
 */
class ShipmentPrintController extends ControllerBase {

  /**
   * Factory staff who can see this shipment.
   */
  public static function access(AccountInterface $account, $tec_shipment = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $result = AccessResult::allowedIf($companies->isFactory($account))
      ->addCacheContexts(['user.roles']);
    if (is_numeric($tec_shipment)) {
      if (!Shipment::typesExist()) {
        return AccessResult::forbidden()->addCacheContexts(['user.roles']);
      }
      $tec_shipment = \Drupal::entityTypeManager()->getStorage(Shipment::TYPE)->load((int) $tec_shipment);
    }
    if (!Shipment::isShipment($tec_shipment)) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }
    $result->addCacheableDependency($tec_shipment);
    return $result->andIf($tec_shipment->access('view', $account, TRUE));
  }

  public function packing($tec_shipment): array {
    return ShipmentPrint::packing($tec_shipment);
  }

  /**
   * Old live invoice URL: open the issued 036x, or send them back to Issue.
   */
  public function invoice($tec_shipment): RedirectResponse {
    $issued = TaxInvoice::typesExist() ? TaxInvoice::ofShipment($tec_shipment) : NULL;
    if ($issued) {
      return new RedirectResponse(Url::fromUri('internal:' . TaxInvoice::printPath($issued))->toString());
    }
    $this->messenger()->addWarning($this->t('Issue Tax invoice / receipt on the shipment first. Print does not create a tax invoice.'));
    return new RedirectResponse(Url::fromUri('internal:' . Shipment::viewPath($tec_shipment))->toString());
  }

  public function packingTitle($tec_shipment): string {
    return Shipment::fileTitle($tec_shipment, 'packing');
  }

  public function invoiceTitle($tec_shipment): string {
    $issued = TaxInvoice::typesExist() ? TaxInvoice::ofShipment($tec_shipment) : NULL;
    return $issued ? TaxInvoice::fileTitle($issued) : Shipment::fileTitle($tec_shipment, 'invoice');
  }

}
