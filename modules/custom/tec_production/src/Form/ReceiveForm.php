<?php

namespace Drupal\tec_production\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_production\Purchasing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receive a delivery against a purchase order: /tec_order/{id}/receive.
 *
 * This is the door that was missing until 15 August 2026. A purchase order went
 * out, its quantity started counting as coming in, and there was nowhere to say
 * that it had arrived -- so it counted as coming in forever, and the stock board
 * quietly told people to buy things that were already on the shelf.
 *
 * What happens on submit, per line with a quantity:
 *
 *   1. The received quantity goes up. Nothing stores what is still outstanding;
 *      that is always ordered minus received, worked out when asked.
 *   2. A tec_inventory_transaction is created for the material, converted from
 *      purchase units to inventory units by multiplying by field_tec_units.
 *      That is the only door stock has: the existing ECA models ("Stock
 *      manager") pick the transaction up and move field_tec_stock_level, the
 *      same as the +/- link on /stock does.
 *   3. The transaction points back at the line it came in on, so the material's
 *      history says which order brought each entry, and the order says which
 *      entries it produced.
 *
 * When no line has anything outstanding left, the order goes to 'closed' and
 * stops counting anywhere.
 *
 * Three decisions the owner took on 16 August 2026, all visible in the code:
 *
 * - A short delivery is closed with the checkbox on its row, not with a dialog
 *   at the end. Odoo asks the question once, at validation time, and only when a
 *   line falls short, which is nicer and cannot be forgotten; that is a second
 *   form step and it can be built on top of this later without touching a single
 *   stored value, because the data written is identical either way.
 * - Receiving more than was ordered is allowed, with a warning. The goods are
 *   physically in the building, and stock that lies to make a document tidy is
 *   worse than a surprise.
 * - The reason for closing is optional. It is still worth writing: line items
 *   carry no author and no revisions, so without it that decision leaves no
 *   trace beyond a changed timestamp.
 *
 * Undoing a receipt is deliberately not built. To correct one, register the
 * difference with the +/- link on /stock and fix Received on the line by hand.
 */
class ReceiveForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId() {
    return 'tec_production_receive_form';
  }

  /**
   * Only open purchase orders, and only for whoever may move stock.
   *
   * Receiving is a stock movement with extra paperwork, so it rides on the same
   * permission as the board rather than inventing one. Returning forbidden also
   * hides the tab: Drupal drops local tasks whose route is not accessible, which
   * is why sales orders and closed purchase orders do not show it.
   *
   * The type declaration is not decoration. Without it Drupal hands this method
   * the raw '768' from the path instead of the loaded order, because its
   * arguments resolver only reaches for the upcast object when a parameter says
   * what class it wants (ArgumentsResolver::getArgument()).
   */
  public static function access(AccountInterface $account, ?EntityInterface $tec_order = NULL) {
    if (!$tec_order || $tec_order->bundle() !== 'tec_purchase_order') {
      return AccessResult::forbidden()->addCacheableDependency($tec_order);
    }
    $status = $tec_order->hasField('field_tec_po_status')
      ? $tec_order->get('field_tec_po_status')->value
      : NULL;
    if ($status !== Purchasing::INCOMING_STATUS) {
      return AccessResult::forbidden()->addCacheableDependency($tec_order);
    }

    return AccessResult::allowedIfHasPermission($account, 'access tec stock control')
      ->addCacheableDependency($tec_order);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $tec_order = NULL) {
    if (!$tec_order || $tec_order->bundle() !== 'tec_purchase_order') {
      throw new NotFoundHttpException();
    }
    $form_state->set('order_id', (int) $tec_order->id());

    $form['#attached']['library'][] = 'tec_production/receive';

    $supplier = $tec_order->hasField('field_tec_vendor') ? $tec_order->get('field_tec_vendor')->entity : NULL;
    $form['head'] = [
      '#markup' => '<div class="tec-receive__head">'
        . '<span class="tec-receive__number">' . Html::escape((string) $tec_order->label()) . '</span>'
        . ($supplier ? ' <span class="tec-receive__supplier">' . Html::escape((string) $supplier->label()) . '</span>' : '')
        . '</div>',
    ];

    $form['help'] = [
      '#markup' => '<div class="tec-receive__help">'
        . $this->t('Type what has arrived, in purchase units. <em>Into stock</em> shows what that becomes in inventory units, which is what the warehouse count goes up by. Leave a row at 0 if nothing of it came. Tick <em>Nothing more expected</em> only when the rest of that line is never coming: what is left stops counting as ordered, so the material can be bought again.')
        . '</div>',
    ];

    $form['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delivery note'),
      '#maxlength' => 100,
      '#required' => FALSE,
      '#description' => $this->t("The supplier's document number, if there is one. It is written into every stock entry this receipt creates."),
      '#attributes' => ['class' => ['tec-receive__note']],
    ];

    $form['lines'] = [
      '#type' => 'table',
      '#header' => [
        'material' => $this->t('Material'),
        'ordered' => ['data' => $this->t('Ordered (UoP)'), 'class' => ['tec-receive__h-num']],
        'received' => ['data' => $this->t('Received (UoP)'), 'class' => ['tec-receive__h-num']],
        'outstanding' => ['data' => $this->t('Outstanding (UoP)'), 'class' => ['tec-receive__h-num']],
        'arriving' => ['data' => $this->t('Arriving now (UoP)'), 'class' => ['tec-receive__h-num']],
        'into' => ['data' => $this->t('Into stock (UoS)'), 'class' => ['tec-receive__h-num']],
        'no_more' => $this->t('Nothing more expected'),
        'reason' => $this->t('Reason'),
      ],
      '#empty' => $this->t('This purchase order has no lines.'),
      '#attributes' => ['class' => ['tec-receive__table']],
    ];

    foreach ($tec_order->get('field_tec_line_items')->referencedEntities() as $line) {
      if ($line->bundle() !== 'tec_po_line_item') {
        continue;
      }
      $form['lines'][$line->id()] = $this->lineRow($line);
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['receive'] = [
      '#type' => 'submit',
      '#value' => $this->t('Receive'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $tec_order->toUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * One line: what was ordered, what came, and what is arriving today.
   */
  protected function lineRow($line): array {
    $material = $line->hasField('field_tec_inventory') ? $line->get('field_tec_inventory')->entity : NULL;
    $ordered = (int) ($line->get('field_tec_quantity')->value ?? 0);
    $received = (int) ($line->get('field_tec_quantity_received')->value ?? 0);
    $outstanding = (int) Purchasing::outstanding($line);
    $closed = !empty($line->get('field_tec_no_more_expected')->value);
    $units = $material ? Purchasing::factor($material, 'field_tec_units') : 1.0;

    $uop = $material ? $this->unitName($material, 'field_tec_unit_purchase') : '';
    $uos = $material ? $this->unitName($material, 'field_tec_uos') : '';

    $row = [
      '#attributes' => ['data-units' => (string) $units],
    ];

    $row['material'] = $material
      ? [
        '#type' => 'link',
        '#title' => $material->label(),
        '#url' => $material->toUrl(),
        '#wrapper_attributes' => ['class' => ['tec-receive__c-mat']],
      ]
      : [
        '#markup' => '<span class="tec-receive__muted">' . $this->t('material missing') . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-receive__c-mat']],
      ];

    $row['ordered'] = $this->numberCell($ordered, $uop);
    $row['received'] = $this->numberCell($received, $uop);
    $row['outstanding'] = $this->numberCell($outstanding, $uop);

    if ($closed) {
      // A closed line keeps its numbers on screen for context but takes no
      // input: reopening it is an edit on the line, not a receipt.
      $row['arriving'] = [
        '#markup' => '<span class="tec-receive__muted">—</span>',
        '#wrapper_attributes' => ['class' => ['tec-receive__c-num']],
      ];
      $row['into'] = [
        '#markup' => '<span class="tec-receive__muted">—</span>',
        '#wrapper_attributes' => ['class' => ['tec-receive__c-num']],
      ];
      $row['no_more'] = [
        '#markup' => '<span class="tec-receive__closed">' . $this->t('closed') . '</span>',
      ];
      $reason = (string) ($line->get('field_tec_close_reason')->value ?? '');
      $row['reason'] = [
        '#markup' => $reason === ''
          ? '<span class="tec-receive__muted">—</span>'
          : '<span class="tec-receive__reason">' . Html::escape($reason) . '</span>',
      ];

      return $row;
    }

    $row['arriving'] = [
      '#type' => 'number',
      '#title' => $this->t('Arriving now'),
      '#title_display' => 'invisible',
      '#default_value' => $outstanding,
      '#min' => 0,
      // Purchase quantities are whole purchase units, like the ordered field.
      '#step' => 1,
      '#attributes' => [
        'class' => ['tec-receive__qty'],
        'title' => $units === 1.0
          ? $this->t('Same unit in and out')
          : $this->t('1 @uop = @units @uos', [
            '@uop' => $uop !== '' ? $uop : $this->t('purchase unit'),
            '@units' => rtrim(rtrim(number_format($units, 5, '.', ''), '0'), '.'),
            '@uos' => $uos !== '' ? $uos : $this->t('inventory units'),
          ]),
      ],
      '#wrapper_attributes' => ['class' => ['tec-receive__c-num']],
    ];

    $row['into'] = [
      '#markup' => '<span class="tec-receive__into">' . $this->fmt($outstanding * $units) . '</span>'
        . ($uos !== '' ? ' <span class="tec-receive__unit">' . Html::escape($uos) . '</span>' : ''),
      '#wrapper_attributes' => ['class' => ['tec-receive__c-num']],
    ];

    $row['no_more'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Nothing more expected'),
      '#title_display' => 'invisible',
      '#attributes' => ['class' => ['tec-receive__nomore']],
    ];

    $row['reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reason'),
      '#title_display' => 'invisible',
      '#maxlength' => 255,
      '#size' => 24,
      '#placeholder' => $this->t('why not'),
      '#attributes' => ['class' => ['tec-receive__reason-input']],
    ];

    return $row;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $something = FALSE;
    foreach ($form_state->getValue('lines') ?? [] as $values) {
      if ((int) ($values['arriving'] ?? 0) > 0 || !empty($values['no_more'])) {
        $something = TRUE;
        break;
      }
    }
    if (!$something) {
      $form_state->setErrorByName('lines', $this->t('Nothing to receive: put a quantity on at least one line, or tick that nothing more is expected.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $order = $this->entityTypeManager->getStorage('tec_order')->load($form_state->get('order_id'));
    if (!$order) {
      $this->messenger()->addError($this->t('The purchase order no longer exists.'));
      return;
    }
    $line_storage = $this->entityTypeManager->getStorage('tec_line_item');
    $note = trim((string) $form_state->getValue('note'));

    $into_stock = [];
    $over = [];
    $closed = 0;

    foreach ($form_state->getValue('lines') ?? [] as $lid => $values) {
      $line = $line_storage->load($lid);
      if (!$line || $line->bundle() !== 'tec_po_line_item') {
        continue;
      }
      $quantity = (int) ($values['arriving'] ?? 0);
      $close = !empty($values['no_more']);
      if ($quantity <= 0 && !$close) {
        continue;
      }

      $outstanding = Purchasing::outstanding($line);
      $material = $line->hasField('field_tec_inventory') ? $line->get('field_tec_inventory')->entity : NULL;

      if ($quantity > 0 && $material) {
        if ($quantity > $outstanding) {
          $over[] = [
            'material' => (string) $material->label(),
            'arrived' => $quantity,
            'outstanding' => $this->fmt($outstanding),
          ];
        }
        $units = Purchasing::factor($material, 'field_tec_units');
        // Purchase units in, inventory units out: the warehouse counts in UoS
        // and the order is written in UoP. Getting this multiplication backwards
        // produces a believable number, which is exactly why it lives in one
        // place with the factor read the same way as everywhere else.
        $in_stock = $quantity * $units;

        $this->entityTypeManager->getStorage('tec_inventory')->create([
          'type' => 'tec_inventory_transaction',
          'title' => sprintf('%s +%s (PO %s)', $material->label(), $this->fmt($in_stock), $order->label()),
          'uid' => $this->currentUser()->id(),
          'field_tec_inventory' => $material->id(),
          'field_tec_quantity' => $in_stock,
          'field_tec_po_line' => $line->id(),
          'field_tec_mutation_note' => $note === ''
            ? sprintf('Received on PO %s', $order->label())
            : sprintf('Received on PO %s, delivery note %s', $order->label(), $note),
        ])->save();

        $line->set('field_tec_quantity_received', (int) ($line->get('field_tec_quantity_received')->value ?? 0) + $quantity);
        $into_stock[] = [
          'material' => (string) $material->label(),
          'quantity' => $this->fmt($in_stock),
          'unit' => $this->unitName($material, 'field_tec_uos'),
        ];
      }

      if ($close) {
        $line->set('field_tec_no_more_expected', TRUE);
        $reason = trim((string) ($values['reason'] ?? ''));
        $line->set('field_tec_close_reason', $reason === '' ? NULL : $reason);
        $closed++;
      }

      $line->save();
    }

    $this->report($order, $into_stock, $over, $closed);
    $form_state->setRedirectUrl($order->toUrl());
  }

  /**
   * Closes the order when it has nothing left to bring, and says what happened.
   *
   * The order has to be read again from the database: its line items were loaded
   * before the receipt was written, so the copies hanging off the old object
   * still hold the quantities from before and would say there is more coming.
   */
  protected function report($order, array $into_stock, array $over, int $closed): void {
    foreach ($into_stock as $entry) {
      $this->messenger()->addStatus($this->t('@material: @quantity @unit into stock.', [
        '@material' => $entry['material'],
        '@quantity' => $entry['quantity'],
        '@unit' => $entry['unit'] !== '' ? $entry['unit'] : $this->t('units'),
      ]));
    }
    foreach ($over as $entry) {
      $this->messenger()->addWarning($this->t('@material: @arrived arrived against @outstanding outstanding. The difference went into stock too, because it is in the warehouse.', [
        '@material' => $entry['material'],
        '@arrived' => $entry['arrived'],
        '@outstanding' => $entry['outstanding'],
      ]));
    }
    if ($closed) {
      $this->messenger()->addStatus($this->formatPlural(
        $closed,
        '1 line closed: what was missing is no longer expected, so it stops counting as ordered.',
        '@count lines closed: what was missing is no longer expected, so it stops counting as ordered.'
      ));
    }

    $fresh = $this->entityTypeManager->getStorage('tec_order')->loadUnchanged($order->id());
    if (!$fresh) {
      return;
    }
    $state = Purchasing::receiptState($fresh);
    if ($state === 'partial' || $state === 'none') {
      return;
    }

    $fresh->set('field_tec_po_status', Purchasing::CLOSED_STATUS);
    $fresh->save();
    $this->messenger()->addStatus($state === 'full'
      ? $this->t('Purchase order @number is complete and now closed.', ['@number' => $fresh->label()])
      : $this->t('Purchase order @number is closed with nothing received.', ['@number' => $fresh->label()])
    );
  }

  /**
   * A right-aligned number cell, with its unit as a tooltip.
   */
  protected function numberCell($value, string $unit): array {
    return [
      '#markup' => '<span' . ($unit !== '' ? ' title="' . Html::escape($unit) . '"' : '') . '>'
        . Html::escape((string) $value) . '</span>',
      '#wrapper_attributes' => ['class' => ['tec-receive__c-num']],
    ];
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
