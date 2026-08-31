<?php

/**
 * @file
 * La proforma nace al confirmar, no mientras el pedido esta Open.
 *
 *   php vendor/bin/drush.php scr scripts/la-proforma-tras-confirmar.php
 */

use Drupal\Core\Form\FormState;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\Form\FactoryOrderForm;
use Drupal\tec_portal\Form\FactoryPlaceOrderForm;
use Drupal\tec_portal\OrderLineGrid;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_production\OrderNumber;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZPROFORMA';

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

$lineasDe = function (int $id) use ($gestor): int {
  $pedido = $gestor->getStorage('tec_order')->load($id);
  if (!$pedido || !PortalOrder::hasProforma($pedido)) {
    return 0;
  }
  if (!$pedido->access('view')) {
    throw new AccessDeniedHttpException();
  }
  $n = 0;
  foreach (OrderLineGrid::create()->linesOf($pedido) as $linea) {
    $qty = $linea->hasField('field_tec_quantity') && !$linea->get('field_tec_quantity')->isEmpty()
      ? (float) $linea->get('field_tec_quantity')->value
      : 0;
    if ($qty >= 1) {
      $n++;
    }
  }
  return $n;
};

$termino = function (string $vocabulario) use ($gestor) {
  $tids = $gestor->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->range(0, 1)
    ->execute();
  return $tids ? $gestor->getStorage('taxonomy_term')->load(reset($tids)) : NULL;
};

print "\nLa proforma tras confirmar\n";
print str_repeat('=', 70) . "\n\n";

\Drupal::moduleHandler()->loadInclude('tec_portal', 'install');
tec_portal_update_8008();
tec_portal_update_8011();
\Drupal::moduleHandler()->resetImplementations();
\Drupal::service('router.builder')->rebuild();

print "1. Configuracion\n";
print str_repeat('-', 70) . "\n";

$listas = $gestor->getStorage('view')->load('tec_orders_orders');
$campos = $listas ? ($listas->get('display')['block_2']['display_options']['fields'] ?? []) : [];
$lapiz = (string) ($campos['nothing']['alter']['text'] ?? '');
$impresora = (string) ($campos['views_conditional_field']['or'] ?? '');
$mirar('Open solo lleva lapiz', str_contains($lapiz, '/o/order/') && !str_contains($lapiz, '/o/pf/'));
$mirar('Confirmado lleva impresora', str_contains($impresora, '/o/pf/') && str_contains($impresora, 'title="Print Proforma"'));
$mirar('el tooltip ya no dice Print/PDF', !str_contains($impresora, 'Print/PDF'));

$lineas = $gestor->getStorage('view')->load('tec_order_sales_order_line_items');
$pantallas = $lineas ? $lineas->get('display') : [];
$filtro = $pantallas['page_4']['display_options']['filters']['field_tec_order_status_value'] ?? [];
$mirar('el documento excluye Open', ($filtro['operator'] ?? '') === 'not' && isset($filtro['value']['draft']));
$mirar('el documento excluye Cancelled', isset($filtro['value']['cancelled']));
$apagada = ($pantallas['page_4']['display_options']['enabled'] ?? TRUE) === FALSE;
$rutaPhp = FALSE;
try {
  $rutaPhp = \Drupal::service('router.route_provider')->getRouteByName('tec_portal.proforma')->getPath() === '/o/pf/{tec_order}/print';
}
catch (\Throwable) {
}
$mirar('el print es la pagina PHP', $apagada && $rutaPhp);
$ficha = (string) ($pantallas['attachment_5']['display_options']['fields']['views_conditional_field']['then'] ?? '');
$mirar('ficha Open ya no imprime', str_contains($ficha, '/o/order/') && !str_contains($ficha, '/o/pf/'));

$barrer();

