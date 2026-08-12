<?php

namespace Drupal\tec_production\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tec_production\Entity\ProductionEntry;
use Drupal\tec_production\LineItemDisplay;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Daily Production Log: register produced pieces per order line item.
 *
 * Flow: pick a date and an order from the active queue; a grid of the order's
 * line items appears (ordered / produced / remaining / today). Saving creates
 * one tec_production_entry per line item with a quantity, and the order's
 * field_tec_produced (X on /o/queue) is recomputed automatically via the
 * entity hooks in tec_production.module.
 */
class ProductionLogForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId() {
    return 'tec_production_log_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();

    $date = $form_state->getValue('entry_date')
      ?: ($request->query->get('date') ?: date('Y-m-d'));
    $order_id = (int) ($form_state->getValue('order_id')
      ?: $request->query->get('order', 0));

    $form['#attached']['library'][] = 'tec_production/production_log';
    // Popup styling for line item thumbnails (order-page pattern).
    $form['#attached']['library'][] = 'simple_popup_views/simple_popup_views';

    // Day summary card.
    $day_total = $this->sumForDate($date);
    $form['summary'] = [
      '#markup' => '<div class="tec-log__cards">'
        . '<div class="tec-log__card"><div class="tec-log__card-title">' . $this->t('Produced on @date', ['@date' => date('j M Y', strtotime($date))]) . '</div>'
        . '<div class="tec-log__card-big">' . number_format($day_total, 0) . ' ' . $this->t('pcs') . '</div></div>'
        . '</div>',
    ];

