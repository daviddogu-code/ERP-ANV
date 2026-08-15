<?php

/**
 * @file
 * Busca pedidos guardados con un estado que no esta en la lista, y los arregla.
 *
 *   php vendor\bin\drush.php scr scripts/poner-los-pedidos-en-un-estado-que-existe.php
 *   php vendor\bin\drush.php scr scripts/poner-los-pedidos-en-un-estado-que-existe.php -- de-verdad
 *
 * El campo de estado es una lista cerrada de diez valores, de draft a cancelled.
 * Pero Drupal solo comprueba la lista cuando el valor llega por un formulario:
 * si se guarda por codigo, se traga cualquier cadena y la escribe tal cual. Asi
 * es como el juego de pruebas dejo el pedido de venta en 'new', un estado que no
 * existe en este ERP.
 *
 * Y no es solo cosmetico. Las dos pantallas de trabajo del pedido de venta,
 * o/draft/% para editar las lineas y o/pf/%/print para imprimir, filtran por
 * draft. Un pedido en un estado inventado no sale en ninguna de las dos, asi que
 * queda invisible y sin manera de editarlo. Como encima la prueba de humo solo
 * pide una pantalla si encuentra datos con los que pedirla, las dos se saltaban
 * calladas y el resumen decia "cargan todas" mirando dos pantallas menos.
 *
 * Sin 'de-verdad' solo dice lo que haria.
 */

$deVerdad = in_array('de-verdad', $extra ?? [], TRUE);

$permitidos = [];
foreach (\Drupal::config('field.storage.tec_order.field_tec_order_status')->get('settings.allowed_values') ?? [] as $opcion) {
  $permitidos[$opcion['value']] = $opcion['label'];
}
if (!$permitidos) {
  echo "\n  No encuentro la lista de estados. Mejor no tocar nada.\n\n";
  return;
}

echo "\n";
printf("Los %d estados que existen: %s\n", count($permitidos), implode(', ', array_keys($permitidos)));
echo "\n";

$gestor = \Drupal::entityTypeManager();
$almacen = $gestor->getStorage('tec_order');
$ids = $almacen->getQuery()->accessCheck(FALSE)->sort('id')->execute();

$rotos = [];
foreach ($almacen->loadMultiple($ids) as $pedido) {
  if (!$pedido->hasField('field_tec_order_status') || $pedido->get('field_tec_order_status')->isEmpty()) {
    continue;
  }
  $estado = $pedido->get('field_tec_order_status')->value;
  if (!array_key_exists($estado, $permitidos)) {
    $rotos[] = [$pedido, $estado];
  }
}

if (!$rotos) {
  echo "Ningun pedido tiene un estado inventado.\n\n";
  return;
}

echo "Pedidos con un estado que no existe\n";
echo str_repeat('-', 78) . "\n";
foreach ($rotos as [$pedido, $estado]) {
  printf(
    "  %-6s %-26s %-30s '%s' -> 'draft'\n",
    $pedido->id(),
    $pedido->bundle(),
    mb_substr((string) $pedido->label(), 0, 30),
    $estado
  );
}

echo "\n";
if (!$deVerdad) {
  echo "Esto es un ensayo. Para hacerlo: -- de-verdad\n\n";
  return;
}

foreach ($rotos as [$pedido, $estado]) {
  $tituloAntes = (string) $pedido->label();
  $pedido->set('field_tec_order_status', 'draft');
  $pedido->save();

  $despues = $almacen->loadUnchanged($pedido->id());
  printf("  %-6s estado: %s\n", $pedido->id(), $despues->get('field_tec_order_status')->value);
  // El titulo del pedido lo escribe ECA, asi que merece la pena mirar que el
  // guardado no lo haya renumerado y dejado el juego de pruebas sin su marca.
  if ((string) $despues->label() !== $tituloAntes) {
    printf("  %-6s OJO, el titulo ha cambiado: '%s' -> '%s'\n", $pedido->id(), $tituloAntes, $despues->label());
  }
}

echo "\nHecho.\n\n";
