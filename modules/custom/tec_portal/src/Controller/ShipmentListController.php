<?php

namespace Drupal\tec_portal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_portal\Shipment;
use Drupal\tec_portal\ShipmentList;

/**
 * Factory list of every shipment (/o/ship).
 *
 * The customer card tab is the same table filtered to one company. This
 * page is for a number that arrived by LINE, without opening fifteen cards.
 */
final class ShipmentListController {

  /**
   * Factory staff. Portal customers stay on /my.
   */
  public static function access(AccountInterface $account) {
    $companies = \Drupal::service('tec_portal.company');
    return AccessResult::allowedIf($companies->isFactory($account) && Shipment::typesExist())
      ->addCacheContexts(['user.roles']);
  }

  /**
   * Page title: Shipments, never Invoices.
   */
  public static function title(): string {
    return 'Shipments';
  }

  /**
   * The table, every customer, newest first.
   */
  public function view(): array {
    return (new ShipmentList())->factoryPage();
  }

}
