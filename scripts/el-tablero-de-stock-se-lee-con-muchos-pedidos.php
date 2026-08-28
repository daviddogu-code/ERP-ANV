<?php

/**
 * @file
 * El tablero de stock se puede leer aunque la cola lleve muchos pedidos.
 *
 *   php vendor\bin\drush.php scr scripts/el-tablero-de-stock-se-lee-con-muchos-pedidos.php
 *
 * POR QUE ESTA PRUEBA.
 *
 * El 19 de agosto de 2026 el dueno abrio /stock con diez pedidos en la cola y no
 * entendio nada. La tabla no estaba partida -- tenia las mismas celdas arriba que
 * abajo, 27 y 27 -- y precisamente por eso no la habria cazado ninguna prueba de
 * las que miran si una pantalla carga.
 *
 * Lo que estaba mal era el emparejado. Cada pedido ocupaba DOS columnas, la
 * casilla en una y la cantidad en otra, y la cantidad iba pegada al borde derecho
 * de la suya. Con un numero corto -- un 0, un 30 -- el numero acababa a un dedo de
 * la casilla del pedido SIGUIENTE y a tres dedos de la suya. Se leia «30 ☐»
 * mientras la cabecera decia «☐ 26-001». Una raya de 2px separaba los grupos, y
 * no sirvio de nada: la cercania gana a cualquier raya.
 *
 * Asi que la regla que hay que vigilar no es «cargan las columnas» sino esta: la
 * casilla de un pedido y su cantidad estan en la MISMA celda, y en ese orden.
 * Mientras eso se cumpla no hay forma de leerlo al reves.
 *
 * QUE MIRA.
 *
 * 1. Que la cabecera y todas las filas tienen el mismo numero de celdas.
 * 2. Que un pedido de la cola es UNA columna, no dos, y que no queda ni rastro de
 *    las dos viejas.
 * 3. Que dentro de cada celda de pedido hay una casilla y una cantidad, del mismo
 *    pedido, y la casilla primero.
 * 4. Que la cabecera de cada columna lleva el numero del pedido enlazado y la
 *    casilla que marca la columna entera.
 * 5. Que estan todos los pedidos de la cola y en el orden de la cola.
 * 6. Que el hueco de la cabecera mide lo mismo que una cantidad, que es lo unico
 *    que deja la casilla del maestro en la misma vertical que las de las filas.
 * 7. Que las tres columnas de la izquierda siguen congeladas.
 *
 * No monta ningun juego de prueba: mira el tablero de verdad, con lo que haya en
 * la cola. Si la cola esta vacia no hay nada que mirar y lo dice.
 */

use Drupal\tec_production\Form\ProductionQueueForm;

$problemas = 0;

