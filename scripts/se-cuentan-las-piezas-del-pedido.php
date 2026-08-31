<?php

/**
 * @file
 * Cuantas piezas lleva un pedido, y debajo de que columna lo dice.
 *
 *   php vendor\bin\drush.php scr scripts/se-cuentan-las-piezas-del-pedido.php
 *
 * La misma pregunta se contesta en dos sitios y con dos maquinarias distintas,
 * y esta prueba mira las dos porque son dos formas distintas de romperse:
 *
 *   - **El pedido guardado** lo suma el servidor y escribe una fila mas al final
 *     de la tabla (`OrderFoot`). Se rompe si la fila deja de cuadrar con las
 *     columnas: la cuenta tiene que caer **bajo Qty** y el dinero bajo la ultima,
 *     y una celda de mas o de menos lo desplaza todo sin dar ningun error.
 *   - **El borrador** lo suma el navegador, con el guion inyectado, porque alli
 *     las cifras se mueven mientras se teclea. Se rompe igual, y por eso las dos
 *     buscan su columna por el nombre y no contandolas.
 *
 * Asi que lo que se comprueba no es que el numero salga, sino **donde sale**: se
 * dibuja la tabla, se mira en que columna empieza cada celda del pie sumando los
 * `colspan` de las anteriores, y se compara con la columna de la cabecera. Es la
 * unica manera de ver un descuadre que en pantalla se lee como un numero puesto
 * donde no toca.
 *
 * Compras no cuenta piezas, que es lo que se pidio, y eso tambien se comprueba:
 * es facil que se cuele el dia que alguien copie una pantalla de ventas.
 *
 * Deshace todo lo que crea. Se puede lanzar dos veces.
 */

use Drupal\tec_production\OrderNumber;
use Drupal\views\Views;

const VISTA = 'tec_order_sales_order_line_items';
const INYECTOR = 'asset_injector.js.draft_for_ace_company_view';

// La columna que se cuenta, tal como Views la nombra en la clase de sus celdas.
const CLASE_CANTIDAD = 'views-field-field-tec-quantity';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$problemas = 0;

$mirar = function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, !$bien && $pista !== '' ? ': ' . $pista : '');
  if (!$bien) {
    $problemas++;
  }
};

// Una pantalla de la vista, dibujada.
$dibujar = function (string $pantalla, $argumento): string {
  $vista = Views::getView(VISTA);
  $vista->setDisplay($pantalla);
  $vista->setArguments([$argumento]);
  $vista->preExecute();
  $vista->execute();
  $html = (string) \Drupal::service('renderer')->renderInIsolation($vista->render());
  $vista->destroy();

  return $html;
};

$rutasDe = function (string $html): \DOMXPath {
  $documento = new \DOMDocument();
  @$documento->loadHTML('<?xml encoding="utf-8" ?>' . $html);

  return new \DOMXPath($documento);
};

// En que columna empieza cada celda de una fila. Una celda que abarca cinco
// columnas empuja cinco a la siguiente, y esa cuenta es justo la que hay que
// hacer para saber si una cifra cae bajo su titulo.
$celdasDe = function (\DOMXPath $rutas, \DOMNode $fila): array {
  $celdas = [];
  $columna = 0;
  foreach ($rutas->query('.//td', $fila) as $celda) {
    $ancho = (int) ($celda->getAttribute('colspan') ?: 1);
    $celdas[] = [
      'columna' => $columna,
      'ancho' => $ancho,
      'texto' => trim(preg_replace('/\s+/', ' ', $celda->textContent)),
    ];
    $columna += $ancho;
  }

  return $celdas;
};

// La columna que lleva esta clase en la cabecera, o -1.
$cabecera = function (\DOMXPath $rutas, string $clase): int {
  foreach ($rutas->query('//table//thead//tr[1]/th') as $i => $th) {
    if (in_array($clase, preg_split('/\s+/', (string) $th->getAttribute('class')), TRUE)) {
      return (int) $i;
    }
  }

  return -1;
};

print "\n";
print "=====================================================================\n";
print "  Se cuentan las piezas del pedido\n";
print "=====================================================================\n\n";

$contadores = \Drupal::keyValue(OrderNumber::LEDGER);
$contadoresAntes = $contadores->getAll();
$basura = ['lineas' => [], 'pedidos' => []];

