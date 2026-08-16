<?php

/**
 * @file
 * Prueba la recepcion de mercancia de punta a punta.
 *
 *   php vendor\bin\drush.php scr scripts/funciona-la-recepcion.php
 *
 * Recibir toca las tres cosas que mas duelen si se rompen: lo pendiente de un
 * pedido, el nivel de stock de un material, y la conversion entre la unidad en
 * la que se compra y la unidad en la que se guarda. Un error en la conversion no
 * da ningun aviso: mete en el almacen un numero creible y equivocado, y no se
 * descubre hasta que alguien cuenta rollos a mano semanas despues. Asi que aqui
 * la cuenta se hace por separado, a partir del factor del material, y se compara
 * con lo que ha quedado guardado.
 *
 * Se prueba como lo haria un navegador -se pide la pagina, se devuelve con sus
 * campos y su ficha de seguridad- porque el guardado depende de saber que boton
 * se ha apretado, y un formulario construido a mano no tiene ninguno.
 *
 * El recorrido es el de una entrega de verdad, con sus dos sorpresas:
 *
 *   1. llega parte de una linea y nada de la otra
 *   2. llega el resto de la primera corto, y se cierra lo que falta
 *   3. de la segunda llega mas de lo pedido
 *   4. el pedido se cierra solo y deja de contar en las dos pantallas
 *
 * Al terminar deshace todo lo que ha creado -movimientos, lineas, pedido- y
 * devuelve los niveles de stock a como estaban, asi que se puede lanzar otra vez
 * y no le descuadra las cuentas a comprobacion.php.
 */

use Drupal\Component\Utility\NestedArray;
use Drupal\tec_production\Purchasing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$gestor = \Drupal::entityTypeManager();
$nucleo = \Drupal::service('http_kernel');
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));
$sesion = \Drupal::service('session');

$problemas = 0;

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

/**
 * Pide una pagina como usuario 1.
 */
$pedir = function (string $ruta, string $metodo = 'GET', array $datos = []) use ($nucleo, $sesion) {
  $peticion = Request::create($ruta, $metodo, $datos);
  $peticion->setSession($sesion);
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (\Drupal\Core\Form\EnforcedResponseException $e) {
    return $e->getResponse();
  }
  catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
    // En una peticion de verdad esto lo recoge Drupal y saca una pagina 403. En
    // una interna sale como excepcion, porque se pide expresamente que no las
    // capture para poder ver los fallos de verdad con su rastro.
    return new \Symfony\Component\HttpFoundation\Response('', 403);
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO: %s\n      %s\n      en %s:%d\n", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
    return new \Symfony\Component\HttpFoundation\Response('', 500);
  }
};

/**
 * Devuelve los campos de un formulario tal y como los mandaria un navegador.
 */
$campos = function (string $html): array {
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML($html);
  libxml_clear_errors();

  $datos = [];
  foreach ($documento->getElementsByTagName('input') as $campo) {
    $nombre = $campo->getAttribute('name');
    $tipo = strtolower($campo->getAttribute('type'));
    if ($nombre === '' || in_array($tipo, ['submit', 'button', 'image'], TRUE)) {
      continue;
    }
    if (in_array($tipo, ['checkbox', 'radio'], TRUE) && !$campo->hasAttribute('checked')) {
      continue;
    }
    $datos[$nombre] = $campo->getAttribute('value');
  }
  return $datos;
};

/**
 * Cuenta los botones de una ficha que llevan a una direccion.
 *
 * Busca solo dentro de los botones, no en todo el HTML, y por dos razones.
 *
 * La primera es que el texto pelado da falsos positivos. La direccion tambien
 * sale en la pestana del tema, y esa pestana no es una puerta para nadie: DXPR
 * la saca del flujo de la pagina y la deja flotando encima de la cabecera, donde
 * es ilegible, y su bloque solo lo ve el rol administrador.
 *
 * La segunda es que Drupal calcula las pestanas de una ruta una sola vez por
 * proceso: LocalTaskManager las guarda en memoria con el permiso ya resuelto. En
 * una peticion de verdad eso da igual, porque cada peticion es un proceso nuevo,
 * pero esta prueba pinta la misma ficha antes y despues de cerrar el pedido, asi
 * que la pestana se queda pegada y mentiria.
 */
