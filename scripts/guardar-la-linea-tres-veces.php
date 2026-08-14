<?php

/**
 * Guarda una linea de pedido tres veces con cantidades distintas.
 *
 * Es la prueba del fallo intermitente al guardar pedidos de compra. Antes, una
 * accion de ECA escribia en el campo decimal del total la multiplicacion sin
 * hacer, tal cual, con el asterisco dentro, y Drupal petaba al intentar
 * redondear un texto. La traza del registro lo dejo por escrito:
 *   round('10 * 80.00', 2)
 *
 * El guion cambia la cantidad, guarda, y comprueba tres cosas en cada vuelta:
 * que no salte ninguna excepcion, que el total guardado sea numerico, y que
 * valga cantidad por coste. Al final deja la cantidad como estaba.
 *
 * Uso: drush php:script scripts/guardar-la-linea-tres-veces
 */

$cantidades = [3, 7, 12];

$almacen = \Drupal::entityTypeManager()->getStorage('tec_line_item');
$ids = $almacen->getQuery()->accessCheck(FALSE)->sort('id')->execute();

if (!$ids) {
  echo "No hay ninguna linea de pedido con la que probar.\n";
  return;
}

$linea = $almacen->load(reset($ids));
$cantidadOriginal = $linea->get('field_tec_quantity')->value;
$totalOriginal = $linea->get('field_tec_line_item_total_number')->value;

$material = $linea->get('field_tec_inventory')->entity;
$coste = $material ? (float) $material->get('field_tec_cost')->value : NULL;

echo "\n";
echo "Linea " . $linea->id() . " del pedido.\n";
echo "Material: " . ($material ? $material->label() : 'sin material') . "\n";
echo "Coste de compra: " . ($coste === NULL ? 'desconocido' : number_format($coste, 2)) . "\n";
echo "Estado de partida: cantidad $cantidadOriginal, total $totalOriginal\n";
echo "\n";

// Marca de agua para separar en el registro lo que provoca esta prueba de lo
// que hubiera antes.
$desde = \Drupal::time()->getRequestTime();

$fallos = 0;

foreach ($cantidades as $vuelta => $cantidad) {
  $numero = $vuelta + 1;
  echo "Vuelta $numero: cantidad $cantidad ... ";

  $linea->set('field_tec_quantity', $cantidad);

  try {
    $linea->save();
  }
  catch (\Throwable $error) {
    $fallos++;
    echo "PETA\n";
    echo "  " . get_class($error) . ': ' . $error->getMessage() . "\n";
    continue;
  }

  // Se recarga desde la base para leer lo que de verdad quedo guardado, no lo
  // que tengamos en memoria.
  $almacen->resetCache([$linea->id()]);
  $guardada = $almacen->load($linea->id());
  $total = $guardada->get('field_tec_line_item_total_number')->value;

  $problemas = [];
  if ($total === NULL || $total === '') {
    $problemas[] = 'el total se ha quedado vacio';
  }
  elseif (!is_numeric($total)) {
    $problemas[] = "el total no es un numero: '$total'";
  }
  elseif ($coste !== NULL) {
    $esperado = round($cantidad * $coste, 2);
    if (abs((float) $total - $esperado) > 0.005) {
      $problemas[] = "el total es $total y deberia ser " . number_format($esperado, 2);
    }
  }

  if ($problemas) {
    $fallos++;
    echo "MAL\n";
    foreach ($problemas as $problema) {
      echo "  $problema\n";
    }
  }
  else {
    echo "bien, total $total\n";
  }

  $linea = $guardada;
}

// Deja la linea como estaba.
$linea->set('field_tec_quantity', $cantidadOriginal);
$linea->save();
$almacen->resetCache([$linea->id()]);
$final = $almacen->load($linea->id());

echo "\n";
echo "Devuelta a la cantidad original: " . $final->get('field_tec_quantity')->value;
echo ", total " . $final->get('field_tec_line_item_total_number')->value . "\n";

// Errores que haya escrito Drupal en el registro durante la prueba.
$conexion = \Drupal::database();
if ($conexion->schema()->tableExists('watchdog')) {
  $graves = $conexion->select('watchdog', 'w')
    ->fields('w', ['type', 'message', 'variables'])
    ->condition('timestamp', $desde, '>=')
    ->condition('severity', 4, '<=')
    ->execute()
    ->fetchAll();
  echo "\n";
  if ($graves) {
    $fallos += count($graves);
    echo "El registro ha recogido " . count($graves) . " errores durante la prueba:\n";
    foreach ($graves as $fila) {
      $variables = @unserialize($fila->variables) ?: [];
      $texto = $variables['@message'] ?? $variables['%type'] ?? $fila->message;
      echo "  [" . $fila->type . '] ' . mb_substr(strip_tags((string) $texto), 0, 160) . "\n";
    }
  }
  else {
    echo "El registro no ha recogido ningun error durante la prueba.\n";
  }
}

echo "\n";
if ($fallos === 0) {
  echo "Tres guardados con cantidades distintas y ni un fallo. El error de round() no vuelve.\n";
}
else {
  echo "Quedan $fallos problemas. El fallo sigue ahi.\n";
}
