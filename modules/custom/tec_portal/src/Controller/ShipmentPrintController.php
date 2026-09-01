<?php

namespace Drupal\tec_portal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_portal\Shipment;
use Drupal\tec_portal\ShipmentPrint;

/**
 * Printed packing list and invoice at /o/ship/{id}/pl|inv/print.
 *
 * Factory only. Same access idea as /o/order, not the portal proforma.
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

  public function invoice($tec_shipment): array {
    return ShipmentPrint::invoice($tec_shipment);
  }

  public function packingTitle($tec_shipment): string {
    return Shipment::fileTitle($tec_shipment, 'packing');
  }

  public function invoiceTitle($tec_shipment): string {
    return Shipment::fileTitle($tec_shipment, 'invoice');
  }

}
