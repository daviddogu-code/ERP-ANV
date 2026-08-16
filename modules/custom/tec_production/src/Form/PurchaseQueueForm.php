<?php

namespace Drupal\tec_production\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tec_production\Purchasing;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Purchase Control: what has been ordered and has not arrived yet.
 *
 * This replaces a Google Sheet that was kept by hand beside the ERP. Someone
 * typed each purchase order into it after creating it here, moved a coloured
 * status along as the supplier answered the phone, and deleted the row when the
 * goods reached the factory. Two lists of the same orders, and the one people
 * actually looked at was the one the ERP knew nothing about.
 *
 * Everything on screen the ERP already knew except three things, and those
 * three are now fields: where the goods are, when the supplier said they would
 * arrive, and the invoice. The rest is read off the orders themselves.
 *
 * Which orders appear, in two rules:
 *
 *   An order is here while there is something to chase. Nothing outstanding and
 *   nothing ever received means there is nothing to chase -- a half-written
 *   order with no quantities, or one closed short before anything shipped -- so
 *   it never shows up, whether it is open or closed.
 *
 *   And it leaves when the invoice is filed, which is the owner's rule: the
 *   goods arrived weeks ago, but the paperwork is what finishes it. With one
 *   condition the owner did not ask for and that is worth the extra line: only
 *   once everything has actually arrived. Invoices turn up at the end of the
 *   month, so in practice they come after the goods; but on the day a supplier
 *   invoices ahead of a part-delivery, letting the paperwork hide an order that
 *   still owes material is how the missing half gets forgotten.
 *
 * Two of the columns are not stored anywhere. The delivery date comes from the
 * stock movements that receiving wrote, and the days are arithmetic. Sorting
 * still works on both because the rows are sorted here in PHP rather than by
 * the database, which is affordable while this list is the few dozen orders in
 * flight and not the whole history.
 */
class PurchaseQueueForm extends FormBase {

  /**
   * Where the goods can be while they travel, in the order it normally happens.
   *
   * The order matters twice: it is the order of the dropdown, and it is what
   * the Status column sorts by, so sorting gathers everything that has not left
   * the supplier yet. Alphabetical would put "On the way" above "On process"
   * and mean nothing.
   *
   * Three, where the sheet had four. The fourth is below.
   */
  const STATES = [
    'on_process' => 'On process',
    'ready_to_pick_up' => 'Ready to pick up',
    'on_the_way' => 'On the way',
  ];

  /**
   * Arrived: shown, sorted on, and never stored.
   *
   * The sheet's fourth colour, and the only one that is a fact rather than a
   * report. The goods are here when there are stock movements saying they are,
   * so this is read off the lines like everything else derived in this module.
   * A dropdown entry would let someone mark an order delivered without a single
   * unit entering stock, and the purchase list would then refuse to reorder
   * material that never came.
   */
  const ARRIVED = 'delivered';

  /**
   * Which column the list sorts by when nobody has said otherwise.
   *
   * The soonest promise first, because this screen exists to answer "what
   * should I be chasing this morning".
   */
  const DEFAULT_SORT = 'expected';

  /**
   * Every column can be sorted on, including the two that are not stored.
   */
  const SORTABLE = [
    'order',
    'supplier',
    'status',
    'created',
    'author',
    'expected',
    'delivered',
    'days',
  ];

  protected EntityTypeManagerInterface $entityTypeManager;

  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tec_purchase_queue_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $rows = $this->loadRows();

    $query = $this->getRequest()->query;
    $sort_by = (string) $query->get('order', self::DEFAULT_SORT);
    $direction = $query->get('sort') === 'desc' ? 'desc' : 'asc';
    if (!in_array($sort_by, self::SORTABLE, TRUE)) {
      $sort_by = self::DEFAULT_SORT;
    }
    $this->sortRows($rows, $sort_by, $direction);

