<?php

/**
 * Borrado de los datos de prueba del ERP.
 *
 * Decision del dueno del 12 de agosto de 2026, ejecutada el 14: el ERP nunca se
 * uso, todo lo que hay dentro es de prueba, y se empieza con datos reales.
 *
 * Se ejecuta en dos modos:
 *
 *   php vendor/bin/drush.php scr scripts/borrar-datos-de-prueba.php
 *       simulacion. No toca nada, ni siquiera apaga ECA. Cuenta y ensena.
 *
 *   php vendor/bin/drush.php scr scripts/borrar-datos-de-prueba.php -- --de-verdad
 *       borra.
 *
 * EL ORDEN NO ES ARBITRARIO. Se derivo del grafo de referencias del propio
 * sitio (scripts/quien-apunta-a-que.php) y no de una lista escrita a mano. Tres
 * cosas que se descubrieron ahi y que explican por que esta puesto asi:
 *
 *   - Los registros de produccion van PRIMERO, aunque parezcan lo ultimo. Su
 *     campo `order_id` es obligatorio y apunta a un pedido; si los pedidos se
 *     van antes, quedan catorce registros con una referencia obligatoria al
 *     vacio.
 *   - Las 47 importaciones de escandallo van antes que las variaciones de
 *     talla, porque las referencian (45 filas).
 *   - Los materiales van AL FINAL de todo el contenido. Son lo unico a lo que
 *     apunta media base de datos: 2.553 filas de escandallo, movimientos y
 *     lineas de compra. Borrarlos antes es dispararse en el pie con el fallo
 *     conocido que deja la bandera de bloqueo puesta y rompe el recalculo en
 *     silencio.
 *
 * Y POR ESO SE APAGA ECA. El grafo tiene ciclos: los pedidos apuntan a sus
 * lineas y las lineas a su pedido; los productos a sus variaciones y las
 * variaciones al producto. No existe ningun orden que evite todas las
 * referencias colgando. Lo que evita el dano no es el orden, es que no haya
 * nadie recalculando mientras pasa. El orden solo reduce el desorden.
 *
 * Lo que NO se toca, y conviene que quede escrito:
 *
 *   - `tec_crm_contact_type`. Sus terminos 1395 (Customer) y 1396 (Supplier)
 *     estan grabados a fuego en el codigo de `tec_crm_ux`. Si se borran y se
 *     recrean, el ERP deja de distinguir clientes de proveedores SIN DAR NINGUN
 *     ERROR: empieza a ensenar campos de proveedor en fichas de cliente.
 *   - Los 12 nodos de navegacion que quedan. No son datos, son la portada.
 *   - Las listas de configuracion: unidades, tallas, tipos de material, tipos
 *     de producto y colores (estos ultimos menos los duplicados de la errata).
 */

$deVerdad = in_array('--de-verdad', $_SERVER['argv'] ?? [], TRUE);

$etm = \Drupal::entityTypeManager();
$bd = \Drupal::database();

/** Duplicados de la errata del importador. Se borran al final, cuando ya nada apunta a ellos. */
const COLORES_DUPLICADOS = [
  2752 => 'Metalic Gold (duplicado de 2768 Metallic Gold)',
  2750 => 'Metalic Green (duplicado de 2758 Metallic Green)',
  2751 => 'Metalic Silver (duplicado de 2775 Metallic Silver)',
  1403 => 'Metalic Viridian / Metalic Silver / Metalic Gold (tres colores en un termino)',
  2773 => 'Gray (duplicado de 2763 Grey, que es la grafia del resto de la lista)',
];

const MATERIALES_BASURA = [
  2795 => 'pertur',
];

/** Nodos de navegacion cuyo asunto desaparece. */
const NODOS_A_BORRAR = [4 => 'Brands', 8 => 'Patterns'];

/** Redirecciones que se conservan; el resto son de la epoca del servidor viejo. */
const REDIRECCIONES_QUE_SE_QUEDAN = [1, 2];

$informe = [];

/**
 * Imprime un titulo.
 */
