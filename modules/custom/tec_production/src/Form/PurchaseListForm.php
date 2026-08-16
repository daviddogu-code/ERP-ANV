<?php

namespace Drupal\tec_production\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tec_production\Purchasing;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Purchase List (/purchase): what to buy right now, grouped by supplier.
 *
 * A material shows up here when what is available stops covering its reorder
 * point. Available is stock plus whatever is already coming in on open purchase
 * orders, so something ordered this morning does not get ordered again this
 * afternoon.
 *
 * Which unit each number is in (see docs/material_inventory_naming_matrix.md):
 *   - Stock, reorder point and safety stock are inventory units (UoS).
 *   - Purchases, MOQ and cost are purchase units (UoP).
 *   - field_tec_units (Purchase to Inventory Factor) converts one to the other.
 *
 * The suggested quantity closes the gap up to reorder point + safety stock,
 * rounded up to whole purchase units, and never below the MOQ. Stopping at the
 * reorder point instead would leave the material on the edge and it would come
 * back on the list tomorrow. Every quantity is editable before the order goes
 * out: this screen suggests, the buyer decides.
 *
 * There is deliberately no total across suppliers. Each supplier bills in its
 * own currency (field_tec_purchase_currency), so one grand total would be baht
 * added to dollars.
 *
 * The button writes the same shape of purchase order as the older flag-driven
 * flow "TEC Order: Create Purchase order - Excel lover" (process_kryibry): same
 * owner, same link between order and lines, and literally the same number, since
 * both get their name from OrderNumber on save. That flow lists every material
 * of the supplier with no quantities for someone to type; this one lists only
 * what is short, with the quantity already worked out.
 */
class PurchaseListForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId() {
    return 'tec_production_purchase_list_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $list = $this->shoppingList();

    $form['#attached']['library'][] = 'tec_production/purchase_list';
    // Without this, every supplier table would answer to the same key and the
    // last one would eat the quantities typed in all the others: the value of
    // a child is nested only when its parents are a tree.
    $form['#tree'] = TRUE;

    $form['help'] = [
      '#markup' => '<div class="tec-buy__help">'
      . $this->t('One row per material whose stock, plus what is already on order, no longer covers its reorder point. Quantities are suggestions in purchase units, rounded up and raised to the MOQ: change any of them before creating the order. Untick a row to leave it out.')
      . '</div>',
    ];

    if (!$list['groups']) {
      $form['nothing'] = [
        '#markup' => '<div class="tec-buy__nothing">'
        . $this->t('Nothing to buy: every material with a reorder point is above it.')
        . '</div>',
      ];
    }

    foreach ($list['groups'] as $key => $group) {
      $form['g' . $key] = $this->supplierGroup($key, $group);
    }

    if ($list['without_policy']) {
      $form['without_policy'] = $this->withoutPolicy($list['without_policy']);
    }

