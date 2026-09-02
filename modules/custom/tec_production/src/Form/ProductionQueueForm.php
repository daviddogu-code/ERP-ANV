<?php

namespace Drupal\tec_production\Form;

use Drupal\tec_production\SalesStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Orders on Queue: draggable production queue with computed deadlines.
 *
 * Business rules documented in README.md. Like the original Google Sheet:
 * bottom row = produced first, newest orders at the top, deadlines grow
 * upward.
 */
class ProductionQueueForm extends FormBase {

  /**
   * Sales order statuses that appear on the queue.
   *
   * EXW: Completed, Ready for collection and Shipped stay until Closed.
   */
  const ACTIVE_STATUSES = SalesStatus::ACTIVE;

  /**
   * Post-manufacturing statuses still on the queue (admin / waiting pickup).
   *
   * They do not consume manufacturing capacity or the deadline chain.
   * Completed = manufactured. Ready for collection = EXW pickup.
   * Shipped = goods already left on at least one shipment; leftover stays.
   */
  const POST_MFG_STATUSES = SalesStatus::POST_MFG;

  /**
   * Forward-only status transitions from the queue and the order card.
   *
   * Open is sealed with Confirm, not from here. The last step (Shipped →
   * Closed) removes the order from the queue. Corrections that go backwards
   * stay on the order edit form.
   */
  const STATUS_NEXT = SalesStatus::NEXT;

