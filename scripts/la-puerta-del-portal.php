<?php

/**
 * @file
 * La puerta del portal: /my es de una empresa, y el login llega ahi.
 *
 *   php vendor/bin/drush.php scr scripts/la-puerta-del-portal.php
 *
 * Paso 4. Crea dos empresas, dos logins Customer, un pedido cada una. Pide
 * /my y /my/order como cada uno. Comprueba que A no ve el de B, que /start
 * redirige, que el personal va a /start, y que la ruta no es de administracion.
 * Lo borra al final.
 */

use Drupal\tec_portal\CustomerCompany;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZPUERTA';

$gestor = \Drupal::entityTypeManager();
$companies = \Drupal::service('tec_portal.company');
$cambiador = \Drupal::service('account_switcher');
$kernel = \Drupal::service('http_kernel');
$problemas = 0;
$basura = [];

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

$barrer = function () use (&$basura): void {
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
  foreach (['tec_line_item', 'tec_order', 'tec_crm'] as $tipo) {
    foreach ($gestor->getStorage($tipo)->loadMultiple() as $entidad) {
      if (str_starts_with((string) $entidad->label(), MARCA)) {
        $entidad->delete();
      }
    }
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
      'location' => (string) $respuesta->headers->get('Location', ''),
    ];
  }
  finally {
    $cambiador->switchBack();
  }
};

print "\nLa puerta del portal\n";
print str_repeat('=', 70) . "\n\n";

$barrer();

