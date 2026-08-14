<?php

/**
 * Dibuja los displays que se han tocado y ensena las columnas que salen.
 *
 * La prueba de humo pide paginas por su direccion, y dos de las pantallas que
 * se han tocado no se pueden pedir asi porque necesitan un pedido que cumpla el
 * filtro. Esto va por debajo: ejecuta el display con el argumento a mano, lo
 * renderiza y saca las cabeceras de la tabla con la primera fila. Si algo se ha
 * quedado colgando, salta aqui.
 *
 * Uso: drush php:script scripts/dibujar-los-displays-tocados
 */

// Que display se prueba y con que argumento.
$aProbar = [
  ['tec_inventory', 'block_1', []],
  ['tec_inventory', 'data_export_1', []],
  // Las cuatro piezas se piden con el material dentro, una a una.
  ['tec_inventory_elements', 'block_1', ['material']],
  ['tec_inventory_elements', 'block_2', ['material']],
  ['tec_inventory_elements', 'block_3', ['material']],
  ['tec_inventory_elements', 'block_4', ['material']],
  // Las lineas se prueban con el pedido de compra, que es el que lleva
  // materiales dentro; con uno de venta las columnas de material salen vacias
  // porque una linea de venta lleva un producto, no una materia prima.
  ['tec_order_sales_order_line_items', 'block_2', ['compra']],
  ['tec_order_sales_order_line_items', 'page_1', ['compra']],
  ['tec_order_sales_order_line_items', 'page_3', ['compra']],
  ['tec_order_material_calculation_terms', 'default', ['pedido']],
  ['tec_order_material_calculation_terms', 'block_1', ['pedido']],
  ['tec_order_material_calculation_summary', 'default', ['pedido']],
  ['tec_order_material_calculation_summary', 'block_1', ['pedido']],
];

$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));

// Un pedido de cada clase, para tener con que rellenar los argumentos.
$almacen = \Drupal::entityTypeManager()->getStorage('tec_order');
$pedidos = [];
foreach (['tec_purchase_order', 'tec_sales_order'] as $clase) {
  $ids = $almacen->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', $clase)
    ->sort('id', 'DESC')
    ->range(0, 1)
    ->execute();
  if ($ids) {
    $pedidos[$clase] = reset($ids);
  }
}

// Y un material, para las piezas del listado.
$materiales = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->sort('tid')
  ->range(0, 1)
  ->execute();
$material = $materiales ? reset($materiales) : NULL;

echo "\n";
echo "Pedidos con los que se prueba: " . json_encode($pedidos) . "\n";
echo "Material con el que se prueba: " . ($material ?? 'NO HAY') . "\n";

$fallos = 0;
$renderizador = \Drupal::service('renderer');

foreach ($aProbar as [$nombre, $display, $argumentos]) {
  $vista = \Drupal\views\Views::getView($nombre);
  if (!$vista) {
    echo "\nNo existe la vista $nombre.\n";
    $fallos++;
    continue;
  }

  // Los dos pedidos valen; se prueba con el de venta si hay, que es el que mas
  // columnas mueve, y si no con el de compra.
  $valores = [];
  foreach ($argumentos as $que) {
    $valores[] = match ($que) {
      'pedido' => $pedidos['tec_sales_order'] ?? $pedidos['tec_purchase_order'] ?? 1,
      'compra' => $pedidos['tec_purchase_order'] ?? $pedidos['tec_sales_order'] ?? 1,
      'material' => $material,
    };
  }

  echo "\n";
  echo str_repeat('-', 78) . "\n";
  printf(" %s / %s   argumentos: %s\n", $nombre, $display, $valores ? implode(', ', $valores) : 'ninguno');
  echo str_repeat('-', 78) . "\n";

  try {
    $vista->setDisplay($display);
    $vista->setArguments($valores);
    $vista->preExecute();
    $vista->execute();
    $salida = $vista->render();
    $html = (string) $renderizador->renderInIsolation($salida);
  }
  catch (\Throwable $error) {
    $fallos++;
    echo "  PETA: " . get_class($error) . ': ' . $error->getMessage() . "\n";
    echo "  en " . basename($error->getFile()) . ':' . $error->getLine() . "\n";
    continue;
  }

  printf("  filas: %d\n", count($vista->result));

  // Las cabeceras de la tabla, que es lo que ve quien usa el ERP.
  if (preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $html, $encontrados)) {
    $cabeceras = array_map(fn($t) => trim(preg_replace('/\s+/', ' ', strip_tags($t))), $encontrados[1]);
    $cabeceras = array_values(array_filter($cabeceras, fn($t) => $t !== ''));
    if ($cabeceras) {
      echo "  columnas: " . implode(' | ', $cabeceras) . "\n";
    }
  }

  // Y la primera fila de datos, para ver que los numeros salen.
  if (preg_match('/<tbody.*?<tr[^>]*>(.*?)<\/tr>/s', $html, $fila)) {
    if (preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $fila[1], $celdas)) {
      $valores = array_map(fn($t) => trim(preg_replace('/\s+/', ' ', strip_tags($t))), $celdas[1]);
      echo "  primera fila: " . implode(' | ', array_slice($valores, 0, 14)) . "\n";
    }
  }
  else {
    // Los bloques sueltos no son tablas: son un trozo de HTML con la unidad y
    // el factor entre parentesis. Se ensena el texto pelado.
    $texto = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    echo $texto === '' ? "  (no dibuja nada)\n" : "  texto: $texto\n";
  }

  $vista->destroy();
}

echo "\n";
echo str_repeat('=', 78) . "\n";
if ($fallos === 0) {
  echo " Los " . count($aProbar) . " displays dibujan sin petar.\n";
}
else {
  echo " $fallos displays de " . count($aProbar) . " petan.\n";
}
echo str_repeat('=', 78) . "\n\n";
