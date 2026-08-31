<?php

namespace Drupal\tec_portal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_portal\Proforma;

/**
 * Printed proforma at /o/pf/{id}/print.
 */
class ProformaController extends ControllerBase {

  /**
   * Factory staff or the portal customer who owns this sales order.
   */
  public static function access(AccountInterface $account, $tec_order = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $allowed = $companies->isFactory($account) || $companies->isPortalCustomer($account);
    $result = AccessResult::allowedIf($allowed)->addCacheContexts(['user.roles', 'user']);
    if (is_numeric($tec_order)) {
      $tec_order = \Drupal::entityTypeManager()->getStorage('tec_order')->load((int) $tec_order);
    }
    if (!$tec_order instanceof EntityInterface
      || $tec_order->getEntityTypeId() !== 'tec_order'
      || $tec_order->bundle() !== 'tec_sales_order'
      || !PortalOrder::hasProforma($tec_order)) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles', 'user']);
    }
    $result->addCacheableDependency($tec_order);
    return $result->andIf($tec_order->access('view', $account, TRUE));
  }

  /**
   * The document.
   */
  public function view($tec_order): array {
    return Proforma::page($tec_order);
  }

  /**
   * Browser tab title — also the default Save-as-PDF filename.
   */
  public function title($tec_order): string {
    return Proforma::fileTitle($tec_order);
  }

}
