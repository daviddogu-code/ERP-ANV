<?php

/**
 * Sales VAT, company letterhead, and chrome logos on production.
 *
 * Does not run 10004 (factory statuses). That already ran on 30 August.
 *
 *   php vendor/bin/drush.php scr scripts/poner-iva-y-pedidos-en-servidor.php
 */

\Drupal::moduleHandler()->loadInclude('tec_production', 'install');

$schema = \Drupal::keyValue('system.schema');
$antes = (int) $schema->get('tec_production');
echo "schema before: $antes\n";

if ($antes < 10004) {
  echo "REFUSING: schema is $antes, expected at least 10004. Statuses are not this script.\n";
  return;
}

if ($antes < 10005) {
  tec_production_update_10005();
  $schema->set('tec_production', 10005);
  echo "ran 10005\n";
}
else {
  tec_production_ensure_sales_vat_rate();
  echo "10005 already applied; ensure_sales_vat_rate() only\n";
}

$ahora = (int) $schema->get('tec_production');
if ($ahora < 10006) {
  tec_production_update_10006();
  $schema->set('tec_production', 10006);
  echo "ran 10006\n";
}
else {
  tec_production_ensure_company_identity();
  echo "10006 already applied; ensure_company_identity() only\n";
}

echo 'schema after: ' . $schema->get('tec_production') . "\n";

$field = \Drupal\field\Entity\FieldConfig::loadByName('tec_order', 'tec_sales_order', 'field_tec_vat_rate');
echo 'sales vat field: ' . ($field ? 'yes' : 'MISSING') . "\n";

$company = \Drupal::config('tec_production.settings');
echo 'company legal_name: ' . (string) $company->get('legal_name') . "\n";
echo 'vat_rate setting left alone: ' . var_export($company->get('vat_rate'), TRUE) . "\n";
echo 'queue_tile_nid left alone: ' . var_export($company->get('queue_tile_nid'), TRUE) . "\n";

$logo = 'public://anv-logo.png';
foreach (['gin.settings', 'dxpr_theme.settings'] as $name) {
  $cfg = \Drupal::configFactory()->getEditable($name);
  $cfg->set('logo.path', $logo);
  $cfg->set('logo.use_default', FALSE);
  $cfg->set('favicon.path', $logo);
  $cfg->set('favicon.use_default', FALSE);
  $cfg->set('favicon.mimetype', 'image/png');
  $cfg->save();
  echo "$name logo=" . $cfg->get('logo.path') . " favicon=" . $cfg->get('favicon.path') . "\n";
}
