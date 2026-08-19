<?php

namespace Drupal\tec_inventory\Plugin\views\area;

use Drupal\tec_inventory\MaterialCost;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * The foot of a size's BoM: what its materials add up to.
 *
 * The rows above it already say what each material costs, and a column of
 * figures nobody adds up is an invitation to add it up wrong. This is the same
 * arithmetic the catalogue needs, so both ask MaterialCost and there is one
 * answer rather than two free to drift.
 *
 * It is an area and not a field because it says something about the size, not
 * about a line of its BoM, and it has to print once whatever the rows did.
 *
 * The reason the sum is not simply the rendered column added up is that the
 * column rounds each line to the satang before showing it. On a material worth
 * six ten-thousandths of a baht per centimetre, every line rounds to zero and a
 * total built that way would say a glove is sewn with free thread.
 */
#[ViewsArea("tec_size_material_cost_total")]
class MaterialCostTotal extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $size = $this->size();
    if (!$size) {
      return [];
    }

    $sums = MaterialCost::ofSize($size);
    // A size with no BoM yet has nothing to total, and a foot saying zero would
    // read as a product that costs nothing to make.
    if (!$sums || $sums['lines'] === 0) {
      return [];
    }

    $rows = [[
      'class' => ['tec-material-cost__row--total'],
      'data' => [
        [
          'data' => $this->t('Material cost'),
          'class' => ['tec-material-cost__label'],
        ],
        [
          // Escaped rather than typed, because this file has no business
          // depending on surviving a trip through an editor that guesses at
          // encodings, and several in this project have not. The space after it
          // is what every other figure on these screens has.
          'data' => "\u{0E3F} " . number_format($sums['total'], 2),
          'class' => ['tec-material-cost__amount'],
        ],
      ],
    ]];

    // A material with no consumption cost is left out of the sum, so the sum has
    // to say so. Silence here is a product that looks cheaper than it is.
    if ($sums['unpriced']) {
      $rows[] = [
        'class' => ['tec-material-cost__row--warning'],
        'data' => [
          [
            'data' => $this->formatPlural(
              count($sums['unpriced']),
              'One material has no cost yet and is not in this total: @which',
              '@count materials have no cost yet and are not in this total: @which',
              ['@which' => implode(', ', $sums['unpriced'])]
            ),
            'class' => ['tec-material-cost__note'],
            'colspan' => 2,
          ],
        ],
      ];
    }

    return [
      '#theme' => 'table',
      '#rows' => $rows,
      '#attributes' => ['class' => ['tec-material-cost']],
      '#attached' => ['library' => ['tec_inventory/material_cost']],
      '#cache' => ['tags' => $sums['tags']],
    ];
  }

  /**
   * The size this table is about.
   *
   * The BoM table is put on the size's card by a viewfield that passes
   * `[tec_product:id]`, so the argument is the size and there is nothing
   * cleverer to do. Anything else asking for this display without an argument
   * gets no foot rather than a wrong one.
   */
  private function size() {
    $id = (int) ($this->view->args[0] ?? 0);
    if ($id <= 0) {
      return NULL;
    }
    $size = \Drupal::entityTypeManager()->getStorage('tec_product')->load($id);

    return $size && $size->bundle() === 'tec_size_variation' ? $size : NULL;
  }

}
