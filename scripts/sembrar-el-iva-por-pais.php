<?php

/**
 * @file
 * Fills in the VAT treatment of every supplier from the country on its card.
 *
 * A supplier with a Thai address is set to charge VAT, one anywhere else to
 * charge none. That is right for all but one group: small Thai suppliers who
 * are below the registration threshold and issue no tax invoice. There is no
 * way to tell those from the data, so this leaves them looking registered and
 * prints the list of Thai suppliers at the end for someone to go through. It is
 * a review of a handful of cards rather than typing three hundred.
 *
 * Cards that already say something are left alone, so a correction survives
 * this being run again.
 *
 * By default it only reports. Add --de-verdad to make the changes.
 *
 * Run: php vendor\bin\drush.php scr scripts/sembrar-el-iva-por-pais.php -- --de-verdad
 */

use Drupal\tec_production\Vat;

$de_verdad = in_array('--de-verdad', $extra ?? [], TRUE)
  || in_array('--de-verdad', (array) ($_SERVER['argv'] ?? []), TRUE);

$almacen = \Drupal::entityTypeManager()->getStorage('tec_crm');
$ids = $almacen->getQuery()->accessCheck(FALSE)->execute();

$puestos = ['standard' => 0, 'foreign' => 0];
$ya_tenian = 0;
$sin_direccion = [];
$tailandeses = [];
$no_proveedores = 0;

foreach ($almacen->loadMultiple($ids) as $contacto) {
  if (!$contacto->hasField(Vat::TREATMENT_FIELD)) {
    continue;
  }
  if (!tec_crm_ux_entity_is_supplier($contacto)) {
    $no_proveedores++;
    continue;
  }

  $nombre = $contacto->label() . ' (' . $contacto->id() . ')';

  if (!$contacto->get(Vat::TREATMENT_FIELD)->isEmpty()) {
    $ya_tenian++;
    if ($contacto->get(Vat::TREATMENT_FIELD)->value === Vat::STANDARD) {
      $tailandeses[] = $nombre . '  [already set]';
    }
    continue;
  }

  // The address field is the Address module's, so it carries a country code
  // rather than a line of text somebody typed. That is the whole reason this
  // can be seeded at all.
  $pais = '';
  if ($contacto->hasField('field_tec_address') && !$contacto->get('field_tec_address')->isEmpty()) {
    $pais = (string) $contacto->get('field_tec_address')->country_code;
  }

  if ($pais === '') {
    // Guessing from no address would be guessing. Left blank, and the code
    // treats a blank card as charging VAT, which is the mistake that gets
    // noticed rather than the one that hides.
    $sin_direccion[] = $nombre;
    continue;
  }

  $tratamiento = $pais === 'TH' ? Vat::STANDARD : Vat::FOREIGN;
  if ($tratamiento === Vat::STANDARD) {
    $tailandeses[] = $nombre;
  }
  $puestos[$tratamiento === Vat::STANDARD ? 'standard' : 'foreign']++;

  if ($de_verdad) {
    $contacto->set(Vat::TREATMENT_FIELD, $tratamiento)->save();
  }
}

print ($de_verdad ? "DONE" : "DRY RUN, nothing saved. Add --de-verdad to apply.") . "\n";
print str_repeat('=', 72) . "\n\n";

print "Suppliers set to charge VAT (Thai address): " . $puestos['standard'] . "\n";
print "Suppliers set to charge none (abroad):      " . $puestos['foreign'] . "\n";
print "Already filled in, left alone:              " . $ya_tenian . "\n";
print "No address, left blank:                     " . count($sin_direccion) . "\n";
print "Contacts that are not suppliers, skipped:   " . $no_proveedores . "\n";

if ($sin_direccion) {
  print "\nNo address on the card, so nobody could tell. Fill these in by hand:\n";
  foreach ($sin_direccion as $quien) {
    print '  ' . $quien . "\n";
  }
}

if ($tailandeses) {
  print "\n" . str_repeat('-', 72) . "\n";
  print "THAI SUPPLIERS -- these now all say they charge 7%.\n";
  print "Go through them and switch any that are too small to be registered\n";
  print "to \"Thai, not registered, charges no VAT\".\n";
  print str_repeat('-', 72) . "\n";
  foreach ($tailandeses as $quien) {
    print '  ' . $quien . "\n";
  }
}
