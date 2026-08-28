<?php

namespace Drupal\tec_portal\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\Catalogue;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\PortalLineTable;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_production\LineItemDisplay;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One sales order on /my/order/{id}: quantities while Open, then Confirm.
 *
 * While Open the grid is the catalogue, like /my/order/new, so sizes left
 * at 0 can still be filled in. Confirming drops those zeros, sends the
 * order to the factory, and takes the pen away. Accounting Verified stays
 * a factory click.
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
    $account = $this->currentUser();

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

    $form['meta'] = [
      '#markup' => '<div class="tec-portal__meta">'
        . PortalOrder::statusMarkup($tec_order)
        . '</div>',
      '#allowed_tags' => ['div', 'span'],
    ];

    if ($open) {
      $form['help'] = [
        '#markup' => '<div class="tec-portal__help">'
        . $this->t('You can add or change quantities until you confirm. Confirming sends the order to the factory and drops sizes left at 0. It cannot be undone from here.')
        . '</div>',
      ];
      $grid = $this->catalogue->grid($account);
      $qty_by_size = $this->qtyBySize($lines);
      $allowed = [];
      $all_qty = 0.0;
      $all_money = 0.0;
      foreach ($grid as $brand) {
        foreach ($brand['rows'] as $row) {
          $allowed[] = $row['size_id'];
          $q = (int) ($qty_by_size[$row['size_id']] ?? 0);
          $all_qty += $q;
          $all_money += $q * (float) $row['price'];
        }
        $form['b' . $brand['id']] = $this->catalogueBrandGroup($brand, $qty_by_size);
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
      elseif (count($grid) > 1) {
        $form['grand'] = $this->grandTotalTable($all_qty, $all_money);
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

    $groups = $this->brandGroups($lines);
    if (!$groups) {
      $form['empty'] = [
        '#type' => 'table',
        '#header' => $this->lineTableHeader(),
        '#empty' => $this->t('This order has no lines.'),
        '#attributes' => ['class' => ['tec-portal__table']],
      ];
    }
    else {
      $all_qty = 0.0;
      $all_money = 0.0;
      foreach ($groups as $group) {
        $all_qty += $group['qty'];
        $all_money += $group['amount'];
        $form['b' . $group['id']] = $this->orderedBrandGroup($group);
      }
      if (count($groups) > 1) {
        $form['grand'] = $this->grandTotalTable($all_qty, $all_money);
      }
    }

    return $form;
  }

  /**
   * One brand's saved lines (confirmed orders: no zeros).
   */
  protected function orderedBrandGroup(array $group): array {
    $element = [
      '#type' => 'fieldset',
      '#title' => $group['name'],
      '#attributes' => ['class' => ['tec-portal__group']],
    ];
    $element['lines'] = [
      '#type' => 'table',
      '#header' => $this->lineTableHeader(),
      '#attributes' => ['class' => ['tec-portal__table']],
      '#footer' => [$this->totalsFooter(FALSE, $group['qty'], $group['amount'])],
    ];

    foreach ($group['lines'] as $line) {
      $lid = (int) $line->id();
      $qty = $this->qtyOf($line);
      $price = $this->priceOf($line);
      $total = $this->totalOf($line, $qty, $price);
      $product = $this->productOf($line);
      $size = $this->sizeOf($line);
      $colour = $this->colourOf($line);
      $element['lines'][$lid] = [
        '#attributes' => [
          'data-price' => number_format($price, 2, '.', ''),
          'data-qty' => (string) (int) $qty,
        ],
        'image' => $this->imageCell($colour),
        'product' => ['#plain_text' => $this->productName($product) ?: (string) $line->label()],
        'material' => ['#plain_text' => LineItemDisplay::materialLabel($product)],
        'colour' => ['#plain_text' => LineItemDisplay::colorLabel($colour)],
        'size' => ['#plain_text' => LineItemDisplay::sizeLabel($size)],
        'qty' => [
          '#plain_text' => number_format($qty, 0),
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-qty']],
        ],
        'price' => [
          '#markup' => Html::escape($this->money($price)),
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-price']],
        ],
        'item_total' => [
          '#markup' => '<span class="tec-portal__item-total">' . Html::escape($this->money($total)) . '</span>',
          '#wrapper_attributes' => ['class' => ['tec-portal__num', 'tec-portal__col-total']],
          '#allowed_tags' => ['span'],
        ],
      ];
    }

    return $element;
  }

  /**
   * Saved lines with quantity, grouped by brand.
   *
   * @return array<int, array{id: int, name: string, lines: array, qty: float, amount: float}>
   */
  protected function brandGroups(array $lines): array {
    $buckets = [];
    foreach ($this->companies->brandIds($this->currentUser()) as $tid) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      $buckets[$tid] = [
        'id' => $tid,
        'name' => $term ? (string) $term->label() : (string) $this->t('Brand'),
        'lines' => [],
        'qty' => 0.0,
        'amount' => 0.0,
      ];
    }
    $extra = [];
    $other = [
      'id' => 0,
      'name' => (string) $this->t('Other'),
      'lines' => [],
      'qty' => 0.0,
      'amount' => 0.0,
    ];

    foreach ($lines as $line) {
      $qty = $this->qtyOf($line);
      if ($qty < 1) {
        continue;
      }
      $price = $this->priceOf($line);
      $amount = $this->totalOf($line, $qty, $price);
      $product = $this->productOf($line);
      $bid = $product ? $this->companies->productBrandId($product) : 0;
      if ($bid && isset($buckets[$bid])) {
        $buckets[$bid]['lines'][] = $line;
        $buckets[$bid]['qty'] += $qty;
        $buckets[$bid]['amount'] += $amount;
      }
      elseif ($bid) {
        if (!isset($extra[$bid])) {
          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($bid);
          $extra[$bid] = [
            'id' => $bid,
            'name' => $term ? (string) $term->label() : (string) $this->t('Brand'),
            'lines' => [],
            'qty' => 0.0,
            'amount' => 0.0,
          ];
        }
        $extra[$bid]['lines'][] = $line;
        $extra[$bid]['qty'] += $qty;
        $extra[$bid]['amount'] += $amount;
      }
      else {
        $other['lines'][] = $line;
        $other['qty'] += $qty;
        $other['amount'] += $amount;
      }
    }

    $groups = [];
    foreach ($buckets as $group) {
      if ($group['lines']) {
        $groups[] = $group;
      }
    }
    foreach ($extra as $group) {
      $groups[] = $group;
    }
    if ($other['lines']) {
      $groups[] = $other;
    }
    return $groups;
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
    $form_state->setRedirect('tec_portal.order', ['tec_order' => $order->id()]);
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
    $order->set('field_tec_order_status', PortalOrder::PENDING_DEPOSIT);
    $order->save();
    $this->messenger()->addStatus($this->t('Order @number confirmed. It can no longer be changed from here.', [
      '@number' => $order->label(),
    ]));
    $form_state->setRedirect('tec_portal.order', ['tec_order' => $order->id()]);
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
    if (!$this->companies->isPortalCustomer($account) || !$order->access('view', $account) || !PortalOrder::isOpen($order)) {
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
        $row = $this->catalogue->rowForSize($account, $sid);
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
    foreach ($form_state->getValues() as $key => $group) {
      if (!is_string($key) || !str_starts_with($key, 'b') || !is_array($group)) {
        continue;
      }
      foreach ($group['lines'] ?? [] as $sid => $row) {
        if (is_array($row) && array_key_exists('qty', $row)) {
          $posted[(int) $sid] = (int) $row['qty'];
        }
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