  /**
   * Column metadata taken from the "Orders on Queue" Google Sheet.
   *
   * Taxonomy term label => [Lao/Thai label, color group]. The order of this
   * array defines the column order; the displayed name is the taxonomy term
   * label itself. Terms not listed here (e.g. textile) are appended after,
   * ordered by term weight.
   */
  const SHEET_COLUMNS = [
    'Gloves' => ['ນວມ', 'green'],
    'MMA Gloves' => ['ນວມ ເອັມ ເອັມ ເອ', 'green'],
    'Shin guards' => ['ສະນັບແຂ້ງ', 'blue'],
    'Head guards' => ['ຫົວ', 'blue'],
    'Kick Pads' => ['ເປົ້າເຕະ', 'yellow'],
    'Belly pads' => ['ເຂັມຂັດ', 'yellow'],
    'Focus mitts' => ['ເປົ້າມື', 'yellow'],
    'Kick shields' => ['ເປົ້າເຕະສີ່ຫຼ່ຽມ', 'yellow'],
    'Body shield' => ['ເສື້ອເກາະ', 'yellow'],
    'Calf Kick Pads' => ['ສະນັບຂາ', 'yellow'],
    'Thigh Pads' => ['ເປົ້າຂາ', 'yellow'],
    'Donuts' => ['ໂດນັດ', 'yellow'],
    'Wall bags' => ['ເປົ້ານິ່ງ', 'yellow'],
    'Heavy bags' => ['ກະສອບຊາຍ', 'yellow'],
    'Rope dividers' => ['กันสะบัดเชือก', 'yellow'],
    'Rope covers' => ['ປອກເຊືອກ', 'yellow'],
    'Corner pads' => ['แผ่นรองมุม', 'yellow'],
    'Buckle Covers' => ['กระจับมุม', 'yellow'],
  ];

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId() {
    return 'tec_production_queue_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->configFactory()->get('tec_production.settings');
    $staff = (float) $settings->get('capacity_staff');
    $overtime = (float) $settings->get('capacity_overtime');
    $factor = (float) $settings->get('capacity_factor');
    $days_month = (float) ($settings->get('days_per_month') ?: 30);
    $include_open = (bool) $settings->get('include_open');

    $pieces_day = $staff * $factor / 8 * (8 + $overtime);
    $pieces_month = $pieces_day * $days_month;

    $rows = $this->loadQueueRows();

    // Category columns in the same order as the Google Sheet. All sheet
    // columns are always visible (0 when empty); other product types (e.g.
    // textile) appear only when present in the queue, appended by weight.
    $categories = [];
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids_by_label = [];
    foreach ($term_storage->loadTree('tec_product_types') as $term) {
      $tids_by_label[$term->name] = ['tid' => (int) $term->tid, 'weight' => (int) $term->weight];
    }
    foreach (array_keys(self::SHEET_COLUMNS) as $label) {
      if (isset($tids_by_label[$label])) {
        $categories[$tids_by_label[$label]['tid']] = [
          'label' => $label,
          'weight' => $tids_by_label[$label]['weight'],
          'total' => 0.0,
        ];
      }
    }
    foreach ($rows as $row) {
      foreach ($row['categories'] as $tid => $info) {
        if (!isset($categories[$tid])) {
          $categories[$tid] = ['label' => $info['label'], 'weight' => $info['weight'], 'total' => 0.0];
        }
        $categories[$tid]['total'] += $info['qty'];
      }
    }
    $sheet_order = array_flip(array_keys(self::SHEET_COLUMNS));
    uasort($categories, static function ($a, $b) use ($sheet_order) {
      $ia = $sheet_order[$a['label']] ?? (1000 + $a['weight']);
      $ib = $sheet_order[$b['label']] ?? (1000 + $b['weight']);
      return $ia <=> $ib;
    });

    // Deadline chain (production order: position 1 first) + backlog sums.
    // Post-mfg (Completed / Ready for Delivery) stays visible but does not
    // consume manufacturing capacity or backlog months.
    $today = strtotime('today');
    $cumulative = 0.0;
    $backlog_confirmed = 0.0;
    $backlog_all = 0.0;
    foreach ($rows as &$row) {
      if (!$row['is_post_mfg']) {
        $backlog_all += $row['remaining'];
        if (!$row['is_open']) {
          $backlog_confirmed += $row['remaining'];
        }
      }
      $counts = !$row['is_post_mfg'] && (!$row['is_open'] || $include_open);
      if ($counts && $pieces_day > 0) {
        $cumulative += $row['remaining'];
        $row['deadline'] = $today + (int) round($cumulative / $pieces_day * 86400);
      }
      else {
        $row['deadline'] = NULL;
      }
    }
    unset($row);

    // Display like the Google Sheet: newest / last-to-produce at the TOP,
    // next-to-produce at the BOTTOM. Deadlines grow upward.
    $rows = array_reverse($rows);

    $total_pieces = array_sum(array_column($rows, 'total'));
    $total_produced = array_sum(array_column($rows, 'produced'));

    $form['#attached']['library'][] = 'tec_production/production_queue';

    // Summary header.
    $form['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-queue__summary']],
      'backlog' => [
        '#markup' => '<div class="tec-queue__cards">'
          . $this->summaryCard($this->t('Capacity'), number_format($pieces_day, 1) . ' / day', number_format($pieces_month, 0) . ' / month')
          . $this->summaryCard($this->t('Backlog confirmed'), $pieces_month > 0 ? number_format($backlog_confirmed / $pieces_month, 1) . ' ' . $this->t('months') : '—', number_format($backlog_confirmed, 0) . ' ' . $this->t('pcs'))
          . $this->summaryCard($this->t('Backlog with pipeline'), $pieces_month > 0 ? number_format($backlog_all / $pieces_month, 1) . ' ' . $this->t('months') : '—', number_format($backlog_all, 0) . ' ' . $this->t('pcs'))
          . $this->summaryCard($this->t('Queue'), count($rows) . ' ' . $this->t('orders'), number_format($total_pieces - $total_produced, 0) . ' ' . $this->t('pcs remaining'))
          . '</div>',
      ],
    ];

    // Capacity / projection settings.
    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Capacity & projection settings'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['tec-queue__settings']],
    ];
    $form['settings']['capacity_staff'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of staff'),
      '#default_value' => $staff,
      '#step' => 0.1,
      '#min' => 0,
    ];
    $form['settings']['capacity_overtime'] = [
      '#type' => 'number',
      '#title' => $this->t('Overtime hours per day'),
      '#default_value' => $overtime,
      '#step' => 0.5,
      '#min' => 0,
    ];
    $form['settings']['capacity_factor'] = [
      '#type' => 'number',
      '#title' => $this->t('Productivity factor'),
      '#default_value' => $factor,
      '#step' => 0.1,
      '#min' => 0,
      '#description' => $this->t('Pieces/day = staff × factor ÷ 8 × (8 + overtime). Current: @n', ['@n' => number_format($pieces_day, 2)]),
    ];
    $form['settings']['include_open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Open orders (no deposit yet, grey rows) in the deadline projection'),
      '#default_value' => $include_open,
    ];
    $form['settings']['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply settings'),
      '#submit' => ['::applySettings'],
      '#limit_validation_errors' => [['capacity_staff'], ['capacity_overtime'], ['capacity_factor'], ['include_open']],
    ];

    $form['legend'] = $this->statusLegend();

    // Queue table, laid out like the Google Sheet:
    // NO | ORDER | status | categories | TOTAL | Deadline | Produced | Remaining.
    $header = [
      ['data' => $this->headerCell('NO', 'ລຳດັບ'), 'class' => ['tec-queue__no-col']],
      ['data' => $this->headerCell('ORDER', 'ອໍເດີ')],
      ['data' => $this->headerCell('STATUS', '')],
    ];
    foreach ($categories as $cat) {
      $meta = self::SHEET_COLUMNS[$cat['label']] ?? ['', 'other'];
      $header[] = [
        'data' => $this->headerCell($cat['label'], $meta[0]),
        'class' => ['tec-queue__cat', 'tec-queue__cat--' . $meta[1]],
      ];
    }
    $header[] = ['data' => $this->headerCell('TOTAL', 'ທັງໝົດ'), 'class' => ['tec-queue__total-col']];
    $header[] = ['data' => $this->headerCell('DEADLINE', ''), 'class' => ['tec-queue__end-col']];
    $header[] = ['data' => $this->headerCell('PRODUCED', ''), 'class' => ['tec-queue__end-col']];
    $header[] = ['data' => $this->headerCell('REMAINING', ''), 'class' => ['tec-queue__end-col']];
    $header[] = $this->t('Weight');

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No orders on queue.'),
      '#prefix' => '<div class="tec-queue__table-wrap">',
      '#suffix' => '</div>',
      '#attributes' => [
        'id' => 'tec-production-queue',
        'data-pieces-day' => $pieces_day,
        'data-include-open' => $include_open ? '1' : '0',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'queue-order-weight',
        ],
      ],
    ];

    // TOTAL row pinned at the top, like row 4 of the sheet (not draggable).
    $totals_row = [
      '#attributes' => ['class' => ['tec-queue__totals']],
      'no' => ['#markup' => ''],
      'order' => ['#markup' => '<strong>TOTAL</strong>'],
      'status' => ['#markup' => ''],
    ];
    foreach ($categories as $tid => $cat) {
      $totals_row['cat_' . $tid] = [
        '#markup' => '<strong>' . number_format($cat['total'], 0) . '</strong>',
        '#wrapper_attributes' => ['class' => ['tec-queue__num']],
      ];
    }
    $totals_row['total'] = [
      '#markup' => '<strong>' . number_format($total_pieces, 0) . '</strong>',
      '#wrapper_attributes' => ['class' => ['tec-queue__num', 'tec-queue__total-cell']],
    ];
    $totals_row['deadline'] = [
      '#markup' => '',
      '#wrapper_attributes' => ['class' => ['tec-queue__end-cell']],
    ];
    $totals_row['produced'] = [
      '#markup' => '<strong>' . number_format($total_produced, 0) . '</strong>',
      '#wrapper_attributes' => ['class' => ['tec-queue__num', 'tec-queue__end-cell']],
    ];
    $totals_row['remaining'] = [
      '#markup' => '<strong>' . number_format($backlog_all, 0) . '</strong>',
      '#wrapper_attributes' => ['class' => ['tec-queue__num', 'tec-queue__end-cell']],
    ];
    $totals_row['weight'] = ['#markup' => ''];
    $form['table']['totals'] = $totals_row;

    $delta = max(count($rows), 1);
    $display_weight = 0;
    foreach ($rows as $row) {
      $display_weight++;
      $oid = $row['id'];
      $classes = ['draggable', 'tec-queue__row'];
      if ($row['is_open']) {
        $classes[] = 'tec-queue__row--open';
      }
      elseif ($row['status'] === SalesStatus::ON_PRODUCTION) {
        $classes[] = 'tec-queue__row--producing';
      }
      elseif ($row['status'] === SalesStatus::COMPLETED) {
        $classes[] = 'tec-queue__row--completed';
      }
      elseif ($row['status'] === SalesStatus::READY_FOR_COLLECTION) {
        $classes[] = 'tec-queue__row--ready';
      }
      elseif ($row['status'] === SalesStatus::SHIPPED) {
        $classes[] = 'tec-queue__row--shipped';
      }

      $element = [
        '#attributes' => [
          'class' => $classes,
          'data-remaining' => $row['remaining'],
          'data-open' => $row['is_open'] ? '1' : '0',
          'data-post-mfg' => $row['is_post_mfg'] ? '1' : '0',
          'data-status' => $row['status'],
        ],
      ];
      $element['no'] = [
        '#markup' => '<span class="tec-queue__no">' . $row['position'] . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-queue__no-cell']],
      ];
      $element['order'] = [
        '#type' => 'link',
        '#title' => $row['label'],
        '#url' => Url::fromUri('internal:/tec_order/' . $oid),
        '#attributes' => ['title' => $row['label']],
        '#wrapper_attributes' => ['class' => ['tec-queue__order-cell']],
      ];
      // Status is read-only; advance is a forward-only button (no dropdown,
      // no downgrade). Corrections backwards stay on the order edit form.
      $status_label = SalesStatus::label($row['status']);
      $pill = SalesStatus::pill($row['status']);
      $pill['#attributes']['class'][] = 'tec-queue__status';
      $pill['#attributes']['title'] = $status_label;
      $element['status'] = [
        '#type' => 'container',
        '#wrapper_attributes' => ['class' => ['tec-queue__status-cell']],
        'label' => $pill,
      ];
      $next = SalesStatus::next($row['status']);
      if ($next !== NULL) {
        $next_label = SalesStatus::label($next);
        // Arrow only — next status lives in the tooltip so the current
        // status label stays readable in the narrow STATUS column.
        $element['status']['advance'] = [
          '#type' => 'submit',
          '#value' => '→',
          '#name' => 'advance_' . $oid,
          '#order_id' => $oid,
          '#next_status' => $next,
          '#submit' => ['::advanceStatus'],
          '#limit_validation_errors' => [],
          // No Drupal "button" classes — styled as a flat inline link in CSS.
          '#attributes' => [
            'class' => ['tec-queue__advance'],
            'title' => $this->t('Advance to @status (forward only)', [
              '@status' => $next_label,
            ]),
            'aria-label' => $this->t('Advance to @status', [
              '@status' => $next_label,
            ]),
          ],
        ];
      }
      foreach ($categories as $tid => $cat) {
        $qty = $row['categories'][$tid]['qty'] ?? 0;
        $element['cat_' . $tid] = [
          '#markup' => $qty > 0 ? number_format($qty, 0) : '<span class="tec-queue__zero">0</span>',
          '#wrapper_attributes' => ['class' => ['tec-queue__num']],
        ];
      }
      $element['total'] = [
        '#markup' => '<strong>' . number_format($row['total'], 0) . '</strong>',
        '#wrapper_attributes' => ['class' => ['tec-queue__num', 'tec-queue__total-cell']],
      ];
      $element['deadline'] = [
        '#markup' => '<span class="tec-queue__deadline">' . ($row['deadline'] ? date('j M Y', $row['deadline']) : '—') . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-queue__num', 'tec-queue__deadline-cell', 'tec-queue__end-cell']],
      ];
      // Produced is owned by the Daily Production Log and read-only here for
      // everyone. Production (including initial balances for legacy orders)
      // is registered only on /production/log, so a stale queue tab can
      // never overwrite log-calculated values.
      $element['produced'] = [
        '#markup' => '<span class="tec-queue__produced-locked" title="' . $this->t('Calculated from the Daily Production Log') . '">' . number_format($row['produced'], 0) . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-queue__num', 'tec-queue__end-cell']],
      ];
      $element['remaining'] = [
        '#markup' => '<span class="tec-queue__remaining">' . number_format($row['remaining'], 0) . '</span>',
        '#wrapper_attributes' => ['class' => ['tec-queue__num', 'tec-queue__end-cell']],
      ];
      $element['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $display_weight,
        '#delta' => $delta,
        '#attributes' => ['class' => ['queue-order-weight']],
      ];

      $form['table'][$oid] = $element;
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save queue'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Advance one order one step forward in the status flow.
   *
   * Does not save queue positions. Never allows downgrade.
   */
  public function advanceStatus(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $oid = (int) ($trigger['#order_id'] ?? 0);
    $expected_next = (string) ($trigger['#next_status'] ?? '');
    if ($oid < 1 || $expected_next === '') {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('tec_order');
    $order = $storage->load($oid);
    if (!$order || !$order->hasField('field_tec_order_status')) {
      $this->messenger()->addError($this->t('Order #@id not found.', ['@id' => $oid]));
      return;
    }

    $current = SalesStatus::of($order);
    $allowed_next = SalesStatus::next($current);
    if ($allowed_next === NULL || $allowed_next !== $expected_next) {
      $this->messenger()->addWarning($this->t(
        '@order status changed meanwhile (@status). Refresh and try again.',
        [
          '@order' => $order->label() ?: ('#' . $oid),
          '@status' => SalesStatus::label($current),
        ]
      ));
      $form_state->setRedirect('tec_production.queue');
      return;
    }

    $applied = SalesStatus::applyNext($order);
    $next_label = SalesStatus::label($applied ?? $allowed_next);

    if ($allowed_next === SalesStatus::CLOSED) {
      $this->messenger()->addStatus($this->t(
        '@order marked @status and left the queue.',
        [
          '@order' => $order->label() ?: ('#' . $oid),
          '@status' => $next_label,
        ]
      ));
    }
    else {
      $this->messenger()->addStatus($this->t(
        '@order advanced to @status.',
        [
          '@order' => $order->label() ?: ('#' . $oid),
          '@status' => $next_label,
        ]
      ));
    }

    $form_state->setRedirect('tec_production.queue');
  }

  /**
   * Save capacity/projection settings only.
   */
  public function applySettings(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('tec_production.settings')
      ->set('capacity_staff', (float) $form_state->getValue('capacity_staff'))
      ->set('capacity_overtime', (float) $form_state->getValue('capacity_overtime'))
      ->set('capacity_factor', (float) $form_state->getValue('capacity_factor'))
      ->set('include_open', (bool) $form_state->getValue('include_open'))
      ->save();
    $this->messenger()->addStatus($this->t('Settings updated. Deadlines recalculated.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('table') ?: [];

    // Normalize tabledrag weights into clean sequential positions. The table
    // shows last-to-produce at the top, so the top row (lowest weight) gets
    // the HIGHEST production position.
    uasort($values, static fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    $storage = $this->entityTypeManager->getStorage('tec_order');
    $position = count($values) + 1;
    $saved = 0;
    foreach ($values as $oid => $row_values) {
      $position--;
      $order = $storage->load($oid);
      if (!$order) {
        continue;
      }
      // Only queue positions are saved here. field_tec_produced is owned by
      // the Daily Production Log and never written from this form.
      if ($order->hasField('field_tec_queue_position') && (int) $order->get('field_tec_queue_position')->value !== $position) {
        $order->set('field_tec_queue_position', $position);
        $order->save();
        $saved++;
      }
    }

    $this->messenger()->addStatus($this->t('Queue saved (@n orders updated). Deadlines recalculated.', ['@n' => $saved]));
  }

  /**
   * Load active sales orders as queue rows, sorted by queue position.
   */
  protected function loadQueueRows(): array {
    $storage = $this->entityTypeManager->getStorage('tec_order');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_sales_order')
      ->condition('field_tec_order_status', array_merge(self::ACTIVE_STATUSES, array_keys(SalesStatus::MIGRATE)), 'IN')
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return [];
    }

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      if ($order->hasField('field_tec_other_charges') && (bool) $order->get('field_tec_other_charges')->value) {
        continue;
      }
      $status = SalesStatus::of($order);
      $total = 0.0;
      $cats = [];
      if ($order->hasField('field_tec_line_items')) {
        foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
          $qty = $line->hasField('field_tec_quantity') && !$line->get('field_tec_quantity')->isEmpty()
            ? (float) $line->get('field_tec_quantity')->value
            : 0.0;
          $total += $qty;

          $product = $line->hasField('field_tec_product') ? $line->get('field_tec_product')->entity : NULL;
          if ($product && $product->hasField('field_tec_product_type') && !$product->get('field_tec_product_type')->isEmpty()) {
            $term = $product->get('field_tec_product_type')->entity;
            if ($term) {
              $tid = (int) $term->id();
              if (!isset($cats[$tid])) {
                $cats[$tid] = ['label' => $term->label(), 'weight' => (int) $term->getWeight(), 'qty' => 0.0];
              }
              $cats[$tid]['qty'] += $qty;
            }
          }
        }
      }

      $produced = $order->hasField('field_tec_produced') && !$order->get('field_tec_produced')->isEmpty()
        ? (float) $order->get('field_tec_produced')->value
        : 0.0;

      $position = $order->hasField('field_tec_queue_position') && !$order->get('field_tec_queue_position')->isEmpty()
        ? (int) $order->get('field_tec_queue_position')->value
        : PHP_INT_MAX;

      $oid = (int) $order->id();
      $rows[] = [
        'id' => $oid,
        'label' => $order->label() ?: ('#' . $oid),
        'status' => $status,
        'status_label' => SalesStatus::label($status),
        'is_open' => in_array($status, ['draft', 'pending_deposit'], TRUE),
        'is_post_mfg' => in_array($status, self::POST_MFG_STATUSES, TRUE),
        'categories' => $cats,
        'total' => $total,
        'produced' => $produced,
        'remaining' => max(0.0, $total - $produced),
        'position' => $position,
      ];
    }

    // Position asc; orders never positioned go last (newest last).
    usort($rows, static function ($a, $b) {
      if ($a['position'] === $b['position']) {
        return $a['id'] <=> $b['id'];
      }
      return $a['position'] <=> $b['position'];
    });

    // Re-index positions to sane sequential values for the weight elements.
    $i = 0;
    foreach ($rows as &$row) {
      $row['position'] = ++$i;
    }
    unset($row);

    return $rows;
  }

  /**
   * Read-only factory status sequence. Not clickable; the row pills advance.
   */
  protected function statusLegend(): array {
    $legend = SalesStatus::legend();
    $legend['#attributes']['class'][] = 'tec-queue__legend';
    return $legend;
  }

  /**
   * Small summary card markup.
   */
  protected function summaryCard($title, $big, $small): string {
    return '<div class="tec-queue__card"><div class="tec-queue__card-title">' . $title . '</div>'
      . '<div class="tec-queue__card-big">' . $big . '</div>'
      . '<div class="tec-queue__card-small">' . $small . '</div></div>';
  }

  /**
   * Two-line header cell: English on top, Lao/Thai underneath (as the sheet).
   */
  protected function headerCell(string $en, string $lao): array {
    return [
      '#markup' => '<span class="tec-queue__h-en">' . $en . '</span>'
        . ($lao !== '' ? '<br><span class="tec-queue__h-lo">' . $lao . '</span>' : ''),
    ];
  }

}
