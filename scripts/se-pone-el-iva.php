<?php

/**
 * @file
 * Checks that a purchase order comes out with the right VAT rate on it.
 *
 * The rate is decided once, when the order is created, from the supplier's
 * card. Everything worth breaking is in that sentence: the right rate, only at
 * creation, and never again afterwards. So this creates orders for a supplier
 * of each kind and looks at what they ended up with, then edits one and checks
 * it did not move.
 *
 * Cleans up after itself.
 *
 * Run: php vendor\bin\drush.php scr scripts/se-pone-el-iva.php
 */

use Drupal\tec_production\Vat;

$gestor = \Drupal::entityTypeManager();
$contactos = $gestor->getStorage('tec_crm');
$pedidos = $gestor->getStorage('tec_order');

$problemas = 0;
$basura = [];

/**
 * Prints a verdict and counts what went wrong.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas) {
  if (!$bien) {
    $problemas++;
  }
  print ($bien ? '  ok    ' : '  WRONG ') . $que . ($detalle === '' ? '' : '   [' . $detalle . ']') . "\n";
};

/**
 * A throwaway supplier with a given VAT treatment.
 */
$proveedor = function (string $nombre, ?string $tratamiento) use ($contactos, &$basura) {
  $valores = [
    'type' => 'tec_contact_organization',
    'title' => $nombre,
    'field_tec_customer_code' => substr(str_replace(' ', '', $nombre), 0, 6),
    // 1396 is the Supplier contact type. Without it the card is not a supplier
    // and the field would be hidden, which is not what is being tested here.
    'field_tec_contact_type' => [1396],
  ];
  if ($tratamiento !== NULL) {
    $valores[Vat::TREATMENT_FIELD] = $tratamiento;
  }
  $contacto = $contactos->create($valores);
  $contacto->save();
  $basura[] = $contacto;
  return $contacto;
};

/**
 * A purchase order for a supplier.
 */
$pedido = function ($proveedor, array $extra = []) use ($pedidos, &$basura) {
  $orden = $pedidos->create([
    'type' => 'tec_purchase_order',
    'field_tec_vendor' => $proveedor ? $proveedor->id() : NULL,
    'uid' => 1,
  ] + $extra);
  $orden->save();
  $basura[] = $orden;
  return $orden;
};

try {
  print "The setting\n";
  $tipo = Vat::standardRate();
  $mirar('the standard rate is a number and not zero', $tipo > 0, (string) $tipo);
  $mirar('the three treatments are there', count(Vat::treatments()) === 3, implode(', ', array_keys(Vat::treatments())));

  print "\nWhat each kind of supplier gets\n";

  $registrado = $proveedor('VATTEST registered', Vat::STANDARD);
  $uno = $pedido($registrado);
  $mirar('a registered Thai supplier gets the standard rate',
    (float) $uno->get(Vat::RATE_FIELD)->value === $tipo,
    $uno->label() . ' -> ' . $uno->get(Vat::RATE_FIELD)->value);

  $pequeno = $proveedor('VATTEST small', Vat::LOCAL_EXEMPT);
  $dos = $pedido($pequeno);
  $mirar('a small unregistered Thai supplier gets zero',
    (float) $dos->get(Vat::RATE_FIELD)->value === 0.0,
    $dos->label() . ' -> ' . $dos->get(Vat::RATE_FIELD)->value);

  $extranjero = $proveedor('VATTEST abroad', Vat::FOREIGN);
  $tres = $pedido($extranjero);
  $mirar('a supplier abroad gets zero',
    (float) $tres->get(Vat::RATE_FIELD)->value === 0.0,
    $tres->label() . ' -> ' . $tres->get(Vat::RATE_FIELD)->value);

  $sin_decir = $proveedor('VATTEST blank', NULL);
  $cuatro = $pedido($sin_decir);
  $mirar('a card nobody has filled in yet errs towards charging VAT',
    (float) $cuatro->get(Vat::RATE_FIELD)->value === $tipo,
    $cuatro->label() . ' -> ' . $cuatro->get(Vat::RATE_FIELD)->value);

  print "\nOnce stamped, it stays stamped\n";

  // The reason the rate is copied onto the order at all: this must not move.
  $registrado->set(Vat::TREATMENT_FIELD, Vat::LOCAL_EXEMPT)->save();
  $uno = $pedidos->loadUnchanged($uno->id());
  $uno->set('field_tec_priority', 'high');
  $uno->save();
  $uno = $pedidos->loadUnchanged($uno->id());
  $mirar('the supplier stopping charging VAT does not rewrite an old order',
    (float) $uno->get(Vat::RATE_FIELD)->value === $tipo,
    'still ' . $uno->get(Vat::RATE_FIELD)->value);

  $a_mano = $pedido($registrado, [Vat::RATE_FIELD => 3]);
  $mirar('a rate typed in by hand at creation is not overwritten',
    (float) $a_mano->get(Vat::RATE_FIELD)->value === 3.0,
    (string) $a_mano->get(Vat::RATE_FIELD)->value);

  print "\nThe arithmetic\n";
  $mirar('7% of 1000 is 70', Vat::on(1000, 7.0) === 70.0, (string) Vat::on(1000, 7.0));
  $mirar('0% of anything is nothing', Vat::on(1611.5, 0.0) === 0.0, (string) Vat::on(1611.5, 0.0));
  $mirar('it rounds to the satang', Vat::on(1611.5, 7.0) === 112.81, (string) Vat::on(1611.5, 7.0));

  print "\nWhere the field is and is not\n";
  $mirar('purchase orders have the rate field',
    (bool) \Drupal\field\Entity\FieldConfig::loadByName('tec_order', 'tec_purchase_order', Vat::RATE_FIELD));
  $mirar('sales orders do not, because this is about buying',
    !\Drupal\field\Entity\FieldConfig::loadByName('tec_order', 'tec_sales_order', Vat::RATE_FIELD));
  foreach (['tec_contact_organization', 'tec_contact_person'] as $bundle) {
    $mirar('contacts of type ' . $bundle . ' carry a VAT treatment',
      (bool) \Drupal\field\Entity\FieldConfig::loadByName('tec_crm', $bundle, Vat::TREATMENT_FIELD));
  }
}
finally {
  foreach (array_reverse($basura) as $cosa) {
    try {
      $cosa->delete();
    }
    catch (\Throwable $e) {
      print "  could not clean up " . $cosa->getEntityTypeId() . ' ' . $cosa->id() . "\n";
    }
  }
}

print "\n" . ($problemas === 0 ? "All good." : $problemas . " thing(s) wrong.") . "\n";