$botones = function (string $html, string $destino): int {
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML($html);
  libxml_clear_errors();

  $encontrados = 0;
  foreach ((new \DOMXPath($documento))->query('//div[contains(@class, "btn-default")]//a[@href]') as $enlace) {
    $destino_enlace = $enlace->getAttribute('href');
    if ($destino_enlace === $destino || str_starts_with($destino_enlace, $destino . '?')) {
      $encontrados++;
    }
  }
  return $encontrados;
};

/**
 * Las celdas de la fila que una lista dedica a un pedido.
 *
 * Devuelve el texto de cada celda con las direcciones de sus enlaces pegadas
 * detras, que es lo que hace falta para preguntar de una vez por lo que se lee y
 * por lo que se puede pulsar. Se busca la fila por el enlace a la ficha, exacto y
 * no por parecido, porque "contiene /tec_order/75" tambien encontraria el 750.
 */
$fila = function (string $html, int $pedido): array {
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML($html);
  libxml_clear_errors();

  $xpath = new \DOMXPath($documento);
  $filas = $xpath->query('//tr[.//a[@href="/tec_order/' . $pedido . '"]]');
  if (!$filas->length) {
    return [];
  }

  $celdas = [];
  foreach ($xpath->query('.//td', $filas->item(0)) as $celda) {
    $enlaces = [];
    foreach ($xpath->query('.//a/@href', $celda) as $href) {
      $enlaces[] = $href->nodeValue;
    }
    $celdas[] = trim(preg_replace('/\s+/', ' ', $celda->textContent)) . ' ' . implode(' ', $enlaces);
  }
  return $celdas;
};

/**
 * Los nombres con corchetes hay que anidarlos o no llegan al nucleo.
 */
$anidar = function (array $datos): array {
  $anidados = [];
  foreach ($datos as $nombre => $valor) {
    if (!preg_match_all('/\[([^\]]*)\]/', $nombre, $partes)) {
      $anidados[$nombre] = $valor;
      continue;
    }
    $camino = array_merge([strstr($nombre, '[', TRUE)], $partes[1]);
    NestedArray::setValue($anidados, $camino, $valor);
  }
  return $anidados;
};

/**
 * Los avisos y mensajes que ha dejado la peticion, y los limpia.
 */
$mensajes = function (): array {
  $todos = [];
  foreach (\Drupal::messenger()->all() as $tipo => $lista) {
    foreach ($lista as $mensaje) {
      $todos[] = $tipo . ': ' . strip_tags((string) $mensaje);
    }
  }
  \Drupal::messenger()->deleteAll();
  return $todos;
};

/**
 * El stock de un material, leido de la base sin cache.
 */
$stock = function (int $tid) use ($gestor): float {
  $material = $gestor->getStorage('taxonomy_term')->loadUnchanged($tid);
  return (float) ($material->get('field_tec_stock_level')->value ?? 0);
};

/**
 * Redondeo corto, para comparar sin pelearse con los decimales.
 */
$redondo = fn(float $numero): string => rtrim(rtrim(number_format($numero, 4, '.', ''), '0'), '.');

echo "\n";
echo "Preparando un pedido con dos lineas\n";
echo str_repeat('-', 78) . "\n";

// Dos materiales con proveedor, que es lo que necesita un pedido de compra.
$termino_storage = $gestor->getStorage('taxonomy_term');
$ids = $termino_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->exists('field_tec_vendor')
  ->range(0, 2)
  ->execute();
if (count($ids) < 2) {
  echo "\n  Hacen falta dos materiales con proveedor y no los hay. Nada que probar.\n\n";
  return;
}
$materiales = array_values($termino_storage->loadMultiple($ids));
[$material_a, $material_b] = $materiales;

$datos_a = [
  'tid' => (int) $material_a->id(),
  'nombre' => (string) $material_a->label(),
  'units' => Purchasing::factor($material_a, 'field_tec_units'),
  'stock' => $stock((int) $material_a->id()),
];
$datos_b = [
  'tid' => (int) $material_b->id(),
  'nombre' => (string) $material_b->label(),
  'units' => Purchasing::factor($material_b, 'field_tec_units'),
  'stock' => $stock((int) $material_b->id()),
];

