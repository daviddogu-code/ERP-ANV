<?php

/**
 * @file
 * La pantalla de control de compras dice la verdad sobre lo que esta de camino.
 *
 *   php vendor\bin\drush.php scr scripts/lo-que-esta-de-camino-se-ve.php
 *
 * Se montan los cinco casos que se dan de verdad y se comprueba cual sale en
 * pantalla y cual no, que es lo unico que hace util una lista de seguimiento:
 *
 *   A  pedido y sin recibir nada, con fecha prometida ya pasada  -> sale, tarde
 *   B  pedido y recibido a medias                                -> sale
 *   C  recibido entero y sin factura                             -> sale, Delivered
 *   D  recibido entero y con la factura archivada                -> no sale
 *   E  medio escrito, sin cantidades                             -> no sale
 *
 * El D y el E son los dos que importan. El D es la regla del dueno: la fila se
 * va cuando llega el papel, no cuando llega la mercancia. Y el E es la basura
 * que ya hay en la base de datos -pedidos empezados y abandonados- que sin esta
 * regla llenaria la pantalla de filas que no hay que perseguir.
 *
 * Se comprueba ademas que "Delivered" no se pueda teclear: en las filas donde
 * la mercancia esta dentro no hay desplegable, y el campo guardado solo admite
 * los tres estados que una persona si sabe.
 *
 * Al terminar borra lo que ha creado y devuelve el contador de numeros de
 * pedido a donde estaba, para no gastarle numeros a nadie.
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\tec_production\Form\PurchaseQueueForm;
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
 * Las filas de la tabla, indexadas por el numero de pedido que llevan dentro.
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

try {
  echo "\nSe montan los cinco casos\n";
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
  $pedido_de = function (?float $pedido, float $recibido = 0.0) use ($gestor, $material, $proveedor, &$basura, &$lineas, &$movimientos) {
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

  $c = $pedido_de(100, 100);
  $d = $pedido_de(100, 100);
  $e = $pedido_de(NULL);

  // La factura del D. Va al almacen privado, que es donde tienen que ir.
  $dir = 'private://supplier-invoices';
  \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
  $factura = \Drupal::service('file.repository')
    ->writeData('%PDF-1.4 prueba', $dir . '/' . MARCA . '-factura.pdf', FileExists::Replace);
  $factura->setPermanent();
  $factura->save();
  $basura['factura'] = $factura;
  $d->set('field_tec_supplier_invoice', ['target_id' => $factura->id()])->save();

  foreach ([['A', $a, 'none'], ['B', $b, 'partial'], ['C', $c, 'full'], ['D', $d, 'full'], ['E', $e, 'cancelled']] as [$cual, $pedido, $esperado]) {
    $real = Purchasing::receiptState($pedido);
    $mirar("el $cual es '$esperado'", $real === $esperado, $pedido->label() . ', ' . $real);
  }

  echo "\nQuien sale en la pantalla y quien no\n";
  echo str_repeat('-', 78) . "\n";

  $respuesta = $pedir(PANTALLA);
  $mirar('la pantalla abre', $respuesta->getStatusCode() === 200, 'estado ' . $respuesta->getStatusCode());
  $html = (string) $respuesta->getContent();
  $tabla = $filas($html);

  foreach ([['A', $a], ['B', $b], ['C', $c]] as [$cual, $pedido]) {
    $mirar("el $cual sale", $fila_de($tabla, $pedido->label()) !== NULL, $pedido->label());
  }
  $mirar('el D no sale, que ya tiene factura', $fila_de($tabla, $d->label()) === NULL, $d->label());
  $mirar('el E no sale, que no hay nada que perseguir', $fila_de($tabla, $e->label()) === NULL, $e->label());

  echo "\nLo que dice cada fila\n";
  echo str_repeat('-', 78) . "\n";

  $fa = $fila_de($tabla, $a->label());
  $fb = $fila_de($tabla, $b->label());
  $fc = $fila_de($tabla, $c->label());

  if ($fa && $fb && $fc) {
    $mirar('el A va tarde y se marca', str_contains($fa['nodo']->getAttribute('class'), 'tec-pq__row--late'));
    $mirar('el B no va tarde', !str_contains($fb['nodo']->getAttribute('class'), 'tec-pq__row--late'));

    $mirar('el A se puede mover a mano', $tiene($fa, './/select'));
    $mirar('el B tambien', $tiene($fb, './/select'));
    $mirar('el C ya no: la mercancia esta dentro', !$tiene($fc, './/select'));
    $mirar('y lo dice con su pastilla', $tiene($fc, './/span[contains(@class,"tec-pq__pill--delivered")]'));

    $mirar('el A no tiene fecha de llegada', str_contains($fa['texto'], '—'));
    $mirar('el C si la tiene', str_contains($fc['texto'], date('j M Y')), date('j M Y'));

    $mirar('el proveedor sale en las tres', str_contains($fa['texto'], MARCA . ' proveedor')
      && str_contains($fb['texto'], MARCA . ' proveedor')
      && str_contains($fc['texto'], MARCA . ' proveedor'));
    $mirar('y el numero entero, no solo el 26-00x', str_contains($fa['texto'], 'ZZCL '), $a->label());

    $mirar('la fecha prometida se teclea en la propia tabla', $tiene($fa, './/input[@type="date"]'));
    $mirar('y el C tambien la admite, que aun puede haber papeleo', $tiene($fc, './/input[@type="date"]'));

    $mirar('cada fila ofrece archivar su factura', $tiene($fa, './/a[contains(@class,"tec-pq__invoice")]'));
    $mirar('y se abre en una ventanita', $tiene($fa, './/a[@data-dialog-type="modal"]'));
  }
  else {
    $mirar('las tres filas estan para poder mirarlas', FALSE);
  }

  echo "\nLas columnas y su orden\n";
  echo str_repeat('-', 78) . "\n";

  $dom = new DOMDocument();
  @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
  $xp = new DOMXPath($dom);
  $cabeceras = [];
  foreach ($xp->query('//table[contains(@class,"tec-pq__table")]//thead//th') as $th) {
    $cabeceras[] = trim(preg_replace('/\s+/u', ' ', $th->textContent));
  }
  $esperadas = ['Order #', 'Suppliers Name', 'Status', 'Created', 'Created By', 'Expected delivery day', 'Delivered date', 'Days', 'Invoice'];
  $mirar('estan las nueve columnas de la hoja, en su orden', $cabeceras === $esperadas, implode(' | ', $cabeceras));

  $ordenables = $xp->query('//table[contains(@class,"tec-pq__table")]//thead//th/a')->length;
  $mirar('ocho se pueden ordenar', $ordenables === 8, $ordenables . ' de 8');

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
  $mirar('al revés se dan la vuelta',
    isset($bajando['A'], $bajando['B']) && $bajando['B'] < $bajando['A']);
  $mirar('pero el que no tiene fecha sigue abajo',
    isset($bajando['C']) && $bajando['C'] > max($bajando['A'] ?? 0, $bajando['B'] ?? 0));

  $por_nombre = $orden_de('?order=supplier&sort=asc');
  $mirar('y ordenar por proveedor no pierde a nadie', count($por_nombre) === 3, count($por_nombre) . ' de 3');

  echo "\nGuardar lo que se teclea en la tabla\n";
  echo str_repeat('-', 78) . "\n";

  $estado = new FormState();
  $estado->setValues([
    'table' => [
      $a->id() => ['status' => 'ready_to_pick_up', 'expected' => '2026-12-24'],
      // El C no manda estado porque en pantalla no tiene desplegable. Si algun
      // dia lo mandara, tampoco se guardaria.
      $c->id() => ['status' => 'on_process', 'expected' => ''],
    ],
  ]);
  $formulario = [];
  PurchaseQueueForm::create(\Drupal::getContainer())->submitForm($formulario, $estado);

  $a = $gestor->getStorage('tec_order')->loadUnchanged($a->id());
  $c = $gestor->getStorage('tec_order')->loadUnchanged($c->id());
  $mirar('el A se ha movido a Ready to pick up',
    $a->get('field_tec_po_queue_status')->value === 'ready_to_pick_up',
    (string) $a->get('field_tec_po_queue_status')->value);
  $mirar('y ha cogido la fecha nueva',
    $a->get('field_tec_expected_delivery')->value === '2026-12-24',
    (string) $a->get('field_tec_expected_delivery')->value);
  $mirar('el C sigue leyendose Delivered pese al envio',
    Purchasing::receiptState($c) === 'full');

  echo "\nDelivered no se puede teclear en ningun sitio\n";
  echo str_repeat('-', 78) . "\n";

  $permitidos = \Drupal::service('entity_field.manager')
    ->getFieldStorageDefinitions('tec_order')['field_tec_po_queue_status']
    ->getSetting('allowed_values');
  $mirar('el campo guardado solo admite los tres de mano',
    !array_key_exists('delivered', $permitidos), implode(', ', array_keys($permitidos)));
  $mirar('y la pantalla ofrece esos tres',
    array_keys(PurchaseQueueForm::STATES) === array_keys($permitidos));

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
