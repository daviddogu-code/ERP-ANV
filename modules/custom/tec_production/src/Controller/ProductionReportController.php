<?php

namespace Drupal\tec_production\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\tec_production\Form\ProductionQueueForm;
use Drupal\tec_production\LineItemDisplay;
use Symfony\Component\HttpFoundation\Request;

/**
 * Production Report (phase 3): month view over the Daily Production Log.
 *
 * Rows = days of the selected month, columns = product categories (same
 * set and order as Orders on Queue), cells = pieces registered that day.
 * Each day with production expands to an order / color / size breakdown.
 * Replaces the COUNT PRODUCTION Google Sheet.
 */
class ProductionReportController extends ControllerBase {

  /**
   * Page callback for /production/report.
   */
  public function build(Request $request): array {
    // Selected month: ?month=YYYY-MM, defaults to the current month.
    $month = $request->query->get('month', '');
    if (!preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
      $month = date('Y-m');
    }
    $first = $month . '-01';
    $days_in_month = (int) date('t', strtotime($first));
    $last = $month . '-' . str_pad((string) $days_in_month, 2, '0', STR_PAD_LEFT);

    [$per_day, $categories, $details] = $this->collectMonth($first, $last);

    $month_total = 0.0;
    foreach ($per_day as $day_data) {
      $month_total += $day_data['total'];
    }
    $days_with_production = count(array_filter($per_day, static fn($d) => $d['total'] > 0));

    // Theoretical capacity from the queue settings, real capacity measured
    // from the log (pieces per day with activity).
    $settings = $this->config('tec_production.settings');
    $pieces_day_theoretical = (float) $settings->get('capacity_staff')
      * (float) $settings->get('capacity_factor') / 8
      * (8 + (float) $settings->get('capacity_overtime'));
    $pieces_day_real = $days_with_production > 0 ? $month_total / $days_with_production : 0.0;

    $build = [
      '#title' => $this->t('Production Report'),
      '#attached' => ['library' => ['tec_production/production_report']],
      // New log entries must refresh this page.
      '#cache' => ['max-age' => 0],
    ];

    $build['nav'] = $this->buildMonthNav($month);

    $build['cards'] = [
      '#markup' => '<div class="tec-report__cards">'
        . $this->card($this->t('Month total'), number_format($month_total, 0), $this->t('pieces'))
        . $this->card($this->t('Days with production'), (string) $days_with_production, $this->t('of @n', ['@n' => $days_in_month]))
        . $this->card($this->t('Real capacity'), $days_with_production ? number_format($pieces_day_real, 1) : '—', $this->t('pieces / active day'))
        . $this->card($this->t('Planned capacity'), number_format($pieces_day_theoretical, 1), $this->t('pieces / day (queue)'))
        . '</div>',
    ];

    $build['table'] = $this->buildTable($month, $days_in_month, $per_day, $categories, $details);

    return $build;
  }