printf("         %s: factor %s, stock %s\n", $datos_a['nombre'], $redondo($datos_a['units']), $redondo($datos_a['stock']));
printf("         %s: factor %s, stock %s\n", $datos_b['nombre'], $redondo($datos_b['units']), $redondo($datos_b['stock']));

$proveedor = $material_a->get('field_tec_vendor')->target_id;
$linea_storage = $gestor->getStorage('tec_line_item');
$pedido_storage = $gestor->getStorage('tec_order');

// Lo que ya venia de camino antes de meter nada. Puede haber otros pedidos
// abiertos con estos mismos materiales, asi que todo lo que se mire de aqui en
// adelante es la diferencia contra esto y no el numero pelado.
$antes = Purchasing::onOrder($gestor, [$datos_a['tid'], $datos_b['tid']]);
$camino = fn(array $mapa, int $tid): float => (float) ($mapa[$tid] ?? 0.0) - (float) ($antes[$tid] ?? 0.0);
if ($antes) {
  printf("         ya venian de camino: %s\n", json_encode($antes));
}

$pedidos_antes = (int) $pedido_storage->getQuery()->accessCheck(FALSE)
  ->condition('type', 'tec_purchase_order')->count()->execute();

$linea_a = $linea_storage->create([
  'type' => 'tec_po_line_item',
  'field_tec_inventory' => $datos_a['tid'],
  'field_tec_quantity' => 10,
  'field_tec_price' => 1,
  'field_tec_vendor' => $proveedor,
  'uid' => 1,
]);
$linea_a->save();
$linea_b = $linea_storage->create([
  'type' => 'tec_po_line_item',
  'field_tec_inventory' => $datos_b['tid'],
  'field_tec_quantity' => 4,
  'field_tec_price' => 1,
  'field_tec_vendor' => $proveedor,
  'uid' => 1,
]);
$linea_b->save();

// No title given, and none would stick if it were: since 16 August 2026 the name
// of an order is decided by OrderNumber on save, so the test cannot pick one and
// has to read back what the order ended up being called.
$pedido = $pedido_storage->create([
  'type' => 'tec_purchase_order',
  'field_tec_vendor' => $proveedor,
  'field_tec_line_items' => [$linea_a->id(), $linea_b->id()],
  'uid' => 1,
]);
$pedido->save();
$linea_a->set('field_tec_order', $pedido->id())->save();
$linea_b->set('field_tec_order', $pedido->id())->save();

printf("         pedido %s (ficha %d), lineas %d y %d\n", $pedido->label(), $pedido->id(), $linea_a->id(), $linea_b->id());
$mirar('el pedido nace abierto', $pedido->get('field_tec_po_status')->value === 'open', (string) $pedido->get('field_tec_po_status')->value);

$pendiente = Purchasing::onOrder($gestor, [$datos_a['tid'], $datos_b['tid']]);
$mirar('lo pendiente arranca siendo lo pedido',
  $camino($pendiente, $datos_a['tid']) === 10.0 && $camino($pendiente, $datos_b['tid']) === 4.0,
  sprintf('%s y %s de mas', $redondo($camino($pendiente, $datos_a['tid'])), $redondo($camino($pendiente, $datos_b['tid'])))
);

echo "\n";
echo "La pantalla de recibir\n";
echo str_repeat('-', 78) . "\n";

$direccion = '/tec_order/' . $pedido->id() . '/receive';
$respuesta = $pedir($direccion);
$mirar('la pantalla carga', $respuesta->getStatusCode() === 200, 'codigo ' . $respuesta->getStatusCode());
if ($respuesta->getStatusCode() !== 200) {
  echo "\n  Sin pantalla no hay nada que probar. Se recoge y se sale.\n";
  $linea_a->delete();
  $linea_b->delete();
  $pedido->delete();
  return;
}
$html = $respuesta->getContent();
$mirar('trae las dos lineas', substr_count($html, 'tec-receive__qty') === 2, substr_count($html, 'tec-receive__qty') . ' casillas');
$mirar('trae la conversion en pantalla', str_contains($html, 'tec-receive__into'));

