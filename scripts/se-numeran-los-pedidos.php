<?php

/**
 * @file
 * Proves orders get named "CODE YY-NNN", and that they get it by being saved.
 *
 * The case that had to work is the one that was asked for in plain words:
 * customer A places an order and it is A 26-001, customer B places one and it is
 * B 26-001 -- not 002 -- and when A comes back it is A 26-002. Per contact, and
 * restarting each year. That is checked here literally.
 *
 * Since 19 August 2026 the number is not spent when an order is created but the
 * first time somebody presses Save on it. Creating one is a single click that
 * builds a shopping list, and sixteen of the thirty-six orders on the site had
 * never had a quantity typed into them while each held a number of its contact's
 * sequence for ever. So the first thing checked here is what "+ Order" costs:
 * nothing. Then that Save is what pays, from the real Save of the real screen --
 * the handler is looked for in the form Drupal builds for /o/draft, behind the
 * one that writes the lines, and then run the way pressing the button runs it.
 *
 * Both routes that create orders are exercised, because for years they
 * disagreed: the flag on a contact card, which runs an ECA process, and code
 * calling the entity API, which is what the purchase list does.
 *
 * The flag route is worth the trouble of testing because of an ordering trap.
 * Both ECA processes used to create the order, save it, and attach the contact
 * afterwards. The short code in the name comes from that contact, so with the
 * old order of steps every order would come out with no code at all. This
 * asserts the contact really is there in time to be read.
 *
 * Everything it creates, it deletes.
 */

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\UserSession;
use Drupal\tec_production\OrderNumber;
use Drupal\views\Form\ViewsForm;
use Drupal\views\Views;

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
// expensive to run carelessly: every order it saves burns a real number for a
// real supplier, and deleting the order afterwards no longer gives it back.
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
 * What "+ Order" leaves behind: an order with a line and no number yet.
 *
 * Built the way code builds them, with no title, so that the name it comes out
 * with is the one the presave hook gives it and not one this test made up. The
 * line is there because the draft screen is a list of lines and a screen with
 * no rows is not the screen anybody uses.
 */
$borrador = static function (string $bundle, $contact = NULL) use ($etm, $order_storage, &$basura) {
  $values = ['type' => $bundle, 'status' => 1];
  $field = OrderNumber::contactField($bundle) ?? 'field_tec_customer';
  if ($contact) {
    $values[$field] = $contact->id();
  }
  $order = $order_storage->create($values);
  $order->save();
  $basura['tec_order'][] = $order->id();

  $line = $etm->getStorage('tec_line_item')->create([
    'type' => $bundle === 'tec_purchase_order' ? 'tec_po_line_item' : 'tec_sales_order_line_item',
    'title' => 'PRUEBA numeracion linea',
    'field_tec_quantity' => 1,
    'field_tec_order' => $order->id(),
  ]);
  $line->save();
  $basura['tec_line_item'][] = $line->id();

  $order->set('field_tec_line_items', [$line->id()])->save();

  return $order_storage->loadUnchanged($order->id());
};

/**
 * The display of the line items view that each kind of order is drafted on.
 */
$pantalla = static fn(string $bundle): string => $bundle === 'tec_purchase_order' ? 'page_1' : 'page_2';

/**
 * Pressing Save on the draft screen, as far as this order is concerned.
 *
 * The button runs a list of handlers and the last of them is ours; the ones in
 * front save the lines, which is Drupal's business and not this test's. So what
 * is run here is ours, with the form state it is given on that screen: the view
 * of the draft, carrying the order id as its argument. That the button really
 * does reach it is asserted separately, from the form Drupal builds.
 */
$guardar = static function ($order) use ($order_storage, $pantalla) {
  $view = Views::getView('tec_order_sales_order_line_items');
  $view->setDisplay($pantalla($order->bundle()));
  $view->setArguments([$order->id()]);

  $form_state = new FormState();
  $form_state->setBuildInfo(['args' => [$view, []]]);
  $form = [];
  _tec_production_earn_the_number($form, $form_state);

  return $order_storage->loadUnchanged($order->id());
};

/**
 * An order the way one really comes about: created, then kept.
 */
$pedido = static fn(string $bundle, $contact = NULL) => $guardar($borrador($bundle, $contact));

/**
 * How high this contact's counter stands, so that not moving can be asserted.
 */
$contador = static function (string $bundle, $contact = NULL) use ($ledger): int {
  return (int) $ledger->get(OrderNumber::ledgerKey($bundle, OrderNumber::prefix($contact)), 0);
};

