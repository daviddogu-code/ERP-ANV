<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\Catalogue;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\OrderLineGrid;
use Drupal\tec_portal\PortalLineTable;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_production\Vat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One sales order on /my/order/{id}: quantities while Open, then Confirm.
 *
 * While Open the grid is the catalogue, like /my/order/new, so sizes left
 * at 0 can still be filled in. Confirming drops those zeros, sends the
 * order to the factory, and takes the pen away. Processing stays
 * a factory click after the deposit is seen.
 */
class OrderForm extends FormBase {

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
    return 'tec_portal_order_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $tec_order = NULL) {
    $id = (int) ($form_state->get('order_id') ?: ($tec_order ? $tec_order->id() : 0));
    if ($id) {
      $loaded = $this->entityTypeManager->getStorage('tec_order')->load($id);
      if ($loaded) {
        $tec_order = $loaded;
      }
    }
    if (!$tec_order || $tec_order->getEntityTypeId() !== 'tec_order') {
      throw new NotFoundHttpException();
    }

    $form_state->set('order_id', (int) $tec_order->id());
    $open = PortalOrder::isOpen($tec_order);
    $lines = $this->linesOf($tec_order);

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'tec-portal';
    $form['#attributes']['class'][] = 'tec-portal--place';
    $form['#attached']['library'][] = 'tec_portal/portal';

    $form['back'] = $this->backLink($tec_order);

