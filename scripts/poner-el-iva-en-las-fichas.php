<?php

/**
 * @file
 * Creates the two fields VAT needs, and the setting holding the rate.
 *
 * On a contact card, a short list saying how that supplier is treated: charges
 * VAT, Thai but not registered, or outside Thailand. One fact per supplier,
 * filled in once, instead of a decision taken again on every order.
 *
 * On a purchase order, the rate it was raised under. It is written there at
 * creation rather than looked up from the supplier every time it is shown,
 * because an order printed in March has to still say in March's terms in
 * December, whatever happened in between to the rate or to the supplier's
 * registration.
 *
 * The rate itself lives in one setting so that a change to it is one edit and
 * not three hundred. See src/Vat.php for why the shape is like this.
 *
 * Safe to run twice: everything here checks before it creates.
 *
 * Run: php vendor\bin\drush.php scr scripts/poner-el-iva-en-las-fichas.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\tec_production\Vat;

$hecho = [];
$ya = [];

// ---------------------------------------------------------------------------
// 1. The rate, in one place.
// ---------------------------------------------------------------------------

$ajustes = \Drupal::configFactory()->getEditable('tec_production.settings');
if ($ajustes->get('vat_rate') === NULL) {
  $ajustes->set('vat_rate', 7)->save();
  $hecho[] = 'setting vat_rate = 7';
}
else {
  $ya[] = 'setting vat_rate is already ' . $ajustes->get('vat_rate');
}

// ---------------------------------------------------------------------------
// 2. How a contact is treated for VAT.
// ---------------------------------------------------------------------------

// Handed over as a plain value => label map. A list field is stored internally
// as a list of {value, label} pairs, and passing that shape in is the obvious
// thing to try and is rejected: the setting is validated in its normalised
// form, and Drupal does the converting itself on save.
if (!FieldStorageConfig::loadByName('tec_crm', Vat::TREATMENT_FIELD)) {
  FieldStorageConfig::create([
    'field_name' => Vat::TREATMENT_FIELD,
    'entity_type' => 'tec_crm',
    'type' => 'list_string',
    'cardinality' => 1,
    'settings' => ['allowed_values' => Vat::treatments()],
  ])->save();
  $hecho[] = 'field storage ' . Vat::TREATMENT_FIELD;
}
else {
  $ya[] = 'field storage ' . Vat::TREATMENT_FIELD;
}

// Both kinds of contact, because a supplier here is sometimes a person and not
// a company: the other purchase fields, currency, incoterm and payment terms,
// are already on both.
foreach (['tec_contact_organization', 'tec_contact_person'] as $bundle) {
  if (FieldConfig::loadByName('tec_crm', $bundle, Vat::TREATMENT_FIELD)) {
    $ya[] = 'field ' . Vat::TREATMENT_FIELD . ' on ' . $bundle;
    continue;
  }
  FieldConfig::create([
    'field_name' => Vat::TREATMENT_FIELD,
    'entity_type' => 'tec_crm',
    'bundle' => $bundle,
    'label' => 'VAT treatment',
    'description' => 'Whether this supplier charges Thai VAT. Copied onto each purchase order when it is created, and can be changed there for a one-off.',
    'required' => FALSE,
    'settings' => [],
  ])->save();
  $hecho[] = 'field ' . Vat::TREATMENT_FIELD . ' on ' . $bundle;
}

// ---------------------------------------------------------------------------
// 3. The rate on a purchase order.
// ---------------------------------------------------------------------------

if (!FieldStorageConfig::loadByName('tec_order', Vat::RATE_FIELD)) {
  FieldStorageConfig::create([
    'field_name' => Vat::RATE_FIELD,
    'entity_type' => 'tec_order',
    'type' => 'decimal',
    'cardinality' => 1,
    // Two decimals because a rate can be 7.5 somewhere one day, and five
    // digits because no sales tax anywhere needs more than three before the
    // point.
    'settings' => ['precision' => 5, 'scale' => 2],
  ])->save();
  $hecho[] = 'field storage ' . Vat::RATE_FIELD;
}
else {
  $ya[] = 'field storage ' . Vat::RATE_FIELD;
}

if (!FieldConfig::loadByName('tec_order', 'tec_purchase_order', Vat::RATE_FIELD)) {
  FieldConfig::create([
    'field_name' => Vat::RATE_FIELD,
    'entity_type' => 'tec_order',
    'bundle' => 'tec_purchase_order',
    'label' => 'VAT %',
    'description' => 'The rate this order was raised under. Filled in from the supplier when the order is created. Change it only for a one-off; changing it does not change the supplier.',
    'required' => FALSE,
    'settings' => [],
  ])->save();
  $hecho[] = 'field ' . Vat::RATE_FIELD . ' on tec_purchase_order';
}
else {
  $ya[] = 'field ' . Vat::RATE_FIELD . ' on tec_purchase_order';
}

// ---------------------------------------------------------------------------
// 4. Where they show up.
// ---------------------------------------------------------------------------

/**
 * Puts a field into a display right behind another one, or hides it.
 *
 * Following an existing field rather than picking a weight, so that the new one
 * lands wherever its neighbour has ended up and stays next to it if the card is
 * ever rearranged. Where the neighbour is not shown, the new field is taken out
 * altogether: several of these displays are cut down to a name and a button on
 * purpose, and a new field has to be pushed out of them rather than left alone,
 * because Drupal helpfully adds every new field to every display by itself.
 */
