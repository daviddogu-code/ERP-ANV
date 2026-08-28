<?php

/**
 * @file
 * El confirmar del portal: cantidades mientras Open, luego el candado.
 *
 *   php vendor/bin/drush.php scr scripts/el-confirmar-del-portal.php
 *
 * Paso 6. El cliente cambia cantidades, pulsa Confirm, y el pedido pasa a
 * Pending deposit. Eso no es Accounting Verified. Despues ya no edita.
 * B no confirma el de A. Los contadores de numero de pedido se restauran.
 */

use Drupal\Core\Form\FormState;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\Form\OrderForm;
use Drupal\tec_portal\Form\PlaceOrderForm;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_production\Form\ProductionQueueForm;
use Drupal\tec_production\OrderNumber;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZCONFIRM';

$gestor = \Drupal::entityTypeManager();
$cambiador = \Drupal::service('account_switcher');
$kernel = \Drupal::service('http_kernel');
$problemas = 0;
$basura = [];
$ledger = \Drupal::keyValue(OrderNumber::LEDGER);
$ledgerAntes = $ledger->getAll();

$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$guardar = function ($entidad) use (&$basura) {
  $entidad->save();
  $basura[] = $entidad;
  return $entidad;
};

$barrer = function () use (&$basura, $ledger, $ledgerAntes): void {
  foreach (array_reverse($basura) as $entidad) {
    try {
      $entidad->delete();
    }
    catch (\Throwable $e) {
    }
  }
  $basura = [];
  $gestor = \Drupal::entityTypeManager();
  foreach ($gestor->getStorage('user')->loadByProperties([]) as $user) {
    if (str_starts_with((string) $user->getAccountName(), MARCA)) {
      $user->delete();
    }
  }
  foreach (['tec_line_item', 'tec_order', 'tec_product', 'tec_crm'] as $tipo) {
    foreach ($gestor->getStorage($tipo)->loadMultiple() as $entidad) {
      if (str_starts_with((string) $entidad->label(), MARCA)) {
        $entidad->delete();
      }
    }
  }
  foreach ($gestor->getStorage('taxonomy_term')->loadByProperties(['vid' => 'tec_brands']) as $termino) {
    if (str_starts_with((string) $termino->label(), MARCA)) {
      $termino->delete();
    }
  }
  $ledger->deleteAll();
  foreach ($ledgerAntes as $clave => $valor) {
    $ledger->set($clave, $valor);
  }
};

$pedir = function (string $ruta, $cuenta) use ($cambiador, $kernel): array {
  $cambiador->switchTo($cuenta);
  try {
    $peticion = Request::create($ruta);
    $original = \Drupal::request();
    if ($original && $original->hasSession()) {
      $peticion->setSession($original->getSession());
    }
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST);
    return [
      'code' => $respuesta->getStatusCode(),
      'body' => (string) $respuesta->getContent(),
    ];
  }
  finally {
    $cambiador->switchBack();
  }
};

$termino = function (string $vocabulario) use ($gestor) {
  $tids = $gestor->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->range(0, 1)
    ->execute();
  return $tids ? $gestor->getStorage('taxonomy_term')->load(reset($tids)) : NULL;
};

print "\nEl confirmar del portal\n";
print str_repeat('=', 70) . "\n\n";

$barrer();