  /**
   * Aggregate log entries of the month per day and category.
   *
   * @return array
   *   [per_day, categories, details]:
   *   - per_day: day number => ['total' => x, 'cats' => [tid => qty]]
   *   - categories: tid => label, ordered like the queue columns
   *   - details: day number => list of rows for the drill-down.
   */
  protected function collectMonth(string $first, string $last): array {
    $storage = $this->entityTypeManager()->getStorage('tec_production_entry');
    $ids = $storage->getQuery()
      ->condition('entry_date', $first, '>=')
      ->condition('entry_date', $last, '<=')
      ->sort('entry_date')
      ->accessCheck(FALSE)
      ->execute();

    $per_day = [];
    $found_cats = [];
    $details = [];

    foreach ($storage->loadMultiple($ids) as $entry) {
      $qty = (float) $entry->get('quantity')->value;
      if ($qty == 0) {
        continue;
      }
      $day = (int) substr($entry->get('entry_date')->value, 8, 2);
      $line = $entry->get('line_item_id')->entity;
      $order = $entry->get('order_id')->entity;

      $product = $line && $line->hasField('field_tec_product') ? $line->get('field_tec_product')->entity : NULL;

      $tid = 0;
      $cat_label = (string) $this->t('(no category)');
      $cat_weight = 10000;
      if ($product && $product->hasField('field_tec_product_type') && !$product->get('field_tec_product_type')->isEmpty()) {
        $term = $product->get('field_tec_product_type')->entity;
        if ($term) {
          $tid = (int) $term->id();
          $cat_label = $term->label();
          $cat_weight = (int) $term->getWeight();
        }
      }

      $per_day += [$day => ['total' => 0.0, 'cats' => []]];
      $per_day[$day]['total'] += $qty;
      $per_day[$day]['cats'][$tid] = ($per_day[$day]['cats'][$tid] ?? 0.0) + $qty;
      $found_cats[$tid] = ['label' => $cat_label, 'weight' => $cat_weight];

      $color = $line ? LineItemDisplay::colorVariation($line) : NULL;
      $size = $line && $line->hasField('field_tec_size_variation') ? $line->get('field_tec_size_variation')->entity : NULL;
      $details[$day][] = [
        'order_id' => $order ? (int) $order->id() : 0,
        'order' => $order ? $order->label() : '—',
        'product' => $product ? $product->label() : '—',
        'material' => LineItemDisplay::materialLabel($product),
        'color' => LineItemDisplay::colorLabel($color),
        'size' => $size ? LineItemDisplay::sizeLabel($size) : '—',
        'qty' => $qty,
      ];
    }

    // Categories with activity, ordered like the queue sheet columns.
    $sheet_order = array_flip(array_keys(ProductionQueueForm::SHEET_COLUMNS));
    uasort($found_cats, static function ($a, $b) use ($sheet_order) {
      $ia = $sheet_order[$a['label']] ?? (1000 + $a['weight']);
      $ib = $sheet_order[$b['label']] ?? (1000 + $b['weight']);
      return $ia <=> $ib;
    });
    $categories = array_map(static fn($c) => $c['label'], $found_cats);

    return [$per_day, $categories, $details];
  }

