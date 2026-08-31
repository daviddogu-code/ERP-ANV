<?php

namespace Drupal\tec_production\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_production\SalesStatus;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * One step forward on a sales order, the same map as /o/queue.
 */
class SalesStatusController extends ControllerBase {

  /**
   * Factory staff who can run the production queue.
   */
  public static function access(AccountInterface $account, $tec_order) {
    if (!$tec_order || $tec_order->getEntityTypeId() !== 'tec_order' || $tec_order->bundle() !== 'tec_sales_order') {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIf($account->hasPermission('access tec production queue'))
      ->addCacheContexts(['user.permissions']);
  }

  /**
   * Advance and return to the order card.
   */
  public function advance($tec_order): RedirectResponse {
    $back = $tec_order->toUrl()->toString();
    $next = SalesStatus::applyNext($tec_order);
    if ($next === NULL) {
      $this->messenger()->addWarning($this->t(
        '@order has no next status.',
        ['@order' => $tec_order->label() ?: ('#' . $tec_order->id())]
      ));
      return new RedirectResponse($back);
    }
    $this->messenger()->addStatus($this->t('@order advanced to @status.', [
      '@order' => $tec_order->label() ?: ('#' . $tec_order->id()),
      '@status' => SalesStatus::label($next),
    ]));
    return new RedirectResponse($back);
  }

}