// Y la casilla de dar por perdido lo que falta, con la hoja que la hace visible.
// Sin esa hoja la casilla existe en el HTML y no se ve en pantalla: Bootstrap la
// pinta del color que le dice DXPR, que es casi el del fondo, asi que la columna
// entera parecia vacia y el dueno pregunto si faltaba algo. Existir en el HTML no
// es estar en la pantalla.
$mirar('trae la casilla de cerrar la linea', substr_count($html, 'tec-receive__nomore') === 2, substr_count($html, 'tec-receive__nomore') . ' casillas');
$mirar('y la hoja que la hace visible', str_contains($html, 'dxpr-checkbox-fix.css'));
$base = $campos($html);
$mirar('trae la ficha de seguridad', isset($base['form_token']) || isset($base['form_build_id']));

// Una puerta que no se ve es una funcion que no existe. Y la pestana del tema no
// sirve de puerta: DXPR la saca del flujo de la pagina y la deja flotando sobre
// la cabecera, ilegible, y encima su bloque tiene una condicion de rol que solo
// deja pasar a administrator. Asi que lo que se exige es el boton de la ficha,
// al lado de Edit order y Print/PDF.
//
// La primera version de esta comprobacion buscaba el texto de la direccion en el
// HTML. Lo encontraba en la pestana invisible y daba el visto bueno a una
// funcion a la que no habia manera de llegar, asi que ahora se exige un enlace.
$ficha = $pedir('/tec_order/' . $pedido->id());
$mirar('la ficha del pedido abierto carga', $ficha->getStatusCode() === 200, 'codigo ' . $ficha->getStatusCode());
$puertas = $botones($ficha->getContent(), $direccion);
$mirar('y ofrece un boton para recibir', $puertas > 0, $puertas . ' botones a la recepcion');

echo "\n";
echo "Primera entrega: 4 de 10 de la primera linea, nada de la segunda\n";
echo str_repeat('-', 78) . "\n";

$envio = $base;
$envio['lines[' . $linea_a->id() . '][arriving]'] = '4';
$envio['lines[' . $linea_b->id() . '][arriving]'] = '0';
$envio['note'] = '4471-B';
$envio['op'] = 'Receive';
$respuesta = $pedir($direccion, 'POST', $anidar($envio));
$mirar('el formulario redirige al guardar', in_array($respuesta->getStatusCode(), [200, 303], TRUE), 'codigo ' . $respuesta->getStatusCode());
$dichos = $mensajes();

$linea_a = $linea_storage->loadUnchanged($linea_a->id());
$mirar('la linea apunta 4 recibidos', (int) $linea_a->get('field_tec_quantity_received')->value === 4, (string) $linea_a->get('field_tec_quantity_received')->value);
$mirar('quedan 6 pendientes', Purchasing::outstanding($linea_a) === 6.0, $redondo(Purchasing::outstanding($linea_a)));

$esperado = $datos_a['stock'] + 4 * $datos_a['units'];
$visto = $stock($datos_a['tid']);
$mirar('el stock sube lo que toca, no lo que se pidio',
  $redondo($visto) === $redondo($esperado),
  sprintf('%s + 4 x %s = %s, y hay %s', $redondo($datos_a['stock']), $redondo($datos_a['units']), $redondo($esperado), $redondo($visto))
);
$mirar('el stock de la segunda no se ha movido', $redondo($stock($datos_b['tid'])) === $redondo($datos_b['stock']), $redondo($stock($datos_b['tid'])));

$movimientos = $gestor->getStorage('tec_inventory')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_inventory_transaction')
  ->condition('field_tec_po_line', $linea_a->id())
  ->execute();
