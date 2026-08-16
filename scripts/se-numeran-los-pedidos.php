<?php

/**
 * @file
 * Proves orders get named "CODE YY-NNN", by every route that creates one.
 *
 * The case that had to work is the one that was asked for in plain words:
 * customer A places an order and it is A 26-001, customer B places one and it is
 * B 26-001 -- not 002 -- and when A comes back it is A 26-002. Per contact, and
 * restarting each year. That is checked here literally.
 *
 * Two routes create orders and both are checked, because for years they
 * disagreed: the flag on a contact card, which runs an ECA process, and code
 * calling the entity API, which is what the purchase list does. They now share
 * OrderNumber through a presave hook, so a difference between them would be a
 * bug, not a design.
 *
 * The flag route is worth the trouble of testing because of an ordering trap.
 * Both ECA processes used to create the order, save it, and attach the contact
 * afterwards. The name is decided at the first save, so with the old order of
 * steps every order would come out with no short code at all. This asserts the
 * contact really is there in time.
 *
 * Everything it creates, it deletes.
 */

use Drupal\Core\Session\UserSession;
use Drupal\tec_production\OrderNumber;

// Flagging refuses to work for an anonymous visitor, and on the command line
// that is who we are. The flag is only ever pressed by somebody logged in.
$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo(new UserSession(['uid' => 1]));

$etm = \Drupal::entityTypeManager();
$order_storage = $etm->getStorage('tec_order');
$crm_storage = $etm->getStorage('tec_crm');
$flag_service = \Drupal::service('flag');
$year = date('y');

// Since 17 August 2026 a number handed out is remembered, so that deleting an
// order does not put its number back in circulation. That makes this test
// expensive to run carelessly: every order it creates burns a real number for
// a real supplier, and deleting the order afterwards no longer gives it back.
// So the whole ledger is photographed here and put back at the end, and the
// run leaves the site's counters exactly where it found them.
$ledger = \Drupal::keyValue(OrderNumber::LEDGER);
$ledger_before = $ledger->getAll();

$fallos = 0;
$basura = ['tec_order' => [], 'tec_crm' => [], 'tec_line_item' => []];

/**
 * Says whether one thing matches another and remembers if it did not.
 */
$comprobar = static function (string $que, $esperado, $obtenido) use (&$fallos): void {
  $bien = $esperado === $obtenido;
  if (!$bien) {
    $fallos++;
  }
  echo sprintf(
    "  [%s] %-52s %s\n",
    $bien ? 'bien' : 'MAL ',
    $que,
    $bien ? (string) $obtenido : sprintf('esperaba "%s", ha salido "%s"', $esperado, $obtenido)
  );
};

/**
 * A throwaway company card with a given short code.
 */
$ficha = static function (string $code) use ($crm_storage, &$basura) {
  $contact = $crm_storage->create([
    'type' => 'tec_contact_organization',
    'title' => 'PRUEBA numeracion ' . $code,
    'field_tec_customer_code' => $code,
    'status' => 1,
  ]);
  $contact->save();
  $basura['tec_crm'][] = $contact->id();
  return $contact;
};

/**
 * An order made the way code makes them, which is how the purchase list does it.
 */
$pedido = static function (string $bundle, $contact = NULL) use ($order_storage, &$basura) {
  $values = ['type' => $bundle, 'status' => 1];
  $field = OrderNumber::contactField($bundle) ?? 'field_tec_customer';
  if ($contact) {
    $values[$field] = $contact->id();
  }
  $order = $order_storage->create($values);
  $order->save();
  $basura['tec_order'][] = $order->id();
  return $order;
};