function seccion(string $texto): void {
  print "\n" . str_repeat('=', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('=', 78) . "\n\n";
}

/**
 * Cuenta entidades de un tipo, opcionalmente filtrando por paquete.
 */
function contar($etm, string $tipo, ?array $paquetes = NULL): int {
  $consulta = $etm->getStorage($tipo)->getQuery()->accessCheck(FALSE);
  if ($paquetes) {
    $clave = $etm->getDefinition($tipo)->getKey('bundle');
    $consulta->condition($clave, $paquetes, 'IN');
  }
  return (int) $consulta->count()->execute();
}

/**
 * Borra entidades por lotes. Devuelve cuantas borro.
 */
function borrarPorLotes($etm, string $tipo, array $ids, bool $deVerdad, int $lote = 200): int {
  if (!$deVerdad) {
    return 0;
  }
  $almacen = $etm->getStorage($tipo);
  $hechas = 0;
  foreach (array_chunk($ids, $lote) as $trozo) {
    $entidades = $almacen->loadMultiple($trozo);
    $almacen->delete($entidades);
    $hechas += count($entidades);
    print '.';
  }
  return $hechas;
}

/**
 * Un paso del borrado.
 */
function paso(array &$informe, $etm, int $numero, string $que, string $tipo, ?array $paquetes, bool $deVerdad, ?array $idsExplicitos = NULL): void {
  $almacen = $etm->getStorage($tipo);

  if ($idsExplicitos !== NULL) {
    $ids = $idsExplicitos;
  }
  else {
    $consulta = $almacen->getQuery()->accessCheck(FALSE);
    if ($paquetes) {
      $clave = $etm->getDefinition($tipo)->getKey('bundle');
      $consulta->condition($clave, $paquetes, 'IN');
    }
    $ids = array_values($consulta->execute());
  }

  printf("  %2d. %-46s %6d ", $numero, $que, count($ids));

  if (!$deVerdad) {
    print "(simulacion)\n";
    $informe[] = [$numero, $que, count($ids), 0];
    return;
  }

  $borradas = borrarPorLotes($etm, $tipo, $ids, TRUE);
  printf("  borradas %d\n", $borradas);
  $informe[] = [$numero, $que, count($ids), $borradas];
}

seccion($deVerdad ? 'borrado de datos de prueba - DE VERDAD' : 'borrado de datos de prueba - simulacion');

if (!$deVerdad) {
  print "  Este modo no modifica nada. Para ejecutarlo de verdad hay que anadir\n";
  print "  -- --de-verdad al final de la orden.\n\n";
}

// --- Apagar ECA -----------------------------------------------------------
$almacenEca = $etm->getStorage('eca');
$encendidos = [];
foreach ($almacenEca->loadMultiple() as $id => $proceso) {
  if ($proceso->status()) {
    $encendidos[] = $id;
  }
}

printf("  procesos de ECA encendidos: %d\n", count($encendidos));
print "  (para volver a encenderlos a mano si algo se corta:\n   " . implode(', ', $encendidos) . ")\n\n";

if ($deVerdad) {
  foreach ($encendidos as $id) {
    $almacenEca->load($id)->setStatus(FALSE)->save();
  }
  print "  ECA apagado.\n\n";
}

try {

  // --- Los pasos, en el orden que fija el grafo ---------------------------
  paso($informe, $etm, 1, 'registros de produccion', 'tec_production_entry', NULL, $deVerdad);
  paso($informe, $etm, 2, 'registros de importacion de feeds', 'feeds_import_log', NULL, $deVerdad);
  paso($informe, $etm, 3, 'importaciones de escandallo', 'feeds_feed', ['tec_bom_items_importer'], $deVerdad);
  paso($informe, $etm, 4, 'escandallo de lineas de pedido', 'tec_inventory', ['tec_line_item_bom_item'], $deVerdad);
  paso($informe, $etm, 5, 'escandallo de productos', 'tec_inventory', ['tec_bom_item'], $deVerdad);
  paso($informe, $etm, 6, 'movimientos de inventario', 'tec_inventory', ['tec_inventory_transaction'], $deVerdad);
  paso($informe, $etm, 7, 'lineas de pedido', 'tec_line_item', NULL, $deVerdad);
  paso($informe, $etm, 8, 'pedidos y ordenes de compra', 'tec_order', NULL, $deVerdad);
  paso($informe, $etm, 9, 'variaciones de color y talla', 'tec_product', ['tec_color_variation', 'tec_size_variation'], $deVerdad);
  paso($informe, $etm, 10, 'productos', 'tec_product', ['tec_product'], $deVerdad);
  paso($informe, $etm, 11, 'fichas de CRM', 'tec_crm', NULL, $deVerdad);

  // Materiales: aqui, y no antes.
  $materiales = array_values($etm->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)->condition('vid', 'tec_inventory')->execute());
  paso($informe, $etm, 12, 'materiales (vocabulario Inventory)', 'taxonomy_term', NULL, $deVerdad, $materiales);

  paso($informe, $etm, 13, 'nodos Brands y Patterns', 'node', NULL, $deVerdad, array_keys(NODOS_A_BORRAR));

  $redirecciones = array_values(array_diff(
    array_values($etm->getStorage('redirect')->getQuery()->accessCheck(FALSE)->execute()),
    REDIRECCIONES_QUE_SE_QUEDAN
  ));
  paso($informe, $etm, 14, 'redirecciones del servidor viejo', 'redirect', NULL, $deVerdad, $redirecciones);

  $marcasYPatrones = array_values($etm->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)->condition('vid', ['tec_brands', 'tec_patterns'], 'IN')->execute());
  paso($informe, $etm, 15, 'terminos de Brands y Patterns', 'taxonomy_term', NULL, $deVerdad, $marcasYPatrones);

  // La errata del importador. Al final, cuando ya nada apunta a los colores.
  $basura = array_merge(array_keys(COLORES_DUPLICADOS), array_keys(MATERIALES_BASURA));
  $existen = array_values($etm->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)->condition('tid', $basura, 'IN')->execute());
  paso($informe, $etm, 16, 'duplicados de la errata del importador', 'taxonomy_term', NULL, $deVerdad, $existen);

}
finally {
  // --- Volver a encender ECA, pase lo que pase --------------------------
  if ($deVerdad) {
    foreach ($encendidos as $id) {
      $proceso = $almacenEca->load($id);
      if ($proceso) {
        $proceso->setStatus(TRUE)->save();
      }
    }
    printf("\n  ECA vuelto a encender: %d procesos.\n", count($encendidos));
  }
}