    $form['meta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-portal__meta']],
      'number' => [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => (string) $tec_order->label(),
        '#attributes' => ['class' => ['tec-portal__order-no']],
      ],
      'status' => $this->statusElement($tec_order),
    ];
    if (PortalOrder::hasProforma($tec_order)) {
      $form['meta']['proforma'] = [
        '#type' => 'link',
        '#title' => $this->t('Print Proforma'),
        '#url' => Url::fromUri('internal:' . PortalOrder::proformaPath($tec_order)),
        '#attributes' => [
          'class' => ['button', 'tec-portal__proforma'],
          'target' => '_blank',
          'title' => $this->t('Print Proforma'),
        ],
      ];
    }

    if ($open) {
      $form['help'] = [
        '#markup' => '<div class="tec-portal__help">' . $this->openHelp() . '</div>',
      ];
      $company = $this->companyOf($tec_order);
      $grid = $company ? $this->catalogue->gridForCompany($company) : [];
      $qty_by_size = $this->qtyBySize($lines);
      $allowed = [];
      foreach ($grid as $brand) {
        foreach ($brand['rows'] as $row) {
          $allowed[] = $row['size_id'];
        }
      }
      $form_state->set('allowed_sizes', $allowed);
      if (!$grid) {
        $form['empty'] = [
          '#type' => 'table',
          '#header' => $this->lineTableHeader(),
          '#empty' => $this->t('This order has no lines.'),
          '#attributes' => ['class' => ['tec-portal__table']],
        ];
      }
      else {
        $rate = $this->vatRateFromOrder($tec_order) ?? $this->vatRateFromContact($company);
        $this->attachVatSettings($form, $rate);
        $form['lines'] = $this->catalogueLineTable($grid, $qty_by_size, $rate);
      }
      if ($grid) {
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['save'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save quantities'),
          '#submit' => ['::submitSave'],
        ];
        $form['actions']['confirm'] = [
          '#type' => 'submit',
          '#value' => $this->t('Confirm order'),
          '#button_type' => 'primary',
          '#submit' => ['::submitConfirm'],
          '#attributes' => ['class' => ['tec-portal__confirm']],
        ];
      }
      return $form;
    }

    $waiting_pay = PortalOrder::statusOf($tec_order) === PortalOrder::PENDING_DEPOSIT;
    $form['locked'] = [
      '#markup' => '<div class="tec-portal__nothing">'
        . ($waiting_pay
          ? $this->t('This order can no longer be changed. The order will be processed <strong>after payment is received</strong>.')
          : $this->t('This order can no longer be changed.'))
        . '</div>',
      '#allowed_tags' => ['div', 'strong'],
    ];

    $rate = $this->vatRateFromOrder($tec_order);
    $this->attachVatSettings($form, $rate);
    $form['lines'] = OrderLineGrid::create($this->entityTypeManager)->table($tec_order);

    return $form;
  }

  /**
   * Saves quantities and stays on the order.
   */
  public function submitSave(array &$form, FormStateInterface $form_state) {
    $order = $this->applyQuantities($form_state);
    if (!$order) {
      return;
    }
    $this->messenger()->addStatus($this->t('Quantities saved.'));
    $this->redirectToOrder($form_state, $order);
  }

  /**
   * Saves quantities, drops zeros, then seals the order.
   */
  public function submitConfirm(array &$form, FormStateInterface $form_state) {
    $order = $this->applyQuantities($form_state);
    if (!$order) {
      return;
    }
    $pieces = 0;
    foreach ($this->linesOf($order) as $line) {
      $pieces += $this->qtyOf($line);
    }
    if ($pieces < 1) {
      $this->messenger()->addWarning($this->t('Nothing to confirm: every quantity is 0.'));
      return;
    }
    $company = $this->companyOf($order);
    if (!$company || Vat::customerCountry($company) === '') {
      $this->messenger()->addError($this->t('This customer needs a country before the order can be confirmed.'));
      return;
    }
    $order->set('field_tec_order_status', PortalOrder::PENDING_DEPOSIT);
    $order->save();
    $this->messenger()->addStatus($this->t('Order @number confirmed. It can no longer be changed from here.', [
      '@number' => $order->label(),
    ]));
    $this->redirectToOrder($form_state, $order);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Named submit handlers do the work.
  }

  /**
   * Upserts catalogue sizes onto the open order. Quantity 0 removes the line.
   */
  protected function applyQuantities(FormStateInterface $form_state) {
    $order = $this->entityTypeManager->getStorage('tec_order')->load($form_state->get('order_id'));
    if (!$order || $order->bundle() !== 'tec_sales_order') {
      $this->messenger()->addError($this->t('This order is gone.'));
      return NULL;
    }
    $account = $this->currentUser();
    if (!$this->mayEditOpen($account, $order)) {
      $this->messenger()->addWarning($this->t('This order can no longer be changed.'));
      return NULL;
    }

    $allowed = array_map('intval', $form_state->get('allowed_sizes') ?: []);
    $posted = $this->postedQtyBySize($form_state);
    $existing = $this->lineBySize($this->linesOf($order));
    $keep = [];
    $drop = [];
    $created = [];

    foreach ($allowed as $sid) {
      $qty = max(0, (int) ($posted[$sid] ?? 0));
      $line = $existing[$sid] ?? NULL;
      if ($line) {
        if ($qty > 0) {
          $price = $this->priceOf($line);
          $line->set('field_tec_quantity', $qty);
          $line->set('field_tec_line_item_total_number', number_format($qty * $price, 2, '.', ''));
          $line->save();
          $keep[] = (int) $line->id();
        }
        else {
          $drop[] = $line;
        }
      }
      elseif ($qty > 0) {
        $company = $this->companyOf($order);
        $row = $company ? $this->catalogue->rowForSizeOnCompany($company, $sid) : NULL;
        if (!$row) {
          continue;
        }
        $line = $this->createLine($row, $qty, (int) $account->id());
        $keep[] = (int) $line->id();
        $created[] = $line;
      }
    }

    foreach ($this->linesOf($order) as $line) {
      $sid = $this->sizeIdOf($line);
      if ($sid && !in_array($sid, $allowed, TRUE)) {
        $keep[] = (int) $line->id();
      }
    }

    $order->set('field_tec_line_items', array_values(array_unique($keep)));
    $order->save();

    foreach ($created as $line) {
      $line->set('field_tec_order', $order->id());
      $line->save();
    }
    foreach ($drop as $line) {
      $line->delete();
    }

    return $this->entityTypeManager->getStorage('tec_order')->loadUnchanged($order->id()) ?: $order;
  }

  /**
   * Posted quantities keyed by size variation id.
   *
   * @return array<int, int>
   */
  protected function postedQtyBySize(FormStateInterface $form_state): array {
    $posted = [];
    foreach ($form_state->getValue('lines') ?: [] as $sid => $row) {
      if (is_array($row) && array_key_exists('qty', $row)) {
        $posted[(int) $sid] = (int) $row['qty'];
      }
    }
    return $posted;
  }

  /**
   * Lines keyed by size variation id.
   *
   * @return array<int, object>
   */
  protected function lineBySize(array $lines): array {
    $map = [];
    foreach ($lines as $line) {
      $sid = $this->sizeIdOf($line);
      if ($sid) {
        $map[$sid] = $line;
      }
    }
    return $map;
  }

  /**
   * Quantities keyed by size variation id.
   *
   * @return array<int, int>
   */
  protected function qtyBySize(array $lines): array {
    $map = [];
    foreach ($this->lineBySize($lines) as $sid => $line) {
      $map[$sid] = (int) $this->qtyOf($line);
    }
    return $map;
  }

  /**
   * A new sales line from a catalogue row. The order is attached after.
   */
  protected function createLine(array $row, int $quantity, int $uid) {
    $price = (float) $row['price'];
    $title = trim($row['product'] . ' / ' . $row['colour'] . ' / ' . $row['size'], ' /');
    $line = $this->entityTypeManager->getStorage('tec_line_item')->create([
      'type' => 'tec_sales_order_line_item',
      'title' => $title,
      'field_tec_product' => $row['product_id'],
      'field_tec_color_variation' => $row['colour_id'],
      'field_tec_size_variation' => $row['size_id'],
      'field_tec_quantity' => $quantity,
      'field_tec_price' => number_format($price, 2, '.', ''),
      'field_tec_custom_sales_price' => TRUE,
      'field_tec_line_item_total_number' => number_format($quantity * $price, 2, '.', ''),
      'uid' => $uid,
    ]);
    $line->save();
    return $line;
  }

  /**
   * Lines on an order, in the order they were added.
   */
  protected function linesOf($order): array {
    if (!$order->hasField('field_tec_line_items') || $order->get('field_tec_line_items')->isEmpty()) {
      return [];
    }
    return $order->get('field_tec_line_items')->referencedEntities();
  }

  protected function qtyOf($line): float {
    if (!$line->hasField('field_tec_quantity') || $line->get('field_tec_quantity')->isEmpty()) {
      return 0;
    }
    return (float) $line->get('field_tec_quantity')->value;
  }

  protected function priceOf($line): float {
    if (!$line->hasField('field_tec_price') || $line->get('field_tec_price')->isEmpty()) {
      return 0;
    }
    return (float) $line->get('field_tec_price')->value;
  }

  protected function totalOf($line, float $qty, float $price): float {
    if ($line->hasField('field_tec_line_item_total_number') && !$line->get('field_tec_line_item_total_number')->isEmpty()) {
      return (float) $line->get('field_tec_line_item_total_number')->value;
    }
    return $qty * $price;
  }

  protected function sizeIdOf($line): int {
    $size = $this->sizeOf($line);
    return $size ? (int) $size->id() : 0;
  }

  protected function productOf($line): ?EntityInterface {
    return $this->loadProductRef($line, 'field_tec_product');
  }

  protected function sizeOf($line): ?EntityInterface {
    return $this->loadProductRef($line, 'field_tec_size_variation');
  }

  protected function colourOf($line): ?EntityInterface {
    $colour = $this->loadProductRef($line, 'field_tec_color_variation');
    if ($colour) {
      return $colour;
    }
    $size = $this->sizeOf($line);
    return $size ? $this->loadProductRef($size, 'field_tec_color_variation') : NULL;
  }

  protected function loadProductRef($entity, string $field): ?EntityInterface {
    if (!$entity || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return NULL;
    }
    $id = (int) $entity->get($field)->target_id;
    if ($id < 1) {
      return NULL;
    }
    $got = $this->entityTypeManager->getStorage('tec_product')->load($id);
    return $got ?: NULL;
  }

  /**
   * Status pill: customer words on /my, factory words on /o/order.
   */
  protected function statusElement($order): array {
    return [
      '#markup' => PortalOrder::statusMarkup($order),
      '#allowed_tags' => ['span'],
    ];
  }

  /**
   * Back to the list this person came from.
   */
  protected function backLink($order): array {
    return [
      '#type' => 'link',
      '#title' => $this->t('Back to my orders'),
      '#url' => Url::fromRoute('tec_portal.home'),
      '#attributes' => ['class' => ['tec-portal__back']],
    ];
  }

  /**
   * Shown while the order is still Open.
   */
  protected function openHelp() {
    return $this->t('You can add or change quantities until you confirm. Confirming sends the order to the factory and drops sizes left at 0. It cannot be undone from here.');
  }

  /**
   * Route of this screen after Save or Confirm.
   */
  protected function orderViewRoute(): string {
    return 'tec_portal.order';
  }

  /**
   * Stays on this order. Destination in the URL must not yank the factory away.
   */
  protected function redirectToOrder(FormStateInterface $form_state, $order): void {
    $form_state->setIgnoreDestination();
    $form_state->setRedirect($this->orderViewRoute(), ['tec_order' => $order->id()]);
  }

  /**
   * Portal customer or factory staff, Open, and allowed to see the order.
   */
  protected function mayEditOpen($account, $order): bool {
    if (!PortalOrder::isOpen($order) || !$order->access('view', $account)) {
      return FALSE;
    }
    return $this->companies->isPortalCustomer($account) || $this->companies->isFactory($account);
  }

  /**
   * Company card stamped on the order, or NULL.
   */
  protected function companyOf($order): ?EntityInterface {
    $ids = $this->companies->orderCompanyIds($order);
    $id = (int) ($ids[0] ?? 0);
    if ($id < 1) {
      return NULL;
    }
    $company = $this->entityTypeManager->getStorage('tec_crm')->load($id);
    return $company && $company->bundle() === 'tec_contact_organization' ? $company : NULL;
  }

  protected function productName($product): string {
    if (!$product) {
      return '';
    }
    if ($product->hasField('field_product_name')) {
      $name = trim((string) $product->get('field_product_name')->value);
      if ($name !== '') {
        return $name;
      }
    }
    return (string) $product->label();
  }

}
