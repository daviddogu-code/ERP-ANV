<?php

/**
 * @file
 * Por que la prueba de humo no puede pedir las dos pantallas de pedido de venta.
 *
 *   php vendor\bin\drush.php scr scripts/por-que-no-hay-pedido-de-venta.php
 *
 * Las dos pantallas que quedan sin mirar, o/draft/% y o/pf/%/print, salen de la
 * vista tec_order_sales_order_line_items y piden un argumento. La prueba de humo
 * solo acepta un argumento si la vista devuelve filas con el, asi que hace falta
 * saber tres cosas: que pedidos hay, de que tipo, y de donde saca la vista el
 * valor que espera en la direccion.
 *
 * No escribe nada.
 */

use Drupal\views\Views;

$gestor = \Drupal::entityTypeManager();
$almacen = $gestor->getStorage('tec_order');

echo "\n";
echo "Los pedidos que hay en la base\n";
echo str_repeat('-', 78) . "\n";

$ids = $almacen->getQuery()->accessCheck(FALSE)->sort('id')->execute();
if (!$ids) {
  echo "  ninguno\n";
}
foreach ($almacen->loadMultiple($ids) as $pedido) {
  $lineas = $pedido->hasField('field_tec_line_items')
    ? $pedido->get('field_tec_line_items')->count()
    : 0;
  printf(
    "  %-6s %-26s %-34s %s lineas\n",
    $pedido->id(),
    $pedido->bundle(),
    mb_substr((string) $pedido->label(), 0, 34),
    $lineas
  );
}

echo "\n";
echo "Las lineas de pedido de venta que hay\n";
echo str_repeat('-', 78) . "\n";

$lineas = $gestor->getStorage('tec_line_item');
$idsLinea = $lineas->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_sales_order_line_item')
  ->sort('id')
  ->execute();
if (!$idsLinea) {
  echo "  ninguna\n";
}
foreach ($lineas->loadMultiple($idsLinea) as $linea) {
  $pedido = $linea->hasField('field_tec_order') && !$linea->get('field_tec_order')->isEmpty()
    ? $linea->get('field_tec_order')->target_id
    : 'suelta';
  printf(
    "  %-6s %-34s pedido: %s\n",
    $linea->id(),
    mb_substr((string) $linea->label(), 0, 34),
    $pedido
  );
}

echo "\n";
echo "Que argumento espera cada pantalla\n";
echo str_repeat('-', 78) . "\n";

$vista = Views::getView('tec_order_sales_order_line_items');
foreach (array_keys($vista->storage->get('display')) as $displayId) {
  $vista->setDisplay($displayId);
  $ruta = $vista->display_handler->getOption('path');
  if (!$ruta || !str_contains((string) $ruta, '%')) {
    continue;
  }
  printf("\n  %s  (display %s)\n", $ruta, $displayId);
  foreach ($vista->display_handler->getHandlers('argument') as $nombre => $manejador) {
    printf(
      "      argumento %-28s tabla %-34s columna %s\n",
      $nombre,
      $manejador->table,
      $manejador->realField ?: $manejador->field
    );
  }
  foreach ($vista->display_handler->getHandlers('filter') as $nombre => $manejador) {
    $valor = $manejador->value;
    printf(
      "      filtro    %-28s %s\n",
      $nombre,
      is_array($valor) ? implode(', ', array_map('strval', $valor)) : (string) $valor
    );
  }
}
$vista->destroy();

echo "\n";
