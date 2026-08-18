<?php

/**
 * Fabrica un juego de pruebas completo para revisar las vistas de calculo.
 *
 * Con la base vacia de datos de prueba no se puede comprobar si las vistas que
 * calculan materiales por pedido dicen la verdad, porque no hay nada que
 * calcular. Este guion monta la cadena entera de una sola vez:
 *
 *   marca -> cliente -> producto -> variacion de color -> talla
 *   talla -> BoM con materiales dentro
 *   pedido de venta -> linea de pedido que apunta a la talla
 *
 * Todo lo que crea lleva PRUEBA en el nombre, para poder encontrarlo y borrarlo
 * despues con scripts/borrar-el-juego-de-pruebas.php.
 *
 * Uso: drush php:script scripts/fabricar-el-juego-de-pruebas
 */

// El campo de cliente elige con una vista y las vistas comprueban permisos.
$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$gestor = \Drupal::entityTypeManager();
$terminos = $gestor->getStorage('taxonomy_term');

$creado = [];

/**
 * Guarda una entidad y deja constancia de lo que se ha creado.
 */
$anotar = function (string $que, $entidad) use (&$creado) {
  $entidad->save();
  $creado[] = [
    'que' => $que,
    'tipo' => $entidad->getEntityTypeId(),
    'id' => $entidad->id(),
    'nombre' => $entidad->label(),
  ];
  printf("  %-24s %s (%s %s)\n", $que, $entidad->label(), $entidad->getEntityTypeId(), $entidad->id());
  return $entidad;
};

/**
 * Devuelve el primer termino de un vocabulario.
 */
$primerTermino = function (string $vocabulario) use ($terminos) {
  $tids = $terminos->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->sort('tid')
    ->range(0, 1)
    ->execute();
  return $tids ? $terminos->load(reset($tids)) : NULL;
};

/**
 * Busca un termino por nombre dentro de un vocabulario.
 */
$terminoLlamado = function (string $vocabulario, string $nombre) use ($terminos) {
  $tids = $terminos->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->condition('name', $nombre)
    ->range(0, 1)
    ->execute();
  return $tids ? $terminos->load(reset($tids)) : NULL;
};

echo "\n";
echo "Lo que hace falta y no hay:\n";

// La marca es obligatoria en el producto y el vocabulario esta vacio.
$marca = $terminoLlamado('tec_brands', 'PRUEBA marca');
if (!$marca) {
  $marca = $anotar('marca', $terminos->create([
    'vid' => 'tec_brands',
    'name' => 'PRUEBA marca',
  ]));
}
else {
  printf("  %-24s ya estaba (%s)\n", 'marca', $marca->id());
}

// El cliente tiene que ser un contacto de tipo Customer, y el unico contacto
// que hay en la base es el proveedor de prueba.
$tipoCliente = $terminoLlamado('tec_crm_contact_type', 'Customer');
$cliente = $anotar('cliente', $gestor->getStorage('tec_crm')->create([
  'type' => 'tec_contact_organization',
  'title' => 'PRUEBA cliente',
  'field_tec_contact_type' => $tipoCliente->id(),
  // Esta linea es la que ata el cliente al producto de aqui abajo, y sin ella el
  // juego de pruebas no sirve: media docena de pantallas del cliente -su pestana de
  // productos, la de ordenarlos, el pedido de un clic- salen de la marca que compra,
  // no del producto. Antes esa union se escribia al reves, en el producto; el
  // producto solto al cliente el 19 de agosto de 2026.
  'field_tec_brands' => $marca->id(),
]));

echo "\n";
echo "La cadena del producto:\n";

$tipoProducto = $primerTermino('tec_product_types');
$color = $primerTermino('tec_colors');
$talla = $primerTermino('tec_sizes');

$producto = $anotar('producto', $gestor->getStorage('tec_product')->create([
  'type' => 'tec_product',
  'title' => 'PRUEBA producto',
  'field_product_name' => 'PRUEBA producto',
  'field_tec_brand' => $marca->id(),
  'field_tec_product_type' => $tipoProducto->id(),
  'field_tec_product_material' => 'leather',
]));

$variacionColor = $anotar('variacion de color', $gestor->getStorage('tec_product')->create([
  'type' => 'tec_color_variation',
  'title' => 'PRUEBA producto - ' . $color->label(),
  'field_tec_colors' => $color->id(),
  'field_tec_product' => $producto->id(),
]));

$producto->set('field_tec_color_variations', [$variacionColor->id()]);
$producto->save();

$variacionTalla = $anotar('talla', $gestor->getStorage('tec_product')->create([
  'type' => 'tec_size_variation',
  'title' => 'PRUEBA producto - ' . $color->label() . ' - ' . $talla->label(),
  'field_tec_size' => $talla->id(),
  'field_tec_color_variation' => $variacionColor->id(),
  'field_tec_product' => $producto->id(),
  'field_tec_price' => '1250.00',
]));

$variacionColor->set('field_tec_size_variations', [$variacionTalla->id()]);
$variacionColor->save();

echo "\n";
echo "El BoM:\n";

// Se meten dos materiales para que las vistas de calculo tengan que sumar y no
// solo copiar una fila. El segundo se crea aqui si no hay mas que uno.
$materiales = $terminos->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->sort('tid')
  ->execute();
$materiales = $terminos->loadMultiple($materiales);

