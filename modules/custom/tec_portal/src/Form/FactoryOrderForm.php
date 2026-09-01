<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\Shipment;

/**
 * One sales order on /o/order/{id}: same Open grid as /my, for the factory.
 *
 * The company is on the order, not the login. Confirm is the same act as
 * the portal: Pending payment, zeros dropped, pen away.
 */
class FactoryOrderForm extends OrderForm {

  public function getFormId() {
    return 'tec_portal_factory_order_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $tec_order = NULL) {
    $form = parent::buildForm($form, $form_state, $tec_order);
    $id = (int) ($form_state->get('order_id') ?: 0);
    $order = $id ? $this->entityTypeManager->getStorage('tec_order')->load($id) : NULL;
    $company = $order ? $this->companyOf($order) : NULL;
    if ($company && Shipment::typesExist() && isset($form['meta'])) {
      $form['meta']['shipment'] = [
        '#type' => 'link',
        '#title' => $this->t('New shipment'),
        '#url' => Url::fromRoute('tec_portal.shipment_new', ['tec_crm' => $company->id()]),
        '#attributes' => [
          'class' => ['button', 'tec-portal__proforma'],
        ],
      ];
    }
    return $form;
  }

  /**
   * Factory staff who can see this sales order. Not a portal customer.
   */
  public static function access(AccountInterface $account, $tec_order = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $result = AccessResult::allowedIf($companies->isFactory($account))
      ->addCacheContexts(['user.roles']);
    if (is_numeric($tec_order)) {
      $tec_order = \Drupal::entityTypeManager()->getStorage('tec_order')->load((int) $tec_order);
    }
    if (!$tec_order instanceof EntityInterface || $tec_order->getEntityTypeId() !== 'tec_order' || $tec_order->bundle() !== 'tec_sales_order') {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }
    $result->addCacheableDependency($tec_order);
    return $result->andIf($tec_order->access('view', $account, TRUE));
  }

  /**
   * {@inheritdoc}
   */
  protected function backLink($order): array {
    $company = $this->companyOf($order);
    if (!$company) {
      return parent::backLink($order);
    }
    return [
      '#type' => 'link',
      '#title' => $this->t('Back to @company', ['@company' => $company->label()]),
      '#url' => $company->toUrl(),
      '#attributes' => ['class' => ['tec-portal__back']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function openHelp() {
    return $this->t('You can add or change quantities until you confirm. Confirming marks the order Pending payment and drops sizes left at 0. It cannot be undone from here.');
  }

  /**
   * {@inheritdoc}
   */
  protected function orderViewRoute(): string {
    return 'tec_portal.factory_order';
  }

}
