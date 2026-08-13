<?php

/**
 * @file
 * Comprobacion del ERP. Se ejecuta con: drush scr scripts/comprobacion.php
 *
 * Sirve para responder una sola pregunta despues de cada cambio grande:
 * el ERP, ¿sigue haciendo lo mismo que antes?
 *
 * Hace tres cosas. Cuenta el contenido y lo compara con las cifras de
 * referencia, crea entidades de verdad para ver si los automatismos de ECA
 * reaccionan, y revisa que no haya quedado nada roto. Todo lo que crea lo
 * borra al terminar, y lo que modifica lo deja como estaba.
 *
 * Las cifras de referencia son del 13 de agosto de 2026, verificadas contra
 * la copia anterior a la limpieza de modulos. Si el borrado de datos de
 * prueba se llega a ejecutar, hay que actualizarlas.
 */

// -----------------------------------------------------------------------------
// Cifras de referencia.
// -----------------------------------------------------------------------------
// Tres cifras subieron la noche del 13 de agosto de 2026, y no por un fallo: son
// los dos pedidos que se crearon a mano para comprobar que la pantalla de editar
// lineas volvia a abrir tras el arreglo del error 500, el 755 y el 756, con ocho
// lineas cada uno. De ahi los dos pedidos, las dieciseis lineas y los veinticuatro
// elementos de escandallo que cuelgan de ellas. Se actualizan a proposito: una
// cifra que falla siempre por un motivo conocido deja de servir para avisar.
const REFERENCIA = [
  'tec_inventory' => [
    'tec_bom_item' => 4773,
    'tec_inventory_transaction' => 40,
    'tec_line_item_bom_item' => 202,
  ],
  'tec_product' => [
    'tec_product' => 13,
    'tec_color_variation' => 83,
    'tec_size_variation' => 118,
  ],
  'tec_line_item' => [
    'tec_sales_order_line_item' => 101,
    'tec_po_line_item' => 522,
  ],
  'tec_order' => [
    'tec_sales_order' => 18,
    'tec_purchase_order' => 110,
  ],
  'tec_crm' => [
    'tec_contact_organization' => 20,
    'tec_contact_person' => 2,
  ],
];

const REFERENCIA_TAXONOMIA = [
  'tec_inventory' => 869,
  'tec_colors' => 37,
  'tec_brands' => 12,
  'tec_crm_contact_type' => 3,
  'tec_materials' => 23,
  'tec_patterns' => 11,
  'tec_product_types' => 23,
  'tec_sizes' => 14,
  'tec_units' => 13,
];

const REFERENCIA_VARIOS = [
  'usuarios' => 7,
  'ficheros' => 315,
  'procesos_eca' => 36,
  'procesos_eca_encendidos' => 29,
  // Eran 146 hasta el 13 de agosto de 2026. Se quedaron en 145 cuando dxpr_theme
  // 8 trajo los colores del tema a sus propios ajustes y desinstalo `color`, que
  // ya no estaba en el nucleo. Los veintiun ajustes de color siguen ahi y no
  // quedo ni un resto del modulo viejo.
  //
  // Vuelven a ser 146 el 14 de agosto: se desinstalo `upgrade_status`, que ya
  // habia cumplido, y `update` se queda para siempre y por eso ahora si cuenta.
  // Es el modulo del nucleo que avisa de las alertas de seguridad, o sea justo lo
  // que le faltaba a este sitio cuando acumulo ochenta y dos sin que nadie se
  // enterara. Si algun dia se decide apagarlo, esta cifra baja a 145.
  'modulos' => 146,
];

// Herramientas de diagnostico que se instalan durante una actualizacion y se
// quitan al terminar. No cuentan para el total. Vacia desde el 14 de agosto de
// 2026; se deja el mecanismo puesto porque en la proxima subida hara falta otra
// vez.
const MODULOS_TEMPORALES = [];

// -----------------------------------------------------------------------------
// Utilidades.
// -----------------------------------------------------------------------------
$resultados = [];

function comprobar(array &$resultados, string $nombre, bool $bien, string $detalle = ''): void {
  $resultados[] = ['nombre' => $nombre, 'bien' => $bien, 'detalle' => $detalle];
  printf("  [%s] %-50s %s\n", $bien ? 'BIEN' : 'FALLA', $nombre, $detalle);
}