try {
  $tipoCliente = $gestor->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'tec_crm_contact_type',
    'name' => 'Customer',
  ]);
  $tipoCliente = $tipoCliente ? reset($tipoCliente) : NULL;
  if (!$tipoCliente) {
    print "No esta el termino Customer. Aborto.\n";
    throw new \RuntimeException('Missing Customer contact type');
  }

  $crm = $gestor->getStorage('tec_crm');
  $pedidos = $gestor->getStorage('tec_order');
  $lineas = $gestor->getStorage('tec_line_item');
  $dueno = $gestor->getStorage('user')->load(1);

  $orgA = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa A',
    'field_tec_customer_code' => 'ZZUA',
    'field_tec_contact_type' => $tipoCliente->id(),
  ]));
  $orgB = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa B',
    'field_tec_customer_code' => 'ZZUB',
    'field_tec_contact_type' => $tipoCliente->id(),
  ]));

  $personaA = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona A',
    'field_tec_customer_code' => 'ZZUA-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgA->id(),
  ]));
  $personaB = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona B',
    'field_tec_customer_code' => 'ZZUB-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgB->id(),
  ]));

  $pass = 'zzpuerta-pass-' . bin2hex(random_bytes(4));
  $userA = User::create([
    'name' => MARCA . '_a',
    'mail' => 'zzpuerta-a@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userA->set(CustomerCompany::PERSON_FIELD, $personaA->id());
  $guardar($userA);
  $userB = User::create([
    'name' => MARCA . '_b',
    'mail' => 'zzpuerta-b@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userB->set(CustomerCompany::PERSON_FIELD, $personaB->id());
  $guardar($userB);
  $userSuelto = User::create([
    'name' => MARCA . '_suelto',
    'mail' => 'zzpuerta-suelto@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $guardar($userSuelto);

  $pedidoA = $guardar($pedidos->create([
    'type' => 'tec_sales_order',
    'title' => MARCA . ' venta A',
    'field_tec_customer' => $orgA->id(),
    'status' => 1,
  ]));
  $lineaA = $guardar($lineas->create([
    'type' => 'tec_sales_order_line_item',
    'title' => MARCA . ' linea A',
    'field_tec_quantity' => 3,
    'field_tec_price' => '100.00',
    'field_tec_line_item_total_number' => '300.00',
    'field_tec_order' => $pedidoA->id(),
  ]));
  $pedidoA->set('field_tec_line_items', [$lineaA->id()]);
  $pedidoA->save();

  $pedidoB = $guardar($pedidos->create([
    'type' => 'tec_sales_order',
    'title' => MARCA . ' venta B',
    'field_tec_customer' => $orgB->id(),
    'status' => 1,
  ]));

  print "1. Las rutas\n";
  print str_repeat('-', 70) . "\n";
  $home = \Drupal::service('router.route_provider')->getRouteByName('tec_portal.home');
  $mirar('/my no es ruta de administracion', $home->getOption('_admin_route') === FALSE);
  $mirar('/my no lleva el id de la empresa', !str_contains($home->getPath(), '{'));
  $order_route = \Drupal::service('router.route_provider')->getRouteByName('tec_portal.order');
  $mirar('/my/order/{tec_order} tampoco es de administracion', $order_route->getOption('_admin_route') === FALSE);

  print "\n2. El listado /my\n";
  print str_repeat('-', 70) . "\n";
  $paginaA = $pedir('/my', $userA);
  $mirar('A entra en /my', $paginaA['code'] === 200, (string) $paginaA['code']);
  $mirar('A ve el nombre de su empresa', str_contains($paginaA['body'], MARCA . ' empresa A'));
  $mirar('A ve su pedido', str_contains($paginaA['body'], MARCA . ' venta A'));
  $mirar('A no ve el pedido de B', !str_contains($paginaA['body'], MARCA . ' venta B'));

  $paginaB = $pedir('/my', $userB);
  $mirar('B ve su pedido y no el de A', str_contains($paginaB['body'], MARCA . ' venta B') && !str_contains($paginaB['body'], MARCA . ' venta A'));

  $suelto = $pedir('/my', $userSuelto);
  $mirar('sin empresa, /my avisa y no lista pedidos', $suelto['code'] === 200 && str_contains($suelto['body'], 'not linked to a company') && !str_contains($suelto['body'], MARCA . ' venta A'));

  $fabrica = $pedir('/my', $dueno);
  $mirar('el dueno que pisa /my sale hacia /start', in_array($fabrica['code'], [301, 302, 303, 307], TRUE) && str_contains($fabrica['location'], 'start'), $fabrica['code'] . ' ' . $fabrica['location']);

  print "\n3. El detalle /my/order\n";
  print str_repeat('-', 70) . "\n";
  $detalleA = $pedir('/my/order/' . $pedidoA->id(), $userA);
  $mirar('A abre su pedido', $detalleA['code'] === 200, (string) $detalleA['code']);
  $mirar('A ve las piezas de su linea', str_contains($detalleA['body'], '3') && str_contains($detalleA['body'], '300'));

  $ajeno = $pedir('/my/order/' . $pedidoB->id(), $userA);
  $mirar('A no abre el pedido de B', in_array($ajeno['code'], [403, 404], TRUE), (string) $ajeno['code']);

  $compra = $guardar($pedidos->create([
    'type' => 'tec_purchase_order',
    'title' => MARCA . ' compra',
    'field_tec_vendor' => $orgA->id(),
    'status' => 1,
  ]));
  $po = $pedir('/my/order/' . $compra->id(), $userA);
  $mirar('A no abre un pedido de compra por /my/order', in_array($po['code'], [403, 404], TRUE), (string) $po['code']);

  print "\n4. El login y /start\n";
  print str_repeat('-', 70) . "\n";
  $cambiador->switchTo($userA);
  try {
    $evento = new RequestEvent($kernel, Request::create('/start'), HttpKernelInterface::MAIN_REQUEST);
    \Drupal::service('tec_portal.redirect')->onRequest($evento);
    $ir = $evento->hasResponse() ? $evento->getResponse()->getTargetUrl() : '';
    $mirar('A en /start es enviado a /my', $evento->hasResponse() && str_ends_with(rtrim($ir, '/'), '/my'), $ir);
  }
  finally {
    $cambiador->switchBack();
  }

  $cambiador->switchTo($dueno);
  try {
    $evento = new RequestEvent($kernel, Request::create('/start'), HttpKernelInterface::MAIN_REQUEST);
    \Drupal::service('tec_portal.redirect')->onRequest($evento);
    $mirar('el dueno en /start no es redirigido al portal', !$evento->hasResponse());
  }
  finally {
    $cambiador->switchBack();
  }

  $form = \Drupal::formBuilder()->getForm('Drupal\user\Form\UserLoginForm');
  $tiene = FALSE;
  foreach ($form['#submit'] ?? [] as $submit) {
    if ($submit === 'tec_portal_login_submit') {
      $tiene = TRUE;
    }
  }
  $mirar('el formulario de login lleva el envio al portal', $tiene);

  $mirar('A sigue sin poder crear pedidos por la API de acceso', !$gestor->getAccessControlHandler('tec_order')->createAccess('tec_sales_order', $userA));
}
catch (\Throwable $e) {
  $problemas++;
  print "\nEXCEPCION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

print "\n";
$barrer();
print $problemas === 0
  ? "Todo bien. La puerta deja a cada empresa en su lado.\n\n"
  : "Fallaron $problemas comprobaciones.\n\n";
if ($problemas !== 0) {
  throw new \RuntimeException("Fallaron $problemas comprobaciones de la puerta.");
}
