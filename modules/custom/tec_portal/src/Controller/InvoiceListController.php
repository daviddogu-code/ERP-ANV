<?php

namespace Drupal\tec_portal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_portal\InvoiceList;
use Drupal\tec_portal\TaxInvoice;

/**
 * Factory list of every issued tax invoice (/o/inv).
 */
final class InvoiceListController {

  /**
   * Factory staff. Portal customers stay on /my.
   */
  public static function access(AccountInterface $account) {
    $companies = \Drupal::service('tec_portal.company');
    return AccessResult::allowedIf($companies->isFactory($account) && TaxInvoice::typesExist())
      ->addCacheContexts(['user.roles']);
  }

  public static function title(): string {
    return 'Invoices';
  }

  public function view(): array {
    return (new InvoiceList())->factoryPage();
  }

}