$mirar('hay un movimiento y apunta a su linea', count($movimientos) === 1, count($movimientos) . ' movimientos');
if ($movimientos) {
  $movimiento = $gestor->getStorage('tec_inventory')->load(reset($movimientos));
  $nota = (string) $movimiento->get('field_tec_mutation_note')->value;
  $mirar('el movimiento lleva el pedido y el albaran en la nota',
    str_contains($nota, (string) $pedido->label()) && str_contains($nota, '4471-B'), $nota);
  $mirar('el movimiento lleva la cantidad en unidad de almacen',
    $redondo((float) $movimiento->get('field_tec_quantity')->value) === $redondo(4 * $datos_a['units']),
    $redondo((float) $movimiento->get('field_tec_quantity')->value)
  );
}

$pedido = $pedido_storage->loadUnchanged($pedido->id());
$mirar('el pedido sigue abierto', $pedido->get('field_tec_po_status')->value === 'open', (string) $pedido->get('field_tec_po_status')->value);
$mirar('y se lee como entrega a medias', Purchasing::receiptState($pedido) === 'partial', Purchasing::receiptState($pedido));

$pendiente = Purchasing::onOrder($gestor, [$datos_a['tid'], $datos_b['tid']]);
$mirar('lo pendiente de este pedido baja a 6', $camino($pendiente, $datos_a['tid']) === 6.0, $redondo($camino($pendiente, $datos_a['tid'])) . ' de mas');

// Y el tablero ofrece el pedido en la columna de lo pedido, que es el atajo de
// quien firma el albaran. Solo salen los materiales que pide la cola de
// produccion, asi que si el material de la prueba no esta en ninguna orden no
// hay fila donde mirar y se dice, en vez de fingir un fallo.
$tablero = $pedir('/stock');
$mirar('el tablero sigue cargando', $tablero->getStatusCode() === 200, 'codigo ' . $tablero->getStatusCode());
$html_tablero = $tablero->getContent();
if (str_contains($html_tablero, 'data-tid="' . $datos_a['tid'] . '"')) {
  $mirar('el tablero ofrece recibir contra el pedido', str_contains($html_tablero, $direccion));
}
else {
  printf("         %s no lo pide ninguna orden en cola, asi que no tiene fila en el tablero\n", $datos_a['nombre']);
}

// Y la lista de pedidos a proveedores, que es la pantalla de quien persigue las
// entregas. Hasta hoy preguntaba por el estado de los pedidos de venta, que un
// pedido de compra no tiene, asi que la columna del estado salia vacia siempre y
// el lapiz de editar no aparecia nunca.
$lista = $pedir('/supplier-orders');
$mirar('la lista de pedidos a proveedores carga', $lista->getStatusCode() === 200, 'codigo ' . $lista->getStatusCode());
$celdas = implode(' || ', $fila($lista->getContent(), (int) $pedido->id()));
$mirar('el pedido tiene su fila en la lista', $celdas !== '');
$mirar('la lista dice que esta abierto', str_contains($celdas, 'Open'));
$mirar('y que la entrega va a medias', str_contains($celdas, 'Partially received'), substr($celdas, 0, 120));
$mirar('y ofrece el lapiz mientras esta abierto', str_contains($celdas, '/po/draft/' . $pedido->id()));

echo "\n";
echo "Segunda entrega: 5 mas y se cierra lo que falta, y 5 de los 4 pedidos\n";
echo str_repeat('-', 78) . "\n";

$respuesta = $pedir($direccion);
$base = $campos($respuesta->getContent());
$envio = $base;
$envio['lines[' . $linea_a->id() . '][arriving]'] = '5';
$envio['lines[' . $linea_a->id() . '][no_more]'] = '1';
$envio['lines[' . $linea_a->id() . '][reason]'] = 'al proveedor se le acabo la pieza';
$envio['lines[' . $linea_b->id() . '][arriving]'] = '5';
$envio['note'] = '4471-C';
$envio['op'] = 'Receive';
$respuesta = $pedir($direccion, 'POST', $anidar($envio));
$mirar('el formulario guarda', in_array($respuesta->getStatusCode(), [200, 303], TRUE), 'codigo ' . $respuesta->getStatusCode());
$dichos = $mensajes();

$linea_a = $linea_storage->loadUnchanged($linea_a->id());
$linea_b = $linea_storage->loadUnchanged($linea_b->id());

