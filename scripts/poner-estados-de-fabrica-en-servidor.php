<?php

/**
 * Factory sales statuses on production: labels, leftover values, ECA off.
 *
 *   php vendor/bin/drush.php scr scripts/poner-estados-de-fabrica-en-servidor.php
 */

\Drupal::moduleHandler()->loadInclude('tec_production', 'install');
$antes = (int) \Drupal::keyValue('system.schema')->get('tec_production');
echo "schema before: $antes\n";
tec_production_update_10004();
\Drupal::keyValue('system.schema')->set('tec_production', 10004);
echo 'schema after: ' . \Drupal::keyValue('system.schema')->get('tec_production') . "\n";

$f = \Drupal\field\Entity\FieldStorageConfig::loadByName('tec_order', 'field_tec_order_status');
$labels = $f ? $f->getSetting('allowed_values') : [];
echo 'Processing=' . ($labels['in_progress_processing'] ?? '?') . "\n";
echo 'Ready for collection=' . ($labels['ready_for_delivery'] ?? '?') . "\n";
$n = \Drupal::entityTypeManager()->getStorage('tec_order')->getQuery()
  ->accessCheck(FALSE)
  ->condition('field_tec_order_status', ['accounting_verified', 'quality_control_inspection'], 'IN')
  ->count()
  ->execute();
echo "leftover old statuses: $n\n";
echo 'eca=' . var_export(\Drupal::config('eca.eca.process_aomcnxm')->get('status'), TRUE) . "\n";