if (count($materiales) < 2) {
  $primero = reset($materiales);
  $unidad = $primerTermino('tec_units');
  $segundo = $anotar('segundo material', $terminos->create([
    'vid' => 'tec_inventory',
    'name' => 'PRUEBA material',
    'field_tec_material_type' => $primero->get('field_tec_material_type')->target_id,
    'field_tec_vendor' => $primero->get('field_tec_vendor')->target_id,
    'field_tec_cost' => '45.00',
    'field_tec_unit_purchase' => $unidad->id(),
    'field_tec_uos' => $unidad->id(),
    'field_tec_unit_use' => $unidad->id(),
    'field_tec_units' => '3.00',
    'field_tec_split_into' => '50.00',
    'field_tec_traceability' => 1,
    // La politica de reposicion, que es lo que hace que este material salga en
    // la lista de compra (/purchase). Sin ella esa pantalla se dibuja vacia y
    // la prueba de humo la aprueba sin haber pintado ni una fila, que es
    // justo el aprobado falso contra el que avisa cargan-las-paginas.php.
    // Con el stock a cero: faltan 120 de inventario, el factor de compra es 3,
    // asi que sugiere 40 unidades de compra y 1.800 de importe.
    'field_tec_reorder_point' => '100.00',
    'field_tec_safety_stock' => '20.00',
    'field_tec_moq_quantity' => 5,
    'field_tec_lead_time_days' => 14,
  ]));
  $materiales[$segundo->id()] = $segundo;
}

// Cantidad que pide el BoM de cada material, por pieza fabricada.
$cantidades = [2.5, 4];
$partidas = [];
$i = 0;
foreach ($materiales as $material) {
  $cantidad = $cantidades[$i] ?? 1;
  $partida = $anotar('partida de BoM', $gestor->getStorage('tec_inventory')->create([
    'type' => 'tec_bom_item',
    'title' => 'PRUEBA BoM - ' . $material->label(),
    'field_tec_inventory' => $material->id(),
    'field_tec_quantity' => $cantidad,
    'field_tec_size_variation' => $variacionTalla->id(),
  ]));
  $partidas[] = $partida->id();
  printf("      %s: %s por pieza\n", $material->label(), $cantidad);
  $i++;
}

$variacionTalla->set('field_tec_bom', $partidas);
$variacionTalla->save();

echo "\n";
echo "El pedido de venta:\n";

$lineaVenta = $anotar('linea de pedido', $gestor->getStorage('tec_line_item')->create([
  'type' => 'tec_sales_order_line_item',
  'title' => 'PRUEBA linea',
  'field_tec_product' => $producto->id(),
  'field_tec_color_variation' => $variacionColor->id(),
  'field_tec_size_variation' => $variacionTalla->id(),
  'field_tec_quantity' => 30,
  'field_tec_price' => '1250.00',
]));

// El estado tiene que ser draft, que es el que se ensena como "Open". Hasta el
// 15 de agosto de 2026 aqui ponia 'new', que no esta en la lista de valores
// permitidos del campo: Drupal no valida al guardar por codigo, asi que lo
// aceptaba callando y el pedido quedaba en un estado que no existe. Con eso las
// dos pantallas de trabajo del pedido de venta, o/draft/% y o/pf/%/print, no
// encontraban ni una fila, porque las dos filtran por draft. La prueba de humo
// las saltaba y decia "cargan todas" mirando dos pantallas menos.
$pedido = $anotar('pedido de venta', $gestor->getStorage('tec_order')->create([
  'type' => 'tec_sales_order',
  'title' => 'PRUEBA pedido de venta',
  'field_tec_customer' => $cliente->id(),
  'field_tec_line_items' => [$lineaVenta->id()],
  'field_tec_order_status' => 'draft',
]));

$lineaVenta->set('field_tec_order', $pedido->id());
$lineaVenta->save();

echo "\n";
echo str_repeat('-', 78) . "\n";
echo "Lo que tienen que decir las vistas de calculo\n";
echo str_repeat('-', 78) . "\n";
echo "\n";
printf("Pedido de %d piezas de '%s'.\n\n", 30, $variacionTalla->label());
printf("%-22s %10s %10s %12s %14s\n", 'material', 'por pieza', 'x 30', 'coste compra', 'a comprar');
foreach ($partidas as $idPartida) {
  $partida = $gestor->getStorage('tec_inventory')->load($idPartida);
  $material = $partida->get('field_tec_inventory')->entity;
  $porPieza = (float) $partida->get('field_tec_quantity')->value;
  $total = $porPieza * 30;
  $coste = (float) $material->get('field_tec_cost')->value;
  $factorCompra = (float) $material->get('field_tec_units')->value;
  $factorConsumo = (float) $material->get('field_tec_split_into')->value;
  // El BoM pide unidades de consumo. Para comprar hay que subir los dos
  // escalones: consumo a inventario y inventario a compra.
  $enInventario = $factorConsumo > 0 ? $total / $factorConsumo : 0;
  $enCompra = $factorCompra > 0 ? $enInventario / $factorCompra : 0;
  printf(
    "%-22s %10s %10s %12s %14s\n",
    mb_substr($material->label(), 0, 22),
    number_format($porPieza, 2),
    number_format($total, 2),
    number_format($coste, 2),
    number_format($enCompra, 4)
  );
}

// Se deja escrito el inventario de lo creado, porque al guardar la linea de
// pedido ECA fabrica por su cuenta una copia del BoM con el nombre del
// material, sin el PRUEBA delante, y buscando solo por nombre no se encontraria.
$inventario = dirname(__DIR__) . '/scripts/tmp-juego-de-pruebas.json';
file_put_contents($inventario, json_encode($creado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n";
echo "Creado en total: " . count($creado) . " cosas.\n";
echo "Apuntadas en scripts/tmp-juego-de-pruebas.json para poder borrarlas.\n";