$mirar('la primera linea suma 9 recibidos', (int) $linea_a->get('field_tec_quantity_received')->value === 9, (string) $linea_a->get('field_tec_quantity_received')->value);
$mirar('la primera linea queda cerrada', !empty($linea_a->get('field_tec_no_more_expected')->value));
$mirar('y con su motivo escrito', (string) $linea_a->get('field_tec_close_reason')->value === 'al proveedor se le acabo la pieza', (string) $linea_a->get('field_tec_close_reason')->value);
$mirar('cerrada no deja nada pendiente aunque falte 1', Purchasing::outstanding($linea_a) === 0.0, $redondo(Purchasing::outstanding($linea_a)));

$mirar('la segunda linea acepta los 5 de mas', (int) $linea_b->get('field_tec_quantity_received')->value === 5, (string) $linea_b->get('field_tec_quantity_received')->value);
$mirar('recibir de mas no deja pendiente negativo', Purchasing::outstanding($linea_b) === 0.0, $redondo(Purchasing::outstanding($linea_b)));
$aviso = FALSE;
foreach ($dichos as $dicho) {
  if (str_starts_with($dicho, 'warning') && str_contains($dicho, 'outstanding')) {
    $aviso = TRUE;
  }
}
$mirar('y avisa en pantalla de que pasa de lo pedido', $aviso, $aviso ? '' : implode(' | ', $dichos));

$esperado_a = $datos_a['stock'] + 9 * $datos_a['units'];
$esperado_b = $datos_b['stock'] + 5 * $datos_b['units'];
$mirar('el stock de la primera cuadra con los 9 recibidos',
  $redondo($stock($datos_a['tid'])) === $redondo($esperado_a),
  sprintf('esperado %s, hay %s', $redondo($esperado_a), $redondo($stock($datos_a['tid'])))
);
$mirar('el stock de la segunda cuadra con los 5 recibidos',
  $redondo($stock($datos_b['tid'])) === $redondo($esperado_b),
  sprintf('esperado %s, hay %s', $redondo($esperado_b), $redondo($stock($datos_b['tid'])))
);

$pedido = $pedido_storage->loadUnchanged($pedido->id());
$mirar('el pedido se cierra solo', $pedido->get('field_tec_po_status')->value === 'closed', (string) $pedido->get('field_tec_po_status')->value);
$mirar('y se lee como entregado', Purchasing::receiptState($pedido) === 'full', Purchasing::receiptState($pedido));

$pendiente = Purchasing::onOrder($gestor, [$datos_a['tid'], $datos_b['tid']]);
$mirar('este pedido ya no pone nada en camino',
  $camino($pendiente, $datos_a['tid']) === 0.0 && $camino($pendiente, $datos_b['tid']) === 0.0,
  json_encode($pendiente)
);

$respuesta = $pedir($direccion);
$mirar('un pedido cerrado ya no se puede recibir', $respuesta->getStatusCode() === 403, 'codigo ' . $respuesta->getStatusCode());

echo "\n";
echo "Las pantallas que arrastra\n";
echo str_repeat('-', 78) . "\n";

$ficha = $pedir('/tec_order/' . $pedido->id());
$mirar('la ficha del pedido carga', $ficha->getStatusCode() === 200, 'codigo ' . $ficha->getStatusCode());
$html_ficha = $ficha->getContent();
$mirar('y su tabla de lineas cuenta lo recibido', str_contains($html_ficha, 'Received'));

// Los tres botones de la ficha salen del mismo condicional sobre el estado, asi
// que cerrado tiene que decir lo mismo que la lista de proveedores: la copia para
// contabilidad se queda, y lo que cambia el pedido se va.
$mirar('cerrado, la ficha conserva el boton de imprimir', $botones($html_ficha, '/po/' . $pedido->id() . '/print') > 0);
$mirar('cerrado, la ficha pierde el de editar', $botones($html_ficha, '/po/draft/' . $pedido->id()) === 0);
$mirar('cerrado, la ficha pierde el de recibir', $botones($html_ficha, $direccion) === 0);

