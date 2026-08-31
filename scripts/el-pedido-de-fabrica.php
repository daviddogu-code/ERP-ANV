<?php

/**
 * @file
 * El pedido de fabrica: /customer/{id}/order/new, luego /o/order/{id}.
 *
 *   php vendor/bin/drush.php scr scripts/el-pedido-de-fabrica.php
 */

use Drupal\Core\Form\FormState;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\Form\FactoryOrderForm;
use Drupal\tec_portal\Form\FactoryPlaceOrderForm;
use Drupal\tec_portal\Form\PlaceOrderForm;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_production\OrderNumber;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZFABPED';

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

print "\nEl pedido de fabrica\n";
print str_repeat('=', 70) . "\n\n";

\Drupal::moduleHandler()->loadInclude('tec_portal', 'install');
tec_portal_update_8004();
tec_portal_update_8005();
tec_portal_update_8006();
tec_portal_update_8007();
tec_portal_update_8008();

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

  $marcaA = $guardar($terminos->create(['vid' => 'tec_brands', 'name' => MARCA . ' marca A']));
  $marcaB = $guardar($terminos->create(['vid' => 'tec_brands', 'name' => MARCA . ' marca B']));

  $orgA = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa A',
    'field_tec_customer_code' => 'ZZFA',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marcaA->id()],
    'field_tec_address' => [['country_code' => 'TH']],
  ]));
  $orgB = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa B',
    'field_tec_customer_code' => 'ZZFB',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marcaB->id()],
    'field_tec_address' => [['country_code' => 'IT']],
  ]));

  $personaA = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona A',
    'field_tec_customer_code' => 'ZZFA-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgA->id(),
  ]));

  $pass = 'zzfab-pass-' . bin2hex(random_bytes(4));
  $userA = User::create([
    'name' => MARCA . '_a',
    'mail' => 'zzfab-a@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userA->set(CustomerCompany::PERSON_FIELD, $personaA->id());
  $guardar($userA);

  $cadena = function (string $sufijo, $marca) use ($guardar, $productos, $tipoProducto, $colorTerm, $tallaTerm) {
    $prod = $guardar($productos->create([
      'type' => 'tec_product',
      'title' => MARCA . ' producto ' . $sufijo,
      'field_product_name' => MARCA . ' producto ' . $sufijo,
      'field_tec_brand' => $marca->id(),
      'field_tec_product_type' => $tipoProducto->id(),
      'field_tec_product_material' => 'leather',
    ]));
    $col = $guardar($productos->create([
      'type' => 'tec_color_variation',
      'title' => MARCA . ' color ' . $sufijo,
      'field_tec_colors' => $colorTerm->id(),
      'field_tec_product' => $prod->id(),
    ]));
    $prod->set('field_tec_color_variations', [$col->id()]);
    $prod->save();
    $talla = $guardar($productos->create([
      'type' => 'tec_size_variation',
      'title' => MARCA . ' talla ' . $sufijo,
      'field_tec_size' => $tallaTerm->id(),
      'field_tec_color_variation' => $col->id(),
      'field_tec_product' => $prod->id(),
      'field_tec_price' => $sufijo === 'A' ? '100.00' : '999.00',
    ]));
    $col->set('field_tec_size_variations', [$talla->id()]);
    $col->save();
    return [$prod, $col, $talla];
  };

  [$prodA, $colA, $tallaA] = $cadena('A', $marcaA);
  [$prodB, $colB, $tallaB] = $cadena('B', $marcaB);

  $fabrica = $gestor->getStorage('user')->load(1);
  $ruta = '/customer/' . $orgA->id() . '/order/new';
  $renderer = \Drupal::service('renderer');

  print "1. Acceso\n";
  print str_repeat('-', 70) . "\n";
  $mirar('la fabrica puede abrir el grid', FactoryPlaceOrderForm::access($fabrica, $orgA)->isAllowed());
  $mirar('el cliente del portal no', !FactoryPlaceOrderForm::access($userA, $orgA)->isAllowed());
  $mirar('la fabrica no usa /my/order/new', !PlaceOrderForm::access($fabrica)->isAllowed());
  $mirar('una persona no es una empresa', !FactoryPlaceOrderForm::access($fabrica, $personaA)->isAllowed());

  $cambiador->switchTo($fabrica);
  try {
    $html = (string) $renderer->renderRoot(\Drupal::formBuilder()->getForm(FactoryPlaceOrderForm::class, $orgA));
    $boton = (string) $renderer->renderRoot($gestor->getViewBuilder('tec_crm')->view($orgA, 'sales_order_button'));
  }
  finally {
    $cambiador->switchBack();
  }
  $mirar('la fabrica ve el formulario', $html !== '');
  $mirar('ve el producto de A', stripos($html, MARCA . ' producto A') !== FALSE);
  $mirar('no ve el producto de B', stripos($html, MARCA . ' producto B') === FALSE);
  $mirar('una sola lista', substr_count($html, 'tec-portal__table') === 1);
  $mirar('un solo Total', str_contains($html, '>Total<') && !str_contains($html, 'Grand total'));
  $mirar('vuelve a la ficha', str_contains($html, 'Back to ' . MARCA . ' empresa A'));

  $delCliente = $pedir($ruta, $userA);
  $mirar('el portal recibe 403', in_array($delCliente['code'], [403, 404], TRUE), (string) $delCliente['code']);

  $mirar('Place an order apunta al grid', str_contains($boton, $ruta));
  $mirar('no es el flag que recarga la ficha', !str_contains($boton, '/flag/flag/'));

  $cambiador->switchTo($fabrica);
  try {
    $flagHtml = (string) $renderer->renderRoot(\Drupal::service('flag.link_builder')->build('tec_crm', $orgA->id(), 'tec_order_draft_sales', 'default'));
  }
  finally {
    $cambiador->switchBack();
  }
  $mirar('el flag de la ficha apunta al grid', str_contains($flagHtml, $ruta), $flagHtml === '' ? 'vacio' : '');
  $mirar('el flag ya no recarga la ficha', !str_contains($flagHtml, '/flag/flag/'));

  print "\n2. Place order crea el SO\n";
  print str_repeat('-', 70) . "\n";
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
      $tallaA->id() => ['qty' => 4],
      $tallaB->id() => ['qty' => 9],
    ]);
    $formulario->submitForm($construido, $estado);
    $redirect = $estado->getRedirect();
  }
  finally {
    $cambiador->switchBack();
  }

  $despues = $pedidos->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_tec_customer', $orgA->id())
    ->execute();
  $nuevos = array_diff($despues, $antes);
  $mirar('nacio un pedido', count($nuevos) === 1, (string) count($nuevos));

  $pedido = $nuevos ? $pedidos->load(reset($nuevos)) : NULL;
  if ($pedido) {
    $basura[] = $pedido;
    $idsCliente = [];
    foreach ($pedido->get('field_tec_customer') as $item) {
      $idsCliente[] = (int) $item->target_id;
    }
    $mirar('el pedido es de A', $idsCliente === [(int) $orgA->id()], implode(',', $idsCliente));
    $mirar('tiene numero', OrderNumber::isNumber($pedido->label()), (string) $pedido->label());
    $lineas = $pedido->get('field_tec_line_items')->referencedEntities();
    $mirar('una sola linea (B no cuelo)', count($lineas) === 1, (string) count($lineas));
    if ($lineas) {
      $linea = reset($lineas);
      $basura[] = $linea;
      $mirar('cantidad 4', (int) $linea->get('field_tec_quantity')->value === 4);
    }
    $ir = $redirect ? $redirect->toString() : '';
    $mirar('despues va a /o/order', str_contains($ir, '/o/order/' . $pedido->id()), $ir);
    $mirar('no va al draft', !str_contains($ir, '/o/draft/'), $ir);
  }

  print "\n3. Cero no crea pedido\n";
  print str_repeat('-', 70) . "\n";
  $antesCero = $pedidos->getQuery()->accessCheck(FALSE)->condition('field_tec_customer', $orgA->id())->execute();
  $cambiador->switchTo($fabrica);
  try {
    $formulario = FactoryPlaceOrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado, $orgA);
    $estado->setValue('lines', [
      $tallaA->id() => ['qty' => 0],
    ]);
    $formulario->submitForm($construido, $estado);
  }
  finally {
    $cambiador->switchBack();
  }
  $despuesCero = $pedidos->getQuery()->accessCheck(FALSE)->condition('field_tec_customer', $orgA->id())->execute();
  $mirar('cantidad 0 no crea otro pedido', count($despuesCero) === count($antesCero));

  $eca = $gestor->hasDefinition('eca') ? $gestor->getStorage('eca')->load('process_sclj26d') : NULL;
  $mirar('ECA ya no crea el SO al flaggear', $eca && !$eca->status());

  print "\n4. Open de fabrica: misma rejilla y Confirm\n";
  print str_repeat('-', 70) . "\n";
  if ($pedido) {
    $mirar('la fabrica puede abrir /o/order', FactoryOrderForm::access($fabrica, $pedido)->isAllowed());
    $mirar('el cliente del portal no', !FactoryOrderForm::access($userA, $pedido)->isAllowed());

    $cambiador->switchTo($fabrica);
    try {
      $abierto = (string) $renderer->renderRoot(\Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedido));
    }
    finally {
      $cambiador->switchBack();
    }
    $mirar('ve Save quantities', str_contains($abierto, 'Save quantities'));
    $mirar('ve Confirm order', str_contains($abierto, 'Confirm order'));
    $mirar('Open no imprime proforma', !str_contains($abierto, 'Print Proforma') && !str_contains($abierto, '/o/pf/'));
    $mirar('ve el catalogo de A', stripos($abierto, MARCA . ' producto A') !== FALSE);
    $mirar('no ve el de B', stripos($abierto, MARCA . ' producto B') === FALSE);
    $mirar('no hay Delete', stripos($abierto, 'Delete') === FALSE);
    $mirar('no hay Cancel', stripos($abierto, 'Cancel') === FALSE);
    $mirar('vuelve a la empresa', str_contains($abierto, 'Back to ' . MARCA . ' empresa A'));

    $cambiador->switchTo($fabrica);
    try {
      $formulario = FactoryOrderForm::create(\Drupal::getContainer());
      $estado = new FormState();
      $construido = $formulario->buildForm([], $estado, $pedido);
      $estado->setValue('lines', [
        $tallaA->id() => ['qty' => 7],
      ]);
      $formulario->submitSave($construido, $estado);
    }
    finally {
      $cambiador->switchBack();
    }
    $pedido = $pedidos->load($pedido->id());
    $lineas = $pedido->get('field_tec_line_items')->referencedEntities();
    $linea = $lineas ? reset($lineas) : NULL;
    $mirar('guardar pasa a 7', $linea && (int) $linea->get('field_tec_quantity')->value === 7, $linea ? (string) $linea->get('field_tec_quantity')->value : '');
    $mirar('sigue Open', PortalOrder::isOpen($pedido), PortalOrder::statusOf($pedido));

    $cambiador->switchTo($fabrica);
    try {
      $formulario = FactoryOrderForm::create(\Drupal::getContainer());
      $estado = new FormState();
      $construido = $formulario->buildForm([], $estado, $pedido);
      $estado->setValue('lines', [
        $tallaA->id() => ['qty' => 7],
      ]);
      $formulario->submitConfirm($construido, $estado);
      $irConfirm = $estado->getRedirect();
    }
    finally {
      $cambiador->switchBack();
    }
    $pedido = $pedidos->load($pedido->id());
    $mirar('Confirm deja Pending payment', PortalOrder::statusOf($pedido) === PortalOrder::PENDING_DEPOSIT, PortalOrder::statusOf($pedido));
    $irConfirm = $irConfirm ? $irConfirm->toString() : '';
    $mirar('despues de Confirm sigue en /o/order', str_contains($irConfirm, '/o/order/' . $pedido->id()), $irConfirm);

    $cambiador->switchTo($fabrica);
    try {
      $cerrado = (string) $renderer->renderRoot(\Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedido));
    }
    finally {
      $cambiador->switchBack();
    }
    $mirar('ya no hay Save', !str_contains($cerrado, 'Save quantities'));
    $mirar('ya no hay Confirm', !str_contains($cerrado, 'Confirm order'));
    $mirar('Confirmado ve Print Proforma', str_contains($cerrado, 'Print Proforma') && str_contains($cerrado, '/o/pf/' . $pedido->id() . '/print'));
  }

  print "\n5. /o/draft apagado, /po/draft intacto\n";
  print str_repeat('-', 70) . "\n";
  $vista = $gestor->getStorage('view')->load('tec_order_sales_order_line_items');
  $pantallas = $vista ? $vista->get('display') : [];
  $mirar('sales draft apagado', ($pantallas['page_2']['display_options']['enabled'] ?? TRUE) === FALSE);
  $mirar('compras draft sigue', ($pantallas['page_1']['display_options']['enabled'] ?? TRUE) !== FALSE);
  $mirar('compras sigue en /po/draft', ($pantallas['page_1']['display_options']['path'] ?? '') === 'po/draft/%');
  $lapiz = (string) ($pantallas['attachment_5']['display_options']['fields']['views_conditional_field']['then'] ?? '');
  $mirar('Edit order de la ficha apunta a /o/order', str_contains($lapiz, '/o/order/') && !str_contains($lapiz, '/o/draft/'), $lapiz === '' ? 'sin texto' : 'ok');
  $mirar('ficha Open ya no imprime', !str_contains($lapiz, '/o/pf/'));
  $mirar('el lapiz no lleva destination', !str_contains($lapiz, 'destination='));
  $poLapiz = (string) ($pantallas['attachment_6']['display_options']['fields']['views_conditional_field']['then'] ?? '');
  $mirar('compras sigue con destination', str_contains($poLapiz, '/po/draft/') && str_contains($poLapiz, 'destination='), $poLapiz === '' ? 'sin texto' : 'ok');
}
catch (\Throwable $e) {
  $problemas++;
  print "\nEXCEPCION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

print "\n";
$barrer();
print $problemas === 0
  ? "Todo bien. Fabrica pide en el grid, confirma en /o/order, y compras sigue en /po/draft.\n\n"
  : "Fallaron $problemas comprobaciones.\n\n";
if ($problemas !== 0) {
  throw new \RuntimeException("Fallaron $problemas comprobaciones del pedido de fabrica.");
}