try {
  $defs = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('tec_order');
  $allowed = $defs['field_tec_order_status']->getSetting('allowed_values') ?? [];
  $mirar('existe el estado pending_deposit', isset($allowed['pending_deposit']));
  $mirar('la cola salta de pending_deposit a accounting_verified', (ProductionQueueForm::STATUS_NEXT['pending_deposit'] ?? '') === 'accounting_verified');
  $mirar('la cola sigue saltando de Open a accounting_verified', (ProductionQueueForm::STATUS_NEXT['draft'] ?? '') === 'accounting_verified');

  $tipoCliente = $gestor->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'tec_crm_contact_type',
    'name' => 'Customer',
  ]);
  $tipoCliente = $tipoCliente ? reset($tipoCliente) : NULL;
  $tipoProducto = $termino('tec_product_types');
  $colorTerm = $termino('tec_colors');
  $tallaTerm = $termino('tec_sizes');
  if (!$tipoCliente || !$tipoProducto || !$colorTerm || !$tallaTerm) {
    throw new \RuntimeException('Faltan terminos de catalogo (tipo, color o talla).');
  }

  $crm = $gestor->getStorage('tec_crm');
  $productos = $gestor->getStorage('tec_product');
  $terminos = $gestor->getStorage('taxonomy_term');
  $pedidos = $gestor->getStorage('tec_order');
  $lineasGestor = $gestor->getStorage('tec_line_item');

  $marcaA = $guardar($terminos->create(['vid' => 'tec_brands', 'name' => MARCA . ' marca A']));
  $marcaB = $guardar($terminos->create(['vid' => 'tec_brands', 'name' => MARCA . ' marca B']));

  $orgA = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa A',
    'field_tec_customer_code' => 'ZZCA',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marcaA->id()],
  ]));
  $orgB = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa B',
    'field_tec_customer_code' => 'ZZCB',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marcaB->id()],
  ]));

  $personaA = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona A',
    'field_tec_customer_code' => 'ZZCA-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgA->id(),
  ]));
  $personaB = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona B',
    'field_tec_customer_code' => 'ZZCB-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgB->id(),
  ]));

  $pass = 'zzconfirm-pass-' . bin2hex(random_bytes(4));
  $userA = User::create([
    'name' => MARCA . '_a',
    'mail' => 'zzconfirm-a@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userA->set(CustomerCompany::PERSON_FIELD, $personaA->id());
  $guardar($userA);

  $userB = User::create([
    'name' => MARCA . '_b',
    'mail' => 'zzconfirm-b@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userB->set(CustomerCompany::PERSON_FIELD, $personaB->id());
  $guardar($userB);

  $prod = $guardar($productos->create([
    'type' => 'tec_product',
    'title' => MARCA . ' producto A',
    'field_product_name' => MARCA . ' producto A',
    'field_tec_brand' => $marcaA->id(),
    'field_tec_product_type' => $tipoProducto->id(),
    'field_tec_product_material' => 'leather',
  ]));
  $col = $guardar($productos->create([
    'type' => 'tec_color_variation',
    'title' => MARCA . ' color A',
    'field_tec_colors' => $colorTerm->id(),
    'field_tec_product' => $prod->id(),
  ]));
  $prod->set('field_tec_color_variations', [$col->id()]);
  $prod->save();
  $tallaA = $guardar($productos->create([
    'type' => 'tec_size_variation',
    'title' => MARCA . ' talla A',
    'field_tec_size' => $tallaTerm->id(),
    'field_tec_color_variation' => $col->id(),
    'field_tec_product' => $prod->id(),
    'field_tec_price' => '100.00',
  ]));
  $col->set('field_tec_size_variations', [$tallaA->id()]);
  $col->save();

  $colocar = function (int $qty) use ($cambiador, $pedidos, $orgA, $userA, $marcaA, $tallaA, &$basura) {
    $antes = $pedidos->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_tec_customer', $orgA->id())
      ->execute();
    $cambiador->switchTo($userA);
    try {
      $formulario = PlaceOrderForm::create(\Drupal::getContainer());
      $estado = new FormState();
      $construido = $formulario->buildForm([], $estado);
      $estado->setValue('b' . $marcaA->id(), [
        'lines' => [
          $tallaA->id() => ['qty' => $qty],
        ],
      ]);
      $formulario->submitForm($construido, $estado);
    }
    finally {
      $cambiador->switchBack();
    }
    $despues = $pedidos->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_tec_customer', $orgA->id())
      ->execute();
    $nuevos = array_diff($despues, $antes);
    $pedido = $nuevos ? $pedidos->load(reset($nuevos)) : NULL;
    if ($pedido) {
      $basura[] = $pedido;
      foreach ($pedido->get('field_tec_line_items')->referencedEntities() as $linea) {
        $basura[] = $linea;
      }
    }
    return $pedido;
  };

  $lineaDe = function ($pedido) {
    $lineas = $pedido->get('field_tec_line_items')->referencedEntities();
    return $lineas ? reset($lineas) : NULL;
  };

  print "1. Open: cambiar cantidades\n";
  print str_repeat('-', 70) . "\n";
  $pedido = $colocar(4);
  $mirar('nacio un pedido Open', $pedido && PortalOrder::isOpen($pedido), $pedido ? PortalOrder::statusOf($pedido) : 'ninguno');
  $linea = $pedido ? $lineaDe($pedido) : NULL;
  $mirar('la linea nacio con 4', $linea && (int) $linea->get('field_tec_quantity')->value === 4);

  $pagina = $pedido ? $pedir('/my/order/' . $pedido->id(), $userA) : ['code' => 0, 'body' => ''];
  $mirar('A entra al pedido', $pagina['code'] === 200, (string) $pagina['code']);
  $mirar('ve Open', str_contains($pagina['body'], 'Open'));
  $mirar('ve Save quantities', str_contains($pagina['body'], 'Save quantities'));
  $mirar('ve Confirm order', str_contains($pagina['body'], 'Confirm order'));

  $cambiador->switchTo($userA);
  try {
    $formulario = OrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $pedido);
    $estado->setValue('lines', [
      (int) $linea->id() => ['qty' => 7],
    ]);
    $formulario->submitSave($construido, $estado);
  }
  finally {
    $cambiador->switchBack();
  }
  $linea = $lineasGestor->load($linea->id());
  $mirar('la cantidad paso de 4 a 7', $linea && (int) $linea->get('field_tec_quantity')->value === 7, $linea ? (string) $linea->get('field_tec_quantity')->value : '');
  $mirar('el total es 700', $linea && (float) $linea->get('field_tec_line_item_total_number')->value === 700.0, $linea ? (string) $linea->get('field_tec_line_item_total_number')->value : '');
  $pedido = $pedidos->load($pedido->id());
  $mirar('sigue Open despues de guardar', PortalOrder::isOpen($pedido));

  $cambiador->switchTo($userA);
  $precioAntes = (string) $linea->get('field_tec_price')->value;
  $productoAntes = (int) $linea->get('field_tec_product')->target_id;
  try {
    $linea->set('field_tec_price', '1.00');
    $linea->save();
  }
  finally {
    $cambiador->switchBack();
  }
  $linea = $lineasGestor->load($linea->id());
  $mirar('el precio no se deja cambiar', $linea && (string) $linea->get('field_tec_price')->value === $precioAntes, $linea ? (string) $linea->get('field_tec_price')->value : '');
  $mirar('el producto no se deja cambiar', $linea && (int) $linea->get('field_tec_product')->target_id === $productoAntes);

  print "\n2. Confirm con cantidad 0 no sella\n";
  print str_repeat('-', 70) . "\n";
  $pedidoCero = $colocar(1);
  $lineaCero = $pedidoCero ? $lineaDe($pedidoCero) : NULL;
  $cambiador->switchTo($userA);
  try {
    $formulario = OrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $pedidoCero);
    $estado->setValue('lines', [
      (int) $lineaCero->id() => ['qty' => 0],
    ]);
    $formulario->submitConfirm($construido, $estado);
  }
  finally {
    $cambiador->switchBack();
  }
  $pedidoCero = $pedidos->load($pedidoCero->id());
  $mirar('cantidad 0 avisa y no sella', $pedidoCero && PortalOrder::isOpen($pedidoCero), $pedidoCero ? PortalOrder::statusOf($pedidoCero) : '');

  print "\n3. Confirm: Pending deposit, ya no edita\n";
  print str_repeat('-', 70) . "\n";
  $cambiador->switchTo($userA);
  try {
    $formulario = OrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $pedido);
    $estado->setValue('lines', [
      (int) $linea->id() => ['qty' => 7],
    ]);
    $formulario->submitConfirm($construido, $estado);
  }
  finally {
    $cambiador->switchBack();
  }
  $pedido = $pedidos->load($pedido->id());
  $mirar('paso a pending_deposit', $pedido && PortalOrder::statusOf($pedido) === PortalOrder::PENDING_DEPOSIT, $pedido ? PortalOrder::statusOf($pedido) : '');
  $mirar('la etiqueta del portal es Pending deposit', $pedido && PortalOrder::portalLabel($pedido) === 'Pending deposit');
  $mirar('no es Accounting Verified', $pedido && PortalOrder::statusOf($pedido) !== 'accounting_verified');

  $sellado = $pedir('/my/order/' . $pedido->id(), $userA);
  $mirar('A sigue viendo el pedido', $sellado['code'] === 200, (string) $sellado['code']);
  $mirar('ve Pending deposit', str_contains($sellado['body'], 'Pending deposit'));
  $mirar('ya no ve Confirm order', !str_contains($sellado['body'], 'Confirm order'));
  $mirar('dice que ya no se cambia', str_contains($sellado['body'], 'can no longer be changed'));
  $mirar('no le enseña Accounting Verified', !str_contains($sellado['body'], 'Accounting Verified'));

  $listado = $pedir('/my', $userA);
  $mirar('el listado dice Pending deposit', str_contains($listado['body'], 'Pending deposit'));

  $cambiador->switchTo($userA);
  try {
    $formulario = OrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $pedido);
    $estado->setValue('lines', [
      (int) $linea->id() => ['qty' => 99],
    ]);
    $formulario->submitSave($construido, $estado);
  }
  finally {
    $cambiador->switchBack();
  }
  $linea = $lineasGestor->load($linea->id());
  $mirar('despues de confirmar la cantidad sigue en 7', $linea && (int) $linea->get('field_tec_quantity')->value === 7, $linea ? (string) $linea->get('field_tec_quantity')->value : '');

  $cambiador->switchTo($userA);
  $apiQty = FALSE;
  try {
    $linea->set('field_tec_quantity', 99);
    $linea->save();
  }
  catch (\Throwable $e) {
    $apiQty = TRUE;
  }
  finally {
    $cambiador->switchBack();
  }
  $linea = $lineasGestor->load($linea->id());
  $mirar('la API tampoco cambia la cantidad sellada', $apiQty && $linea && (int) $linea->get('field_tec_quantity')->value === 7);

  $cambiador->switchTo($userA);
  $apiStatus = FALSE;
  try {
    $pedido->set('field_tec_order_status', 'accounting_verified');
    $pedido->save();
  }
  catch (\Throwable $e) {
    $apiStatus = TRUE;
  }
  finally {
    $cambiador->switchBack();
  }
  $pedido = $pedidos->load($pedido->id());
  $mirar('el cliente no puede poner Accounting Verified', $apiStatus && PortalOrder::statusOf($pedido) === PortalOrder::PENDING_DEPOSIT);

  print "\n4. B no confirma el de A. ECK sigue cerrado\n";
  print str_repeat('-', 70) . "\n";
  $deB = $pedir('/my/order/' . $pedido->id(), $userB);
  $mirar('B no ve el pedido de A', in_array($deB['code'], [403, 404], TRUE), (string) $deB['code']);

  $cambiador->switchTo($userB);
  try {
    $formulario = OrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $pedido);
    $estado->setValue('lines', [
      (int) $linea->id() => ['qty' => 1],
    ]);
    $formulario->submitConfirm($construido, $estado);
  }
  finally {
    $cambiador->switchBack();
  }
  $pedido = $pedidos->load($pedido->id());
  $mirar('B no sello ni movio el de A', PortalOrder::statusOf($pedido) === PortalOrder::PENDING_DEPOSIT);

  $handler = $gestor->getAccessControlHandler('tec_order');
  $mirar('A sigue sin createAccess de ECK', !$handler->createAccess('tec_sales_order', $userA));
  $mirar('A sigue sin update de ECK', !$handler->access($pedido, 'update', $userA));
  $mirar('A sigue sin delete de ECK', !$handler->access($pedido, 'delete', $userA));
}
catch (\Throwable $e) {
  $problemas++;
  print "\nEXCEPCION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

print "\n";
$barrer();
print $problemas === 0
  ? "Todo bien. Open se edita, Confirm sella, Pending deposit no es el deposito.\n\n"
  : "Fallaron $problemas comprobaciones.\n\n";
if ($problemas !== 0) {
  throw new \RuntimeException("Fallaron $problemas comprobaciones del confirmar.");
}
