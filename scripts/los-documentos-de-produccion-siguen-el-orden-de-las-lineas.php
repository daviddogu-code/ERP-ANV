<?php

/**
 * @file
 * Los documentos de produccion de un pedido salen en el orden de sus lineas.
 *
 *   php vendor\bin\drush.php scr scripts/los-documentos-de-produccion-siguen-el-orden-de-las-lineas.php
 *
 * Monta un pedido con tres productos anadidos a proposito **al contrario del
 * alfabeto** -- ZORRO, MEDIO y ABETO, en ese orden -- porque el fallo que esto
 * vigila es justo ese: el bloque ordenaba por el nombre del producto, asi que
 * ABETO salia primero por muy al final que se hubiera anadido, y el papel que se
 * lleva al taller dejaba de parecerse a la pantalla desde la que se imprimio.
 *
 * Con tres nombres al reves, un orden alfabetico y un orden de lineas no pueden
 * coincidir por casualidad: es la unica manera de que esta prueba sirva de algo.
 *
 * Comprueba cuatro cosas:
 *
 *   1. Que el documento saca los productos en el orden en que se anadieron.
 *   2. Que **no** los saca en orden alfabetico, que es de donde venimos.
 *   3. Que la pestana Line items (tabla PHP) y la vista de la proforma dicen
 *      el mismo orden. Las dos usan la misma clave, el numero de la linea.
 *   4. Que un producto que vuelve a aparecer mas abajo en el pedido **se queda
 *      donde salio la primera vez** en vez de bajarse al final. Se pide ZORRO
 *      otra vez en la ultima linea a proposito.
 *
 * Las tallas no se ordenan por linea y eso es correcto: el documento lista todas
 * las del catalogo, incluidas las que van a cero, y esas nunca estuvieron en una
 * linea. Van por el peso de su termino.
 *
 * Deshace todo lo que crea, incluido el numero de pedido que gasta: desde el 17
 * de agosto de 2026 un numero entregado se recuerda, asi que se fotografian los
 * contadores al empezar y se devuelven al acabar.
 */

use Drupal\tec_production\OrderNumber;
use Drupal\views\Views;

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$almacenProducto = $gestor->getStorage('tec_product');
$almacenTermino = $gestor->getStorage('taxonomy_term');
$almacenPedido = $gestor->getStorage('tec_order');
$almacenLinea = $gestor->getStorage('tec_line_item');

$contadores = \Drupal::keyValue(OrderNumber::LEDGER);
$contadoresAntes = $contadores->getAll();

$problemas = 0;
$basura = ['lineas' => [], 'pedido' => [], 'producto' => [], 'terminos' => []];

$mirar = function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, !$bien && $pista !== '' ? ': ' . $pista : '');
  if (!$bien) {
    $problemas++;
  }
};

print "\n";
print "=====================================================================\n";
print "  Los documentos de produccion siguen el orden de las lineas\n";
print "=====================================================================\n\n";

