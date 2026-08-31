<?php

namespace Drupal\tec_production\Form;

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
 *   And it leaves when it reaches Closed, which is goods in and invoice filed.
 *   That is the owner's rule: the goods arrived weeks ago, but the paperwork is
 *   what finishes it. Requiring both halves is what stops an invoice that turns
 *   up before a part-delivery from hiding an order that still owes material.
 *
 * Nothing on this screen has a Save button. Every change posts to
 * PurchaseQueueController the moment it is made, the same way the checkboxes on
 * /stock do, because a list that is edited a cell at a time between phone calls
 * is a list where somebody eventually closes the tab first.
 *
 * Three of the columns are not stored anywhere. The delivery date comes from
 * the stock movements that receiving wrote, the days are arithmetic, and the
 * status is Purchasing::progress(). Sorting still works on all three because
 * the rows are sorted here in PHP rather than by the database, which is
 * affordable while this list is the few dozen orders in flight and not the
 * whole history.
 */
class PurchaseQueueForm extends FormBase {

  /**
   * Which column the list sorts by when nobody has said otherwise.
   *
   * The soonest promise first, because this screen exists to answer "what
   * should I be chasing this morning".
   */
  const DEFAULT_SORT = 'expected';

  /**
   * Every column can be sorted on, including the ones that are not stored.
   */
  const SORTABLE = [
    'order',
    'supplier',
    'status',
    'delivery',
    'created',
    'author',
    'expected',
    'delivered',
    'days',
  ];

  /**
   * Links that open beside this screen instead of replacing it.
   *
   * The order and the supplier are things you look up while working down the
   * list -- a price, a phone number -- and losing the list to read one of them
   * means finding your place again afterwards.
   *
   * Receiving is deliberately not one of these and goes the other way, back to
   * the queue when it is done: see receiveCell().
   */
  const NEW_TAB = ['target' => '_blank', 'rel' => 'noopener'];

  /**
   * How much has arrived, in the words the supplier list already uses.
   */
  const DELIVERY = [
    'none' => 'Nothing received',
    'partial' => 'Partially received',
    'full' => 'Fully received',
    'cancelled' => 'Cancelled',
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
    $form['#attached']['library'][] = 'tec_production/receipt_state';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $waiting = 0;
    $late = 0;
    foreach ($rows as $row) {
      if ($row['coming']) {
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
      ) . ' ' . $this->t('@waiting still to arrive, @late past their promised day. Changes save on their own.', [
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
        $this->sortHeader('delivery', $this->t('Delivery'), $sort_by, $direction),
        $this->sortHeader('created', $this->t('Created'), $sort_by, $direction),
        $this->sortHeader('author', $this->t('Created By'), $sort_by, $direction),
        $this->sortHeader('expected', $this->t('Expected delivery day'), $sort_by, $direction),
        $this->sortHeader('delivered', $this->t('Delivered date'), $sort_by, $direction),
        $this->sortHeader('days', $this->t('Days'), $sort_by, $direction),
        ['data' => $this->t('Receive'), 'class' => ['tec-pq__h']],
        ['data' => $this->t('Invoice'), 'class' => ['tec-pq__h']],
      ],
      '#empty' => $this->t('Nothing on order. Everything that was bought has arrived and been invoiced.'),
      '#attributes' => ['class' => ['tec-pq__table']],
      '#prefix' => '<div class="tec-pq__wrap">',
      '#suffix' => '</div>',
    ];

    foreach ($rows as $row) {
      $oid = $row['id'];

      $element = [
        '#attributes' => [
          'class' => array_filter([
            'tec-pq__row',
            $row['overdue'] ? 'tec-pq__row--late' : '',
          ]),
          'data-order' => $oid,
        ],
      ];
      $element['order'] = [
        '#type' => 'link',
        '#title' => $row['label'],
        '#url' => Url::fromRoute('entity.tec_order.canonical', ['tec_order' => $oid]),
        '#attributes' => self::NEW_TAB,
        '#wrapper_attributes' => ['class' => ['tec-pq__order']],
      ];
      $element['supplier'] = $this->supplierCell($row);
      $element['status'] = $this->statusCell($row);
      $element['delivery'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => self::DELIVERY[$row['delivery']] ?? $row['delivery'],
        '#attributes' => ['class' => ['tec-receipt', 'tec-receipt--' . $row['delivery']]],
      ];
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
      $element['receive'] = $this->receiveCell($oid, $row);
      $element['invoice'] = [
        '#type' => 'link',
        '#title' => $row['invoice'] ? $this->t('Filed') : $this->t('Upload'),
        '#url' => Purchasing::invoiceDialogUrl($oid),
        '#attributes' => Purchasing::invoiceDialogAttributes(array_filter([
          'tec-pq__invoice',
          $row['invoice'] ? 'tec-pq__invoice--filed' : '',
        ])) + [
          'title' => $row['invoice']
            ? $this->t('Invoice already filed: @name', ['@name' => $row['invoice']])
            : $this->t('File the invoice and take @order off this screen', ['@order' => $row['label']]),
        ],
        '#wrapper_attributes' => ['class' => ['tec-pq__invoice-cell']],
      ];

      $form['table'][$oid] = $element;
    }

    return $form;
  }

  /**
   * No-op: every cell saves itself through PurchaseQueueController
   * (POST /supplier-orders/queue/save). There is no Save button.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * The supplier, linked to their card.
   *
   * Whoever chases these orders needs the phone number, and the phone number is
   * on the card. Before this the name was plain text and getting to the card
   * meant opening the order first.
   *
   * The name is capped in CSS rather than cut here, so the registered suffix is
   * still in the page for anyone searching it, and the tooltip carries it whole.
   */
  protected function supplierCell(array $row): array {
    if ($row['supplier'] === '') {
      return ['#markup' => '<span class="tec-pq__none">—</span>'];
    }
    if (!$row['supplier_url'] instanceof Url) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $row['supplier'],
        '#attributes' => [
          'class' => ['tec-pq__supplier'],
          'title' => $row['supplier'],
        ],
      ];
    }