try {
  echo "=== El caso que se pidio, palabra por palabra ===\n";
  $a = $ficha('A');
  $b = $ficha('B');

  $comprobar('venta 1, cliente A', "A {$year}-001", (string) $pedido('tec_sales_order', $a)->label());
  $comprobar('venta 2, cliente B', "B {$year}-001", (string) $pedido('tec_sales_order', $b)->label());
  $comprobar('venta 3, cliente A', "A {$year}-002", (string) $pedido('tec_sales_order', $a)->label());

  echo "\n=== Las compras usan el mismo contador, por proveedor ===\n";
  // This used to expect "KJ 26-004" because KJ had three orders the day it was
  // written. KJ has sixteen now and the test had been failing on a fact about
  // the world rather than about the code. What matters is that a purchase order
  // carries its supplier's code and continues that supplier's sequence, so that
  // is what is asked: two in a row, one after the other.
  $kj = $crm_storage->load(31);
  if ($kj) {
    $primero_kj = (string) $pedido('tec_purchase_order', $kj)->label();
    $segundo_kj = (string) $pedido('tec_purchase_order', $kj)->label();
    $numero_kj = (int) substr($primero_kj, -3);

    $comprobar('la compra lleva el codigo del proveedor', TRUE, str_starts_with($primero_kj, "KJ {$year}-"));
    $comprobar('y la siguiente va justo detras', "KJ {$year}-" . str_pad((string) ($numero_kj + 1), 3, '0', STR_PAD_LEFT), $segundo_kj);
  }
  else {
    echo "  [dato] la ficha de KJ no esta, me lo salto\n";
  }

  echo "\n=== Sin codigo corto no queda un espacio suelto delante ===\n";
  $comprobar('venta sin cliente', "{$year}-001", (string) $pedido('tec_sales_order')->label());

  echo "\n=== Un numero ya usado no se reparte dos veces ===\n";
  // Borrowing A 26-001 away and creating another order for A: counting alone
  // would hand out 002, which is taken. It has to climb to 003.
  $primero = $order_storage->load($basura['tec_order'][0]);
  $primero->set('title', 'PRUEBA hueco')->save();
  $comprobar('con un hueco por medio, sigue subiendo', "A {$year}-003", (string) $pedido('tec_sales_order', $a)->label());

  echo "\n=== Un numero entregado no vuelve, aunque se borre el pedido ===\n";
  // The owner asked it as a question on 17 August 2026: delete POLYTE 26-012 and
  // make another one, what number does it get? It used to get 26-012 again, and
  // the supplier could well be holding the first one on paper. Now the number is
  // spent the moment it is handed out.
  $z = $ficha('ZTEST');
  $comprobar('el primero de un proveedor nuevo', "ZTEST {$year}-001", (string) $pedido('tec_purchase_order', $z)->label());

  $segundo = $pedido('tec_purchase_order', $z);
  $comprobar('el segundo va detras', "ZTEST {$year}-002", (string) $segundo->label());

  $segundo->delete();
  $comprobar('borrado el segundo, el siguiente no lo hereda', "ZTEST {$year}-003", (string) $pedido('tec_purchase_order', $z)->label());

  // And the harder version of the same thing: with every order gone there is
  // nothing left to count, so anything that worked it out from what exists would
  // start over at 001.
  foreach ($order_storage->loadMultiple($order_storage->getQuery()->accessCheck(FALSE)
    ->condition('type', 'tec_purchase_order')
    ->condition('title', "ZTEST {$year}-", 'STARTS_WITH')
    ->execute()) as $suyo) {
    $suyo->delete();
  }
  $comprobar('y sin ninguno vivo, tampoco empieza de cero', "ZTEST {$year}-004", (string) $pedido('tec_purchase_order', $z)->label());

  echo "\n=== Los borradores no gastan numero ===\n";
  // A draft order is a staging area that gets thrown away. Burning a number on
  // one would leave a hole in a customer's sequence that nobody can explain.
  $draft = $pedido('tec_draft_order', $a);
  $comprobar('un borrador no recibe numero', FALSE, str_contains((string) $draft->label(), $year . '-'));

  echo "\n=== Por la bandera de la ficha, que es como lo usa la gente ===\n";
  foreach ([
    'tec_draft_order_excel_lover' => ['tec_sales_order', 'field_tec_customer', $a, 'A'],
    'tec_order_draft_po' => ['tec_purchase_order', 'field_tec_vendor', $kj, 'KJ'],
  ] as $flag_id => [$bundle, $field, $contact, $code]) {
    if (!$contact) {
      echo "  [dato] no hay ficha para {$flag_id}, me lo salto\n";
      continue;
    }
    $flag = $flag_service->getFlagById($flag_id);
    if (!$flag) {
      echo "  [dato] la bandera {$flag_id} no existe, me lo salto\n";
      continue;
    }

    $antes = $order_storage->getQuery()->accessCheck(FALSE)->condition('type', $bundle)->execute();
    try {
      $flag_service->flag($flag, $contact, \Drupal::currentUser()->getAccount());
    }
    catch (\Throwable $e) {
      // The process ends by redirecting, which has nowhere to go on the command
      // line. The order is already created and named by then.
      echo "  [dato] la bandera ha acabado con: " . mb_substr($e->getMessage(), 0, 60) . "\n";
    }
    $order_storage->resetCache();
    $despues = $order_storage->getQuery()->accessCheck(FALSE)->condition('type', $bundle)->execute();

    $nuevos = array_diff($despues, $antes);
    if (count($nuevos) !== 1) {
      echo sprintf("  [MAL ] %-52s la bandera ha creado %d pedidos\n", $flag_id, count($nuevos));
      $fallos++;
      $basura['tec_order'] = array_merge($basura['tec_order'], $nuevos);
      continue;
    }

    $order = $order_storage->load(reset($nuevos));
    $basura['tec_order'][] = $order->id();

    // The point of the whole rotation: the contact is attached before the save
    // that decides the name, so the code is in the name.
    $pegado = !$order->get($field)->isEmpty() && $order->get($field)->target_id == $contact->id();
    $comprobar($flag_id . ': el contacto esta puesto', TRUE, $pegado);
    $comprobar($flag_id . ': el nombre lleva el codigo', TRUE, str_starts_with((string) $order->label(), $code . ' ' . $year . '-'));
    echo "         el pedido ha salido como \"" . $order->label() . "\"\n";
  }
}
finally {
  echo "\n=== Recogiendo ===\n";

  // The processes unflag on their way through, but a process that fell over
  // early would leave the flag on and the next run would behave differently.
  foreach (['tec_draft_order_excel_lover', 'tec_order_draft_po'] as $flag_id) {
    $flag = $flag_service->getFlagById($flag_id);
    foreach ($basura['tec_crm'] as $id) {
      $contact = $crm_storage->load($id);
      if ($flag && $contact) {
        foreach ($flag_service->getEntityFlaggings($flag, $contact) as $flagging) {
          $flagging->delete();
        }
      }
    }
  }

  foreach (['tec_order', 'tec_line_item', 'tec_crm'] as $entity_type) {
    $ids = array_unique(array_filter($basura[$entity_type]));
    if (!$ids) {
      continue;
    }
    $storage = $etm->getStorage($entity_type);
    $storage->resetCache();
    $entities = $storage->loadMultiple($ids);
    // Lines first, or an order takes its lines' reason to exist with it.
    foreach ($entities as $entity) {
      if ($entity_type === 'tec_order' && $entity->hasField('field_tec_line_items')) {
        foreach ($entity->get('field_tec_line_items')->referencedEntities() as $line) {
          $line->delete();
        }
      }
    }
    $storage->delete($entities);
    echo sprintf("  %-16s borrados %d\n", $entity_type, count($entities));
  }

  // The counters go back to where they were found, or this test would quietly
  // advance the real numbering of every supplier it touched each time it ran.
  $ledger->deleteAll();
  if ($ledger_before) {
    $ledger->setMultiple($ledger_before);
  }
  echo sprintf("  %-16s devueltos %d\n", 'contadores', count($ledger_before));

  $account_switcher->switchBack();
}

echo "\n";
echo $fallos ? "HAY {$fallos} FALLOS.\n" : "Todo bien.\n";
