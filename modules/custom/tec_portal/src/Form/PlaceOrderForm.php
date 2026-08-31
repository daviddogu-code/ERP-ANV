<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\Catalogue;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\PortalLineTable;
use Drupal\tec_production\OrderNumber;
use Drupal\tec_production\Vat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Place an order (/my/order/new): quantities against this company's catalogue.
 *
 * Same shape as /purchase: a PHP grid, one submit, and the order exists when
 * the button is pressed. Lines with quantity 0 are not written. The company
 * on the order is taken from the session, never from the form.
 */
class PlaceOrderForm extends FormBase {

  use PortalLineTable;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomerCompany $companies,
    protected Catalogue $catalogue,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tec_portal.company'),
      $container->get('tec_portal.catalogue'),
    );
  }

  public function getFormId() {
    return 'tec_portal_place_order_form';
  }

  /**
   * Portal customers with a company. Nobody else.
   */
  public static function access(AccountInterface $account) {
    $companies = \Drupal::service('tec_portal.company');
    return AccessResult::allowedIf($companies->isPortalCustomer($account) && $companies->id($account) !== NULL)
      ->addCacheContexts(['user']);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $company = $this->companies->company($this->currentUser());
    if (!$company) {
      $form['nothing'] = [
        '#markup' => '<div class="tec-portal__nothing">'
        . $this->t('This login is not linked to a company.')
        . '</div>',
      ];
      return $form;
    }
    return $this->buildGridForm($form, $form_state, $company, [
      '#type' => 'link',
      '#title' => $this->t('Back to my orders'),
      '#url' => Url::fromRoute('tec_portal.home'),
      '#attributes' => ['class' => ['tec-portal__back']],
    ], $this->t('Type a quantity next to each size you want. Sizes left at 0 are not ordered. The order is placed when you press the button.'));
  }

  /**
   * Catalogue table, Place order, company stamped on form state.
   */
  protected function buildGridForm(array $form, FormStateInterface $form_state, EntityInterface $company, array $back, $help): array {
    $grid = $this->catalogue->gridForCompany($company);
    $allowed = [];
    foreach ($grid as $brand) {
      foreach ($brand['rows'] as $row) {
        $allowed[] = $row['size_id'];
      }
    }
    $form_state->set('allowed_sizes', $allowed);
    $form_state->set('company_id', (int) $company->id());

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'tec-portal';
    $form['#attributes']['class'][] = 'tec-portal--place';
    $form['#attached']['library'][] = 'tec_portal/portal';

    $rate = $this->vatRateFromContact($company);
    $this->attachVatSettings($form, $rate);

    $form['back'] = $back;
    $form['help'] = [
      '#markup' => '<div class="tec-portal__help">' . $help . '</div>',
    ];

    if (!$grid) {
      $form['nothing'] = [
        '#markup' => '<div class="tec-portal__nothing">'
        . $this->t('Nothing to order yet. The factory has not put sizes on your brands.')
        . '</div>',
      ];
      return $form;
    }

    $form['lines'] = $this->catalogueLineTable($grid, [], $rate);
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Place order'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company = $this->companyForSubmit($form_state);
    if (!$company) {
      $this->messenger()->addError($this->t('This order has no company.'));
      return;
    }

    $allowed = array_map('intval', $form_state->get('allowed_sizes') ?: []);
    $wanted = [];
    $posted = $form_state->getValue('lines') ?: [];
    foreach ($allowed as $sid) {
      $qty = (int) ($posted[$sid]['qty'] ?? $posted[(string) $sid]['qty'] ?? 0);
      if ($qty > 0) {
        $wanted[$sid] = $qty;
      }
    }

    if (!$wanted) {
      $this->messenger()->addWarning($this->t('Nothing was ordered: every quantity was 0.'));
      return;
    }

    if (Vat::customerCountry($company) === '') {
      $this->messenger()->addError($this->t('This customer needs a country before an order can be placed.'));
      return;
    }

    $order = $this->createSalesOrder($wanted, $company);
    $this->messenger()->addStatus($this->t('Order @number placed with @count lines.', [
      '@number' => $order->label(),
      '@count' => count($wanted),
    ]));
    $this->afterPlace($form_state, $order, $company);
  }

  /**
   * Company for this submit. Portal: the login. Factory overrides this.
   */
  protected function companyForSubmit(FormStateInterface $form_state): ?EntityInterface {
    $id = (int) $form_state->get('company_id');
    $mine = $this->companies->id($this->currentUser());
    if (!$id || $mine !== $id) {
      return NULL;
    }
    $company = $this->entityTypeManager->getStorage('tec_crm')->load($id);
    return $company && $company->bundle() === 'tec_contact_organization' ? $company : NULL;
  }

  /**
   * After a successful place. Portal stays on /my/order/{id}.
   */
  protected function afterPlace(FormStateInterface $form_state, $order, EntityInterface $company): void {
    $form_state->setRedirect('tec_portal.order', ['tec_order' => $order->id()]);
  }

  /**
   * Writes the sales order and its lines for one company.
   *
   * @param array<int, int> $wanted
   *   Quantity keyed by size variation id.
   */
  protected function createSalesOrder(array $wanted, EntityInterface $company) {
    $account = $this->currentUser();
    $company_id = (int) $company->id();
    $line_storage = $this->entityTypeManager->getStorage('tec_line_item');
    $lines = [];
    foreach ($wanted as $size_id => $quantity) {
      $row = $this->catalogue->rowForSizeOnCompany($company, $size_id);
      if (!$row) {
        continue;
      }
      $price = $row['price'];
      $title = trim($row['product'] . ' / ' . $row['colour'] . ' / ' . $row['size'], ' /');
      $line = $line_storage->create([
        'type' => 'tec_sales_order_line_item',
        'title' => $title,
        'field_tec_product' => $row['product_id'],
        'field_tec_color_variation' => $row['colour_id'],
        'field_tec_size_variation' => $row['size_id'],
        'field_tec_quantity' => $quantity,
        'field_tec_price' => number_format($price, 2, '.', ''),
        // Catalogue price is already on the size. Factory ECA
        // process_xra7tma otherwise looks for a product-level sales
        // price that this catalogue does not use, and warns.
        'field_tec_custom_sales_price' => TRUE,
        'field_tec_line_item_total_number' => number_format($quantity * $price, 2, '.', ''),
        'uid' => $account->id(),
      ]);
      $line->save();
      $lines[] = $line;
    }

    if (!$lines) {
      throw new \RuntimeException('Portal order had no lines after filtering.');
    }

    $order_storage = $this->entityTypeManager->getStorage('tec_order');
    $order = $order_storage->create([
      'type' => 'tec_sales_order',
      'field_tec_customer' => $company_id,
      'field_tec_line_items' => array_map(static fn($line) => $line->id(), $lines),
      'field_tec_order_status' => 'draft',
      'status' => 1,
      'uid' => $account->id(),
    ]);
    OrderNumber::earn($order);
    $order->save();

    foreach ($lines as $line) {
      $line->set('field_tec_order', $order->id());
      $line->save();
    }

    return $order;
  }

}
