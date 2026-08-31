<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Place an order from the factory customer card (/customer/{id}/order/new).
 *
 * Same grid as /my/order/new. The company is the route argument, not the
 * login. The order exists when Place order is pressed; quantity 0 is not
 * written. Then /o/order/{id}, the same Open screen as /my.
 */
class FactoryPlaceOrderForm extends PlaceOrderForm {

  public function getFormId() {
    return 'tec_portal_factory_place_order_form';
  }

  /**
   * Factory staff who can see this organisation. Not a portal customer.
   */
  public static function access(AccountInterface $account, $tec_crm = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $result = AccessResult::allowedIf($companies->isFactory($account))
      ->addCacheContexts(['user.roles']);
    if (is_numeric($tec_crm)) {
      $tec_crm = \Drupal::entityTypeManager()->getStorage('tec_crm')->load((int) $tec_crm);
    }
    if (!$tec_crm instanceof EntityInterface || $tec_crm->getEntityTypeId() !== 'tec_crm' || $tec_crm->bundle() !== 'tec_contact_organization') {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }
    $result->addCacheableDependency($tec_crm);
    return $result->andIf($tec_crm->access('view', $account, TRUE));
  }

  public function buildForm(array $form, FormStateInterface $form_state, $tec_crm = NULL) {
    if (!$tec_crm instanceof EntityInterface || $tec_crm->bundle() !== 'tec_contact_organization') {
      throw new NotFoundHttpException();
    }
    return $this->buildGridForm($form, $form_state, $tec_crm, [
      '#type' => 'link',
      '#title' => $this->t('Back to @company', ['@company' => $tec_crm->label()]),
      '#url' => $tec_crm->toUrl(),
      '#attributes' => ['class' => ['tec-portal__back']],
    ], $this->t('Type a quantity next to each size you want. Sizes left at 0 are not ordered. The order is placed when you press the button.'));
  }

  /**
   * Company from the route, stamped at build. Not the logged-in person.
   */
  protected function companyForSubmit(FormStateInterface $form_state): ?EntityInterface {
    $id = (int) $form_state->get('company_id');
    if ($id < 1) {
      return NULL;
    }
    $company = $this->entityTypeManager->getStorage('tec_crm')->load($id);
    if (!$company || $company->bundle() !== 'tec_contact_organization') {
      return NULL;
    }
    $access = self::access($this->currentUser(), $company);
    return $access->isAllowed() ? $company : NULL;
  }

  /**
   * Factory continues on the Open grid, same as the customer on /my.
   */
  protected function afterPlace(FormStateInterface $form_state, $order, EntityInterface $company): void {
    $form_state->setRedirect('tec_portal.factory_order', ['tec_order' => $order->id()]);
  }

}
