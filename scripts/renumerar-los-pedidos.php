<?php

/**
 * @file
 * Renames the orders that already exist to "CODE YY-NNN".
 *
 * The three purchase orders are called "26-1", "26-2" and "26-3": no short code,
 * no padding, and a counter that ran across every supplier at once. The sales
 * order is called "PRUEBA pedido de venta", which somebody typed.
 *
 * Numbers are handed out per contact and per year from now on, so the old ones
 * are recomputed the same way: within each contact and each year, the orders are
 * put in the order they were created and numbered 001 upwards. Renumbering by
 * creation date rather than keeping the old sequence matters because the old
 * counter was shared: two suppliers' orders were interleaved in one run of
 * numbers, and splitting that run per supplier means the numbers move.
 *
 * The year comes from each order's own creation date, not from today. An order
 * placed in 2025 is a 2025 order however long ago that was.
 *
 * Nothing is renamed twice: an order already carrying the name it should have is
 * left alone, so this can be run again without churning.
 *
 * Pass --de-verdad to write. Without it, it only says what it would do.
 */

use Drupal\tec_production\OrderNumber;

$de_verdad = in_array('--de-verdad', $extra ?? [], TRUE) || in_array('--de-verdad', $_SERVER['argv'] ?? [], TRUE);

$etm = \Drupal::entityTypeManager();
$storage = $etm->getStorage('tec_order');

$bundles = ['tec_purchase_order', 'tec_sales_order'];

// Group by contact and year first, because the counter runs inside each group.
$groups = [];
foreach ($storage->loadMultiple() as $order) {
  $bundle = $order->bundle();
  if (!in_array($bundle, $bundles, TRUE)) {
    continue;
  }
  $field = OrderNumber::contactField($bundle);
  $contact = $order->hasField($field) && !$order->get($field)->isEmpty()
    ? $order->get($field)->entity
    : NULL;

  $created = (int) $order->get('created')->value;
  $key = $bundle . '|' . ($contact ? $contact->id() : 'nadie') . '|' . date('y', $created);
  $groups[$key][] = ['order' => $order, 'created' => $created, 'contact' => $contact];
}

$cambios = 0;
$igual = 0;

foreach ($groups as $key => $rows) {
  [$bundle, $contact_id, $year] = explode('|', $key);

  usort($rows, static function (array $a, array $b): int {
    return [$a['created'], (int) $a['order']->id()] <=> [$b['created'], (int) $b['order']->id()];
  });

  $code = OrderNumber::shortCode($rows[0]['contact']);
  $prefix = ($code === '' ? '' : $code . ' ') . $year . '-';

  echo sprintf(
    "%s / %s / 20%s  (%d %s)\n",
    str_replace('tec_', '', $bundle),
    $rows[0]['contact'] ? $rows[0]['contact']->label() : 'sin contacto',
    $year,
    count($rows),
    count($rows) === 1 ? 'pedido' : 'pedidos'
  );

  foreach ($rows as $i => $row) {
    $order = $row['order'];
    $name = $prefix . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
    $old = (string) $order->label();

    if ($old === $name) {
      echo sprintf("  %-5s ya se llama \"%s\"\n", $order->id(), $name);
      $igual++;
      continue;
    }

    echo sprintf("  %-5s \"%s\"  ->  \"%s\"\n", $order->id(), $old, $name);
    $cambios++;

    if ($de_verdad) {
      $order->set('title', $name);
      $order->save();
    }
  }
}

echo "\n";
if ($de_verdad) {
  echo "Renombrados {$cambios}. Ya estaban bien {$igual}.\n";
}
else {
  echo "Ensayo: {$cambios} cambiarian, {$igual} ya estan bien.\n";
  echo "Para hacerlo de verdad: drush scr scripts/renumerar-los-pedidos.php -- --de-verdad\n";
}