    $form['#attached']['library'][] = 'tec_production/purchase_queue';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $waiting = 0;
    $late = 0;
    foreach ($rows as $row) {
      if ($row['status'] !== self::ARRIVED) {
        $waiting++;
      }
      if ($row['overdue']) {
        $late++;
      }
    }
    $form['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['tec-pq__summary']],
      '#value' => $this->formatPlural(
        count($rows),
        '1 order in flight.',
        '@count orders in flight.'
      ) . ' ' . $this->t('@waiting still to arrive, @late past their promised day.', [
        '@waiting' => $waiting,
        '@late' => $late,
      ]),
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->sortHeader('order', $this->t('Order #'), $sort_by, $direction),
        $this->sortHeader('supplier', $this->t('Suppliers Name'), $sort_by, $direction),
        $this->sortHeader('status', $this->t('Status'), $sort_by, $direction),
        $this->sortHeader('created', $this->t('Created'), $sort_by, $direction),
        $this->sortHeader('author', $this->t('Created By'), $sort_by, $direction),
        $this->sortHeader('expected', $this->t('Expected delivery day'), $sort_by, $direction),
        $this->sortHeader('delivered', $this->t('Delivered date'), $sort_by, $direction),
        $this->sortHeader('days', $this->t('Days'), $sort_by, $direction),
        ['data' => $this->t('Invoice'), 'class' => ['tec-pq__h']],
      ],
      '#empty' => $this->t('Nothing on order. Everything that was bought has arrived and been invoiced.'),
      '#attributes' => ['class' => ['tec-pq__table']],
      '#prefix' => '<div class="tec-pq__wrap">',
      '#suffix' => '</div>',
    ];

    foreach ($rows as $row) {
      $oid = $row['id'];
      $arrived = $row['status'] === self::ARRIVED;

      $element = [
        '#attributes' => [
          'class' => array_filter([
            'tec-pq__row',
            $row['overdue'] ? 'tec-pq__row--late' : '',
          ]),
        ],
      ];
      $element['order'] = [
        '#type' => 'link',
        '#title' => $row['label'],
        '#url' => Url::fromRoute('entity.tec_order.canonical', ['tec_order' => $oid]),
        '#wrapper_attributes' => ['class' => ['tec-pq__order']],
      ];
      $element['supplier'] = [
        '#markup' => $row['supplier'] === '' ? '<span class="tec-pq__none">—</span>' : $row['supplier'],
      ];

      // Delivered is shown, never offered. It is the receipt's word, and the
      // way to take it back is to correct the receipt, not to retype it here.
      if ($arrived) {
        $element['status'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Delivered'),
          '#attributes' => [
            'class' => ['tec-pq__pill', 'tec-pq__pill--delivered'],
            'title' => $this->t('Everything ordered has been received.'),
          ],
          '#wrapper_attributes' => ['class' => ['tec-pq__status']],
        ];
      }
      else {
        $element['status'] = [
          '#type' => 'select',
          '#title' => $this->t('Delivery progress'),
          '#title_display' => 'invisible',
          '#options' => self::STATES,
          '#default_value' => $row['status'],
          '#attributes' => ['class' => ['tec-pq__select', 'tec-pq__select--' . $row['status']]],
          '#wrapper_attributes' => ['class' => ['tec-pq__status']],
        ];
      }

      $element['created'] = ['#markup' => $this->day($row['created'])];
      $element['author'] = ['#markup' => $row['author']];
      $element['expected'] = [
        '#type' => 'date',
        '#title' => $this->t('Expected delivery day'),
        '#title_display' => 'invisible',
        '#default_value' => $row['expected'] ?? '',
        '#attributes' => ['class' => ['tec-pq__date']],
        '#wrapper_attributes' => ['class' => ['tec-pq__expected']],
      ];
      $element['delivered'] = [
        '#markup' => $row['delivered']
          ? $this->day($row['delivered'])
          : '<span class="tec-pq__none">—</span>',
      ];
      $element['days'] = [
        '#markup' => '<span class="tec-pq__days">' . $row['days'] . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-pq__num']],
      ];
      $element['invoice'] = [
        '#type' => 'link',
        '#title' => $row['invoice'] ? $this->t('Filed') : $this->t('Upload'),
        '#url' => Url::fromRoute('tec_production.po_invoice', ['tec_order' => $oid]),
        '#attributes' => [
          'class' => array_filter([
            'use-ajax',
            'tec-pq__invoice',
            $row['invoice'] ? 'tec-pq__invoice--filed' : '',
          ]),
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 520]),
          'title' => $row['invoice']
            ? $this->t('Invoice already filed: @name', ['@name' => $row['invoice']])
            : $this->t('File the invoice and take @order off this screen', ['@order' => $row['label']]),
        ],
        '#wrapper_attributes' => ['class' => ['tec-pq__invoice-cell']],
      ];

      $form['table'][$oid] = $element;
    }

    if ($rows) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['save'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save changes'),
        '#button_type' => 'primary',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('tec_order');
    $saved = 0;

    foreach (($form_state->getValue('table') ?: []) as $oid => $values) {
      $order = $storage->load($oid);
      if (!$order || $order->bundle() !== 'tec_purchase_order') {
        continue;
      }
      $touched = FALSE;

      // A row that has arrived shows a pill instead of a dropdown, so nothing
      // comes back for it and nothing is written. Checking against the three
      // storable states is also what keeps a hand-made post from inventing a
      // fourth one.
      if (isset($values['status'], self::STATES[$values['status']])
        && $order->get('field_tec_po_queue_status')->value !== $values['status']) {
        $order->set('field_tec_po_queue_status', $values['status']);
        $touched = TRUE;
      }

      $expected = trim((string) ($values['expected'] ?? ''));
      $expected = $expected === '' ? NULL : $expected;
      if (($order->get('field_tec_expected_delivery')->value ?: NULL) !== $expected) {
        $order->set('field_tec_expected_delivery', $expected);
        $touched = TRUE;
      }

      if ($touched) {
        $order->save();
        $saved++;
      }
    }

    $this->messenger()->addStatus($saved
      ? $this->formatPlural($saved, '1 order updated.', '@count orders updated.')
      : $this->t('Nothing had changed.'));

    $form_state->setRedirect('tec_production.purchase_queue', [], [
      'query' => array_intersect_key($this->getRequest()->query->all(), ['order' => '', 'sort' => '']),
    ]);
  }

  /**
   * The purchase orders that are still worth chasing.
   */
  protected function loadRows(): array {
    $storage = $this->entityTypeManager->getStorage('tec_order');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_purchase_order')
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return [];
    }

    $open = [];
    $states = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      $state = Purchasing::receiptState($order);
      // Nothing outstanding and nothing ever received: there is nothing to
      // chase, so it is not a queue entry no matter what its status says.
      if ($state === 'cancelled') {
        continue;
      }
      // Everything arrived and the invoice is filed. This is what empties the
      // screen without anybody deleting rows.
      if ($state === 'full' && !$order->get('field_tec_supplier_invoice')->isEmpty()) {
        continue;
      }
      $open[(int) $order->id()] = $order;
      $states[(int) $order->id()] = $state;
    }
    if (!$open) {
      return [];
    }

    $arrivals = Purchasing::deliveredOn($this->entityTypeManager, $open);
    $today = strtotime('today');
    $rows = [];

    foreach ($open as $oid => $order) {
      $supplier = $order->hasField('field_tec_vendor') ? $order->get('field_tec_vendor')->entity : NULL;
      $author = $order->get('uid')->entity;
      $created = (int) $order->get('created')->value;
      $delivered = $arrivals[$oid] ?? NULL;
      $expected = $order->get('field_tec_expected_delivery')->value ?: NULL;
      // Everything ordered has been received: the goods are in the building and
      // no dropdown gets a say in it. Otherwise it is whatever the person
      // chasing the order last said, defaulting to the first step.
      $status = $states[$oid] === 'full'
        ? self::ARRIVED
        : ($order->get('field_tec_po_queue_status')->value ?: array_key_first(self::STATES));

      // Days is how long this order has been alive: until it lands it keeps
      // counting, and after it lands it stops at what the supplier took.
      $days = (int) floor((($delivered ?: $today) - $created) / 86400);
      $due = $expected ? strtotime($expected) : NULL;

      $invoice = $order->get('field_tec_supplier_invoice')->entity;

      $rows[] = [
        'id' => $oid,
        'label' => $order->label() ?: ('#' . $oid),
        'supplier' => $supplier ? (string) $supplier->label() : '',
        'status' => $status,
        'invoice' => $invoice ? (string) $invoice->getFilename() : NULL,
        'created' => $created,
        'author' => $author ? (string) $author->getDisplayName() : (string) $this->t('Unknown'),
        'expected' => $expected,
        'delivered' => $delivered,
        'days' => max(0, $days),
        'overdue' => $status !== self::ARRIVED && $due !== NULL && $due < $today,
        'sort' => [
          'order' => $order->label() ?: ('#' . $oid),
          'supplier' => $supplier ? (string) $supplier->label() : '',
          'status' => array_flip(array_keys(self::STATES))[$status] ?? count(self::STATES),
          'created' => $created,
          'author' => $author ? (string) $author->getDisplayName() : '',
          'expected' => $due,
          'delivered' => $delivered,
          'days' => $days,
        ],
      ];
    }

    return $rows;
  }

  /**
   * Sort the rows in memory, always leaving the blanks at the bottom.
   *
   * Blanks sink whichever way the arrow points. On a chasing list an order with
   * no promised date is not "the earliest" nor "the latest", it is the one
   * nobody has pinned down, and burying it under the real dates is right both
   * times.
   */
  protected function sortRows(array &$rows, string $by, string $direction): void {
    $sign = $direction === 'desc' ? -1 : 1;
    usort($rows, static function ($a, $b) use ($by, $sign) {
      $x = $a['sort'][$by] ?? NULL;
      $y = $b['sort'][$by] ?? NULL;
      $x_blank = ($x === NULL || $x === '');
      $y_blank = ($y === NULL || $y === '');
      if ($x_blank || $y_blank) {
        if ($x_blank && $y_blank) {
          return strnatcasecmp((string) $a['label'], (string) $b['label']);
        }
        return $x_blank ? 1 : -1;
      }
      $cmp = is_string($x) ? strnatcasecmp($x, (string) $y) : ($x <=> $y);
      // A stable second key, so two orders promised the same day do not swap
      // places every time the page is drawn.
      return $cmp !== 0 ? $sign * $cmp : strnatcasecmp((string) $a['label'], (string) $b['label']);
    });
  }

  /**
   * A column heading that sorts, with the arrow showing the way it is sorted.
   */
  protected function sortHeader(string $key, $label, string $current, string $direction): array {
    $active = $current === $key;
    $classes = ['tec-pq__h'];
    if ($active) {
      $classes[] = 'tec-pq__h--sorted';
      $classes[] = 'tec-pq__h--' . $direction;
    }

    return [
      'data' => [
        '#type' => 'link',
        '#title' => $label,
        '#url' => Url::fromRoute('tec_production.purchase_queue', [], [
          'query' => [
            'order' => $key,
            'sort' => ($active && $direction === 'asc') ? 'desc' : 'asc',
          ],
        ]),
      ],
      'class' => $classes,
    ];
  }

  /**
   * A date the way the sheet wrote them.
   */
  protected function day($timestamp): string {
    return $this->dateFormatter->format((int) $timestamp, 'custom', 'j M Y');
  }

}
