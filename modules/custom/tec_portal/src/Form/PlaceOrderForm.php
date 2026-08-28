<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\Catalogue;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\PortalLineTable;
use Drupal\tec_production\OrderNumber;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Place an order (/my/order/new): quantities against this company's catalogue.
 *
 * Same shape as /purchase: a PHP grid, one submit, and the order exists when
 * the button is pressed. The factory + Order flag is left alone. Lines with
 * quantity 0 are not written. The company on the order is taken from the
 * session, never from the form.
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
    $account = $this->currentUser();
    $grid = $this->catalogue->grid($account);
    $allowed = [];
    foreach ($grid as $brand) {
      foreach ($brand['rows'] as $row) {
        $allowed[] = $row['size_id'];
      }
    }
    $form_state->set('allowed_sizes', $allowed);

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'tec-portal';
    $form['#attributes']['class'][] = 'tec-portal--place';
    $form['#attached']['library'][] = 'tec_portal/portal';

    $form['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to my orders'),
      '#url' => Url::fromRoute('tec_portal.home'),
      '#attributes' => ['class' => ['tec-portal__back']],
    ];

    $form['help'] = [
      '#markup' => '<div class="tec-portal__help">'
        . $this->t('Type a quantity next to each size you want. Sizes left at 0 are not ordered. The order is placed when you press the button.')
        . '</div>',
    ];

    if (!$grid) {
      $form['nothing'] = [
        '#markup' => '<div class="tec-portal__nothing">'
        . $this->t('Nothing to order yet. The factory has not put sizes on your brands.')
        . '</div>',
      ];
      return $form;
    }

    foreach ($grid as $brand) {
      $form['b' . $brand['id']] = $this->catalogueBrandGroup($brand);
    }

    if (count($grid) > 1) {
      $form['grand'] = $this->grandTotalTable();
    }

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
    $allowed = array_map('intval', $form_state->get('allowed_sizes') ?: []);
    $wanted = [];
    $values = $form_state->getValues();
    foreach ($allowed as $sid) {
      $qty = 0;
      foreach ($values as $key => $group) {
        if (!is_string($key) || !str_starts_with($key, 'b') || !is_array($group)) {
          continue;
        }
        $got = $group['lines'][$sid]['qty'] ?? $group['lines'][(string) $sid]['qty'] ?? NULL;
        if ($got !== NULL) {
          $qty = (int) $got;
          break;
        }
      }
      if ($qty > 0) {
        $wanted[$sid] = $qty;
      }
    }

    if (!$wanted) {
      $this->messenger()->addWarning($this->t('Nothing was ordered: every quantity was 0.'));
      return;
    }

    $order = $this->createSalesOrder($wanted);
    $this->messenger()->addStatus($this->t('Order @number placed with @count lines.', [
      '@number' => $order->label(),
      '@count' => count($wanted),
    ]));
    $form_state->setRedirect('tec_portal.order', ['tec_order' => $order->id()]);
  }

  /**
   * Writes the sales order and its lines for the logged-in company.
   *
   * @param array<int, int> $wanted
   *   Quantity keyed by size variation id.
   */
  protected function createSalesOrder(array $wanted) {
    $account = $this->currentUser();
    $company_id = $this->companies->id($account);
    if ($company_id === NULL) {
      throw new \RuntimeException('Portal order without a company.');
    }

    $line_storage = $this->entityTypeManager->getStorage('tec_line_item');
    $lines = [];
    foreach ($wanted as $size_id => $quantity) {
      $row = $this->catalogue->rowForSize($account, $size_id);
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
