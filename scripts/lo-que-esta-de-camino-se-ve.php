<?php

/**
 * @file
 * La pantalla de control de compras dice la verdad sobre lo que esta de camino.
 *
 *   php vendor\bin\drush.php scr scripts/lo-que-esta-de-camino-se-ve.php
 *
 * Se montan los siete casos que se dan de verdad y se comprueba que dice la
 * pantalla de cada uno, que es lo unico que hace util una lista de seguimiento:
 *
 *   A  pedido y sin recibir nada, con la fecha prometida pasada  -> sale, Open
 *   B  pedido y recibido a medias                                -> sale
 *   C  recibido entero y sin factura                             -> sale, Delivered
 *   D  recibido entero y con la factura archivada                -> no sale, Closed
 *   E  medio escrito, sin cantidades, abierto                    -> no sale, Open
 *   F  cerrado sin que llegase nada                              -> no sale, Cancelled
 *   G  hecho hace tres dias a las once de la manana              -> sale, y dice 3
 *
 * El D y el E son los dos que importan para la lista. El D es la regla del
 * dueno: la fila se va cuando llega el papel, no cuando llega la mercancia. Y
 * el E es la basura que ya hay en la base de datos -pedidos empezados y
 * abandonados- que sin esa regla llenaria la pantalla de filas que no hay que
 * perseguir.
 *
 * El E y el F son el par que importa para la palabra. Los dos se leen igual
 * desde las lineas -nada pendiente y nada recibido- y son cosas distintas: uno
 * es un borrador de esta manana y el otro un pedido que alguien dio por
 * perdido. La bandera de abierto o cerrado es lo unico que los separa.
 *
 * El G es la resta de los dias, que estuvo mal un dia entero: mezclaba la
 * medianoche de hoy con la hora exacta en que se hizo el pedido y se dejaba un
 * dia por el camino.
 *
 * Se comprueba ademas que "Delivered" no se pueda teclear -en las filas donde
 * la mercancia esta dentro no hay desplegable, y el campo guardado solo admite
 * los tres estados que una persona si sabe- y que las cuatro pantallas que
 * ensenan un estado ensenen el mismo.
 *
 * Al terminar borra lo que ha creado y devuelve el contador de numeros de
 * pedido a donde estaba, para no gastarle numeros a nadie.
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\tec_production\Controller\PurchaseQueueController;
use Drupal\tec_production\OrderNumber;
use Drupal\tec_production\Purchasing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZCOLA';
const PANTALLA = '/supplier-orders/queue';

$gestor = \Drupal::entityTypeManager();
$nucleo = \Drupal::service('http_kernel');
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));
$sesion = \Drupal::service('session');

$problemas = 0;
$basura = [];
$cuaderno = \Drupal::keyValue(OrderNumber::LEDGER);
$antes = $cuaderno->getAll();

$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$pedir = function (string $ruta) use ($nucleo, $sesion) {
  $peticion = Request::create($ruta);
  $peticion->setSession($sesion);
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
    // Una subpeticion sin capturar lanza el 403 en vez de devolverlo, y aqui lo
    // que se esta probando es justo que unas puertas esten cerradas.
    return new \Symfony\Component\HttpFoundation\Response('', $e->getStatusCode());
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO al pedir %s: %s\n      %s\n", $ruta, get_class($e), $e->getMessage());
    return new \Symfony\Component\HttpFoundation\Response('', 500);
  }
};

/**
 * Las filas de la tabla, con su nodo para poder mirarlas por dentro.
 */
$filas = function (string $html): array {
  $dom = new DOMDocument();
  @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
  $xp = new DOMXPath($dom);
  $salida = [];
  foreach ($xp->query('//table[contains(@class,"tec-pq__table")]/tbody/tr') as $tr) {
    $salida[] = ['nodo' => $tr, 'texto' => preg_replace('/\s+/u', ' ', $tr->textContent), 'xp' => $xp];
  }
  return $salida;
};

