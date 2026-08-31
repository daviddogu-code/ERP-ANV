<?php

/**
 * Sales Open is /o/order. Disable Views /o/draft. Purchase /po/draft stays.
 *
 *   php vendor/bin/drush.php scr scripts/apagar-draft-de-venta.php
 */
\Drupal::moduleHandler()->loadInclude('tec_portal', 'install');
tec_portal_update_8005();

$view = \Drupal::entityTypeManager()->getStorage('view')->load('tec_order_sales_order_line_items');
$displays = $view ? $view->get('display') : [];
$sales = $displays['page_2']['display_options']['enabled'] ?? TRUE;
$po = $displays['page_1']['display_options']['path'] ?? '';
print 'sales page_2 enabled=' . ($sales === FALSE ? 'off' : 'on') . "\n";
print 'purchase page_1 path=' . $po . "\n";