try {
  // -------------------------------------------------------------------------
  // 1. El pedido guardado: la pantalla y el papel.
  // -------------------------------------------------------------------------
  $almacenPedido = $gestor->getStorage('tec_order');
  $almacenLinea = $gestor->getStorage('tec_line_item');

  $pedido = $almacenPedido->create(['type' => 'tec_sales_order', 'title' => 'PRUEBA piezas']);
  $pedido->save();
  $basura['pedidos'][] = $pedido->id();

  // Mil y pico piezas a proposito: es la unica manera de ver el separador de
  // millares, que en la cifra de al lado ya estuvo mal una vez.
  $lineas = [];
  foreach ([[7, '100.00'], [5, '250.00'], [1000, '12.50']] as [$cantidad, $precio]) {
    $linea = $almacenLinea->create([
      'type' => 'tec_sales_order_line_item',
      'title' => 'PRUEBA piezas linea',
      'field_tec_quantity' => $cantidad,
      'field_tec_price' => $precio,
      'field_tec_line_item_total_number' => number_format($cantidad * (float) $precio, 2, '.', ''),
      'field_tec_order' => ['target_id' => $pedido->id()],
    ]);
    $linea->save();
    $basura['lineas'][] = $linea->id();
    $lineas[] = $linea->id();
  }

  $pedido = $almacenPedido->loadUnchanged($pedido->id());
  $pedido->set('field_tec_line_items', $lineas);
  $pedido->save();

  // 7 + 5 + 1000, y 700 + 1250 + 12500.
  $piezas = '1,012';
  $dinero = '14,450.00';

  foreach ([
    'block_1' => 'la pagina del pedido',
    'page_4' => 'la Pro Forma impresa',
  ] as $pantalla => $quien) {
    print $quien . " (" . $pantalla . ")\n";
    print str_repeat('-', 70) . "\n";

    $rutas = $rutasDe($dibujar($pantalla, $pedido->id()));
    $columnas = $rutas->query('//table//thead//tr[1]/th')->length;
    $enCantidad = $cabecera($rutas, CLASE_CANTIDAD);

    $pies = $rutas->query('//tr[contains(@class, "tec-foot-row")]');
    $mirar('tiene una fila de pie, y una sola', $pies->length === 1,
      $pies->length . ' filas de pie');
    if ($pies->length !== 1) {
      continue;
    }

    $celdas = $celdasDe($rutas, $pies->item(0));
    $ancho = array_sum(array_column($celdas, 'ancho'));

    printf("  %d columnas, cantidad en la %s, el pie ocupa %d en %d celdas\n",
      $columnas, $enCantidad > -1 ? $enCantidad : 'ninguna', $ancho, count($celdas));
    foreach ($celdas as $celda) {
      printf("      columna %-2d ancho %-2d %s\n", $celda['columna'], $celda['ancho'],
        $celda['texto'] === '' ? '·' : $celda['texto']);
    }

    $mirar('el pie ocupa la tabla entera, ni una columna mas ni una menos',
      $ancho === $columnas, $ancho . ' para ' . $columnas);

    // Lo que se pidio: la cuenta, bajo Qty.
    $donde = -1;
    foreach ($celdas as $celda) {
      if ($celda['texto'] === $piezas) {
        $donde = $celda['columna'];
      }
    }
    $mirar('las piezas van debajo de su columna', $donde > -1 && $donde === $enCantidad,
      $donde < 0 ? 'no sale ' . $piezas . ' en el pie' : 'salen en la columna ' . $donde . ' y Qty es la ' . $enCantidad);

    // Y el dinero, en la ultima, que es donde acaba la mirada.
    $ultima = end($celdas);
    $mirar('y el dinero en la ultima columna',
      $ultima['columna'] === $columnas - 1 && str_contains($ultima['texto'], $dinero),
      'la ultima celda es "' . $ultima['texto'] . '" en la columna ' . $ultima['columna']);

    $mirar('los millares llevan coma y nada mas',
      !preg_match('/\d,\s+\d/', implode(' ', array_column($celdas, 'texto'))),
      'hay un separador de millares con espacio');

    print "\n";
  }

  // -------------------------------------------------------------------------
  // 2. Lo mismo, contra los pedidos que ya existen.
  // -------------------------------------------------------------------------
  print "Los pedidos que ya hay\n";
  print str_repeat('-', 70) . "\n";

  $ids = $gestor->getStorage('tec_order')->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_sales_order')
    ->condition('field_tec_line_items', NULL, 'IS NOT NULL')
    ->sort('id', 'DESC')
    ->range(0, 4)
    ->execute();

  foreach ($ids as $id) {
    $viejo = $almacenPedido->load($id);

    // A mano, y solo las lineas que la pantalla ensena: la que vale cero no sale
    // en la tabla, asi que tampoco puede estar en la suma de su pie.
    $aMano = 0;
    foreach ($viejo->get('field_tec_line_items')->referencedEntities() as $linea) {
      $aMano += (int) $linea->get('field_tec_quantity')->value;
    }

    $rutas = $rutasDe($dibujar('block_1', $id));
    $enCantidad = $cabecera($rutas, CLASE_CANTIDAD);
    $pie = $rutas->query('//tr[contains(@class, "tec-foot-row")]')->item(0);
    $dice = -1;
    if ($pie) {
      foreach ($celdasDe($rutas, $pie) as $celda) {
        if ($celda['columna'] === $enCantidad) {
          $dice = (int) str_replace(',', '', $celda['texto']);
        }
      }
    }

    // Un pedido en el que ninguna linea lleva cantidad no ensena ni una fila
    // -- la pantalla filtra las que valen cero -- y una tabla sin filas no
    // lleva pie. No es un fallo: es que no hay nada que sumar.
    $esperado = $aMano > 0 ? $aMano : -1;

    printf("  %-26s a mano %-6d el pie %-6s\n", $viejo->label(), $aMano,
      $dice < 0 ? ($aMano > 0 ? '(nada)' : '(sin lineas)') : $dice);
    $mirar('el pie de ' . $viejo->label() . ' dice las piezas que tiene', $dice === $esperado,
      'a mano ' . $aMano . ', el pie ' . $dice);
  }

  // -------------------------------------------------------------------------
  // 3. Compras no cuenta piezas.
  // -------------------------------------------------------------------------
  print "\n";
  print "Compras, que no lo pidio\n";
  print str_repeat('-', 70) . "\n";

  $compras = $gestor->getStorage('tec_order')->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_purchase_order')
    ->condition('field_tec_line_items', NULL, 'IS NOT NULL')
    ->sort('id', 'DESC')
    ->range(0, 1)
    ->execute();

  if (!$compras) {
    print "  No hay ningun pedido de compra con lineas. Esta parte no se puede probar.\n";
  }
  else {
    $compra = reset($compras);
    foreach (['block_2' => 'la pagina de un pedido de compra', 'page_3' => 'su impreso'] as $pantalla => $quien) {
      $rutas = $rutasDe($dibujar($pantalla, $compra));
      $mirar($quien . ' no cuenta piezas',
        $rutas->query('//tr[contains(@class, "tec-foot-row")]')->length === 0,
        $pantalla . ' saca una fila de pie que nadie pidio');
      $mirar('y conserva su pie de IVA',
        $rutas->query('//table[contains(@class, "tec-vat-totals")]')->length === 1,
        $pantalla . ' se ha quedado sin Subtotal / VAT / Total');
    }
  }

  // -------------------------------------------------------------------------
  // 4. La pantalla que sobraba.
  // -------------------------------------------------------------------------
  print "\n";
  print "La pantalla que ponia el pie por fuera\n";
  print str_repeat('-', 70) . "\n";

  $vista = $gestor->getStorage('view')->load(VISTA);
  $pantallas = $vista->get('display');
  $mirar('attachment_1 ya no existe', !isset($pantallas['attachment_1']),
    'sigue ahi, asi que el pedido tiene dos pies');

  // Los campos agregados que quedan son de pantallas que no dibujan este pie.
  // Se cuentan igual, porque un campo agregado arrastra el separador de millares
  // raro y esa trampa sigue viva donde queden.
  $sumas = [];
  $conEspacio = [];
  foreach ($pantallas as $nombre => $pantalla) {
    foreach ($pantalla['display_options']['fields'] ?? [] as $clave => $campo) {
      if (($campo['group_type'] ?? 'group') === 'group') {
        continue;
      }
      $sumas[] = $nombre . '/' . $clave;
      if (($campo['separator'] ?? ',') !== ',') {
        $conEspacio[] = $nombre . '/' . $clave . ' = "' . $campo['separator'] . '"';
      }
    }
  }
  printf("  campos agregados que quedan: %s\n", $sumas ? implode(', ', $sumas) : 'ninguno');
  $mirar('ninguno separa los millares con nada que no sea una coma', !$conEspacio,
    implode('; ', $conEspacio));

  // -------------------------------------------------------------------------
  // 5. El borrador, que suma en el navegador.
  // -------------------------------------------------------------------------
  print "\n";
  print "El borrador\n";
  print str_repeat('-', 70) . "\n";

  $guion = \Drupal::config(INYECTOR);
  $codigo = (string) $guion->get('code');
  $donde = (string) $guion->get('conditions.request_path.pages');

  printf("  el guion son %d lineas y se carga en %s\n",
    substr_count($codigo, "\n") + 1,
    str_replace(["\r\n", "\n"], ', ', trim($donde)));

  $mirar('el guion del borrador sigue enganchado a compras',
    str_contains($donde, '/po/draft/*') && !str_contains($donde, '/o/draft/*'),
    $donde);
  $mirar('y cuenta piezas', str_contains($codigo, 'grand-total-pieces'),
    'no hay ninguna celda de piezas en el guion');
  $mirar('busca las columnas por su nombre y no por su numero',
    str_contains($codigo, 'function columnOf'),
    'la fila del pie vuelve a contar columnas a mano');
  $mirar('y el pie de compras sigue teniendo sus tres lineas',
    str_contains($codigo, "'Subtotal'") && str_contains($codigo, "'VAT '") && str_contains($codigo, "'Total'"),
    'se ha perdido el pie con IVA');

  // La cuenta que hace el guion, hecha aqui contra las columnas de verdad. Es
  // la parte que se rompe sin dar error: una celda de mas o de menos y la fila
  // del pie se descuadra de su tabla.
  foreach ([['page_2', 'ventas', TRUE], ['page_1', 'compras', FALSE]] as [$pantalla, $quien, $cuenta]) {
    $rutas = $rutasDe($dibujar($pantalla, $pedido->id()));
    $cabeceras = $rutas->query('//table//thead//tr[1]/th');
    $columnas = $cabeceras->length;

    // Igual que el guion: la columna de cantidad **sin** sufijo es la de ventas.
    $enColumna = -1;
    foreach ($cabeceras as $i => $th) {
      $clases = preg_split('/\s+/', (string) $th->getAttribute('class'));
      if ($enColumna === -1 && in_array('views-field-form-field-field-tec-quantity', $clases, TRUE)) {
        $enColumna = (int) $i;
      }
    }

    $hueco = $columnas - $enColumna - 2;
    $conPiezas = $enColumna > -1 && $hueco > 0;
    $celdas = $conPiezas
      ? ($enColumna > 0 ? $enColumna : 0) + 1 + $hueco + 1
      : max(1, $columnas - 1) + 1;

    printf("  %-8s (%s): %d columnas, cantidad en la %s, el pie ocupa %d\n",
      $pantalla, $quien, $columnas, $enColumna > -1 ? $enColumna : 'ninguna', $celdas);

    $mirar('el pie de ' . $quien . ' cuadra con su tabla', $celdas === $columnas,
      $celdas . ' celdas para ' . $columnas . ' columnas');
    $mirar($cuenta ? 'y ventas cuenta piezas' : 'y compras no cuenta piezas', $conPiezas === $cuenta,
      $conPiezas ? 'las cuenta' : 'no las cuenta');
  }
}
finally {
  foreach ($basura['lineas'] as $id) {
    $gestor->getStorage('tec_line_item')->loadUnchanged($id)?->delete();
  }
  foreach ($basura['pedidos'] as $id) {
    $gestor->getStorage('tec_order')->loadUnchanged($id)?->delete();
  }
  $contadores->deleteAll();
  if ($contadoresAntes) {
    $contadores->setMultiple($contadoresAntes);
  }
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. La cuenta de piezas cae bajo su columna, en pantalla y en papel.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";
