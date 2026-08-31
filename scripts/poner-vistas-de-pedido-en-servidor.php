<?php

/**
 * Factory Open is /o/order. Print only after Confirm. Sales /o/draft off.
 *
 * Runs tec_portal 8005–8008 against the Views Drupal is actually serving.
 * Does not run tec_production 10004/10005/10006.
 *
 *   php vendor/bin/drush.php scr scripts/poner-vistas-de-pedido-en-servidor.php
 */

\Drupal::moduleHandler()->loadInclude('tec_portal', 'install');

$schema = \Drupal::keyValue('system.schema');
$antes = (int) $schema->get('tec_portal');
echo "tec_portal schema before: $antes\n";

$views = \Drupal::entityTypeManager()->getStorage('view');
$lineas = $views->load('tec_order_sales_order_line_items');
$pedidos = $views->load('tec_orders_orders');
$draftOn = static function ($view): string {
  if (!$view) {
    return 'missing view';
  }
  $bits = [];
  foreach ($view->get('display') as $id => $display) {
    $path = (string) ($display['display_options']['path'] ?? '');
    if ($path !== 'o/draft/%' && $path !== 'o/draft/%/') {
      continue;
    }
    $on = ($display['display_options']['enabled'] ?? TRUE) !== FALSE;
    $bits[] = "$id " . ($on ? 'ON' : 'off');
  }
  return $bits ? implode(', ', $bits) : 'no o/draft display';
};
$iconos = static function ($view, string $display): string {
  if (!$view) {
    return 'missing';
  }
  $fields = $view->get('display')[$display]['display_options']['fields'] ?? [];
  $nothing = (string) ($fields['nothing']['alter']['text'] ?? '');
  $or = (string) ($fields['views_conditional_field']['or'] ?? '');
  $draft = str_contains($nothing, '/o/draft/') || str_contains($or, '/o/draft/');
  $printInNothing = str_contains($nothing, '/o/pf/');
  $printInOr = str_contains($or, '/o/pf/');
  $edit = str_contains($nothing, '/o/order/');
  return 'edit=' . ($edit ? 'yes' : 'no')
    . ' print-on-open=' . ($printInNothing ? 'YES' : 'no')
    . ' print-after=' . ($printInOr ? 'yes' : 'no')
    . ' leftover-draft=' . ($draft ? 'YES' : 'no');
};

echo 'draft displays before: ' . $draftOn($lineas) . "\n";
echo 'orders default icons before: ' . $iconos($pedidos, 'default') . "\n";

tec_portal_update_8005();
tec_portal_update_8006();
tec_portal_update_8007();
tec_portal_update_8008();

if ($antes < 8008) {
  $schema->set('tec_portal', 8008);
}
echo 'tec_portal schema after: ' . $schema->get('tec_portal') . "\n";

$lineas = $views->load('tec_order_sales_order_line_items');
$pedidos = $views->load('tec_orders_orders');
echo 'draft displays after: ' . $draftOn($lineas) . "\n";
echo 'orders default icons after: ' . $iconos($pedidos, 'default') . "\n";
echo 'orders block_1 icons after: ' . $iconos($pedidos, 'block_1') . "\n";
echo 'orders block_2 icons after: ' . $iconos($pedidos, 'block_2') . "\n";

$po = (string) ($lineas->get('display')['page_1']['display_options']['path'] ?? '');
echo 'purchase draft path: ' . $po . "\n";