    return [
      '#type' => 'link',
      '#title' => $row['supplier'],
      '#url' => $row['supplier_url'],
      '#attributes' => self::NEW_TAB + [
        'class' => ['tec-pq__supplier'],
        'title' => $row['supplier'],
      ],
    ];
  }

  /**
   * The status: a pill you can open, or a pill you cannot.
   *
   * Only the three manual steps are ever offered, and Open is not among them
   * because Open is the absence of a choice, not a choice. Delivered, Closed
   * and Cancelled are facts read off the data, so they render as plain pills
   * with no way to argue with them: taking Delivered back means correcting the
   * receipt, which is where the claim was made.
   *
   * This is a hand-built listbox rather than a select because a native dropdown
   * cannot be relied on to paint its options -- Safari ignores the colours
   * entirely -- and the colours are the whole point of the column.
   */
  protected function statusCell(array $row): array {
    $progress = $row['status'];
    $label = Purchasing::PROGRESS[$progress] ?? $progress;

    if (!in_array($progress, Purchasing::PROGRESS_MANUAL, TRUE) && $progress !== 'open') {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $label,
        '#attributes' => [
          'class' => ['tec-po-status', 'tec-po-status--' . $progress],
          'title' => $this->statusWhy($progress),
        ],
        '#wrapper_attributes' => ['class' => ['tec-pq__status']],
      ];
    }

    $cell = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tec-pq__menu'],
        'data-value' => $progress,
      ],
      '#wrapper_attributes' => ['class' => ['tec-pq__status']],
    ];
    $cell['trigger'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $label,
      '#attributes' => [
        'type' => 'button',
        'class' => ['tec-po-status', 'tec-po-status--' . $progress, 'tec-pq__trigger'],
        'aria-haspopup' => 'listbox',
        'aria-expanded' => 'false',
      ],
    ];
    $cell['list'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tec-pq__list'],
        'role' => 'listbox',
        'tabindex' => '-1',
        'aria-label' => $this->t('Delivery progress'),
        'hidden' => TRUE,
      ],
    ];
    foreach (Purchasing::PROGRESS_MANUAL as $value) {
      $cell['list'][$value] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => Purchasing::PROGRESS[$value],
        '#attributes' => [
          'class' => ['tec-pq__opt', 'tec-po-status', 'tec-po-status--' . $value],
          'role' => 'option',
          'tabindex' => '-1',
          'data-value' => $value,
          'aria-selected' => $progress === $value ? 'true' : 'false',
        ],
      ];
    }

    return $cell;
  }

  /**
   * Why a status cannot be changed, for the tooltip on the pills that are read
   * off the data rather than chosen.
   */
  protected function statusWhy(string $progress) {
    $why = [
      'delivered' => $this->t('Everything ordered has been received. File the invoice to finish it.'),
      'closed' => $this->t('Received in full and the invoice is filed.'),
      'cancelled' => $this->t('Closed without anything ever arriving.'),
    ];

    return $why[$progress] ?? '';
  }

  /**
   * A way straight to the receiving screen, where there is one.
   *
   * Asking the route rather than repeating its rules is deliberate. Receiving
   * is only open on an open purchase order and only for whoever may move stock,
   * and a button that leads to a page the person cannot open is worse than no
   * button: they click it, get told off, and stop trusting the screen.
   *
   * The button comes back here instead of opening in a new tab. Receiving is not
   * a thing you look up, it is a thing that changes this screen: a second tab
   * would leave the one in front of you showing the state before the goods
   * arrived. Coming back, the row answers by itself -- Delivered with its date
   * and the invoice waiting, or still open with what is left to come.
   *
   * The destination is read off the request rather than written out, so whoever
   * had sorted by promised day is returned to that order and not to the default.
   */
  protected function receiveCell(int $oid, array $row): array {
    $url = Url::fromRoute('tec_production.po_receive', ['tec_order' => $oid], [
      'query' => $this->getRedirectDestination()->getAsArray(),
    ]);
    if (!$url->access()) {
      return ['#markup' => '<span class="tec-pq__none">—</span>'];
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Receive'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['tec-pq__receive'],
        'title' => $this->t('Book in what has arrived of @order', ['@order' => $row['label']]),
      ],
      '#wrapper_attributes' => ['class' => ['tec-pq__receive-cell']],
    ];
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
    $delivery = [];
    $progress = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      $state = Purchasing::receiptState($order);
      // Nothing outstanding and nothing ever received: there is nothing to
      // chase, whether it was written off or nobody ever filled it in.
      if ($state === 'cancelled') {
        continue;
      }
      $reading = Purchasing::progress($order);
      // Goods in and invoice filed. This is what empties the screen without
      // anybody deleting rows.
      if ($reading === 'closed') {
        continue;
      }
      $oid = (int) $order->id();
      $open[$oid] = $order;
      $delivery[$oid] = $state;
      $progress[$oid] = $reading;
    }
    if (!$open) {
      return [];
    }

    $arrivals = Purchasing::deliveredOn($this->entityTypeManager, $open);
    $today = strtotime('today');
    $rank = array_flip(array_keys(Purchasing::PROGRESS));
    $rows = [];

    foreach ($open as $oid => $order) {
      $supplier = $order->hasField('field_tec_vendor') ? $order->get('field_tec_vendor')->entity : NULL;
      $author = $order->get('uid')->entity;
      $created = (int) $order->get('created')->value;
      $delivered = $arrivals[$oid] ?? NULL;
      $expected = $order->get('field_tec_expected_delivery')->value ?: NULL;
      $status = $progress[$oid];
      $coming = $status === 'open' || in_array($status, Purchasing::PROGRESS_MANUAL, TRUE);

      $due = $expected ? strtotime($expected) : NULL;
      $invoice = $order->get('field_tec_supplier_invoice')->entity;

      $rows[] = [
        'id' => $oid,
        'label' => $order->label() ?: ('#' . $oid),
        'supplier' => $supplier ? (string) $supplier->label() : '',
        'supplier_url' => $supplier && $supplier->hasLinkTemplate('canonical') ? $supplier->toUrl() : NULL,
        'status' => $status,
        'delivery' => $delivery[$oid],
        'coming' => $coming,
        'invoice' => $invoice ? (string) $invoice->getFilename() : NULL,
        'created' => $created,
        'author' => $author ? (string) $author->getDisplayName() : (string) $this->t('Unknown'),
        'expected' => $expected,
        'delivered' => $delivered,
        'days' => $this->daysBetween($created, $delivered ?: $today),
        'overdue' => $coming && $due !== NULL && $due < $today,
        'sort' => [
          'order' => $order->label() ?: ('#' . $oid),
          'supplier' => $supplier ? (string) $supplier->label() : '',
          'status' => $rank[$status] ?? count($rank),
          'delivery' => array_search($delivery[$oid], array_keys(self::DELIVERY), TRUE),
          'created' => $created,
          'author' => $author ? (string) $author->getDisplayName() : '',
          'expected' => $due,
          'delivered' => $delivered,
          'days' => $this->daysBetween($created, $delivered ?: $today),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Whole days between two moments, counted the way a calendar counts them.
   *
   * Both ends are dropped to midnight first. Subtracting the raw timestamps is
   * what this did until 17 August 2026, and it was wrong by a day most of the
   * time: an order placed on the 14th at eleven in the morning, read on the
   * 17th, gave two days and nineteen hours, and the floor threw the answer away
   * as 2. What anyone means by "how many days has this taken" is how many dates
   * have turned over, which is 3.
   *
   * Rounding rather than flooring the division covers the hour a daylight
   * saving change would add or remove. Thailand does not have one, but this
   * function should not be the reason a site somewhere else is out by a day.
   */
  protected function daysBetween(int $from, int $to): int {
    $days = (int) round((strtotime('today', $to) - strtotime('today', $from)) / 86400);

    return max(0, $days);
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
