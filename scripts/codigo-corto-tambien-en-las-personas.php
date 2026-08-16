<?php

/**
 * @file
 * Puts the short code on person contacts too, not just on companies.
 *
 * The single short code field landed on 16 August 2026, but only on the company
 * cards. That looked complete because both contacts in the system are companies.
 *
 * It is not complete. The customer selector on a sales order
 * (tec_crm_references:entity_reference_3) filters by contact type, not by kind of
 * card, so a person tagged as Customer is perfectly selectable. The first time
 * somebody sells to a person, the order would be born with no code in its number
 * -- and there would be no field on the card to put one in.
 *
 * Same field, same wording, same rule, both kinds of card. A short code you have
 * to remember does not apply to companies is not one field, it is two.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$field_name = 'field_tec_customer_code';
$from = 'tec_contact_organization';
$to = 'tec_contact_person';

$storage = FieldStorageConfig::loadByName('tec_crm', $field_name);
if (!$storage) {
  echo "No existe el almacenamiento del campo. Nada que hacer.\n";
  return;
}

$model = FieldConfig::loadByName('tec_crm', $from, $field_name);
if (!$model) {
  echo "No existe el campo en las fichas de organizacion, no hay de donde copiar.\n";
  return;
}

$existing = FieldConfig::loadByName('tec_crm', $to, $field_name);
if ($existing) {
  echo "Ya estaba en las fichas de persona.\n";
}
else {
  FieldConfig::create([
    'field_storage' => $storage,
    'bundle' => $to,
    'label' => $model->getLabel(),
    'description' => $model->getDescription(),
    'required' => $model->isRequired(),
  ])->save();
  echo "Campo puesto en las fichas de persona: \"{$model->getLabel()}\", obligatorio.\n";
}

// Same place on the form and in the same displays as on a company card, so that
// whoever knows where to look on one knows where to look on the other.
$display_repository = \Drupal::service('entity_display.repository');
$config_factory = \Drupal::configFactory();

foreach (['form' => 'core.entity_form_display.tec_crm.', 'view' => 'core.entity_view_display.tec_crm.'] as $kind => $prefix) {
  foreach ($config_factory->listAll($prefix . $to . '.') as $name) {
    $mode = substr($name, strlen($prefix . $to . '.'));
    $model_name = $prefix . $from . '.' . $mode;
    $model_display = $config_factory->get($model_name);
    if ($model_display->isNew()) {
      echo sprintf("  %-5s %-18s la ficha de organizacion no tiene esta vista, lo escondo\n", $kind, $mode);
      $component = NULL;
    }
    else {
      $component = $model_display->get('content.' . $field_name);
    }

    $display = $kind === 'form'
      ? $display_repository->getFormDisplay('tec_crm', $to, $mode)
      : $display_repository->getViewDisplay('tec_crm', $to, $mode);

    if ($component) {
      $display->setComponent($field_name, $component)->save();
      echo sprintf("  %-5s %-18s puesto con peso %s\n", $kind, $mode, $component['weight'] ?? '?');
    }
    else {
      $display->removeComponent($field_name)->save();
      echo sprintf("  %-5s %-18s escondido\n", $kind, $mode);
    }
  }
}

echo "\nHecho.\n";