function titulo(string $t): void {
  echo "\n" . $t . "\n" . str_repeat('-', strlen($t)) . "\n";
}

$etm = \Drupal::entityTypeManager();
$db = \Drupal::database();
$wid_inicial = (int) $db->query('SELECT MAX(wid) FROM {watchdog}')->fetchField();

echo "\n";
echo "===============================================================\n";
echo " COMPROBACION DEL ERP - " . date('d/m/Y H:i:s') . "\n";
echo "===============================================================\n";

// -----------------------------------------------------------------------------
// 1. Recuentos de contenido.
// -----------------------------------------------------------------------------
titulo('1. El contenido sigue entero');

foreach (REFERENCIA as $tipo => $bundles) {
  foreach ($bundles as $bundle => $esperado) {
    $n = (int) $etm->getStorage($tipo)->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->count()
      ->execute();
    comprobar($resultados, $bundle, $n === $esperado, "$n (se esperaban $esperado)");
  }
}

foreach (REFERENCIA_TAXONOMIA as $vid => $esperado) {
  $n = (int) $etm->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vid)
    ->count()
    ->execute();
  comprobar($resultados, "taxonomia $vid", $n === $esperado, "$n (se esperaban $esperado)");
}

$n = (int) $etm->getStorage('user')->getQuery()->accessCheck(FALSE)->count()->execute();
comprobar($resultados, 'usuarios', $n === REFERENCIA_VARIOS['usuarios'], "$n (se esperaban " . REFERENCIA_VARIOS['usuarios'] . ")");

$n = (int) $etm->getStorage('file')->getQuery()->accessCheck(FALSE)->count()->execute();
comprobar($resultados, 'ficheros', $n === REFERENCIA_VARIOS['ficheros'], "$n (se esperaban " . REFERENCIA_VARIOS['ficheros'] . ")");

// -----------------------------------------------------------------------------
// 2. Estructura: modulos y automatismos.
// -----------------------------------------------------------------------------
titulo('2. Los modulos y los automatismos siguen ahi');

$instalados = array_keys(\Drupal::service('extension.list.module')->getAllInstalledInfo());
$temporales = array_intersect($instalados, MODULOS_TEMPORALES);
$modulos = count($instalados) - count($temporales);
comprobar($resultados, 'modulos instalados', $modulos === REFERENCIA_VARIOS['modulos'],
  "$modulos (se esperaban " . REFERENCIA_VARIOS['modulos'] . ")"
  . ($temporales ? ', mas ' . implode(' y ', $temporales) . ' de diagnostico' : ''));

$ecas = $etm->getStorage('eca')->loadMultiple();
$encendidos = count(array_filter($ecas, fn($e) => $e->status()));
comprobar($resultados, 'procesos de ECA', count($ecas) === REFERENCIA_VARIOS['procesos_eca'],
  count($ecas) . " (se esperaban " . REFERENCIA_VARIOS['procesos_eca'] . ")");
comprobar($resultados, 'procesos de ECA encendidos', $encendidos === REFERENCIA_VARIOS['procesos_eca_encendidos'],
  "$encendidos (se esperaban " . REFERENCIA_VARIOS['procesos_eca_encendidos'] . ")");

// El parche de inline_entity_form se aplica a mano y Composer lo borra sin
// avisar cada vez que toca el modulo. Esta linea es la que hay que vigilar.
$ief = DRUPAL_ROOT . '/modules/contrib/inline_entity_form/inline_entity_form.module';
$parche_puesto = file_exists($ief)
  && str_contains(file_get_contents($ief), "'entities']) ?? []");
comprobar($resultados, 'parche de inline_entity_form', $parche_puesto,
  $parche_puesto ? 'sigue aplicado' : 'HA DESAPARECIDO, lo ha borrado Composer');

// -----------------------------------------------------------------------------
// 3. Los automatismos reaccionan.
// -----------------------------------------------------------------------------
titulo('3. Los automatismos reaccionan');

$creadas = [];
$restaurar = [];

$tid = reset($etm->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)->condition('vid', 'tec_inventory')->exists('name')->range(0, 1)->execute());
$material = $etm->getStorage('taxonomy_term')->load($tid);

