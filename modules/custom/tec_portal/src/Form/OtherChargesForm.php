<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\OtherCharges;
use Drupal\tec_portal\PortalLineTable;
use Drupal\tec_production\Vat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Type Other charges for a customer (/customer/{id}/charge/new).
 *
 * Factory only. The order exists when Place Other charges is pressed, then
 * /o/order/{id} is the same Open screen to add lines and Confirm.
 */
class OtherChargesForm extends FormBase {

  use PortalLineTable;
  use OtherChargesLines;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomerCompany $companies,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tec_portal.company'),
    );
  }

  public function getFormId() {
    return 'tec_portal_other_charges_form';
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
    $form_state->set('company_id', (int) $tec_crm->id());
    if ($form_state->get('charge_blanks') === NULL) {
      $form_state->set('charge_blanks', 3);
    }

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'tec-portal';
    $form['#attributes']['class'][] = 'tec-portal--place';
    $form['#attached']['library'][] = 'tec_portal/portal';

    $rate = $this->vatRateFromContact($tec_crm);
    $this->attachVatSettings($form, $rate);

    $form['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to @company', ['@company' => $tec_crm->label()]),
      '#url' => $tec_crm->toUrl(),
      '#attributes' => ['class' => ['tec-portal__back']],
    ];
    $form['help'] = [
      '#markup' => '<div class="tec-portal__help">'
        . $this->t('Description, quantity and price. The customer pays 100% before work starts. This does not go on a shipment.')
        . '</div>',
    ];
    $form['lines'] = $this->chargeLinesTable([], (int) $form_state->get('charge_blanks'), $rate);
    $form['actions'] = [
      '#type' => 'actions',
      'add' => [
        '#type' => 'submit',
        '#value' => $this->t('Add line'),
        '#submit' => ['::submitAddLine'],
        '#limit_validation_errors' => [],
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Place Other charges'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  public function submitAddLine(array &$form, FormStateInterface $form_state) {
    $form_state->set('charge_blanks', ((int) $form_state->get('charge_blanks')) + 1);
    $form_state->setRebuild();
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = (int) $form_state->get('company_id');
    $company = $id ? $this->entityTypeManager->getStorage('tec_crm')->load($id) : NULL;
    if (!$company || $company->bundle() !== 'tec_contact_organization') {
      $this->messenger()->addError($this->t('This order has no company.'));
      return;
    }
    if (Vat::customerCountry($company) === '') {
      $this->messenger()->addError($this->t('This customer needs a country before an order can be placed.'));
      return;
    }

    $rows = $this->completeChargeRows($form_state);
    if (!$rows) {
      $this->messenger()->addWarning($this->t('Nothing was ordered: add a description, a quantity and a price.'));
      return;
    }

    $order = OtherCharges::createOrder($company, $rows, (int) $this->currentUser()->id());
    $this->messenger()->addStatus($this->t('Order @number placed with @count lines.', [
      '@number' => $order->label(),
      '@count' => count($rows),
    ]));
    $form_state->setIgnoreDestination();
    $form_state->setRedirect('tec_portal.factory_order', ['tec_order' => $order->id()]);
  }

}
