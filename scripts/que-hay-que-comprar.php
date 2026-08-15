<?php

/**
 * @file
 * Calcula la lista de compra por reposicion y la ensena agrupada por proveedor.
 *
 *   php vendor/bin/drush scr scripts/que-hay-que-comprar.php
 *
 * Esto es el motor de la pantalla "Purchase List" antes de que la pantalla
 * exista. Se escribe primero como script por dos razones: para ver si con los
 * datos de hoy la lista traeria alguna fila, y para dejar la aritmetica a la
 * vista y poder discutirla sin pelearse con una vista de Drupal.
 *
 * La regla, decidida por el dueno el 15 de agosto de 2026:
 *
 *   - Entra en la lista el material cuyo stock esta igual o por debajo de su
 *     punto de pedido. Sin punto de pedido no hay politica de reposicion, asi
 *     que ese material no entra nunca: se anota aparte como dato que falta.
 *   - Cuenta como stock lo que hay mas lo que ya viene de camino en pedidos de
 *     compra abiertos, o la lista mandaria pedir otra vez lo pedido ayer. Lo
 *     que viene de camino se cuenta con el mismo codigo que el tablero de
 *     stock, Purchasing::onOrder, para que las dos pantallas no discrepen.
 *   - La cantidad sugerida sube el stock hasta punto de pedido mas stock de
 *     seguridad. Subir solo hasta el punto de pedido dejaria el material otra
 *     vez en el borde, y volveria a salir en la lista al dia siguiente.
 *   - Esa cantidad esta en unidad de inventario, y se compra en unidad de
 *     compra: se divide por el factor de compra a inventario y se redondea
 *     hacia arriba, porque nadie vende media caja.
 *   - Si sale por debajo del MOQ, se sube al MOQ.
 *   - El importe de la linea es cantidad de compra por coste de compra.
 *
 * Las unidades de cada campo no estan escritas en la configuracion, salen de
 * docs/material_inventory_naming_matrix.md: el stock se cuenta en unidad de
 * inventario, y el coste y el MOQ van en unidad de compra.
 *
 * No escribe nada.
 */

/**
 * Los materiales, con todo lo que hace falta para decidir si hay que comprar.
 */
function materiales(): array {
  $almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $ids = $almacen->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'tec_inventory')
    ->sort('name')
    ->execute();
  return $ids ? $almacen->loadMultiple($ids) : [];
}

/**
 * El numero que guarda un campo, o NULL si esta vacio.
 *
 * Vacio y cero no son lo mismo aqui: un punto de pedido a cero es una decision
 * ("no repongas esto"), y un punto de pedido vacio es un dato que falta.
 */
function numero($entidad, string $campo): ?float {
  if (!$entidad->hasField($campo) || $entidad->get($campo)->isEmpty()) {
    return NULL;
  }
  $valor = $entidad->get($campo)->value;
  return $valor === NULL || $valor === '' ? NULL : (float) $valor;
}

/**
 * La etiqueta de lo que apunta un campo de referencia, o una raya.
 */
function etiqueta($entidad, string $campo): string {
  if (!$entidad->hasField($campo) || $entidad->get($campo)->isEmpty()) {
    return '-';
  }
  $referida = $entidad->get($campo)->entity;
  return $referida ? $referida->label() : '(borrado)';
}

/**
 * Lo que hay que comprar de un material, o NULL si no hay que comprar nada.
 */
function sugerencia($material, float $enCamino): ?array {
  $puntoDePedido = numero($material, 'field_tec_reorder_point');
  if ($puntoDePedido === NULL) {
    return NULL;
  }

  // Un factor vacio o a cero no puede dividir. Se trata como 1, que es lo que
  // significa cuando la unidad de compra y la de inventario son la misma.
  $factor = numero($material, 'field_tec_units');
  $factor = ($factor === NULL || $factor <= 0) ? 1.0 : $factor;

  // El stock esta en unidad de inventario y lo que viene de camino en unidad de
  // compra, asi que hay que llevarlo a la misma antes de sumarlos.
  $stock = numero($material, 'field_tec_stock_level') ?? 0.0;
  $disponible = $stock + $enCamino * $factor;
  if ($disponible > $puntoDePedido) {
    return NULL;
  }

  $seguridad = numero($material, 'field_tec_safety_stock') ?? 0.0;
  $faltan = ($puntoDePedido + $seguridad) - $disponible;

  $comprar = (int) ceil($faltan / $factor);
  $comprar = max($comprar, 1);

  $moq = numero($material, 'field_tec_moq_quantity');
  $porElMoq = $moq !== NULL && $comprar < $moq;
  if ($porElMoq) {
    $comprar = (int) $moq;
  }

  $coste = numero($material, 'field_tec_cost');

  return [
    'material' => $material,
    'proveedor' => etiqueta($material, 'field_tec_vendor'),
    'stock' => $stock,
    'en_camino' => $enCamino,
    'rop' => $puntoDePedido,
    'seguridad' => $seguridad,
    'factor' => $factor,
    'moq' => $moq,
    'por_el_moq' => $porElMoq,
    'comprar' => $comprar,
    'unidad_compra' => etiqueta($material, 'field_tec_unit_purchase'),
    'unidad_stock' => etiqueta($material, 'field_tec_uos'),
    'coste' => $coste,
    'importe' => $coste === NULL ? NULL : $comprar * $coste,
    'plazo' => numero($material, 'field_tec_lead_time_days'),
  ];
}