try {
  // -------------------------------------------------------------------------
  // Tres productos, cada uno con un color y una talla.
  // -------------------------------------------------------------------------
  $talla = $almacenTermino->create(['vid' => 'tec_sizes', 'name' => 'PRUEBA talla del orden', 'weight' => 0]);
  $talla->save();
  $basura['terminos'][] = $talla->id();

  $arbol = [];
  foreach (['PRUEBA ZORRO', 'PRUEBA MEDIO', 'PRUEBA ABETO'] as $nombre) {
    $tallaVar = $almacenProducto->create([
      'type' => 'tec_size_variation',
      'title' => $nombre . ' talla',
      'field_tec_size' => ['target_id' => $talla->id()],
      'field_tec_price' => '100.00',
    ]);
    $tallaVar->save();
    $basura['producto'][] = $tallaVar->id();

    $color = $almacenProducto->create([
      'type' => 'tec_color_variation',
      'title' => $nombre . ' color',
      'field_tec_size_variations' => [$tallaVar->id()],
    ]);
    $color->save();
    $basura['producto'][] = $color->id();

    $producto = $almacenProducto->create([
      'type' => 'tec_product',
      'title' => $nombre,
      'field_product_name' => $nombre,
      'field_tec_color_variations' => [$color->id()],
    ]);
    $producto->save();
    $basura['producto'][] = $producto->id();

    $arbol[$nombre] = ['producto' => $producto, 'color' => $color, 'talla' => $tallaVar];
  }

  // -------------------------------------------------------------------------
  // El pedido, con las lineas en el orden en que las escribiria el dueno.
  // -------------------------------------------------------------------------
  $pedido = $almacenPedido->create(['type' => 'tec_sales_order', 'title' => 'PRUEBA orden de los documentos']);
  $pedido->save();
  $basura['pedido'][] = $pedido->id();

  // ZORRO dos veces: la primera arriba, la segunda al final. Tiene que quedarse
  // arriba, donde aparecio.
  $comoSeEscribieron = ['PRUEBA ZORRO', 'PRUEBA MEDIO', 'PRUEBA ABETO', 'PRUEBA ZORRO'];
  $lineas = [];
  foreach ($comoSeEscribieron as $indice => $nombre) {
    $linea = $almacenLinea->create([
      'type' => 'tec_sales_order_line_item',
      'title' => $nombre . ' linea ' . ($indice + 1),
      'field_tec_product' => ['target_id' => $arbol[$nombre]['producto']->id()],
      'field_tec_color_variation' => ['target_id' => $arbol[$nombre]['color']->id()],
      'field_tec_size_variation' => ['target_id' => $arbol[$nombre]['talla']->id()],
      'field_tec_quantity' => ($indice + 1) * 10,
      'field_tec_order' => ['target_id' => $pedido->id()],
    ]);
    $linea->save();
    $basura['lineas'][] = $linea->id();
    $lineas[] = $linea;
  }

  $pedido = $almacenPedido->loadUnchanged($pedido->id());
  $pedido->set('field_tec_line_items', array_map(fn($l) => $l->id(), $lineas));
  $pedido->save();

  printf("  Pedido %d \"%s\"\n", $pedido->id(), $pedido->label());
  printf("  Lineas, en el orden escrito: %s\n\n",
    implode(', ', array_map(fn($l) => $l->id() . ' ' . str_replace('PRUEBA ', '', (string) $l->label()), $lineas)));

  // -------------------------------------------------------------------------
  // El documento.
  // -------------------------------------------------------------------------
  print "El documento de produccion\n";
  print str_repeat('-', 70) . "\n";

  $bloque = \Drupal::service('plugin.manager.block')
    ->createInstance('tec_order_production_documents', []);
  $metodo = new \ReflectionMethod($bloque, 'buildDocumentData');
  $metodo->setAccessible(TRUE);
  $datos = $metodo->invoke($bloque, $almacenPedido->loadUnchanged($pedido->id()));

  $dice = array_map(fn(array $p) => (string) $p['label'], $datos['products']);
  printf("  productos: %s\n", implode(' | ', $dice));

  $esperado = ['PRUEBA ZORRO', 'PRUEBA MEDIO', 'PRUEBA ABETO'];
  $alfabetico = $esperado;
  sort($alfabetico);

  $mirar('los productos salen en el orden de las lineas',
    $dice === $esperado, implode(' | ', $dice) . ' en vez de ' . implode(' | ', $esperado));
  $mirar('y no en orden alfabetico, que es de donde viene el fallo',
    $dice !== $alfabetico, 'salen alfabeticos: ' . implode(' | ', $dice));
  $mirar('el producto pedido dos veces se queda donde aparecio la primera',
    ($dice[0] ?? '') === 'PRUEBA ZORRO', 'el primero es ' . ($dice[0] ?? 'ninguno'));

  // Las cantidades, para saber que el documento no se ha limitado a ordenar bien
  // una lista vacia. ZORRO lleva dos lineas: 10 y 40.
  $totales = [];
  foreach ($datos['products'] as $producto) {
    $totales[(string) $producto['label']] = (float) $producto['product_total'];
  }
  printf("  cantidades: %s\n", implode(', ', array_map(fn($k, $v) => $k . '=' . $v, array_keys($totales), $totales)));
  $mirar('y las cantidades son las de las lineas, con las dos de ZORRO sumadas',
    ($totales['PRUEBA ZORRO'] ?? 0) === 50.0
    && ($totales['PRUEBA MEDIO'] ?? 0) === 20.0
    && ($totales['PRUEBA ABETO'] ?? 0) === 30.0,
    implode(', ', array_map(fn($k, $v) => $k . '=' . $v, array_keys($totales), $totales)));

  // -------------------------------------------------------------------------
  // La pestana Line items (tabla PHP) y la vista de la proforma (sigue viva).
  // -------------------------------------------------------------------------
  print "\n";
  print "La pestana Line items y la vista de la proforma\n";
  print str_repeat('-', 70) . "\n";

  $htmlLineas = (string) \Drupal::service('renderer')->renderRoot(
    \Drupal\tec_portal\OrderLineGrid::create()->build($almacenPedido->loadUnchanged($pedido->id()))
  );
  $posZorro = strpos($htmlLineas, 'PRUEBA ZORRO');
  $posMedio = strpos($htmlLineas, 'PRUEBA MEDIO');
  $posAbeto = strpos($htmlLineas, 'PRUEBA ABETO');
  $mirar('la tabla PHP de Line items esta en la ficha',
    str_contains($htmlLineas, 'tec-portal__table') && str_contains($htmlLineas, '>Image<'));
  $mirar('y saca los productos en el orden en que se anadieron',
    $posZorro !== FALSE && $posMedio !== FALSE && $posAbeto !== FALSE
    && $posZorro < $posMedio && $posMedio < $posAbeto,
    sprintf('ZORRO@%s MEDIO@%s ABETO@%s', $posZorro === FALSE ? 'no' : $posZorro, $posMedio === FALSE ? 'no' : $posMedio, $posAbeto === FALSE ? 'no' : $posAbeto));

  $vista = Views::getView('tec_order_sales_order_line_items');
  $vista->setDisplay('block_1');
  $vista->setArguments([$pedido->id()]);
  $vista->preExecute();
  $vista->execute();
  $enPantalla = [];
  foreach ($vista->result as $fila) {
    $enPantalla[] = (int) $fila->_entity->id();
  }
  $sql = (string) $vista->query->query();
  $vista->destroy();

  printf("  lineas en la vista de la proforma: %s\n", implode(', ', $enPantalla));
  $mirar('la vista de la proforma saca las lineas en el orden en que se anadieron',
    $enPantalla === array_map(fn($l) => (int) $l->id(), $lineas),
    implode(', ', $enPantalla));
  $mirar('y ya no mira la tabla de pesos de arrastrar productos',
    !str_contains($sql, 'draggableviews_structure'),
    'la consulta sigue enganchando draggableviews_structure');

  $primeraLinea = $almacenLinea->load($enPantalla[0] ?? 0);
  $primerProducto = $primeraLinea ? $primeraLinea->get('field_tec_product')->entity : NULL;
  $mirar('el primer producto del documento es el de la primera linea de esa vista',
    $primerProducto && (string) $primerProducto->label() === ($dice[0] ?? ''),
    'vista: ' . ($primerProducto ? $primerProducto->label() : 'nada') . ', documento: ' . ($dice[0] ?? 'nada'));
}
finally {
  // -------------------------------------------------------------------------
  // Recoger, contadores incluidos.
  // -------------------------------------------------------------------------
  foreach ($basura['lineas'] as $id) {
    $almacenLinea->loadUnchanged($id)?->delete();
  }
  foreach ($basura['pedido'] as $id) {
    $almacenPedido->loadUnchanged($id)?->delete();
  }
  foreach (array_reverse($basura['producto']) as $id) {
    try {
      $almacenProducto->loadUnchanged($id)?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar el producto %s: %s\n", $id, $e->getMessage());
    }
  }
  foreach ($basura['terminos'] as $id) {
    try {
      $almacenTermino->loadUnchanged($id)?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar el termino %s: %s\n", $id, $e->getMessage());
    }
  }
  $contadores->deleteAll();
  if ($contadoresAntes) {
    $contadores->setMultiple($contadoresAntes);
  }
  printf("\n  Recogido. Contadores devueltos: %d\n", count($contadoresAntes));
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. El papel dice lo mismo que la pantalla, y en el mismo orden.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";