/**
 * Apunta una comprobacion.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

print "\n" . str_repeat('=', 70) . "\n";
print "  El tablero de stock se lee con muchos pedidos\n";
print str_repeat('=', 70) . "\n";

// -----------------------------------------------------------------------------
print "\nQue hay en la cola\n";
print str_repeat('-', 70) . "\n";

$gestor = \Drupal::entityTypeManager();
$almacenPedidos = $gestor->getStorage('tec_order');
$ids = $almacenPedidos->getQuery()
  ->condition('type', 'tec_sales_order')
  ->condition('field_tec_order_status', ProductionQueueForm::ACTIVE_STATUSES, 'IN')
  ->accessCheck(FALSE)
  ->execute();

// El tablero los ordena por el sitio en la cola, y los que no tienen sitio van al
// final por su numero de ficha. Se repite aqui la misma cuenta para poder exigir
// que las columnas salgan en ese orden y no en otro.
$enLaCola = [];
foreach ($almacenPedidos->loadMultiple($ids) as $pedido) {
  $enLaCola[] = [
    'id' => (int) $pedido->id(),
    'label' => $pedido->label() ?: ('#' . $pedido->id()),
    'sitio' => $pedido->hasField('field_tec_queue_position') && !$pedido->get('field_tec_queue_position')->isEmpty()
      ? (int) $pedido->get('field_tec_queue_position')->value
      : PHP_INT_MAX,
  ];
}
usort($enLaCola, fn($a, $b) => $a['sitio'] === $b['sitio'] ? $a['id'] <=> $b['id'] : $a['sitio'] <=> $b['sitio']);

printf("  %d pedidos en la cola: %s\n", count($enLaCola),
  implode(', ', array_map(fn($p) => $p['label'], $enLaCola)) ?: 'ninguno');

if (!$enLaCola) {
  print "\n  La cola esta vacia, asi que el tablero no tiene ninguna columna que mirar.\n";
  print "  Monta el juego de prueba y vuelve:\n";
  print "      php vendor\\bin\\drush.php scr scripts/fabricar-el-juego-de-pruebas.php\n\n";
  return;
}

// -----------------------------------------------------------------------------
print "\nLa pantalla\n";
print str_repeat('-', 70) . "\n";

\Drupal::currentUser()->setAccount(\Drupal\user\Entity\User::load(1));
$peticion = \Symfony\Component\HttpFoundation\Request::create('/stock');
$peticion->setSession(\Drupal::service('session'));
$respuesta = \Drupal::service('http_kernel')
  ->handle($peticion, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);

$mirar('la pantalla contesta', $respuesta->getStatusCode() === 200,
  'codigo ' . $respuesta->getStatusCode());
if ($respuesta->getStatusCode() !== 200) {
  print "\n  No sigo sin pantalla.\n\n";
  return;
}

$documento = new \DOMDocument();
libxml_use_internal_errors(TRUE);
$documento->loadHTML($respuesta->getContent());
libxml_clear_errors();
$xpath = new \DOMXPath($documento);

$tabla = $xpath->query('//table[contains(@class, "tec-stock__table")]')->item(0);
$mirar('y trae la tabla del tablero', $tabla !== NULL);
if (!$tabla) {
  print "\n  No sigo sin tabla.\n\n";
  return;
}

// Parte de lo que sostiene esta pantalla son dos medidas de la hoja de estilos que
// tienen que coincidir, asi que se lee aqui y se mira mas abajo.
$hoja = (string) file_get_contents(DRUPAL_ROOT . '/modules/custom/tec_production/css/stock-control.css');

$cabecera = $xpath->query('.//thead/tr/th', $tabla);
$filas = $xpath->query('.//tbody/tr', $tabla);
$mirar('con materiales dentro', $filas->length > 0, $filas->length . ' filas');

$descuadradas = [];
foreach ($filas as $fila) {
  $celdas = $xpath->query('./td', $fila)->length;
  if ($celdas !== $cabecera->length) {
    $descuadradas[] = ($fila->getAttribute('data-tid') ?: '?') . ' con ' . $celdas;
  }
}
$mirar('todas las filas tienen tantas celdas como la cabecera', !$descuadradas,
  $descuadradas ? implode(', ', $descuadradas) . ' frente a ' . $cabecera->length : $cabecera->length . ' celdas');

// -----------------------------------------------------------------------------
print "\nUn pedido, una columna\n";
print str_repeat('-', 70) . "\n";

$columnas = $xpath->query('.//thead/tr/th[contains(@class, "tec-stock__h-ord")]', $tabla);
$mirar('hay una columna por pedido de la cola, y no dos',
  $columnas->length === count($enLaCola),
  $columnas->length . ' columnas para ' . count($enLaCola) . ' pedidos');

// Las dos columnas viejas tenian clases propias. Si vuelve a aparecer una es que
// alguien ha vuelto a separar la casilla de su cantidad.
$viejas = 0;
foreach (['tec-stock__h-chk', 'tec-stock__c-chk', 'tec-stock__c-qty'] as $claseVieja) {
  $viejas += $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ' . $claseVieja . ' ")]', $tabla)->length;
}
$mirar('y no queda rastro de la casilla en columna aparte', $viejas === 0,
  $viejas . ' celdas con las clases de antes');

$anchoTotal = $cabecera->length;
$mirar('asi que el tablero cabe en menos columnas',
  $anchoTotal === 7 + count($enLaCola),
  $anchoTotal . ' columnas: 7 fijas y ' . count($enLaCola) . ' de pedidos');

// -----------------------------------------------------------------------------
print "\nCada casilla, con su cantidad y en su celda\n";
print str_repeat('-', 70) . "\n";

// Esto es la prueba de verdad. Se recorre celda a celda y se exige que la casilla
// y la cantidad que hay dentro hablen del MISMO pedido, que es lo que no se podia
// garantizar cuando vivian en columnas distintas.
// La clase se busca entera y no por trozos: hay otra que empieza igual, la de la
// columna Ordered, y un contains() se la traga y dice que le faltan las casillas.
$esColumnaDePedido = 'contains(concat(" ", normalize-space(@class), " "), " tec-stock__c-ord ")';

$sueltas = [];
$desparejadas = [];
$alReves = [];
$vistas = 0;
foreach ($filas as $fila) {
  foreach ($xpath->query('./td[' . $esColumnaDePedido . ']', $fila) as $celda) {
    $vistas++;
    $casillas = $xpath->query('.//input[contains(@class, "tec-stock__chk")]', $celda);
    $cantidades = $xpath->query('.//span[contains(@class, "tec-stock__qty")]', $celda);
    if ($casillas->length !== 1 || $cantidades->length !== 1) {
      $sueltas[] = $casillas->length . ' casillas y ' . $cantidades->length . ' cantidades';
      continue;
    }
    $deLaCasilla = $casillas->item(0)->getAttribute('data-order');
    $deLaCantidad = $cantidades->item(0)->getAttribute('data-order');
    if ($deLaCasilla !== $deLaCantidad) {
      $desparejadas[] = 'casilla del ' . $deLaCasilla . ' con cantidad del ' . $deLaCantidad;
    }
    // La casilla tiene que ir DELANTE: el grupo se lee «☐ 30» y el aire queda a
    // la izquierda, separando este pedido del anterior.
    $orden = $xpath->query('.//input[contains(@class, "tec-stock__chk")] | .//span[contains(@class, "tec-stock__qty")]', $celda);
    if ($orden->length === 2 && $orden->item(0)->nodeName !== 'input') {
      $alReves[] = 'el ' . $deLaCasilla;
    }
  }
}

$mirar('cada celda de pedido lleva una casilla y una cantidad', !$sueltas,
  $sueltas ? implode('; ', array_slice($sueltas, 0, 3)) : $vistas . ' celdas miradas');
$mirar('y las dos son del mismo pedido', !$desparejadas,
  $desparejadas ? implode('; ', array_slice($desparejadas, 0, 3)) : 'las ' . $vistas);
$mirar('y la casilla va delante de su cantidad', !$alReves,
  $alReves ? implode(', ', array_slice($alReves, 0, 3)) : 'las ' . $vistas);

// -----------------------------------------------------------------------------
print "\nLa columna de lo que ya esta pedido\n";
print str_repeat('-', 70) . "\n";

// La otra mitad del fallo del 19 de agosto, y la que no cazo la primera version de
// esta prueba. La celda de Ordered lleva la cifra y ademas un enlace por cada
// pedido de compra que se espera. Con los enlaces AL LADO de la cifra pasaban dos
// cosas: la celda esta alineada a la derecha, asi que un «45 PO PO PO» dejaba el 45
// en el extremo izquierdo y la cabecera encima del ultimo enlace; y con seis
// enlaces la celda ocupaba mas que tres columnas. Recortar la celda no valia -- la
// tabla se dimensiona a max-content, o sea que la columna se cobra el ancho que
// pida el contenido y un max-width solo recorta la caja mientras el texto se sale
// encima de las dos columnas siguientes. Estaba escribiendo -561.99 sobre 636.99.
$cifrasSueltas = [];
$enlacesSueltos = 0;
$enlacesEnSuSitio = 0;
foreach ($filas as $fila) {
  $celda = $xpath->query('./td[contains(concat(" ", normalize-space(@class), " "), " tec-stock__c-onorder ")]', $fila)->item(0);
  if (!$celda) {
    $cifrasSueltas[] = 'la fila ' . ($fila->getAttribute('data-tid') ?: '?') . ' no tiene la columna';
    continue;
  }
  if ($xpath->query('.//span[contains(@class, "tec-stock__onorder-num")]', $celda)->length !== 1) {
    $cifrasSueltas[] = 'la fila ' . ($fila->getAttribute('data-tid') ?: '?') . ' no tiene su cifra en un bloque';
  }
  // Un enlace colgando directamente de la celda es un enlace al lado de la cifra.
  $enlacesSueltos += $xpath->query('./a[contains(@class, "tec-stock__receive")]', $celda)->length;
  $enlacesEnSuSitio += $xpath->query('.//div[contains(@class, "tec-stock__waiting")]/a[contains(@class, "tec-stock__receive")]', $celda)->length;
}

$mirar('la cifra de lo pedido va en su propia linea', !$cifrasSueltas,
  $cifrasSueltas ? implode('; ', $cifrasSueltas) : 'las ' . $filas->length . ' filas');
$mirar('y los pedidos de compra debajo, no a su lado', $enlacesSueltos === 0,
  $enlacesSueltos > 0 ? $enlacesSueltos . ' enlaces colgando de la celda' : $enlacesEnSuSitio . ' enlaces, todos en su bloque');

// Y que el ancho siga donde tiene que estar. Es la leccion del dia: la medida va en
// el bloque de dentro, porque un max-width en una celda de esta tabla no recorta
// nada, solo esconde que el texto se sale.
$mirar('el ancho lo lleva el bloque de enlaces',
  (bool) preg_match('/\.tec-stock__waiting\s*\{[^}]*\bwidth:\s*([\d.]+rem)/', (string) $hoja, $ancho),
  isset($ancho[1]) ? 'el bloque mide ' . $ancho[1] : 'el bloque no tiene ancho propio');
$recorta = (bool) preg_match('/c-onorder\s*\{[^}]*max-width/', $hoja);
$mirar('y la celda no intenta recortarse, que no puede', !$recorta,
  $recorta ? 'la celda vuelve a llevar max-width' : 'la celda no lleva max-width');

// Los enlaces son los pedidos de compra que faltan por llegar, y son los mismos que
// cuenta /purchase. Si se cae uno, alguien vuelve a comprar lo que ya viene.
$tids = [];
foreach ($filas as $fila) {
  if ($tid = $fila->getAttribute('data-tid')) {
    $tids[] = (int) $tid;
  }
}
$esperando = \Drupal\tec_production\Purchasing::incoming($gestor, $tids);
$deberian = array_sum(array_map('count', $esperando));
$mirar('y estan todos los que se esperan', $enlacesEnSuSitio === $deberian,
  $enlacesEnSuSitio . ' enlaces para ' . $deberian . ' pedidos de compra pendientes');

// -----------------------------------------------------------------------------
print "\nLa cabecera de cada columna\n";
print str_repeat('-', 70) . "\n";

$sinNombre = [];
$sinMaestro = [];
$fuueraDeOrden = [];
foreach ($columnas as $i => $columna) {
  $esperado = $enLaCola[$i] ?? NULL;
  $enlace = $xpath->query('.//a[contains(@class, "tec-stock__ord-link")]', $columna)->item(0);
  $maestro = $xpath->query('.//input[contains(@class, "tec-stock__master")]', $columna)->item(0);

  if (!$enlace) {
    $sinNombre[] = 'columna ' . ($i + 1);
  }
  elseif ($esperado && trim($enlace->textContent) !== $esperado['label']) {
    $fuueraDeOrden[] = 'la ' . ($i + 1) . ' dice ' . trim($enlace->textContent) . ' y toca ' . $esperado['label'];
  }
  if (!$maestro || ($esperado && $maestro->getAttribute('data-order') !== (string) $esperado['id'])) {
    $sinMaestro[] = 'columna ' . ($i + 1);
  }
}

$mirar('cada columna lleva el numero del pedido, enlazado', !$sinNombre,
  $sinNombre ? implode(', ', $sinNombre) : 'las ' . $columnas->length);
$mirar('y su casilla de marcar la columna entera', !$sinMaestro,
  $sinMaestro ? implode(', ', $sinMaestro) : 'las ' . $columnas->length);
$mirar('y salen en el orden de la cola', !$fuueraDeOrden,
  $fuueraDeOrden ? implode('; ', array_slice($fuueraDeOrden, 0, 3)) : 'de ' . $enLaCola[0]['label'] . ' a ' . end($enLaCola)['label']);

// -----------------------------------------------------------------------------
print "\nLo que sostiene la vertical\n";
print str_repeat('-', 70) . "\n";

// La casilla del maestro queda encima de las de su columna porque detras lleva un
// hueco que mide lo mismo que una cantidad. Si alguien cambia una de las dos
// medidas y no la otra, la cabecera se descoloca de su columna sin que nada falle.
$mirar('el hueco de la cabecera y la cantidad miden lo mismo',
  (bool) preg_match('/\.tec-stock__qty,\s*\.tec-stock__pair-gap\s*\{[^}]*min-width:\s*([\d.]+rem)/', $hoja, $medida),
  isset($medida[1]) ? 'las dos ' . $medida[1] . ', declaradas juntas' : 'ya no se declaran juntas');

$mirar('y el hueco existe en la cabecera',
  $xpath->query('.//thead//span[contains(@class, "tec-stock__pair-gap")]', $tabla)->length === $columnas->length,
  $xpath->query('.//thead//span[contains(@class, "tec-stock__pair-gap")]', $tabla)->length . ' huecos para ' . $columnas->length . ' columnas');

// Las tres de la izquierda se quedan quietas mientras los pedidos pasan por
// debajo. Es lo que hace que un tablero de veinte columnas se pueda mirar.
$congeladas = [];
foreach (['tec-stock__h-stock' => 'el stock', 'tec-stock__h-mat' => 'el material', 'tec-stock__h-img' => 'la foto'] as $clase => $cual) {
  if ($xpath->query('.//thead/tr/th[contains(@class, "' . $clase . '")]', $tabla)->length !== 1) {
    $congeladas[] = $cual;
  }
}
$mirar('y las tres columnas congeladas siguen en su sitio', !$congeladas,
  $congeladas ? 'falta ' . implode(', ', $congeladas) : 'stock, material y foto');

// -----------------------------------------------------------------------------
print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Un pedido es una columna y su casilla no se puede confundir.\n\n";
}
else {
  printf("  %d cosas mal.\n\n", $problemas);
}