try {
  echo "=== Crear un pedido no gasta numero ===\n";
  // The whole point of the change. "+ Order" builds a line per size the customer
  // could buy, and plenty of those clicks are a look rather than an order.
  $w = $ficha('WTEST');
  $antes = $contador('tec_sales_order', $w);
  $recien = $borrador('tec_sales_order', $w);

  $comprobar('el pedido recien creado no lleva numero', FALSE, OrderNumber::isNumber($recien->label()));
  $comprobar('y se llama por lo que es', 'Draft order for ' . $w->label(), (string) $recien->label());
  $comprobar('el contador no se ha movido', $antes, $contador('tec_sales_order', $w));

  // And thrown away, it costs nothing either: the next one is still 001.
  $recien->delete();
  $comprobar('tirado el borrador, el siguiente empieza por el principio',
    "WTEST {$year}-001", (string) $pedido('tec_sales_order', $w)->label());

  echo "\n=== El Save de la pantalla de borrador es el que lo paga ===\n";
  $x = $ficha('XTEST');
  $suyo = $borrador('tec_sales_order', $x);

  // The wiring: the button on /o/draft really does reach the handler, and it
  // reaches it last, behind the handler that writes what was typed.
  $view = Views::getView('tec_order_sales_order_line_items');
  $view->setDisplay('page_2');
  $view->setArguments([$suyo->id()]);
  $view->execute();
  $formulario = \Drupal::formBuilder()->getForm(
    ViewsForm::create(\Drupal::getContainer(), $view->storage->id(), $view->current_display, $view->args),
    $view,
    []
  );
  $manos = array_map(
    static fn($handler) => is_array($handler)
      ? (is_object($handler[0]) ? get_class($handler[0]) : $handler[0]) . '::' . $handler[1]
      : (string) $handler,
    $formulario['#submit'] ?? []
  );
  $nuestra = array_search('_tec_production_earn_the_number', $manos, TRUE);
  $lineas = array_search('Drupal\views_entity_form_field\Plugin\views\field\EntityFormField::saveEntities', $manos, TRUE);

  $comprobar('el Save de /o/draft llama al que da el numero', TRUE, $nuestra !== FALSE);
  $comprobar('y lo llama despues de guardar las lineas', TRUE, $lineas !== FALSE && $nuestra > $lineas);
  $comprobar('hasta pulsarlo sigue sin numero', FALSE, OrderNumber::isNumber($suyo->label()));

  // And now the button.
  $guardado = $guardar($suyo);
  $comprobar('al guardar, se lo gana', "XTEST {$year}-001", (string) $guardado->label());

  $otra_vez = $guardar($guardado);
  $comprobar('guardar otra vez no gasta otro', "XTEST {$year}-001", (string) $otra_vez->label());
  $comprobar('y el contador esta donde lo dejo', 1, $contador('tec_sales_order', $x));

  echo "\n=== El caso que se pidio, palabra por palabra ===\n";
  $a = $ficha('A');
  $b = $ficha('B');

  $ventaA1 = $pedido('tec_sales_order', $a);
  $comprobar('venta 1, cliente A', "A {$year}-001", (string) $ventaA1->label());
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
  // Esto pedia "26-001" a pelo, y volvia a ser un dato sobre el mundo y no sobre el
  // codigo: hay un pedido de venta de verdad, el 1003, que ya se llama asi, asi que
  // el siguiente sin cliente sale 26-002 y hacia bien. Lo que importa es que sin
  // codigo corto el nombre EMPIEZA por el ano, sin espacio ni guion suelto delante,
  // y que sigue la serie. Eso es lo que se pregunta.
  $sinCliente = (string) $pedido('tec_sales_order')->label();
  $comprobar('venta sin cliente, sin nada delante del ano', TRUE,
    (bool) preg_match("/^{$year}-\d{3}$/", $sinCliente));
  $siguienteSinCliente = (string) $pedido('tec_sales_order')->label();
  $comprobar('y la siguiente sin cliente va justo detras',
    "{$year}-" . str_pad((string) ((int) substr($sinCliente, -3) + 1), 3, '0', STR_PAD_LEFT),
    $siguienteSinCliente);

  echo "\n=== Un numero ya usado no se reparte dos veces ===\n";
  // Renaming A 26-001 out of the way and saving another order for A: counting
  // alone would hand out 002, which is taken. It has to climb to 003.
  $ventaA1->set('title', 'PRUEBA hueco')->save();
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
    ->execute()) as $viejo) {
    $viejo->delete();
  }
  $comprobar('y sin ninguno vivo, tampoco empieza de cero', "ZTEST {$year}-004", (string) $pedido('tec_purchase_order', $z)->label());

  echo "\n=== Los borradores no gastan numero ===\n";
  // The draft bundle is a staging area that gets thrown away. It is not in the
  // list of kinds that are numbered, so not even saving it hands it one.
  $draft = $pedido('tec_draft_order', $a);
  $comprobar('un borrador no recibe numero', FALSE, str_contains((string) $draft->label(), $year . '-'));

  echo "\n=== Por la bandera de la ficha, que es como lo usa la gente ===\n";
  foreach ([
    'tec_order_draft_sales' => ['tec_sales_order', 'field_tec_customer', $a, 'A'],
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

    $alto = $contador($bundle, $contact);
    $antes = $order_storage->getQuery()->accessCheck(FALSE)->condition('type', $bundle)->execute();
    try {
      $flag_service->flag($flag, $contact, \Drupal::currentUser()->getAccount());
    }
    catch (\Throwable $e) {
      // The process ends by redirecting, which has nowhere to go on the command
      // line. The order is already created by then.
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

    $comprobar($flag_id . ': pulsar la bandera no gasta numero', FALSE, OrderNumber::isNumber($order->label()));
    $comprobar($flag_id . ': ni mueve el contador', $alto, $contador($bundle, $contact));

    // The point of the whole rotation: the contact is attached before the save
    // that decides the name, so the code is in the name when Save is pressed.
    $pegado = !$order->get($field)->isEmpty() && $order->get($field)->target_id == $contact->id();
    $comprobar($flag_id . ': el contacto esta puesto', TRUE, $pegado);

    $order = $guardar($order);
    $comprobar($flag_id . ': al guardar, el nombre lleva el codigo', TRUE, str_starts_with((string) $order->label(), $code . ' ' . $year . '-'));
    echo "         el pedido ha salido como \"" . $order->label() . "\"\n";
  }
}
finally {
  echo "\n=== Recogiendo ===\n";

  // The processes unflag on their way through, but a process that fell over
  // early would leave the flag on and the next run would behave differently.
  foreach (['tec_order_draft_sales', 'tec_order_draft_po'] as $flag_id) {
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