    return $form;
  }

  /**
   * One supplier: its short materials, its total and its button.
   */
  protected function supplierGroup(string $key, array $group): array {
    $element = [
      '#type' => 'fieldset',
      '#title' => $group['name'],
      '#attributes' => [
        'class' => ['tec-buy__group'],
        'data-supplier' => $key,
      ],
    ];

    $element['lines'] = [
      '#type' => 'table',
      '#header' => [
        'include' => ['data' => $this->t('Buy'), 'class' => ['tec-buy__h-chk']],
        'material' => ['data' => $this->t('Material'), 'class' => ['tec-buy__h-mat']],
        'stock' => ['data' => $this->t('Stock (UoS)'), 'class' => ['tec-buy__h-num']],
        'on_order' => ['data' => $this->t('Ordered (UoP)'), 'class' => ['tec-buy__h-num']],
        'rop' => ['data' => $this->t('Reorder point (UoS)'), 'class' => ['tec-buy__h-num']],
        'short' => ['data' => $this->t('Short by (UoS)'), 'class' => ['tec-buy__h-num']],
        'moq' => ['data' => $this->t('MOQ'), 'class' => ['tec-buy__h-num']],
        'lead' => ['data' => $this->t('Lead time'), 'class' => ['tec-buy__h-num']],
        'quantity' => ['data' => $this->t('Quantity (UoP)'), 'class' => ['tec-buy__h-qty']],
        'cost' => ['data' => $this->t('Unit cost'), 'class' => ['tec-buy__h-num']],
        'total' => ['data' => $this->t('Line total'), 'class' => ['tec-buy__h-num']],
      ],
      '#attributes' => ['class' => ['tec-buy__table']],
    ];

    foreach ($group['lines'] as $tid => $line) {
      $element['lines'][$tid] = $this->row($line);
    }

    $element['foot'] = [
      '#markup' => '<div class="tec-buy__foot">'
      . $this->t('Supplier total') . ': <span class="tec-buy__group-total">'
      . $this->fmt($group['total']) . '</span>'
      . ($group['currency'] !== '' ? ' <span class="tec-buy__currency">' . Html::escape($group['currency']) . '</span>' : '')
      . '</div>',
    ];

    // A purchase order without a supplier has nobody to send it to, so the
    // materials with no supplier are listed to be fixed, not to be ordered.
    if ($group['supplier_id'] === NULL) {
      $element['no_supplier'] = [
        '#markup' => '<div class="tec-buy__warning">'
        . $this->t('These materials have no supplier, so no purchase order can be created for them. Set the supplier on the material first.')
        . '</div>',
      ];
      return $element;
    }

    $element['actions'] = [
      '#type' => 'actions',
      'create' => [
        '#type' => 'submit',
        '#value' => $this->t('Create purchase order'),
        '#name' => 'create-' . $key,
        '#supplier' => $group['supplier_id'],
        '#attributes' => ['class' => ['tec-buy__create']],
      ],
    ];

    return $element;
  }

  /**
   * One material: the numbers behind the suggestion, and the suggestion.
   */
  protected function row(array $line): array {
    $material = $line['material'];
    $cost = $line['cost'];

    $row = [
      '#attributes' => ['data-cost' => $cost === NULL ? '' : (string) $cost],
    ];

    $row['include'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include @material', ['@material' => $material->label()]),
      '#title_display' => 'invisible',
      // A line with no cost can still be ordered: the price is the supplier's
      // business and someone will fill it in. It is ticked like the rest.
      '#default_value' => 1,
      '#attributes' => ['class' => ['tec-buy__chk']],
      '#wrapper_attributes' => ['class' => ['tec-buy__c-chk']],
    ];

    $row['material'] = [
      '#type' => 'link',
      '#title' => $material->label(),
      '#url' => $material->toUrl(),
      '#wrapper_attributes' => ['class' => ['tec-buy__c-mat']],
    ];

    $row['stock'] = $this->numberCell($this->fmt($line['stock']), $line['unit_stock']);
    $row['on_order'] = $this->numberCell(
      $line['on_order'] > 0 ? $this->fmt($line['on_order']) : '—',
      $line['unit_purchase']
    );
    $row['rop'] = $this->numberCell($this->fmt($line['rop']), $line['unit_stock']);
    $row['short'] = $this->numberCell($this->fmt($line['short']), $line['unit_stock']);
    $row['moq'] = $this->numberCell(
      $line['moq'] === NULL ? '—' : $this->fmt($line['moq']),
      $line['unit_purchase']
    );
    $row['lead'] = $this->numberCell(
      $line['lead'] === NULL ? '—' : $this->t('@days d', ['@days' => (int) $line['lead']]),
      ''
    );

    $row['quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity of @material', ['@material' => $material->label()]),
      '#title_display' => 'invisible',
      '#default_value' => $line['quantity'],
      '#min' => 0,
      // Purchase order quantities are stored in an integer field, so a
      // fractional purchase unit would be silently truncated on save.
      '#step' => 1,
      '#attributes' => [
        'class' => ['tec-buy__qty'],
        'title' => $line['by_moq']
          ? $this->t('Raised to the minimum order quantity')
          : $this->t('Fills the gap up to reorder point plus safety stock'),
      ],
      '#wrapper_attributes' => ['class' => ['tec-buy__c-qty']],
      '#field_suffix' => $line['unit_purchase'] !== '' ? Html::escape($line['unit_purchase']) : NULL,
    ];

    $row['cost'] = $this->numberCell($cost === NULL ? '—' : $this->fmt($cost), '');
    $row['total'] = [
      '#markup' => '<span class="tec-buy__line-total">'
      . ($line['total'] === NULL ? '—' : $this->fmt($line['total']))
      . '</span>',
      '#wrapper_attributes' => ['class' => ['tec-buy__c-num']],
    ];

    return $row;
  }

  /**
   * The materials that can never appear on this list, and why.
   */
  protected function withoutPolicy(array $materials): array {
    $items = [];
    foreach ($materials as $material) {
      $items[] = [
        '#type' => 'link',
        '#title' => $material->label(),
        '#url' => $material->toUrl(),
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('@count materials have no reorder point', ['@count' => count($materials)]),
      '#attributes' => ['class' => ['tec-buy__gap']],
      'why' => [
        '#markup' => '<p>'
        . $this->t('Without a reorder point there is no level to fall below, so these materials never reach this list however low their stock gets. Set one on the material to have it watched.')
        . '</p>',
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * A right-aligned cell, with the unit as a tooltip when there is one.
   */
  protected function numberCell($value, string $unit): array {
    return [
      '#markup' => '<span' . ($unit !== '' ? ' title="' . Html::escape($unit) . '"' : '') . '>'
      . Html::escape((string) $value)
      . '</span>',
      '#wrapper_attributes' => ['class' => ['tec-buy__c-num']],
    ];
  }

  /**
   * Everything to buy, grouped by supplier, plus the materials nobody watches.
   */
  protected function shoppingList(): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_inventory')
      ->execute();
    $materials = $ids ? $storage->loadMultiple($ids) : [];
    $on_order = Purchasing::onOrder($this->entityTypeManager, array_keys($materials));

    $groups = [];
    $without_policy = [];

    foreach ($materials as $tid => $material) {
      $reorder_point = $this->number($material, 'field_tec_reorder_point');
      if ($reorder_point === NULL) {
        $without_policy[] = $material;
        continue;
      }

      $units = Purchasing::factor($material, 'field_tec_units');
      $stock = $this->number($material, 'field_tec_stock_level') ?? 0.0;
      $incoming = (float) ($on_order[$tid] ?? 0.0);
      $available = $stock + $incoming * $units;
      if ($available > $reorder_point) {
        continue;
      }

      $safety = $this->number($material, 'field_tec_safety_stock') ?? 0.0;
      $short = ($reorder_point + $safety) - $available;

      $quantity = max((int) ceil($short / $units), 1);
      $moq = $this->number($material, 'field_tec_moq_quantity');
      $by_moq = $moq !== NULL && $quantity < $moq;
      if ($by_moq) {
        $quantity = (int) $moq;
      }

      $cost = $this->number($material, 'field_tec_cost');
      $supplier = $material->hasField('field_tec_vendor') ? $material->get('field_tec_vendor')->entity : NULL;
      $key = $supplier ? (string) $supplier->id() : 'none';

      $groups[$key]['supplier_id'] = $supplier ? (int) $supplier->id() : NULL;
      $groups[$key]['name'] = $supplier ? $supplier->label() : $this->t('No supplier');
      $groups[$key]['currency'] = $supplier ? $this->plain($supplier, 'field_tec_purchase_currency') : '';
      $groups[$key]['total'] = ($groups[$key]['total'] ?? 0.0) + ($cost === NULL ? 0.0 : $quantity * $cost);
      $groups[$key]['lines'][$tid] = [
        'material' => $material,
        'stock' => $stock,
        'on_order' => $incoming,
        'rop' => $reorder_point,
        'short' => $short,
        'moq' => $moq,
        'lead' => $this->number($material, 'field_tec_lead_time_days'),
        'quantity' => $quantity,
        'by_moq' => $by_moq,
        'cost' => $cost,
        'total' => $cost === NULL ? NULL : $quantity * $cost,
        'unit_purchase' => $this->unitName($material, 'field_tec_unit_purchase'),
        'unit_stock' => $this->unitName($material, 'field_tec_uos'),
      ];
    }

    foreach ($groups as &$group) {
      uasort($group['lines'], static fn($a, $b) => strnatcasecmp((string) $a['material']->label(), (string) $b['material']->label()));
    }
    unset($group);
    uasort($groups, static fn($a, $b) => strnatcasecmp((string) $a['name'], (string) $b['name']));

    usort($without_policy, static fn($a, $b) => strnatcasecmp((string) $a->label(), (string) $b->label()));

    return ['groups' => $groups, 'without_policy' => $without_policy];
  }

  /**
   * Creates the purchase order for the supplier whose button was pressed.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $supplier_id = $trigger['#supplier'] ?? NULL;
    if (!$supplier_id) {
      return;
    }

    $wanted = [];
    foreach ($form_state->getValue(['g' . $supplier_id, 'lines']) ?? [] as $tid => $values) {
      $quantity = (int) ($values['quantity'] ?? 0);
      if (empty($values['include']) || $quantity < 1) {
        continue;
      }
      $wanted[(int) $tid] = $quantity;
    }

    if (!$wanted) {
      $this->messenger()->addWarning($this->t('Nothing was ticked, so no purchase order was created.'));
      return;
    }

    $order = $this->createPurchaseOrder((int) $supplier_id, $wanted);

    $this->messenger()->addStatus($this->t('Purchase order @number created with @count lines.', [
      '@number' => $order->label(),
      '@count' => count($wanted),
    ]));
    $form_state->setRedirectUrl($order->toUrl());
  }

  /**
   * Writes the order and its lines.
   *
   * The lines are created first because the order needs them: its line items
   * field is required. Then each line is pointed back at the order, which is
   * how the rest of the ERP walks from an order to its lines.
   *
   * Nothing here sets the line title or the line total: the presave model
   * "TEC Line item: Set PO line item data" (process_fpvka81) does both. It only
   * does the total when the line already carries a price, which is why the
   * price is set here even though the total is computed from the material cost.
   *
   * @param int $supplier_id
   *   The supplier the order goes to.
   * @param int[] $wanted
   *   Quantity in purchase units, keyed by material term id.
   */
  protected function createPurchaseOrder(int $supplier_id, array $wanted) {
    $line_storage = $this->entityTypeManager->getStorage('tec_line_item');
    $material_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $lines = [];
    foreach ($wanted as $tid => $quantity) {
      $material = $material_storage->load($tid);
      if (!$material) {
        continue;
      }
      $line = $line_storage->create([
        'type' => 'tec_po_line_item',
        'field_tec_inventory' => $tid,
        'field_tec_quantity' => $quantity,
        'field_tec_price' => $this->number($material, 'field_tec_cost'),
        'field_tec_vendor' => $supplier_id,
        'uid' => $this->currentUser()->id(),
      ]);
      $line->save();
      $lines[] = $line;
    }

    // No title here on purpose. The number comes from OrderNumber, through the
    // presave hook, so that this screen and the flag-driven flow cannot hand out
    // the same one. The supplier has to be in this array rather than set
    // afterwards, because that is where the short code in the name comes from.
    $order_storage = $this->entityTypeManager->getStorage('tec_order');
    $order = $order_storage->create([
      'type' => 'tec_purchase_order',
      'field_tec_vendor' => $supplier_id,
      'field_tec_line_items' => array_map(static fn($line) => $line->id(), $lines),
      'uid' => $this->currentUser()->id(),
    ]);
    $order->save();

    foreach ($lines as $line) {
      $line->set('field_tec_order', $order->id());
      $line->save();
    }

    return $order;
  }

  /**
   * The number a field holds, or NULL when it is empty.
   *
   * Empty and zero are not the same thing here: a reorder point of zero is a
   * decision, an empty one is a material nobody is watching.
   */
  protected function number($entity, string $field): ?float {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return NULL;
    }
    $value = $entity->get($field)->value;
    return $value === NULL || $value === '' ? NULL : (float) $value;
  }

  /**
   * The raw value a list field holds, or ''.
   */
  protected function plain($entity, string $field): string {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    return (string) $entity->get($field)->value;
  }

  /**
   * The label of the unit a field points at, or ''.
   */
  protected function unitName($entity, string $field): string {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    $unit = $entity->get($field)->entity;
    return $unit ? (string) $unit->label() : '';
  }

  /**
   * Up to two decimals, no trailing zeros.
   */
  protected function fmt(float $number): string {
    $formatted = number_format($number, 2, '.', ',');
    if (str_contains($formatted, '.')) {
      $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    return $formatted === '-0' ? '0' : $formatted;
  }

}