// --- Y ahora se comprueba ------------------------------------------------
seccion('lo que queda');

foreach (['tec_inventory', 'tec_line_item', 'tec_order', 'tec_product', 'tec_crm', 'tec_production_entry'] as $tipo) {
  printf("  %-24s %6d\n", $tipo, contar($etm, $tipo));
}

print "\n  vocabularios:\n";
foreach ($etm->getStorage('taxonomy_vocabulary')->loadMultiple() as $vid => $vocabulario) {
  $n = (int) $bd->select('taxonomy_term_field_data', 't')->condition('t.vid', $vid)->countQuery()->execute()->fetchField();
  printf("      %-24s %5d\n", $vid, $n);
}

print "\n  nodos de navegacion: " . contar($etm, 'node') . "\n";
print "  redirecciones: " . contar($etm, 'redirect') . "\n";
print "  importaciones: " . contar($etm, 'feeds_feed') . "\n";

print "\n  banderas puestas (en reposo solo debe salir tec_eca_gui_lock con 1):\n";
foreach ($bd->query("SELECT flag_id, COUNT(*) AS n FROM {flagging} GROUP BY flag_id")->fetchAll() as $fila) {
  printf("      %-32s %d\n", $fila->flag_id, $fila->n);
}

$ecas = $almacenEca->loadMultiple();
$ahora = array_filter($ecas, fn($e) => $e->status());
printf("\n  procesos de ECA: %d, encendidos %d (deben ser %d)\n", count($ecas), count($ahora), count($encendidos));

print "\n";