// 3.1 y 3.2 - process_p2q045n.
$bom = $etm->getStorage('tec_inventory')->create([
  'type' => 'tec_bom_item',
  'title' => '',
  'field_tec_inventory' => ['target_id' => $material->id()],
  'field_tec_quantity' => 2.5,
]);
$bom->save();
$creadas[] = ['tec_inventory', $bom->id()];
$bom = $etm->getStorage('tec_inventory')->loadUnchanged($bom->id());
comprobar($resultados, 'escandallo: titulo automatico', $bom->label() === $material->label(),
  "'" . $bom->label() . "'");

$bom->set('field_tec_quantity', 3.75);
$bom->save();
$bom = $etm->getStorage('tec_inventory')->loadUnchanged($bom->id());
$copia = $bom->get('field_tec_quantity_input')->value;
comprobar($resultados, 'escandallo: copia de cantidad', $copia !== NULL && (float) $copia > 0,
  'entrada = ' . var_export($copia, TRUE));

// 3.3 - process_uhiwdqa.
$sid = reset($etm->getStorage('tec_product')->getQuery()
  ->accessCheck(FALSE)->condition('type', 'tec_size_variation')->range(0, 1)->execute());
$talla = $etm->getStorage('tec_product')->load($sid);
$restaurar[] = ['tec_product', $talla->id(), 'field_tec_bom', $talla->get('field_tec_bom')->getValue()];
$antes = count($talla->get('field_tec_bom')->getValue());

$bom2 = $etm->getStorage('tec_inventory')->create([
  'type' => 'tec_bom_item',
  'title' => '',
  'field_tec_inventory' => ['target_id' => $material->id()],
  'field_tec_quantity' => 1.25,
  'field_tec_size_variation' => ['target_id' => $talla->id()],
]);
$bom2->save();
$creadas[] = ['tec_inventory', $bom2->id()];
$talla = $etm->getStorage('tec_product')->loadUnchanged($talla->id());
$despues = array_column($talla->get('field_tec_bom')->getValue(), 'target_id');
comprobar($resultados, 'escandallo: se engancha a la talla', in_array($bom2->id(), $despues),
  "de $antes a " . count($despues));

// 3.4 - process_oy5yfqx.
$restaurar[] = ['taxonomy_term', $material->id(), 'field_tec_stock_mutations', $material->get('field_tec_stock_mutations')->getValue()];
$antes = count($material->get('field_tec_stock_mutations')->getValue());
$mov = $etm->getStorage('tec_inventory')->create([
  'type' => 'tec_inventory_transaction',
  'title' => 'COMPROBACION automatica',
  'field_tec_inventory' => ['target_id' => $material->id()],
  'field_tec_quantity' => 7,
]);
$mov->save();
$creadas[] = ['tec_inventory', $mov->id()];
$material = $etm->getStorage('taxonomy_term')->loadUnchanged($material->id());
$despues = array_column($material->get('field_tec_stock_mutations')->getValue(), 'target_id');
comprobar($resultados, 'inventario: el movimiento se engancha', in_array($mov->id(), $despues),
  "de $antes a " . count($despues));

// 3.5 - process_hkreor6.
$cid = reset($etm->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)->condition('vid', 'tec_colors')->range(0, 1)->execute());
$cv = $etm->getStorage('tec_product')->create([
  'type' => 'tec_color_variation',
  'title' => '',
  'field_tec_colors' => ['target_id' => $cid],
]);
$cv->save();
$creadas[] = ['tec_product', $cv->id()];
$cv = $etm->getStorage('tec_product')->loadUnchanged($cv->id());
comprobar($resultados, 'color: titulo automatico', $cv->label() !== '' && $cv->label() !== NULL,
  "'" . $cv->label() . "'");

// 3.6 - process_lvy385w, sobre una linea real sin bandera y con escandallo sano.
$bloqueadas = $db->select('flagging', 'f')->fields('f', ['entity_id'])
  ->condition('flag_id', 'tec_eca_line_item_lock')->execute()->fetchCol();