  /**
   * Month navigation: previous / current label / next.
   */
  protected function buildMonthNav(string $month): array {
    $ts = strtotime($month . '-01');
    $prev = date('Y-m', strtotime('-1 month', $ts));
    $next = date('Y-m', strtotime('+1 month', $ts));
    $is_current = $month === date('Y-m');

    $link = static fn(string $m, string $text) => [
      '#type' => 'link',
      '#title' => $text,
      '#url' => Url::fromRoute('tec_production.report', [], ['query' => ['month' => $m]]),
      '#attributes' => ['class' => ['tec-report__nav-link']],
    ];

    $nav = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-report__nav']],
      'prev' => $link($prev, '← ' . date('M Y', strtotime($prev . '-01'))),
      'label' => [
        '#markup' => '<span class="tec-report__nav-current">' . date('F Y', $ts) . '</span>',
      ],
    ];
    // No point browsing into the future beyond next month.
    if (!$is_current) {
      $nav['next'] = $link($next, date('M Y', strtotime($next . '-01')) . ' →');
    }
    return $nav;
  }

  /**
   * The days x categories table with totals and per-day drill-down rows.
   */
  protected function buildTable(string $month, int $days_in_month, array $per_day, array $categories, array $details): array {
    $header = [['data' => $this->t('Day'), 'class' => ['tec-report__day-col']]];
    foreach ($categories as $label) {
      $header[] = ['data' => strtoupper($label), 'class' => ['tec-report__cat-col']];
    }
    $header[] = ['data' => $this->t('TOTAL'), 'class' => ['tec-report__total-col']];

    $rows = [];
    $cat_totals = array_fill_keys(array_keys($categories), 0.0);
    $month_total = 0.0;
    $today = date('Y-m-d');

    for ($day = 1; $day <= $days_in_month; $day++) {
      $date = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
      $data = $per_day[$day] ?? NULL;
      $has = $data && $data['total'] > 0;

      $classes = ['tec-report__day-row'];
      if ($date === $today) {
        $classes[] = 'tec-report__row--today';
      }
      if (in_array(date('N', strtotime($date)), ['7'], TRUE)) {
        $classes[] = 'tec-report__row--sunday';
      }
      if ($has) {
        $classes[] = 'tec-report__row--has-data';
      }

      $cells = [];
      $day_label = date('j D', strtotime($date));
      $cells[] = [
        'data' => ['#markup' => $has ? '<span class="tec-report__expander">▸</span> ' . $day_label : $day_label],
        'class' => ['tec-report__day-cell'],
      ];
      foreach (array_keys($categories) as $tid) {
        $qty = $has ? ($data['cats'][$tid] ?? 0.0) : 0.0;
        if ($has) {
          $cat_totals[$tid] += $qty;
        }
        $cells[] = [
          'data' => ['#markup' => $qty > 0 ? number_format($qty, 0) : '<span class="tec-report__zero">—</span>'],
          'class' => ['tec-report__num'],
        ];
      }
      if ($has) {
        $month_total += $data['total'];
      }
      $cells[] = [
        'data' => ['#markup' => $has ? '<strong>' . number_format($data['total'], 0) . '</strong>' : '<span class="tec-report__zero">—</span>'],
        'class' => ['tec-report__num', 'tec-report__row-total'],
      ];

      $rows[] = [
        'data' => $cells,
        'class' => $classes,
        'data-day' => (string) $day,
      ];

      // Hidden drill-down row: order / product / material / color / size.
      if ($has && !empty($details[$day])) {
        $rows[] = [
          'data' => [
            [
              'data' => $this->detailTable($details[$day]),
              'colspan' => count($categories) + 2,
              'class' => ['tec-report__detail-cell'],
            ],
          ],
          'class' => ['tec-report__detail-row'],
          'data-detail-for' => (string) $day,
        ];
      }
    }

    // TOTAL row.
    $total_cells = [['data' => ['#markup' => '<strong>' . $this->t('TOTAL') . '</strong>']]];
    foreach (array_keys($categories) as $tid) {
      $total_cells[] = [
        'data' => ['#markup' => '<strong>' . number_format($cat_totals[$tid], 0) . '</strong>'],
        'class' => ['tec-report__num'],
      ];
    }
    $total_cells[] = [
      'data' => ['#markup' => '<strong>' . number_format($month_total, 0) . '</strong>'],
      'class' => ['tec-report__num'],
    ];
    $rows[] = ['data' => $total_cells, 'class' => ['tec-report__totals']];

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No production registered this month.'),
      '#attributes' => ['class' => ['tec-report__table'], 'id' => 'tec-production-report'],
    ];
  }

  /**
   * Drill-down table for one day, aggregated per order + line.
   */
  protected function detailTable(array $lines): array {
    $rows = [];
    foreach ($lines as $l) {
      $order_cell = $l['order'];
      if ($l['order_id']) {
        $order_cell = [
          'data' => [
            '#type' => 'link',
            '#title' => $l['order'],
            '#url' => Url::fromUserInput('/tec_order/' . $l['order_id']),
          ],
        ];
      }
      $rows[] = [
        $order_cell,
        $l['product'],
        $l['material'],
        $l['color'],
        $l['size'],
        ['data' => ['#markup' => number_format($l['qty'], 0)], 'class' => ['tec-report__num']],
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Order'),
        $this->t('Product'),
        $this->t('Material'),
        $this->t('Color'),
        $this->t('Size'),
        ['data' => $this->t('Qty'), 'class' => ['tec-report__num']],
      ],
      '#rows' => $rows,
      '#attributes' => ['class' => ['tec-report__detail-table']],
    ];
  }

  /**
   * Small summary card markup (same look as queue / log cards).
   */
  protected function card($title, $big, $small): string {
    return '<div class="tec-report__card"><div class="tec-report__card-title">' . $title . '</div>'
      . '<div class="tec-report__card-big">' . $big . '</div>'
      . '<div class="tec-report__card-small">' . $small . '</div></div>';
  }

}
