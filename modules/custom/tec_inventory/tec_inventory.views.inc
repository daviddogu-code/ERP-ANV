<?php

/**
 * @file
 * Views data for tec_inventory.
 */

/**
 * Implements hook_views_data_alter().
 */
function tec_inventory_views_data_alter(array &$data): void {
  // On the global table rather than on the size, because it belongs at the foot
  // of a table and not in a row: the display that uses it lists the lines of a
  // BoM, so the size is its argument and not the subject of its rows.
  //
  // Declaring the plugin is not enough on its own. A views area plugin nobody
  // hangs off a table is a plugin nobody can add, and worse, a display that
  // names it in configuration draws nothing and says nothing about why.
  $data['views']['tec_size_material_cost_total'] = [
    'title' => t('Size material cost total'),
    'help' => t('What the materials of a size add up to, at the foot of its BoM. Shows nothing for anything that is not a size variation, or for a size with no BoM yet.'),
    'area' => [
      'id' => 'tec_size_material_cost_total',
    ],
  ];
}