$detras_de = static function ($display, string $vecino, string $nuevo, array $config): bool {
  if (!$display) {
    return FALSE;
  }
  $antes = $display->getComponent($nuevo);
  $companero = $display->getComponent($vecino);

  if (!$companero) {
    if ($antes === NULL) {
      return FALSE;
    }
    $display->removeComponent($nuevo)->save();
    return TRUE;
  }

  $quiero = $config + [
    'weight' => ($companero['weight'] ?? 0) + 1,
    'region' => $companero['region'] ?? 'content',
  ];
  if ($antes && ($antes['weight'] ?? NULL) == $quiero['weight'] && ($antes['type'] ?? NULL) === ($quiero['type'] ?? NULL)) {
    return FALSE;
  }
  $display->setComponent($nuevo, $quiero)->save();
  return TRUE;
};

// The edit forms, inside the "Supplier purchase defaults" fieldset. The field
// group has to be told about it separately: it keeps its own list of children
// and a field the group does not name falls outside the box.
foreach (['tec_contact_organization', 'tec_contact_person'] as $bundle) {
  $display = EntityFormDisplay::load('tec_crm.' . $bundle . '.default');
  if (!$display) {
    continue;
  }
  if ($detras_de($display, 'field_tec_payment_terms', Vat::TREATMENT_FIELD, ['type' => 'options_select', 'settings' => []])) {
    $hecho[] = 'edit form of ' . $bundle;
  }

  $grupos = $display->getThirdPartySettings('field_group');
  $hijos = $grupos['group_supplier']['children'] ?? [];
  if ($hijos && !in_array(Vat::TREATMENT_FIELD, $hijos, TRUE)) {
    $posicion = array_search('field_tec_payment_terms', $hijos, TRUE);
    array_splice($hijos, $posicion === FALSE ? count($hijos) : $posicion + 1, 0, Vat::TREATMENT_FIELD);
    $grupos['group_supplier']['children'] = $hijos;
    $display->setThirdPartySetting('field_group', 'group_supplier', $grupos['group_supplier'])->save();
    $hecho[] = 'supplier fieldset of ' . $bundle;
  }
}

// The view displays, wherever payment terms are already shown. There are nine
// of them and most are cut down to a name and a button, so mirroring an
// existing supplier field is safer than listing them by hand.
foreach (\Drupal::entityTypeManager()->getStorage('entity_view_display')->loadMultiple() as $display) {
  if ($display->getTargetEntityTypeId() !== 'tec_crm') {
    continue;
  }
  if ($detras_de($display, 'field_tec_payment_terms', Vat::TREATMENT_FIELD, ['type' => 'list_default', 'label' => 'inline', 'settings' => []])) {
    $hecho[] = 'card view ' . $display->id();
  }
}

// The purchase order edit form, next to the order status, which is the other
// thing on that form somebody changes by hand.
$pedido = EntityFormDisplay::load('tec_order.tec_purchase_order.default');
if ($detras_de($pedido, 'field_tec_po_status', Vat::RATE_FIELD, ['type' => 'number', 'settings' => ['placeholder' => '']])) {
  $hecho[] = 'purchase order edit form';
}

// And off every screen that shows an order, for now. A rate on its own is not
// what anyone wants to read; what belongs on those screens is a subtotal, the
// VAT and a total, and that is the next job. Left where Drupal put it, it would
// turn up on the PDF as a bare line saying "VAT %: 7.00".
foreach (\Drupal::entityTypeManager()->getStorage('entity_view_display')->loadMultiple() as $display) {
  if ($display->getTargetEntityTypeId() !== 'tec_order' || $display->getComponent(Vat::RATE_FIELD) === NULL) {
    continue;
  }
  $display->removeComponent(Vat::RATE_FIELD)->save();
  $hecho[] = 'kept off order view ' . $display->id();
}

// ---------------------------------------------------------------------------

print $hecho ? "Created:\n  " . implode("\n  ", $hecho) . "\n" : "Nothing to create.\n";
if ($ya) {
  print "\nAlready there:\n  " . implode("\n  ", $ya) . "\n";
}