$fila_de = function (array $filas, string $etiqueta): ?array {
  foreach ($filas as $fila) {
    if (str_contains($fila['texto'], $etiqueta)) {
      return $fila;
    }
  }
  return NULL;
};

$tiene = fn(array $fila, string $xpath): bool => $fila['xp']->query($xpath, $fila['nodo'])->length > 0;
$texto_de = function (array $fila, string $xpath): string {
  $encontrado = $fila['xp']->query($xpath, $fila['nodo']);
  return $encontrado->length ? trim($encontrado->item(0)->textContent) : '';
};

try {
  echo "\nSe montan los siete casos\n";
  echo str_repeat('-', 78) . "\n";

  $proveedor = $gestor->getStorage('tec_crm')->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' proveedor',
    'name' => MARCA . ' proveedor',
    'field_tec_customer_code' => 'ZZCL',
    'field_tec_contact_type' => [TEC_CRM_UX_TYPE_SUPPLIER],
    'field_tec_address' => [['country_code' => 'TH']],
  ]);
  $proveedor->save();
  $basura['proveedor'] = $proveedor;

  $material = $gestor->getStorage('taxonomy_term')->create([
    'vid' => 'tec_inventory',
    'name' => MARCA . ' material',
    'field_tec_cost' => 10,
    'field_tec_units' => 1,
    'field_tec_split_into' => 1,
  ]);
  $material->save();
  $basura['material'] = $material;

  $lineas = [];
  $movimientos = [];

  /**
   * Un pedido de una linea, con lo pedido y lo recibido que se le diga.
   */
  $pedido_de = function (?float $pedido, float $recibido = 0.0, string $estado = 'open') use ($gestor, $material, $proveedor, &$basura, &$lineas, &$movimientos) {
    $items = [];
    if ($pedido !== NULL) {
      $linea = $gestor->getStorage('tec_line_item')->create([
        'type' => 'tec_po_line_item',
        'field_tec_inventory' => $material->id(),
        'field_tec_quantity' => $pedido,
        'field_tec_price' => 10,
        'field_tec_quantity_received' => $recibido,
        'field_tec_vendor' => $proveedor->id(),
        'uid' => 1,
      ]);
      $linea->save();
      $lineas[] = $linea;
      $items[] = $linea->id();
    }

    $orden = $gestor->getStorage('tec_order')->create([
      'type' => 'tec_purchase_order',
      'field_tec_vendor' => $proveedor->id(),
      'field_tec_line_items' => $items,
      'field_tec_po_status' => $estado,
      'uid' => 1,
    ]);
    $orden->save();
    $basura['pedido_' . $orden->id()] = $orden;

    // Recibir mercancia deja un movimiento de almacen, y es de ahi de donde la
    // pantalla saca la fecha de llegada. Sin el movimiento no hay fecha, que es
    // exactamente lo que se quiere probar.
    if ($recibido > 0.0) {
      $movimiento = $gestor->getStorage('tec_inventory')->create([
        'type' => 'tec_inventory_transaction',
        'title' => MARCA . ' entrada ' . $orden->label(),
        'uid' => 1,
        'field_tec_inventory' => $material->id(),
        'field_tec_quantity' => $recibido,
        'field_tec_po_line' => $linea->id(),
      ]);
      $movimiento->save();
      $movimientos[] = $movimiento;
    }

    return $orden;
  };

  $a = $pedido_de(100, 0);
  $a->set('field_tec_expected_delivery', date('Y-m-d', strtotime('-6 days')))->save();

  $b = $pedido_de(100, 40);
  $b->set('field_tec_po_queue_status', 'on_the_way')
    ->set('field_tec_expected_delivery', date('Y-m-d', strtotime('+10 days')))
    ->save();

  // Recibir del todo es lo que cierra un pedido, asi que el C y el D nacen
  // cerrados: es como los dejaria el formulario de recibir.
  $c = $pedido_de(100, 100, Purchasing::CLOSED_STATUS);
  $d = $pedido_de(100, 100, Purchasing::CLOSED_STATUS);
  $e = $pedido_de(NULL);
  $f = $pedido_de(NULL, 0.0, Purchasing::CLOSED_STATUS);

  // Hace tres dias a las once de la manana. La hora es el detalle que rompia la
  // resta: contra la medianoche de hoy salen dos dias y pico, y el suelo se
  // comia el pico.
  $g = $pedido_de(100, 0);
  $hace_tres = strtotime('today -3 days') + 11 * 3600;
  $g->set('created', $hace_tres)->save();

  // La factura del D. Va al almacen privado, que es donde tienen que ir.
  $dir = 'private://supplier-invoices';
  \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
  $factura = \Drupal::service('file.repository')
    ->writeData('%PDF-1.4 prueba', $dir . '/' . MARCA . '-factura.pdf', FileExists::Replace);
  $factura->setPermanent();
  $factura->save();
  $basura['factura'] = $factura;
  $d->set('field_tec_supplier_invoice', ['target_id' => $factura->id()])->save();

  echo "\nLa lectura unica: siete casos, siete palabras\n";
  echo str_repeat('-', 78) . "\n";

  $lecturas = [
    ['A', $a, 'open', 'nadie lo ha tocado todavia'],
    ['B', $b, 'on_the_way', 'alguien dijo que venia de camino'],
    ['C', $c, 'delivered', 'llego entero y falta el papel'],
    ['D', $d, 'closed', 'llego entero y el papel esta'],
    ['E', $e, 'open', 'un borrador sin cantidades sigue abierto'],
    ['F', $f, 'cancelled', 'cerrado sin que llegase nada'],
  ];
  foreach ($lecturas as [$cual, $pedido, $esperado, $porque]) {
    $real = Purchasing::progress($pedido);
    $mirar("el $cual es '$esperado', $porque", $real === $esperado, $real);
  }

  $mirar('el E y el F se leen igual desde las lineas',
    Purchasing::receiptState($e) === Purchasing::receiptState($f),
    'los dos ' . Purchasing::receiptState($e));
  $mirar('y aun asi dicen cosas distintas',
    Purchasing::progress($e) !== Purchasing::progress($f),
    Purchasing::progress($e) . ' contra ' . Purchasing::progress($f));

  echo "\nQuien sale en la pantalla y quien no\n";
  echo str_repeat('-', 78) . "\n";

  $respuesta = $pedir(PANTALLA);
  $mirar('la pantalla abre', $respuesta->getStatusCode() === 200, 'estado ' . $respuesta->getStatusCode());
  $html = (string) $respuesta->getContent();
  $tabla = $filas($html);

  foreach ([['A', $a], ['B', $b], ['C', $c], ['G', $g]] as [$cual, $pedido]) {
    $mirar("el $cual sale", $fila_de($tabla, $pedido->label()) !== NULL, $pedido->label());
  }
  $mirar('el D no sale, que ya tiene factura', $fila_de($tabla, $d->label()) === NULL, $d->label());
  $mirar('el E no sale, que no hay nada que perseguir', $fila_de($tabla, $e->label()) === NULL, $e->label());
  $mirar('el F tampoco, que se dio por perdido', $fila_de($tabla, $f->label()) === NULL, $f->label());

  echo "\nLos dias son dias de calendario\n";
  echo str_repeat('-', 78) . "\n";

  $fg = $fila_de($tabla, $g->label());
  if ($fg) {
    $dias = $texto_de($fg, './/span[contains(@class,"tec-pq__days")]');
    $mirar('el de hace tres dias dice 3, no 2', $dias === '3', $dias);
  }
  else {
    $mirar('el G esta para poder mirarlo', FALSE);
  }
  $fa = $fila_de($tabla, $a->label());
  if ($fa) {
    $mirar('y el de hoy dice 0', $texto_de($fa, './/span[contains(@class,"tec-pq__days")]') === '0');
  }

  echo "\nLo que dice cada fila\n";
  echo str_repeat('-', 78) . "\n";

  $fb = $fila_de($tabla, $b->label());
  $fc = $fila_de($tabla, $c->label());

  if ($fa && $fb && $fc) {
    $mirar('el A va tarde y se marca', str_contains($fa['nodo']->getAttribute('class'), 'tec-pq__row--late'));
    $mirar('el B no va tarde', !str_contains($fb['nodo']->getAttribute('class'), 'tec-pq__row--late'));

    $mirar('el A arranca en Open',
      $texto_de($fa, './/button[contains(@class,"tec-pq__trigger")]') === 'Open',
      $texto_de($fa, './/button[contains(@class,"tec-pq__trigger")]'));
    $mirar('el A se puede abrir para elegir', $tiene($fa, './/div[contains(@class,"tec-pq__menu")]'));
    $mirar('y ofrece los tres escalones y nada mas',
      $fa['xp']->query('.//div[contains(@class,"tec-pq__opt")]', $fa['nodo'])->length === 3);
    $mirar('el B tambien se puede mover', $tiene($fb, './/button[contains(@class,"tec-pq__trigger")]'));
    $mirar('el C ya no: la mercancia esta dentro', !$tiene($fc, './/button[contains(@class,"tec-pq__trigger")]'));
    $mirar('y lo dice con su pastilla',
      $tiene($fc, './/span[contains(@class,"tec-po-status--delivered")]'));

    $mirar('no queda ni un desplegable de los de antes', !$tiene($fa, './/select'));

    $mirar('la columna Delivery va detras de Status',
      $tiene($fa, './/td[3]//div[contains(@class,"tec-pq__menu")]')
      && $tiene($fa, './/td[4]//span[contains(@class,"tec-receipt")]'));
    $mirar('y dice que al A no ha llegado nada',
      str_contains($texto_de($fa, './/td[4]'), 'Nothing received'), $texto_de($fa, './/td[4]'));
    $mirar('y que al B le ha llegado parte',
      str_contains($texto_de($fb, './/td[4]'), 'Partially received'), $texto_de($fb, './/td[4]'));

    $mirar('el A no tiene fecha de llegada', str_contains($fa['texto'], '—'));
    $mirar('el C si la tiene', str_contains($fc['texto'], date('j M Y')), date('j M Y'));

    $mirar('el proveedor lleva al proveedor',
      $tiene($fa, './/a[contains(@class,"tec-pq__supplier")]'));
    $mirar('y a su ficha, no a otro sitio',
      $texto_de($fa, './/a[contains(@class,"tec-pq__supplier")]') === MARCA . ' proveedor'
      && str_contains($fa['xp']->query('.//a[contains(@class,"tec-pq__supplier")]/@href', $fa['nodo'])->item(0)->nodeValue, (string) $proveedor->id()));
    $mirar('y el numero entero, no solo el 26-00x', str_contains($fa['texto'], 'ZZCL '), $a->label());

    $mirar('y se abre al lado, sin perder la lista',
      $tiene($fa, './/a[contains(@class,"tec-pq__supplier") and @target="_blank"]'));
    $mirar('el numero del pedido tambien se abre al lado',
      $tiene($fa, './/td[contains(@class,"tec-pq__order")]/a[@target="_blank"]'));

    $mirar('la fecha prometida se teclea en la propia tabla', $tiene($fa, './/input[@type="date"]'));
    $mirar('y el C tambien la admite, que aun puede haber papeleo', $tiene($fc, './/input[@type="date"]'));

    $mirar('el A ofrece recibir sin salir de la lista',
      $tiene($fa, './/a[contains(@class,"tec-pq__receive")]'));
    $mirar('y el C no, que esta cerrado y no hay nada que recibir',
      !$tiene($fc, './/a[contains(@class,"tec-pq__receive")]'));

    // Recibir es lo unico que no se abre al lado: cambia esta misma pantalla, y
    // una segunda pestana dejaria la de delante contando lo de antes.
    $vuelta = $fa['xp']->query('.//a[contains(@class,"tec-pq__receive")]/@href', $fa['nodo']);
    $vuelta = $vuelta->length ? urldecode($vuelta->item(0)->nodeValue) : '';
    $mirar('recibir vuelve aqui al terminar, no a la ficha',
      str_contains($vuelta, 'destination=' . PANTALLA), $vuelta);
    $mirar('y no se va a otra pestana, que esta pantalla cambia al recibir',
      !$tiene($fa, './/a[contains(@class,"tec-pq__receive") and @target]'));

    $mirar('cada fila ofrece archivar su factura', $tiene($fa, './/a[contains(@class,"tec-pq__invoice")]'));
    $mirar('y se abre en una ventanita', $tiene($fa, './/a[@data-dialog-type="modal"]'));
  }
  else {
    $mirar('las tres filas estan para poder mirarlas', FALSE);
  }

  echo "\nLas dos salidas del formulario de recibir\n";
  echo str_repeat('-', 78) . "\n";

  // Guardar vuelve a la cola porque Drupal hace caso al destino por encima del
  // redirect del formulario. Cancelar es un enlace y no se entera solo, asi que
  // se le dice: si no, las dos puertas de la misma pantalla salen a sitios
  // distintos y el que cambia de idea acaba en otro lado que el que recibe.
  $salida_de = function (string $ruta) use ($pedir): string {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . (string) $pedir($ruta)->getContent());
    $xp = new DOMXPath($dom);
    foreach ($xp->query('//*[contains(@class,"form-actions")]//a') as $enlace) {
      if (trim($enlace->textContent) === 'Cancel') {
        return $enlace->getAttribute('href');
      }
    }
    return '';
  };

  $recibir = '/tec_order/' . $a->id() . '/receive';
  $ficha = '/tec_order/' . $a->id();

  $desde_cola = $salida_de($recibir . '?destination=' . PANTALLA);
  $mirar('llegando desde la cola, Cancel devuelve a la cola',
    $desde_cola === PANTALLA, $desde_cola);
  // Y con la ordenacion puesta: volver a la cola ordenada de otra manera que la
  // que se estaba mirando es casi tan molesto como no volver.
  $ordenada = PANTALLA . '?order=created&sort=desc';
  $desde_orden = $salida_de($recibir . '?destination=' . urlencode($ordenada));
  $mirar('y vuelve a la cola ordenada como estaba, no a la de por defecto',
    $desde_orden !== '' && str_contains(urldecode($desde_orden), 'order=created')
    && str_contains(urldecode($desde_orden), 'sort=desc'), $desde_orden);

  $desde_ficha = $salida_de($recibir);
  $mirar('y llegando desde la ficha, Cancel devuelve a la ficha',
    $desde_ficha !== '' && str_contains($desde_ficha, $ficha), $desde_ficha);
  $de_fuera = $salida_de($recibir . '?destination=http://ejemplo.invalido/');
  $mirar('y un destino de fuera de casa no se sigue',
    $de_fuera !== '' && str_contains($de_fuera, $ficha), $de_fuera);

  echo "\nLas columnas y su orden\n";
  echo str_repeat('-', 78) . "\n";

  $dom = new DOMDocument();
  @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
  $xp = new DOMXPath($dom);
  $cabeceras = [];
  foreach ($xp->query('//table[contains(@class,"tec-pq__table")]//thead//th') as $th) {
    $cabeceras[] = trim(preg_replace('/\s+/u', ' ', $th->textContent));
  }
  $esperadas = ['Order #', 'Suppliers Name', 'Status', 'Delivery', 'Created', 'Created By', 'Expected delivery day', 'Delivered date', 'Days', 'Receive', 'Invoice'];
  $mirar('estan las once columnas, en su orden', $cabeceras === $esperadas, implode(' | ', $cabeceras));

  $ordenables = $xp->query('//table[contains(@class,"tec-pq__table")]//thead//th/a')->length;
  $mirar('nueve se pueden ordenar', $ordenables === 9, $ordenables . ' de 9');

  $mirar('y no hay boton de guardar en ninguna parte',
    $xp->query('//input[@type="submit"] | //button[@type="submit"]')->length === 0);

  echo "\nOrdenar por una columna que no esta guardada en ninguna parte\n";
  echo str_repeat('-', 78) . "\n";

  // Los dias no son un campo: son una resta. Con Views esta columna no se
  // podria ordenar, y es la que dice a quien hay que llamar primero.
  $orden_de = function (string $consulta) use ($pedir, $filas, $a, $b, $c) {
    $tabla = $filas((string) $pedir(PANTALLA . $consulta)->getContent());
    $posiciones = [];
    foreach ($tabla as $i => $fila) {
      foreach (['A' => $a, 'B' => $b, 'C' => $c] as $cual => $pedido) {
        if (str_contains($fila['texto'], $pedido->label())) {
          $posiciones[$cual] = $i;
        }
      }
    }
    return $posiciones;
  };

  $subiendo = $orden_de('?order=expected&sort=asc');
  $mirar('por fecha prometida, el atrasado va primero',
    isset($subiendo['A'], $subiendo['B']) && $subiendo['A'] < $subiendo['B']);
  $mirar('y el que no tiene fecha se hunde',
    isset($subiendo['C']) && $subiendo['C'] > max($subiendo['A'] ?? 0, $subiendo['B'] ?? 0));

  $bajando = $orden_de('?order=expected&sort=desc');
  $mirar('al reves se dan la vuelta',
    isset($bajando['A'], $bajando['B']) && $bajando['B'] < $bajando['A']);
  $mirar('pero el que no tiene fecha sigue abajo',
    isset($bajando['C']) && $bajando['C'] > max($bajando['A'] ?? 0, $bajando['B'] ?? 0));

  $por_nombre = $orden_de('?order=supplier&sort=asc');
  $mirar('y ordenar por proveedor no pierde a nadie', count($por_nombre) === 3, count($por_nombre) . ' de 3');

  $por_entrega = $orden_de('?order=delivery&sort=asc');
  $mirar('la columna nueva tambien ordena', count($por_entrega) === 3, count($por_entrega) . ' de 3');

  echo "\nGuardar sin boton de guardar\n";
  echo str_repeat('-', 78) . "\n";

  $mandar = function (array $carga) use ($gestor) {
    $peticion = Request::create('/supplier-orders/queue/save', 'POST', [], [], [], [], json_encode($carga));
    $respuesta = PurchaseQueueController::create(\Drupal::getContainer())->save($peticion);
    return [$respuesta->getStatusCode(), json_decode($respuesta->getContent(), TRUE)];
  };

  [$codigo, $cuerpo] = $mandar(['order' => (int) $a->id(), 'field' => 'status', 'value' => 'ready_to_pick_up']);
  $a = $gestor->getStorage('tec_order')->loadUnchanged($a->id());
  $mirar('mover el estado responde que si', $codigo === 200 && !empty($cuerpo['ok']), 'estado ' . $codigo);
  $mirar('y queda guardado', $a->get('field_tec_po_queue_status')->value === 'ready_to_pick_up',
    (string) $a->get('field_tec_po_queue_status')->value);
  $mirar('y contesta con la palabra que toca', ($cuerpo['label'] ?? '') === 'Ready to pick up', (string) ($cuerpo['label'] ?? ''));

  [$codigo] = $mandar(['order' => (int) $a->id(), 'field' => 'expected', 'value' => '2026-12-24']);
  $a = $gestor->getStorage('tec_order')->loadUnchanged($a->id());
  $mirar('la fecha prometida tambien se guarda sola',
    $codigo === 200 && $a->get('field_tec_expected_delivery')->value === '2026-12-24',
    (string) $a->get('field_tec_expected_delivery')->value);

  [$codigo] = $mandar(['order' => (int) $a->id(), 'field' => 'expected', 'value' => 'manana']);
  $mirar('y una fecha inventada se rechaza', $codigo === 400, 'estado ' . $codigo);

  [$codigo] = $mandar(['order' => (int) $a->id(), 'field' => 'status', 'value' => 'delivered']);
  $mirar('nadie puede teclear Delivered por la puerta de atras', $codigo === 400, 'estado ' . $codigo);

  // El C ya tiene la mercancia dentro. Aunque alguien fabrique el envio, lo que
  // contesta el servidor es la verdad, no lo que se le mando.
  [$codigo, $cuerpo] = $mandar(['order' => (int) $c->id(), 'field' => 'status', 'value' => 'on_process']);
  $mirar('y a un pedido servido el servidor le contesta Delivered',
    $codigo === 200 && ($cuerpo['progress'] ?? '') === 'delivered', (string) ($cuerpo['progress'] ?? ''));

  $ruta = \Drupal::service('router.route_provider')->getRouteByName('tec_production.purchase_queue_save');
  $mirar('la puerta de guardar pide su llave',
    $ruta->getRequirement('_csrf_request_header_token') === 'TRUE'
    && $ruta->getRequirement('_permission') === 'access tec purchase queue');

  echo "\nDelivered no se puede teclear en ningun sitio\n";
  echo str_repeat('-', 78) . "\n";

  $permitidos = \Drupal::service('entity_field.manager')
    ->getFieldStorageDefinitions('tec_order')['field_tec_po_queue_status']
    ->getSetting('allowed_values');
  $mirar('el campo guardado solo admite los tres de mano',
    !array_key_exists('delivered', $permitidos), implode(', ', array_keys($permitidos)));
  $mirar('y son los mismos tres que ofrece la pantalla',
    Purchasing::PROGRESS_MANUAL === array_keys($permitidos));
  $mirar('y el campo ya no viene relleno de fabrica',
    !\Drupal\field\Entity\FieldConfig::loadByName('tec_order', 'tec_purchase_order', 'field_tec_po_queue_status')->getDefaultValueLiteral());

  echo "\nLas cuatro pantallas dicen lo mismo\n";
  echo str_repeat('-', 78) . "\n";

  foreach ([
    ['views.view.tec_supplier_orders', 'default'],
    ['views.view.tec_supplier_orders', 'block_3'],
    ['views.view.tec_orders_orders', 'block_3'],
  ] as [$vista, $display]) {
    $campos = \Drupal::config($vista)->get("display.$display.display_options.fields") ?? [];
    $mirar("$vista / $display lee el estado deducido",
      ($campos['tec_progress']['plugin_id'] ?? '') === 'tec_purchase_progress'
      && empty($campos['field_tec_po_status']),
      isset($campos['field_tec_po_status']) ? 'SIGUE CON EL CAMPO CRUDO' : 'tec_progress');
  }

  // La ficha va por Layout Builder, que guarda sus bloques en secciones y no en
  // el contenido del display, asi que hay que preguntarle donde los tiene.
  $bloques = [];
  foreach (\Drupal::service('entity_display.repository')
    ->getViewDisplay('tec_order', 'tec_purchase_order', 'default')
    ->getSections() as $seccion) {
    foreach ($seccion->getComponents() as $bloque) {
      $bloques[] = $bloque->getPluginId();
    }
  }
  $mirar('la ficha coloca el estado deducido y no el campo crudo',
    in_array('extra_field_block:tec_order:tec_purchase_order:tec_progress', $bloques, TRUE)
    && !in_array('field_block:tec_order:tec_purchase_order:field_tec_po_status', $bloques, TRUE));

  $impreso = \Drupal::service('entity_display.repository')
    ->getViewDisplay('tec_order', 'tec_purchase_order', 'pdf');
  $mirar('el impreso tambien, y ya no se puede escribir dentro',
    $impreso->getComponent('tec_progress') !== NULL
    && $impreso->getComponent('field_tec_po_status') === NULL);

  $lista = (string) $pedir('/supplier-orders')->getContent();
  $mirar('la lista de proveedores lo pinta con la misma pastilla',
    str_contains($lista, 'tec-po-status--'));

  // Y dibujada de verdad: un campo anadido que nadie construye deja en su sitio
  // el texto de relleno que pone Layout Builder, y eso saldria en la ficha.
  $ficha = (string) $pedir('/tec_order/' . $a->id())->getContent();
  $mirar('y la ficha del pedido tambien, sin dejarse un hueco',
    str_contains($ficha, 'tec-po-status--') && !str_contains($ficha, 'Placeholder for the'));

  echo "\nLa ventanita de la factura\n";
  echo str_repeat('-', 78) . "\n";

  $respuesta = $pedir('/tec_order/' . $a->id() . '/invoice');
  $mirar('se abre para un pedido de compra', $respuesta->getStatusCode() === 200, 'estado ' . $respuesta->getStatusCode());
  $ventana = (string) $respuesta->getContent();
  $mirar('acepta pdf y fotos', str_contains($ventana, '.pdf,image/*'));
  $mirar('y avisa de que al A todavia le falta mercancia',
    str_contains($ventana, 'stays on the screen'));

  // Un pedido de venta no tiene facturas de proveedor, y la ruta lo sabe.
  $venta = $gestor->getStorage('tec_order')->getQuery()
    ->condition('type', 'tec_sales_order')
    ->range(0, 1)
    ->accessCheck(FALSE)
    ->execute();
  if ($venta) {
    $codigo = $pedir('/tec_order/' . reset($venta) . '/invoice')->getStatusCode();
    $mirar('y no se abre para un pedido de venta', $codigo === 403, 'estado ' . $codigo);
  }

  echo "\nLa fecha de llegada sale del almacen y de ningun campo\n";
  echo str_repeat('-', 78) . "\n";

  $fechas = Purchasing::deliveredOn($gestor, [$a, $b, $c]);
  $mirar('el A no tiene, que no ha llegado nada', !isset($fechas[$a->id()]));
  $mirar('el B si, aunque solo haya llegado parte', isset($fechas[$b->id()]));
  $mirar('y el C tambien', isset($fechas[$c->id()]));
  $mirar('las fechas son de hoy', isset($fechas[$c->id()])
    && date('Y-m-d', $fechas[$c->id()]) === date('Y-m-d'));
}
finally {
  foreach (array_reverse($movimientos ?? []) as $ficha) {
    $basura['mov_' . $ficha->id()] = $ficha;
  }
  foreach (array_reverse($lineas ?? []) as $ficha) {
    $basura['linea_' . $ficha->id()] = $ficha;
  }
  // Los pedidos primero, que son los que apuntan a lo demas.
  uksort($basura, fn($x, $y) => (str_starts_with($y, 'pedido_') <=> str_starts_with($x, 'pedido_')));
  foreach ($basura as $ficha) {
    try {
      $ficha->delete();
    }
    catch (\Throwable $e) {
      printf("  no se ha podido borrar %s %s: %s\n", $ficha->getEntityTypeId(), $ficha->id(), $e->getMessage());
    }
  }
  $cuaderno->deleteAll();
  $cuaderno->setMultiple($antes);
}

echo "\n";
echo $problemas === 0
  ? "La pantalla ensena lo que hay que perseguir, y solo eso.\n\n"
  : "$problemas cosa(s) mal.\n\n";
