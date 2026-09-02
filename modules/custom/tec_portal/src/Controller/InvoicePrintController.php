<?php

namespace Drupal\tec_portal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_portal\TaxInvoice;
use Drupal\tec_portal\TaxInvoicePrint;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Printed TAX INVOICE / RECEIPT at /o/inv/{number}/print.
 *
 * The number is 0361, not the entity id. Print does not issue a document.
 */
class InvoicePrintController extends ControllerBase {

  /**
   * Factory staff. The paper is issued or it is not found.
   */
  public static function access(AccountInterface $account, $number = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $result = AccessResult::allowedIf($companies->isFactory($account) && TaxInvoice::typesExist())
      ->addCacheContexts(['user.roles']);
    $invoice = self::loadIssued($number);
    if (!$invoice) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }
    $result->addCacheableDependency($invoice);
    return $result->andIf($invoice->access('view', $account, TRUE));
  }

  public function view($number): array {
    $invoice = self::loadIssued($number);
    if (!$invoice) {
      throw new NotFoundHttpException();
    }
    return TaxInvoicePrint::page($invoice);
  }

  public function title($number): string {
    $invoice = self::loadIssued($number);
    return TaxInvoice::fileTitle($invoice);
  }

  /**
   * Issued invoice for 0361 or 361, or NULL.
   */
  public static function loadIssued($number) {
    $n = (int) $number;
    if ($n < 1) {
      return NULL;
    }
    $invoice = TaxInvoice::byNumber($n);
    return $invoice && TaxInvoice::isIssued($invoice) ? $invoice : NULL;
  }

}
