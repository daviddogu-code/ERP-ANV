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

  // The same figure as a column, for lists whose rows are sizes. Hung off the
  // product table and not off a field because there is no field: it is the BoM
  // added up, and the BoM is somewhere else.
  $data['tec_product_field_data']['tec_size_material_cost'] = [
    'title' => t('Material cost'),
    'help' => t('What the materials of a size cost at today\'s consumption costs. Empty for products and colours, which have no BoM of their own.'),
    'field' => [
      'id' => 'tec_size_material_cost',
      'click sortable' => FALSE,
    ],
  ];
}