    $form['picker'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-log__picker']],
    ];
    $form['picker']['entry_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Production Date'),
      '#default_value' => $date,
      '#required' => TRUE,
    ];
    $form['picker']['order_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Order'),
      '#options' => $this->activeOrderOptions(),
      '#empty_option' => $this->t('- Select an order -'),
      '#default_value' => $order_id ?: '',
      '#ajax' => [
        'callback' => '::updateGrid',
        'wrapper' => 'tec-log-grid',
        'progress' => ['type' => 'throbber', 'message' => NULL],
      ],
    ];
    // Non-AJAX fallback: full page reload with ?order=&date= in the URL, so
    // the grid always loads even if an AJAX response fails in the browser.
    $form['picker']['load'] = [
      '#type' => 'submit',
      '#value' => $this->t('Load'),
      '#submit' => ['::loadOrderRedirect'],
      '#limit_validation_errors' => [['order_id'], ['entry_date']],
      '#attributes' => ['class' => ['tec-log__load-btn']],
    ];

    // Line item grid for the selected order.
    $form['grid'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'tec-log-grid'],
      '#tree' => TRUE,
    ];

    $order = $order_id
      ? $this->entityTypeManager->getStorage('tec_order')->load($order_id)
      : NULL;

    if ($order) {
      $produced_by_line = $this->producedByLineItem($order_id);
      $header = [
        ['data' => '', 'class' => ['tec-log__img-col']],
        $this->t('Product'),
        $this->t('Material'),
        $this->t('Color'),
        $this->t('Size'),
        ['data' => $this->t('Ordered'), 'class' => ['tec-log__num-col']],
        ['data' => $this->t('Produced'), 'class' => ['tec-log__num-col']],
        ['data' => $this->t('Remaining'), 'class' => ['tec-log__num-col']],
        ['data' => $this->t('Today'), 'class' => ['tec-log__today-col']],
      ];
      $form['grid']['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#empty' => $this->t('This order has no line items.'),
        '#attributes' => ['class' => ['tec-log__table']],
      ];

      $tot_ordered = $tot_produced = 0;
      foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
        $lid = (int) $line->id();
        $ordered = $line->hasField('field_tec_quantity') && !$line->get('field_tec_quantity')->isEmpty()
          ? (int) $line->get('field_tec_quantity')->value : 0;
        $produced = (int) ($produced_by_line[$lid] ?? 0);
        $remaining = max(0, $ordered - $produced);
        $tot_ordered += $ordered;
        $tot_produced += $produced;

        $product = $line->hasField('field_tec_product') ? $line->get('field_tec_product')->entity : NULL;
        $color = LineItemDisplay::colorVariation($line);
        $size = $line->hasField('field_tec_size_variation') ? $line->get('field_tec_size_variation')->entity : NULL;

        $row = [];
        $row['image'] = $this->imageCell($color);
        $row['product'] = ['#markup' => $product ? $product->label() : $this->t('(no product)')];
        $row['material'] = ['#markup' => LineItemDisplay::materialLabel($product)];
        $row['color'] = ['#markup' => LineItemDisplay::colorLabel($color)];
        $row['size'] = ['#markup' => LineItemDisplay::sizeLabel($size)];
        $row['ordered'] = [
          '#markup' => number_format($ordered, 0),
          '#wrapper_attributes' => ['class' => ['tec-log__num']],
        ];
        $row['produced'] = [
          '#markup' => number_format($produced, 0),
          '#wrapper_attributes' => ['class' => ['tec-log__num']],
        ];
        $row['remaining'] = [
          '#markup' => '<strong>' . number_format($remaining, 0) . '</strong>',
          '#wrapper_attributes' => ['class' => ['tec-log__num']],
        ];
        $row['qty'] = [
          '#type' => 'number',
          '#title' => $this->t('Produced today'),
          '#title_display' => 'invisible',
          '#min' => 0,
          '#step' => 1,
          '#default_value' => '',
          '#attributes' => ['class' => ['tec-log__qty-input']],
          '#wrapper_attributes' => ['class' => ['tec-log__num']],
        ];
        $row['#attributes'] = $remaining === 0
          ? ['class' => ['tec-log__row--done']]
          : [];
        $form['grid']['table'][$lid] = $row;
      }

      // Totals row (display only).
      $form['grid']['table']['totals'] = [
        '#attributes' => ['class' => ['tec-log__totals']],
        'image' => ['#markup' => ''],
        'product' => ['#markup' => '<strong>' . $this->t('TOTAL') . '</strong>'],
        'material' => ['#markup' => ''],
        'color' => ['#markup' => ''],
        'size' => ['#markup' => ''],
        'ordered' => [
          '#markup' => '<strong>' . number_format($tot_ordered, 0) . '</strong>',
          '#wrapper_attributes' => ['class' => ['tec-log__num']],
        ],
        'produced' => [
          '#markup' => '<strong>' . number_format($tot_produced, 0) . '</strong>',
          '#wrapper_attributes' => ['class' => ['tec-log__num']],
        ],
        'remaining' => [
          '#markup' => '<strong>' . number_format(max(0, $tot_ordered - $tot_produced), 0) . '</strong>',
          '#wrapper_attributes' => ['class' => ['tec-log__num']],
        ],
        'qty' => ['#markup' => ''],
      ];

      $form['grid']['actions'] = [
        '#type' => 'actions',
        'save' => [
          '#type' => 'submit',
          '#value' => $this->t('Save production'),
          '#button_type' => 'primary',
        ],
      ];
    }

    // Recent entries with delete links.
    $form['history'] = $this->buildHistory();

    return $form;
  }

  /**
   * AJAX callback: refresh the line item grid.
   */
  public function updateGrid(array &$form, FormStateInterface $form_state) {
    return $form['grid'];
  }

  /**
   * Submit handler for the Load button: reload the page with query params.
   */
  public function loadOrderRedirect(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('tec_production.log', [], [
      'query' => [
        'order' => (int) $form_state->getValue('order_id'),
        'date' => $form_state->getValue('entry_date'),
      ],
    ]);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('order_id')) {
      $form_state->setErrorByName('order_id', $this->t('Select an order.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $date = $form_state->getValue('entry_date');
    $order_id = (int) $form_state->getValue('order_id');
    $table = $form_state->getValue(['grid', 'table']) ?: [];

    $count = 0;
    $pieces = 0;
    foreach ($table as $lid => $row) {
      $qty = (int) ($row['qty'] ?? 0);
      if ($qty <= 0 || !is_numeric($lid)) {
        continue;
      }
      ProductionEntry::create([
        'entry_date' => $date,
        'order_id' => $order_id,
        'line_item_id' => (int) $lid,
        'quantity' => $qty,
      ])->save();
      $count++;
      $pieces += $qty;
    }

    if ($count) {
      $this->messenger()->addStatus($this->t('@count entries saved (@pcs pcs). Queue updated.', [
        '@count' => $count,
        '@pcs' => number_format($pieces, 0),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('Nothing to save: all quantities were empty.'));
    }

    // Redirect (instead of rebuild) so a page refresh cannot duplicate rows.
    $form_state->setRedirect('tec_production.log', [], [
      'query' => ['order' => $order_id, 'date' => $date],
    ]);
  }

  /**
   * Options for the order select: active queue orders, next-to-produce first.
   */
  protected function activeOrderOptions(): array {
    $storage = $this->entityTypeManager->getStorage('tec_order');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_sales_order')
      ->condition('field_tec_order_status', ProductionQueueForm::ACTIVE_STATUSES, 'IN')
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return [];
    }

    $status_options = [];
    $defs = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('tec_order');
    if (isset($defs['field_tec_order_status'])) {
      $status_options = $defs['field_tec_order_status']->getSetting('allowed_values');
    }

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      $position = $order->hasField('field_tec_queue_position') && !$order->get('field_tec_queue_position')->isEmpty()
        ? (int) $order->get('field_tec_queue_position')->value
        : PHP_INT_MAX;
      $status = $order->get('field_tec_order_status')->value ?: 'draft';
      $rows[] = [
        'id' => (int) $order->id(),
        'position' => $position,
        'label' => ($order->label() ?: ('#' . $order->id()))
          . ' — ' . ($status_options[$status] ?? $status),
      ];
    }
    // Same visual order as /o/queue top-to-bottom: newest / last-to-produce
    // first, next-to-produce at the end.
    usort($rows, static fn($a, $b) => [$b['position'], $b['id']] <=> [$a['position'], $a['id']]);

    $options = [];
    foreach ($rows as $row) {
      $options[$row['id']] = $row['label'];
    }
    return $options;
  }

  /**
   * SUM of entry quantities per line item for one order.
   */
  protected function producedByLineItem(int $order_id): array {
    $result = \Drupal::entityQueryAggregate('tec_production_entry')
      ->condition('order_id', $order_id)
      ->groupBy('line_item_id')
      ->aggregate('quantity', 'SUM')
      ->accessCheck(FALSE)
      ->execute();
    $sums = [];
    foreach ($result as $row) {
      $sums[(int) $row['line_item_id']] = (int) $row['quantity_sum'];
    }
    return $sums;
  }

  /**
   * Total pieces registered on a given date.
   */
  protected function sumForDate(string $date): int {
    $result = \Drupal::entityQueryAggregate('tec_production_entry')
      ->condition('entry_date', $date)
      ->aggregate('quantity', 'SUM')
      ->accessCheck(FALSE)
      ->execute();
    return (int) ($result[0]['quantity_sum'] ?? 0);
  }

  /**
   * Thumbnail cell: 40x40 image with a large hover popup.
   *
   * Same format as the line items table on the order page (Simple Popup
   * Views markup: small_40x40 visible, large inside the popup). The popup is
   * opened via a CSS :hover rule in production-log.css because the contrib
   * JS binds on page load and would miss this AJAX-loaded grid.
   */
  protected function imageCell($color): array {
    if (!$color || !$color->hasField('field_tec_images') || $color->get('field_tec_images')->isEmpty()) {
      return ['#markup' => ''];
    }
    $file = $color->get('field_tec_images')->entity;
    if (!$file) {
      return ['#markup' => ''];
    }
    $uri = $file->getFileUri();
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['simple-popup-views-global']],
      'wrapper' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['spv-popup-wrapper']],
        'link' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['spv-popup-link', 'spv_on_hover']],
          'img' => [
            '#theme' => 'image_style',
            '#style_name' => 'small_40x40',
            '#uri' => $uri,
            '#attributes' => ['loading' => 'lazy'],
          ],
        ],
        'content' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['spv-popup-content', 'spv-top-popup']],
          'inside' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['spv-inside-popup']],
            'img' => [
              '#theme' => 'image_style',
              '#style_name' => 'large',
              '#uri' => $uri,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Table with the latest log entries and delete links.
   */
  protected function buildHistory(): array {
    $storage = $this->entityTypeManager->getStorage('tec_production_entry');
    $ids = $storage->getQuery()
      ->sort('created', 'DESC')
      ->range(0, 30)
      ->accessCheck(FALSE)
      ->execute();

    $build = [
      '#type' => 'details',
      '#title' => $this->t('Latest entries'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['tec-log__history']],
    ];

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $entry) {
      $order = $entry->get('order_id')->entity;
      $line = $entry->get('line_item_id')->entity;
      $user = $entry->get('uid')->entity;

      $item_label = '—';
      if ($line) {
        $parts = [];
        $product = $line->hasField('field_tec_product') ? $line->get('field_tec_product')->entity : NULL;
        if ($product) {
          $parts[] = $product->label();
        }
        $material_label = LineItemDisplay::materialLabel($product);
        if ($material_label !== '—') {
          $parts[] = $material_label;
        }
        $color_label = LineItemDisplay::colorLabel(LineItemDisplay::colorVariation($line));
        if ($color_label !== '—') {
          $parts[] = $color_label;
        }
        $size = $line->hasField('field_tec_size_variation') ? $line->get('field_tec_size_variation')->entity : NULL;
        $size_label = LineItemDisplay::sizeLabel($size);
        if ($size_label !== '—') {
          $parts[] = $size_label;
        }
        $item_label = $parts ? implode(' · ', $parts) : ('#' . $line->id());
      }

      $created = (int) $entry->get('created')->value;
      $logged = $created
        ? \Drupal::service('date.formatter')->format($created, 'custom', 'j M Y · H:i')
        : '—';

      $rows[] = [
        date('j M Y', strtotime($entry->get('entry_date')->value ?: 'now')),
        $logged,
        $order ? $order->label() : '—',
        $item_label,
        ['data' => number_format((int) $entry->get('quantity')->value, 0), 'class' => ['tec-log__num']],
        $user ? $user->getDisplayName() : '—',
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Delete'),
            '#url' => Url::fromRoute('entity.tec_production_entry.delete_form', [
              'tec_production_entry' => $entry->id(),
            ], [
              'query' => ['destination' => '/production/log'],
            ]),
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Production Date'),
        $this->t('Logged'),
        $this->t('Order'),
        $this->t('Item'),
        $this->t('Qty'),
        $this->t('By'),
        '',
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No production registered yet.'),
      '#attributes' => ['class' => ['tec-log__table']],
    ];

    return $build;
  }

}
