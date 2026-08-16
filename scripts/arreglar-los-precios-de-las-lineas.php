<?php

/**
 * @file
 * Pone en cada linea de compra vieja el precio que de verdad se le cobro.
 *
 * Desde hoy el importe de una linea es su precio por su cantidad, y ya no el
 * coste que tenga hoy el material. Eso deja al descubierto lo que llevaba
 * escrito el campo del precio, que en las lineas creadas desde la ficha de un
 * proveedor no era el coste de compra sino el coste por unidad de consumo: 0,02
 * en un material que se compra a 80. Si se dejaran asi, el pedido de 800 pasaria
 * a valer dos baht la proxima vez que alguien lo guardara.
 *
 * Se reparan de dos maneras distintas, segun lo que haya de donde tirar:
 *
 *   - La linea tiene importe: el precio pasa a ser importe entre cantidad. Se
 *     respeta el dinero que el pedido lleva enseñando desde que se hizo, que es
 *     lo unico que se firmo y lo unico que vio el proveedor.
 *   - La linea no tiene importe: son borradores a medio escribir, sin cantidad y
 *     sin nada enseñado nunca. Se les pone el coste de compra de hoy, que es el
 *     que se aplicaria si alguien los terminara esta tarde.
 *
 * En seco:  php vendor\bin\drush.php scr scripts/arreglar-los-precios-de-las-lineas.php
 * De veras: php vendor\bin\drush.php scr scripts/arreglar-los-precios-de-las-lineas.php -- aplicar
 */

$aplicar = in_array('aplicar', $_SERVER['argv'] ?? [], TRUE);

$gestor = \Drupal::entityTypeManager();
$almacen = $gestor->getStorage('tec_line_item');
$ids = $almacen->getQuery()->accessCheck(FALSE)->condition('type', 'tec_po_line_item')->execute();

printf("%s sobre %d lineas de compra\n\n", $aplicar ? 'APLICANDO' : 'En seco (nada se guarda)', count($ids));

$porImporte = [];
$porCosteDeHoy = [];
$sinNadaDeDondeTirar = [];
$intactas = 0;

foreach ($almacen->loadMultiple($ids) as $linea) {
  $cantidad = (float) ($linea->get('field_tec_quantity')->value ?? 0);
  $total = $linea->get('field_tec_line_item_total_number')->isEmpty()
    ? NULL : (float) $linea->get('field_tec_line_item_total_number')->value;
  $precio = $linea->get('field_tec_price')->isEmpty() ? NULL : (float) $linea->get('field_tec_price')->value;
  $material = $linea->get('field_tec_inventory')->entity;
  $coste = $material && !$material->get('field_tec_cost')->isEmpty()
    ? (float) $material->get('field_tec_cost')->value : NULL;

  if ($total !== NULL && $cantidad > 0) {
    $deberia = round($total / $cantidad, 2);
    $donde = &$porImporte;
  }
  elseif ($coste !== NULL) {
    $deberia = round($coste, 2);
    $donde = &$porCosteDeHoy;
  }
  else {
    $sinNadaDeDondeTirar[] = sprintf('linea %s (%s)', $linea->id(), $material ? $material->label() : 'sin material');
    continue;
  }

  if ($precio !== NULL && abs($precio - $deberia) < 0.005) {
    $intactas++;
    unset($donde);
    continue;
  }

  $donde[] = [
    'linea' => $linea,
    'material' => $material ? $material->label() : '?',
    'cantidad' => $cantidad,
    'antes' => $precio,
    'ahora' => $deberia,
    'total' => $total,
  ];
  unset($donde);
}

/**
 * Cuenta lo que se va a hacer con un grupo, y lo hace si toca.
 */
$obrar = function (string $titulo, array $grupo) use ($aplicar): void {
  printf("%s: %d\n", $titulo, count($grupo));
  if (!$grupo) {
    return;
  }
  print str_repeat('-', 78) . "\n";
  foreach ($grupo as $caso) {
    printf("  linea %-6s %-24s cantidad %-8s precio %10s -> %-10s %s\n",
      $caso['linea']->id(),
      mb_substr($caso['material'], 0, 24),
      rtrim(rtrim(number_format($caso['cantidad'], 2, '.', ''), '0'), '.'),
      $caso['antes'] === NULL ? '(vacio)' : number_format($caso['antes'], 2),
      number_format($caso['ahora'], 2),
      $caso['total'] === NULL ? '' : 'importe ' . number_format($caso['total'], 2));

    if ($aplicar) {
      $caso['linea']->set('field_tec_price', $caso['ahora'])->save();
    }
  }
  print "\n";
};

$obrar('Con importe ya enseñado, el precio sale de el', $porImporte);
$obrar('Sin importe, se les pone el coste de compra de hoy', $porCosteDeHoy);
printf("Ya estaban bien: %d\n", $intactas);
if ($sinNadaDeDondeTirar) {
  printf("Sin nada de donde tirar, se quedan como estan: %s\n", implode(', ', $sinNadaDeDondeTirar));
}

if (!$aplicar) {
  print "\nNada guardado. Para hacerlo de verdad, el mismo comando con: -- aplicar\n";
  return;
}

// -----------------------------------------------------------------------------
// Repaso: el importe lo vuelve a poner la ECA al guardar, asi que hay que mirar
// que haya salido el mismo de antes. Donde el precio no era exacto se puede
// haber movido un centimo, y eso hay que decirlo, no esconderlo.
// -----------------------------------------------------------------------------
print "\nRepaso de los importes\n" . str_repeat('=', 78) . "\n";
$movidos = [];
foreach ($porImporte as $caso) {
  $ahora = $gestor->getStorage('tec_line_item')->loadUnchanged($caso['linea']->id());
  $importe = $ahora->get('field_tec_line_item_total_number')->isEmpty()
    ? NULL : (float) $ahora->get('field_tec_line_item_total_number')->value;
  if ($importe === NULL || abs($importe - $caso['total']) > 0.005) {
    $movidos[] = sprintf('  linea %s: %s -> %s', $caso['linea']->id(),
      number_format($caso['total'], 2), $importe === NULL ? '(vacio)' : number_format($importe, 2));
  }
}
if ($movidos) {
  printf("Se han movido %d:\n%s\n", count($movidos), implode("\n", $movidos));
}
else {
  printf("Ninguno se ha movido. Las %d lineas con importe valen lo mismo que antes.\n", count($porImporte));
}
