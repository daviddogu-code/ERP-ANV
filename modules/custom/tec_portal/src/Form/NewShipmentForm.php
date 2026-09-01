<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\Shipment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Create a shipment for a customer (/customer/{id}/ship/new).
 *
 * Date, sold-to and ship-to. Quantities are the next screen.
 */
class NewShipmentForm extends FormBase {

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
    return 'tec_portal_new_shipment_form';
  }

  /**
   * Factory staff who can see this organisation. Not a portal customer.
   */
  public static function access(AccountInterface $account, $tec_crm = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $result = AccessResult::allowedIf($companies->isFactory($account) && Shipment::typesExist())
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

    $form['#attributes']['class'][] = 'tec-portal';
    $form['#attached']['library'][] = 'tec_portal/shipment_form';

    $form['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to @company', ['@company' => $tec_crm->label()]),
      '#url' => $tec_crm->toUrl(),
      '#attributes' => ['class' => ['tec-portal__back']],
    ];

    $form['help'] = [
      '#markup' => '<div class="tec-portal__help">'
        . $this->t('This shipment can take quantities from more than one confirmed sales order of @company. EXW. Sold to and ship to default to this customer.', [
          '@company' => $tec_crm->label(),
        ])
        . '</div>',
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $selection = [
      'target_bundles' => ['tec_contact_organization'],
    ];
    $form['sold_to'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Sold to'),
      '#target_type' => 'tec_crm',
      '#selection_handler' => 'default:tec_crm',
      '#selection_settings' => $selection,
      '#default_value' => $tec_crm,
      '#required' => TRUE,
    ];
    $form['ship_to'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Ship to'),
      '#target_type' => 'tec_crm',
      '#selection_handler' => 'default:tec_crm',
      '#selection_settings' => $selection,
      '#default_value' => $tec_crm,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create shipment'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach (['sold_to', 'ship_to'] as $key) {
      $id = (int) $form_state->getValue($key);
      $company = $id ? $this->entityTypeManager->getStorage('tec_crm')->load($id) : NULL;
      if (!$company || $company->bundle() !== 'tec_contact_organization') {
        $form_state->setErrorByName($key, $this->t('Choose an organisation.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company_id = (int) $form_state->get('company_id');
    $company = $this->entityTypeManager->getStorage('tec_crm')->load($company_id);
    if (!$company || $company->bundle() !== 'tec_contact_organization') {
      $form_state->setErrorByName('date', $this->t('This company is missing.'));
      return;
    }
    $shipment = $this->entityTypeManager->getStorage(Shipment::TYPE)->create([
      'type' => Shipment::BUNDLE,
      'title' => 'SHIP',
      Shipment::CUSTOMER_FIELD => $company_id,
      Shipment::DATE_FIELD => $form_state->getValue('date'),
      Shipment::INCOTERMS_FIELD => Shipment::INCOTERM,
      Shipment::SOLD_TO_FIELD => (int) $form_state->getValue('sold_to'),
      Shipment::SHIP_TO_FIELD => (int) $form_state->getValue('ship_to'),
    ]);
    $shipment->save();
    Shipment::stampTitle($shipment);
    $form_state->setIgnoreDestination();
    $form_state->setRedirect('tec_portal.shipment', ['tec_shipment' => $shipment->id()]);
  }

}