$rotas = $db->query("SELECT DISTINCT b.entity_id
  FROM {tec_line_item__field_tec_line_item_bom} b
  INNER JOIN {tec_inventory__field_tec_inventory} i ON i.entity_id = b.field_tec_line_item_bom_target_id
  LEFT JOIN {taxonomy_term_field_data} t ON t.tid = i.field_tec_inventory_target_id
  WHERE t.tid IS NULL")->fetchCol();
$excluir = array_merge($bloqueadas, $rotas);

$linea = NULL;
foreach ($etm->getStorage('tec_line_item')->loadMultiple(
  $etm->getStorage('tec_line_item')->getQuery()->accessCheck(FALSE)
    ->condition('type', 'tec_sales_order_line_item')
    ->exists('field_tec_price')->exists('field_tec_quantity')->execute()
) as $li) {
  if (in_array($li->id(), $excluir)) {
    continue;
  }
  if ((float) $li->get('field_tec_price')->value > 0
    && (float) $li->get('field_tec_quantity')->value > 0
    && $li->get('field_tec_line_item_total_number')->value) {
    $linea = $li;
    break;
  }
}

if ($linea) {
  $precio = (float) $linea->get('field_tec_price')->value;
  $c_orig = $linea->get('field_tec_quantity')->getValue();
  $t_orig = $linea->get('field_tec_line_item_total_number')->getValue();
  $c = (float) $linea->get('field_tec_quantity')->value;
  $linea->set('field_tec_quantity', $c + 1);
  $linea->save();
  $r = $etm->getStorage('tec_line_item')->loadUnchanged($linea->id());
  $total = (float) $r->get('field_tec_line_item_total_number')->value;
  comprobar($resultados, 'linea de pedido: recalcula el total',
    abs($total - $precio * ($c + 1)) < 0.01,
    "$precio x " . ($c + 1) . " = $total");
  $r->set('field_tec_quantity', $c_orig);
  $r->set('field_tec_line_item_total_number', $t_orig);
  $r->save();
  $restaurar[] = ['__bandera_linea', $linea->id(), NULL, NULL];
}
else {
  comprobar($resultados, 'linea de pedido: recalcula el total', FALSE, 'no hay ninguna linea valida para probar');
}

// -----------------------------------------------------------------------------
// Limpieza de lo creado.
// -----------------------------------------------------------------------------
foreach (array_reverse($creadas) as [$tipo, $id]) {
  if ($e = $etm->getStorage($tipo)->load($id)) {
    $e->delete();
  }
}
foreach ($restaurar as [$tipo, $id, $campo, $valor]) {
  if ($tipo === '__bandera_linea') {
    foreach ($etm->getStorage('flagging')->loadByProperties([
      'flag_id' => 'tec_eca_line_item_lock',
      'entity_id' => $id,
    ]) as $f) {
      $f->delete();
    }
    continue;
  }
  if ($e = $etm->getStorage($tipo)->loadUnchanged($id)) {
    $e->set($campo, $valor);
    $e->save();
  }
}

// -----------------------------------------------------------------------------
// 4. Nada roto.
// -----------------------------------------------------------------------------
titulo('4. Nada se ha quedado roto');

$colgadas = $db->query("SELECT flag_id, COUNT(*) AS n FROM {flagging}
  WHERE flag_id LIKE '%lock%' GROUP BY flag_id")->fetchAllKeyed();
$linea_lock = (int) ($colgadas['tec_eca_line_item_lock'] ?? 0);
$inv_lock = (int) ($colgadas['tec_eca_inventory_lock'] ?? 0);
$prod_lock = (int) ($colgadas['tec_eca_product_lock'] ?? 0);
comprobar($resultados, 'sin banderas de bloqueo colgadas',
  $linea_lock === 0 && $inv_lock === 0 && $prod_lock === 0,
  "lineas: $linea_lock, inventario: $inv_lock, productos: $prod_lock");

$errores = $db->query('SELECT COUNT(*) FROM {watchdog} WHERE wid > :w AND severity <= 3 AND type <> :t',
  [':w' => $wid_inicial, ':t' => 'eca'])->fetchField();
comprobar($resultados, 'sin errores nuevos en el registro', (int) $errores === 0, "$errores errores");

// -----------------------------------------------------------------------------
// Resumen.
// -----------------------------------------------------------------------------
$mal = array_filter($resultados, fn($r) => !$r['bien']);
echo "\n===============================================================\n";
printf(" RESULTADO: %d bien, %d mal, de %d comprobaciones\n",
  count($resultados) - count($mal), count($mal), count($resultados));
echo "===============================================================\n";

if ($mal) {
  echo "\nLo que falla:\n";
  foreach ($mal as $r) {
    echo '  - ' . $r['nombre'] . ': ' . $r['detalle'] . "\n";
  }
  echo "\nNO SEGUIR hasta entender por que.\n";
}
else {
  echo "\nTodo correcto. Se puede seguir.\n";
}
echo "\n";