$impresa = $pedir('/po/' . $pedido->id() . '/print');
$mirar('la ficha impresa sigue en pie', $impresa->getStatusCode() === 200, 'codigo ' . $impresa->getStatusCode());

// Los botones de esta lista salen de un campo condicional que solo se cumplia
// con el estado "Open". Con el pedido ya cerrado se comprueba lo que se decidio:
// que conserve el de imprimir, porque es la copia para contabilidad, y que
// pierda el de editar, que en un pedido terminado no se quiere tocar a la ligera.
$construccion = views_embed_view('tec_supplier_orders', 'block_3', $proveedor);
$bloque = (string) \Drupal::service('renderer')->renderInIsolation($construccion);
$mirar('un pedido cerrado conserva el boton de imprimir', str_contains($bloque, '/po/' . $pedido->id() . '/print'));
$mirar('y pierde el de editar', !str_contains($bloque, '/po/draft/' . $pedido->id()));

// La misma pregunta en la lista general, que ademas tiene que haber cambiado de
// palabra en la columna de la entrega sin que nadie guarde nada: no hay campo
// detras, se cuenta de las lineas cada vez.
$lista = $pedir('/supplier-orders');
$celdas = implode(' || ', $fila($lista->getContent(), (int) $pedido->id()));
$mirar('la lista dice que ya esta cerrado', str_contains($celdas, 'Closed'), substr($celdas, 0, 120));
$mirar('y que la entrega esta completa', str_contains($celdas, 'Fully received'));
$mirar('cerrado, la lista conserva el de imprimir', str_contains($celdas, '/po/' . $pedido->id() . '/print'));
$mirar('cerrado, la lista pierde el lapiz', !str_contains($celdas, '/po/draft/' . $pedido->id()));

echo "\n";
echo "Recogiendo\n";
echo str_repeat('-', 78) . "\n";

// Los movimientos, primero fuera de la lista del material y luego borrados, que
// al contrario dejarian una referencia apuntando a la nada.
$movimientos = $gestor->getStorage('tec_inventory')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_inventory_transaction')
  ->condition('field_tec_po_line', [$linea_a->id(), $linea_b->id()], 'IN')
  ->execute();
$borrados = 0;
foreach ([$datos_a['tid'], $datos_b['tid']] as $tid) {
  $material = $termino_storage->loadUnchanged($tid);
  if (!$material->hasField('field_tec_stock_mutations')) {
    continue;
  }
  $quedan = [];
  foreach ($material->get('field_tec_stock_mutations') as $item) {
    if (!in_array((int) $item->target_id, array_map('intval', $movimientos), TRUE)) {
      $quedan[] = ['target_id' => $item->target_id];
    }
  }
  $material->set('field_tec_stock_mutations', $quedan);
  $material->save();
}
foreach ($gestor->getStorage('tec_inventory')->loadMultiple($movimientos) as $movimiento) {
  $movimiento->delete();
  $borrados++;
}
printf("         %d movimientos borrados\n", $borrados);

// Y el stock a como estaba, que el ECA lo ha subido de verdad.
foreach ([$datos_a, $datos_b] as $datos) {
  $material = $termino_storage->loadUnchanged($datos['tid']);
  $material->set('field_tec_stock_level', $datos['stock']);
  $material->save();
}
$mirar('los dos stocks vuelven a como estaban',
  $redondo($stock($datos_a['tid'])) === $redondo($datos_a['stock']) && $redondo($stock($datos_b['tid'])) === $redondo($datos_b['stock']),
  sprintf('%s y %s', $redondo($stock($datos_a['tid'])), $redondo($stock($datos_b['tid'])))
);

$linea_a->delete();
$linea_b->delete();
$pedido->delete();
$pedidos_ahora = (int) $pedido_storage->getQuery()->accessCheck(FALSE)
  ->condition('type', 'tec_purchase_order')->count()->execute();
$mirar('la base queda como estaba', $pedidos_ahora === $pedidos_antes, sprintf('%d pedidos', $pedidos_ahora));
$mensajes();

echo "\n";
echo $problemas === 0
  ? "La recepcion funciona: convierte bien, cierra lo que falta y deja de contar.\n\n"
  : "$problemas cosa(s) mal.\n\n";
