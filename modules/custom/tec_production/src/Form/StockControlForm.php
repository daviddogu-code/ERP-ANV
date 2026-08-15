<?php

namespace Drupal\tec_production\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tec_production\Purchasing;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stock Control matrix (ex "Stock new" sheet): materials x orders on queue.
 *
 * Rows: every material referenced by the BoM snapshots of the orders that
 * are currently on the production queue. Columns: fixed stock block on the
 * left, then one checkbox+qty pair per queue order (left = next to produce).
 *
 * The checkbox means "material already allocated / prepared for this order".
 * It does NOT move stock: stock only changes through inventory mutations
 * (tec_inventory_transaction, processed by the existing ECA "Stock manager"
 * models). Only unchecked quantities count towards Required / Stock after
 * queue.
 *
 * One balance column, Stock after queue (UoP) = (stock - required/split)/units.
 * It counts only what is in the warehouse, because production cannot cut a roll
 * that is still on a lorry, and it is shown in purchase units because the only
 * reaction to a red number is to order that many.
 *
 * The board carried three balance columns until 15 August 2026: the same figure
 * in inventory units, and a "Projected (UoP)" that added the goods in transit.
 * Both went out on the owner's call: the first because nobody orders in
 * inventory units, the second because the incoming-goods question belongs to
 * /purchase. Git has them if the buying side ever wants them back here.
 *
 * Unit conversions per material (see README):
 *   field_tec_units      = Purchase to Inventory factor (1 UoP = X UoS)
 *   field_tec_split_into = Inventory to Consumption factor (1 UoS = Y UoU)
 */
class StockControlForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected Connection $database;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->database = $container->get('database');
    return $instance;
  }

  public function getFormId() {
    return 'tec_production_stock_control_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $orders = $this->loadQueueOrders();
    $order_ids = array_column($orders, 'id');

    $requirements = $this->loadRequirements($order_ids);

    $terms = $requirements
      ? $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(array_keys($requirements))
      : [];
    uasort($terms, static fn($a, $b) => strnatcasecmp((string) $a->label(), (string) $b->label()));

    $checks = $this->loadChecks($order_ids);
    $incoming_orders = $this->loadIncoming(array_keys($terms));

    $form['#attached']['library'][] = 'tec_production/stock_control';
    // Modal dialog machinery for the ± (mutation with note) links.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['help'] = [
      '#markup' => '<div class="tec-stock__help">'
        . $this->t('One row per material used by the queue. ☐ = material prepared / set aside for that order (does not move stock). Unchecked quantities count in <em>∑ Required</em> and <em>Stock after queue</em>. <em>Stock after queue (UoP)</em> is what stays in the warehouse once the queue takes what it needs, in purchase units: if it is red, that is roughly what you are short of. It counts only what is physically there, not what is already ordered, so check <em>Ordered (UoP)</em> before buying again. Checkboxes save automatically. Click the <em>Stock</em> number to register a +/- mutation, and the order number next to <em>Ordered</em> to say that a delivery has arrived.')
        . '</div>',
    ];

    // El orden lo fijo el dueno el 15 de agosto de 2026, y es el de quien
    // vigila que no falte material: primero lo que hay, luego de que material
    // se trata, luego si ya se pidio, y al final el saldo y lo que se lleva la
    // cola. Las tres primeras van congeladas a la izquierda al desplazarse.
    $header = [
      'stock' => ['data' => $this->t('Stock (UoS)'), 'class' => ['tec-stock__h-num', 'tec-stock__h-stock']],
      'material' => ['data' => $this->t('Material'), 'class' => ['tec-stock__h-mat']],
      'image' => ['data' => $this->t('Image'), 'class' => ['tec-stock__h-img']],
      'supplier' => ['data' => $this->t('Supplier'), 'class' => ['tec-stock__h-sup']],
      'on_order' => ['data' => $this->t('Ordered (UoP)'), 'class' => ['tec-stock__h-num']],
      'after_queue_uop' => ['data' => $this->t('Stock after queue (UoP)'), 'class' => ['tec-stock__h-num']],
      'required' => ['data' => $this->t('∑ Required (UoU)'), 'class' => ['tec-stock__h-num']],
    ];
    foreach ($orders as $order) {
      $oid = $order['id'];
      $header['chk_' . $oid] = [
        'data' => [
          '#markup' => '<input type="checkbox" class="tec-stock__master" data-order="' . $oid . '" title="'
            . $this->t('Toggle the whole @order column', ['@order' => Html::escape($order['label'])]) . '">',
          // #markup is XSS-filtered with Xss::filterAdmin(), which strips
          // <input>; allow it explicitly or the header checkbox vanishes.
          '#allowed_tags' => ['input'],
        ],
        'class' => ['tec-stock__h-chk'],
      ];
      $header['ord_' . $oid] = [
        'data' => [
          '#type' => 'link',
          '#title' => $order['label'],
          '#url' => Url::fromRoute('entity.tec_order.canonical', ['tec_order' => $oid]),
        ],
        'class' => ['tec-stock__h-ord'],
      ];
    }

    $form['matrix'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No materials: the orders on the queue have no BoM items.'),
      '#attributes' => ['class' => ['tec-stock__table']],
      // Google Sheets style scrolling (same pattern as /o/queue): this div is
      // the horizontal scroll container; JS adds the fixed bottom scrollbar
      // and freezes the header while the page scrolls.
      '#prefix' => '<div class="tec-stock__scroll">',
      '#suffix' => '</div>',
    ];

    foreach ($terms as $tid => $term) {
      $per_order = $requirements[$tid]['per_order'] ?? [];

      $stock = $term->hasField('field_tec_stock_level') && !$term->get('field_tec_stock_level')->isEmpty()
        ? (float) $term->get('field_tec_stock_level')->value
        : 0.0;
      $split = $this->factor($term, 'field_tec_split_into');
      $units = $this->factor($term, 'field_tec_units');
      $waiting_on = $incoming_orders[$tid] ?? [];
      $incoming = array_sum(array_column($waiting_on, 'quantity'));

      $uos_name = $this->unitName($term, 'field_tec_uos');
      $uop_name = $this->unitName($term, 'field_tec_unit_purchase');
      $uou_name = $this->unitName($term, 'field_tec_unit_use');

      $required = 0.0;
      foreach ($per_order as $oid => $qty) {
        if (empty($checks[$oid][$tid])) {
          $required += $qty;
        }
      }
      // Lo que queda en el almacen cuando la cola coge lo suyo, contando solo
      // lo que hay fisicamente: la produccion no puede cortar un rollo que
      // todavia esta en un camion, y menos con plazos de entrega de semanas.
      // El saldo se ensena en unidades de compra, que son en las que se pide:
      // un "faltan 6 metros" se escribe tal cual en el pedido, mientras que el
      // saldo en unidades de inventario hay que convertirlo mentalmente.
      $after_queue_uop = ($stock - $required / $split) / $units;

      $row = [
        '#attributes' => [
          'data-tid' => $tid,
          'data-stock' => (string) $stock,
          'data-split' => (string) $split,
          'data-units' => (string) $units,
        ],
      ];

      // Stock is read-only on the board; mutations only through the ± link,
      // which opens the adjust form (number field + note) in a modal.
      $row['stock'] = [
        '#wrapper_attributes' => ['class' => ['tec-stock__c-num', 'tec-stock__c-stock']],
        'value' => [
          '#markup' => '<span class="tec-stock__stock-num"'
            . ($uos_name !== '' ? ' title="' . Html::escape($uos_name) . '"' : '')
            . '>' . $this->fmt($stock) . '</span>',
        ],
        'popup' => [
          '#type' => 'link',
          '#title' => '±',
          '#url' => Url::fromRoute('tec_production.stock_adjust', ['taxonomy_term' => $tid]),
          '#attributes' => [
            'class' => ['use-ajax', 'tec-stock__adjust-link'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['width' => 460]),
            'title' => $this->t('Register a mutation with a note'),
          ],
        ],
      ];

      $row['material'] = [
        '#type' => 'link',
        '#title' => $term->label(),
        '#url' => $term->toUrl(),
        '#wrapper_attributes' => ['class' => ['tec-stock__c-mat']],
      ];

      $row['image'] = $this->imageCell($term) + [
        '#wrapper_attributes' => ['class' => ['tec-stock__c-img']],
      ];

      $vendor = $term->hasField('field_tec_vendor') ? $term->get('field_tec_vendor')->entity : NULL;
      $row['supplier'] = $vendor
        ? [
          '#type' => 'link',
          '#title' => $vendor->label(),
          '#url' => $vendor->toUrl(),
          '#wrapper_attributes' => ['class' => ['tec-stock__c-sup']],
        ]
        : [
          '#markup' => '<span class="tec-stock__muted">—</span>',
          '#wrapper_attributes' => ['class' => ['tec-stock__c-sup']],
        ];

      // The number, and then the orders it is waiting on. Whoever watches this
      // column is the same person who signs for the delivery, so the order they
      // have to open is right here rather than three screens away.
      $row['on_order'] = [
        '#wrapper_attributes' => ['class' => ['tec-stock__c-num']],
        'quantity' => [
          '#markup' => '<span class="' . ($incoming > 0 ? '' : 'tec-stock__muted') . '"'
            . ($uop_name !== '' ? ' title="' . Html::escape($uop_name) . '"' : '') . '>'
            . $this->fmt($incoming) . '</span>',
        ],
      ];
      foreach ($waiting_on as $oid => $entry) {
        $row['on_order']['po_' . $oid] = [
          '#type' => 'link',
          '#title' => $entry['order']->label(),
          '#url' => Url::fromRoute('tec_production.po_receive', ['tec_order' => $oid]),
          '#attributes' => [
            'class' => ['tec-stock__receive'],
            'title' => $this->t('Receive against purchase order @number: @quantity @unit still to come', [
              '@number' => $entry['order']->label(),
              '@quantity' => $this->fmt($entry['quantity']),
              '@unit' => $uop_name !== '' ? $uop_name : $this->t('purchase units'),
            ]),
          ],
        ];
      }

      $row['after_queue_uop'] = [
        '#markup' => '<span class="tec-stock__after-queue-uop' . ($after_queue_uop < -0.000001 ? ' is-neg' : '') . '"'
          . ($uop_name !== '' ? ' title="' . Html::escape($uop_name) . '"' : '') . '>'
          . $this->fmt($after_queue_uop) . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-stock__c-num']],
      ];

      $row['required'] = [
        '#markup' => '<span class="tec-stock__req"'
          . ($uou_name !== '' ? ' title="' . Html::escape($uou_name) . '"' : '') . '>'
          . $this->fmt($required) . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-stock__c-num']],
      ];

      foreach ($orders as $order) {
        $oid = $order['id'];
        $qty = (float) ($per_order[$oid] ?? 0.0);

        $row['chk_' . $oid] = [
          '#type' => 'checkbox',
          '#default_value' => !empty($checks[$oid][$tid]) ? 1 : 0,
          '#attributes' => [
            'class' => ['tec-stock__chk'],
            'data-order' => (string) $oid,
          ],
          '#wrapper_attributes' => ['class' => ['tec-stock__c-chk']],
        ];

        $row['qty_' . $oid] = [
          '#markup' => '<span class="tec-stock__qty' . ($qty > 0 ? ' has-qty' : ' is-zero') . '"'
            . ' data-qty="' . $qty . '" data-order="' . $oid . '"'
            . ($uou_name !== '' ? ' title="' . Html::escape($uou_name) . '"' : '') . '>'
            . $this->fmt($qty) . '</span>',
          '#wrapper_attributes' => ['class' => ['tec-stock__c-qty']],
        ];
      }

      $form['matrix'][$tid] = $row;
    }

    return $form;
  }

  /**
   * No-op: checkboxes auto-save per click via StockCheckController
   * (POST /stock/check). There is no Save button.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Queue orders in production order: first = next to produce.
   *
   * Same statuses and ordering as /o/queue (position asc, bottom of the
   * queue page first).
   */
  protected function loadQueueOrders(): array {
    $storage = $this->entityTypeManager->getStorage('tec_order');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_sales_order')
      ->condition('field_tec_order_status', ProductionQueueForm::ACTIVE_STATUSES, 'IN')
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return [];
    }

    $orders = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      $position = $order->hasField('field_tec_queue_position') && !$order->get('field_tec_queue_position')->isEmpty()
        ? (int) $order->get('field_tec_queue_position')->value
        : PHP_INT_MAX;
      $orders[] = [
        'id' => (int) $order->id(),
        'label' => $order->label() ?: ('#' . $order->id()),
        'position' => $position,
      ];
    }
    usort($orders, static function ($a, $b) {
      if ($a['position'] === $b['position']) {
        return $a['id'] <=> $b['id'];
      }
      return $a['position'] <=> $b['position'];
    });
    return $orders;
  }

  /**
   * Material requirements per order from the line item BoM snapshots.
   *
   * BoM snapshot quantities are already line-item totals (qty x per-piece
   * factor computed when the snapshot was created), so they are summed
   * without further multiplication. Returns:
   *   [tid => ['per_order' => [order_id => qty_in_uou]]]
   */
  protected function loadRequirements(array $order_ids): array {
    $map = [];
    if (!$order_ids) {
      return $map;
    }
    $order_storage = $this->entityTypeManager->getStorage('tec_order');
    foreach ($order_storage->loadMultiple($order_ids) as $order) {
      if (!$order->hasField('field_tec_line_items')) {
        continue;
      }
      $oid = (int) $order->id();
      foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
        if (!$line->hasField('field_tec_line_item_bom')) {
          continue;
        }
        foreach ($line->get('field_tec_line_item_bom')->referencedEntities() as $bom) {
          $material = $bom->hasField('field_tec_inventory') ? $bom->get('field_tec_inventory')->entity : NULL;
          if (!$material) {
            continue;
          }
          $qty = $bom->hasField('field_tec_quantity') && !$bom->get('field_tec_quantity')->isEmpty()
            ? (float) $bom->get('field_tec_quantity')->value
            : 0.0;
          $tid = (int) $material->id();
          $map[$tid]['per_order'][$oid] = ($map[$tid]['per_order'][$oid] ?? 0.0) + $qty;
        }
      }
    }
    return $map;
  }

  /**
   * Saved checkboxes: [order_id][material_tid] => TRUE.
   */
  protected function loadChecks(array $order_ids): array {
    $checks = [];
    if (!$order_ids) {
      return $checks;
    }
    $result = $this->database->select('tec_stock_check', 'c')
      ->fields('c', ['order_id', 'material_tid'])
      ->condition('order_id', $order_ids, 'IN')
      ->execute();
    foreach ($result as $record) {
      $checks[(int) $record->order_id][(int) $record->material_tid] = TRUE;
    }
    return $checks;
  }

  /**
   * Quantity still to come (UoP) per material, from open purchase orders.
   *
   * Shared with the Purchase List (/purchase), which subtracts what is already
   * coming in before suggesting anything. Both screens have to count it the
   * same way or one of them orders the same roll twice. What a line has already
   * delivered stops counting here, which is what makes the Ordered column drop
   * as deliveries arrive instead of standing still forever.
   */
  protected function loadIncoming(array $tids): array {
    return Purchasing::incoming($this->entityTypeManager, $tids);
  }

  /**
   * Conversion factor with a safe fallback of 1.
   */
  protected function factor($term, string $field): float {
    return Purchasing::factor($term, $field);
  }

  /**
   * Referenced unit term label ('' when not set).
   */
  protected function unitName($term, string $field): string {
    if ($term->hasField($field) && !$term->get($field)->isEmpty()) {
      $unit = $term->get($field)->entity;
      if ($unit) {
        return (string) $unit->label();
      }
    }
    return '';
  }

  /**
   * 40x40 material thumbnail.
   */
  protected function imageCell($term): array {
    if (!$term->hasField('field_tec_inventory_image') || $term->get('field_tec_inventory_image')->isEmpty()) {
      return ['#markup' => ''];
    }
    $file = $term->get('field_tec_inventory_image')->entity;
    if (!$file) {
      return ['#markup' => ''];
    }
    return [
      '#theme' => 'image_style',
      '#style_name' => 'small_40x40',
      '#uri' => $file->getFileUri(),
      '#attributes' => ['loading' => 'lazy', 'class' => ['tec-stock__img']],
    ];
  }

  /**
   * Format a number: up to 2 decimals, no trailing zeros.
   */
  protected function fmt(float $number): string {
    $formatted = number_format($number, 2, '.', ',');
    if (str_contains($formatted, '.')) {
      $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    return $formatted === '-0' ? '0' : $formatted;
  }

}