try {
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
  $renderer = \Drupal::service('renderer');

  $marca = $guardar($terminos->create(['vid' => 'tec_brands', 'name' => MARCA . ' marca']));
  $orgA = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa A',
    'field_tec_customer_code' => 'ZZPA',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marca->id()],
    'field_tec_address' => [['country_code' => 'TH']],
  ]));
  $orgB = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa B',
    'field_tec_customer_code' => 'ZZPB',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marca->id()],
    'field_tec_address' => [['country_code' => 'IT']],
  ]));
  $personaA = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona A',
    'field_tec_customer_code' => 'ZZPA-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgA->id(),
  ]));
  $personaB = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona B',
    'field_tec_customer_code' => 'ZZPB-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgB->id(),
  ]));

  $userA = User::create([
    'name' => MARCA . '_a',
    'mail' => 'zzproforma-a@example.com',
    'status' => 1,
    'pass' => 'zzpf-' . bin2hex(random_bytes(4)),
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userA->set(CustomerCompany::PERSON_FIELD, $personaA->id());
  $guardar($userA);
  $userB = User::create([
    'name' => MARCA . '_b',
    'mail' => 'zzproforma-b@example.com',
    'status' => 1,
    'pass' => 'zzpf-' . bin2hex(random_bytes(4)),
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userB->set(CustomerCompany::PERSON_FIELD, $personaB->id());
  $guardar($userB);

  $prod = $guardar($productos->create([
    'type' => 'tec_product',
    'title' => MARCA . ' producto',
    'field_product_name' => MARCA . ' producto',
    'field_tec_brand' => $marca->id(),
    'field_tec_product_type' => $tipoProducto->id(),
    'field_tec_product_material' => 'leather',
  ]));
  $col = $guardar($productos->create([
    'type' => 'tec_color_variation',
    'title' => MARCA . ' color',
    'field_tec_colors' => $colorTerm->id(),
    'field_tec_product' => $prod->id(),
  ]));
  $prod->set('field_tec_color_variations', [$col->id()]);
  $prod->save();
  $talla = $guardar($productos->create([
    'type' => 'tec_size_variation',
    'title' => MARCA . ' talla',
    'field_tec_size' => $tallaTerm->id(),
    'field_tec_color_variation' => $col->id(),
    'field_tec_product' => $prod->id(),
    'field_tec_price' => '50.00',
  ]));
  $col->set('field_tec_size_variations', [$talla->id()]);
  $col->save();

  $fabrica = $gestor->getStorage('user')->load(1);
  $antes = $pedidos->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_tec_customer', $orgA->id())
    ->execute();

  $cambiador->switchTo($fabrica);
  try {
    $formulario = FactoryPlaceOrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $orgA);
    $estado->setValue('lines', [
      $talla->id() => ['qty' => 3],
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

  print "\n2. Open no tiene proforma\n";
  print str_repeat('-', 70) . "\n";
  $mirar('nacio el pedido', (bool) $pedido);
  if (!$pedido) {
    throw new \RuntimeException('No nacio el pedido de prueba.');
  }
  $mirar('hasProforma en Open es no', !PortalOrder::hasProforma($pedido), PortalOrder::statusOf($pedido));
  $mirar('sin icono de impresora', PortalOrder::printControlMarkup($pedido) === '');

  $cambiador->switchTo($fabrica);
  try {
    $abierto = (string) $renderer->renderRoot(\Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedido));
  }
  finally {
    $cambiador->switchBack();
  }
  $mirar('el formulario Open no imprime', !str_contains($abierto, 'Print Proforma'));
  $mirar('la vista Open no saca lineas', $lineasDe((int) $pedido->id()) === 0);

  $delCliente = $pedir(PortalOrder::proformaPath($pedido), $userA);
  $mirar('el cliente en Open no ve el documento', str_contains($delCliente['body'], "Can't generate") || $delCliente['code'] >= 400 || $lineasDe((int) $pedido->id()) === 0, (string) $delCliente['code']);

  print "\n3. Tras Confirm si hay proforma\n";
  print str_repeat('-', 70) . "\n";
  $cambiador->switchTo($fabrica);
  try {
    $formulario = FactoryOrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $pedido);
    $estado->setValue('lines', [
      $talla->id() => ['qty' => 3],
    ]);
    $formulario->submitConfirm($construido, $estado);
  }
  finally {
    $cambiador->switchBack();
  }
  $pedido = $pedidos->load($pedido->id());
  $mirar('paso a Pending payment', PortalOrder::statusOf($pedido) === PortalOrder::PENDING_DEPOSIT);
  $mirar('hasProforma ahora si', PortalOrder::hasProforma($pedido));
  $mirar('el icono apunta al documento', str_contains(PortalOrder::printControlMarkup($pedido), '/o/pf/' . $pedido->id() . '/print'));
  $mirar('el tooltip dice Print Proforma', str_contains(PortalOrder::printControlMarkup($pedido), 'title="Print Proforma"'));

  $cambiador->switchTo($fabrica);
  try {
    $cerrado = (string) $renderer->renderRoot(\Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedido));
    $nFabrica = $lineasDe((int) $pedido->id());
  }
  finally {
    $cambiador->switchBack();
  }
  $mirar('el pedido cerrado ofrece Print Proforma', str_contains($cerrado, 'Print Proforma'));
  $mirar('la vista ya saca lineas', $nFabrica > 0, (string) $nFabrica);

  $cambiador->switchTo($userA);
  try {
    $mirar('A ve su pedido', $pedido->access('view', $userA));
    $nA = $lineasDe((int) $pedido->id());
  }
  finally {
    $cambiador->switchBack();
  }
  $mirar('A saca las lineas de su proforma', $nA > 0, (string) $nA);

  $cambiador->switchTo($userB);
  try {
    $mirar('B no ve el pedido de A', !$pedido->access('view', $userB));
    $denied = FALSE;
    $nB = -1;
    try {
      $nB = $lineasDe((int) $pedido->id());
    }
    catch (AccessDeniedHttpException | NotFoundHttpException $e) {
      $denied = TRUE;
    }
    $mirar('B no imprime el de A', $denied || $nB === 0, $denied ? 'denegado' : (string) $nB);
  }
  finally {
    $cambiador->switchBack();
  }

  print "\n4. Cancelled no\n";
  print str_repeat('-', 70) . "\n";
  $pedido->set('field_tec_order_status', PortalOrder::CANCELLED);
  $pedido->save();
  $mirar('Cancelled no tiene proforma', !PortalOrder::hasProforma($pedido));
  $mirar('Cancelled no saca lineas', $lineasDe((int) $pedido->id()) === 0);
}
catch (\Throwable $e) {
  $problemas++;
  print "\nEXCEPCION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

print "\n";
$barrer();
print $problemas === 0
  ? "Todo bien. Open no imprime; Confirm abre la proforma; Cancelled tampoco.\n\n"
  : "Fallaron $problemas comprobaciones.\n\n";
if ($problemas !== 0) {
  throw new \RuntimeException("Fallaron $problemas comprobaciones de la proforma.");
}