$todos = materiales();
$enCamino = \Drupal\tec_production\Purchasing::onOrder(\Drupal::entityTypeManager(), array_keys($todos));
$lineas = [];
$sinPuntoDePedido = [];
$sinCoste = [];
$sinProveedor = [];

foreach ($todos as $tid => $material) {
  if (numero($material, 'field_tec_reorder_point') === NULL) {
    $sinPuntoDePedido[] = $material->label();
  }
  $linea = sugerencia($material, (float) ($enCamino[$tid] ?? 0.0));
  if (!$linea) {
    continue;
  }
  if ($linea['coste'] === NULL) {
    $sinCoste[] = $material->label();
  }
  if ($linea['proveedor'] === '-') {
    $sinProveedor[] = $material->label();
  }
  $lineas[$linea['proveedor']][] = $linea;
}

ksort($lineas);

echo "\n";
printf("  %d materiales en el catalogo\n", count($todos));
printf("  %d sin punto de pedido, o sea que no entran nunca en la lista\n", count($sinPuntoDePedido));
printf("  %d materiales hay que comprar, de %d proveedores\n\n", array_sum(array_map('count', $lineas)), count($lineas));

if (!$lineas) {
  echo "  Ningun material esta en o por debajo de su punto de pedido.\n";
  echo "  La pantalla se dibujaria vacia, asi que hace falta un juego de\n";
  echo "  pruebas con algun material bajo minimos para poder verla.\n\n";
}

foreach ($lineas as $proveedor => $suyas) {
  echo "  " . $proveedor . "\n";
  echo '  ' . str_repeat('-', 108) . "\n";
  printf(
    "  %-30s %10s %8s %10s %10s %8s %12s %12s\n",
    'material', 'stock', 'camino', 'ROP', 'faltan', 'MOQ', 'comprar', 'importe'
  );
  $total = 0.0;
  foreach ($suyas as $linea) {
    $total += $linea['importe'] ?? 0.0;
    printf(
      "  %-30s %10s %8s %10s %10s %8s %12s %12s\n",
      substr($linea['material']->label(), 0, 30),
      rtrim(rtrim(number_format($linea['stock'], 2), '0'), '.'),
      $linea['en_camino'] > 0 ? rtrim(rtrim(number_format($linea['en_camino'], 2), '0'), '.') : '-',
      rtrim(rtrim(number_format($linea['rop'], 2), '0'), '.'),
      rtrim(rtrim(number_format(($linea['rop'] + $linea['seguridad']) - ($linea['stock'] + $linea['en_camino'] * $linea['factor']), 2), '0'), '.'),
      $linea['moq'] === NULL ? '-' : (string) (int) $linea['moq'],
      $linea['comprar'] . ' ' . substr($linea['unidad_compra'], 0, 6) . ($linea['por_el_moq'] ? ' (MOQ)' : ''),
      $linea['importe'] === NULL ? 'sin coste' : number_format($linea['importe'], 2)
    );
  }
  printf("  %-30s %75s\n\n", '', 'total ' . number_format($total, 2));
}

foreach ([
  'sin punto de pedido' => $sinPuntoDePedido,
  'hay que comprarlos y no tienen coste' => $sinCoste,
  'hay que comprarlos y no tienen proveedor' => $sinProveedor,
] as $titulo => $lista) {
  if (!$lista) {
    continue;
  }
  printf("  %d %s:\n", count($lista), $titulo);
  foreach (array_slice($lista, 0, 12) as $nombre) {
    echo '      ' . $nombre . "\n";
  }
  if (count($lista) > 12) {
    printf("      y %d mas\n", count($lista) - 12);
  }
  echo "\n";
}
