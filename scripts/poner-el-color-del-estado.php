<?php

/**
 * Order status label on the card keeps its own colour.
 *
 *   php vendor/bin/drush.php scr scripts/poner-el-color-del-estado.php
 */

\Drupal::moduleHandler()->loadInclude('tec_production', 'install');

$schema = \Drupal::keyValue('system.schema');
$antes = (int) $schema->get('tec_production');
echo "schema before: $antes\n";

$clase = static function (): string {
  $display = \Drupal::entityTypeManager()
    ->getStorage('entity_view_display')
    ->load('tec_order.tec_sales_order.default');
  if (!$display) {
    return 'missing display';
  }
  foreach ($display->getThirdPartySetting('layout_builder', 'sections') ?? [] as $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getPluginId() !== 'field_block:tec_order:tec_sales_order:field_tec_order_status') {
        continue;
      }
      $config = $component->get('configuration');
      return (string) ($config['formatter']['third_party_settings']['field_formatter_class']['class'] ?? '');
    }
  }
  return 'missing block';
};

echo 'class before: ' . $clase() . "\n";

if ($antes < 10007) {
  tec_production_update_10007();
  $schema->set('tec_production', 10007);
  echo "ran 10007\n";
}
else {
  tec_production_ensure_status_label_plain();
  echo "10007 already applied; ensure only\n";
}

echo 'schema after: ' . $schema->get('tec_production') . "\n";
echo 'class after: ' . $clase() . "\n";
