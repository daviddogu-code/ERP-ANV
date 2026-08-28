<?php

/**
 * @file
 * El candado del portal: un cliente no ve a la otra empresa.
 *
 *   php vendor/bin/drush.php scr scripts/el-candado-del-portal.php
 *
 * Pasos 1-3: usuario → persona → empresa, rol Customer reconstruido, y el
 * enganche de acceso. Crea dos empresas, dos personas, dos logins Customer,
 * un pedido de venta cada una con su linea, un pedido de compra, una marca y
 * un producto por empresa. Comprueba view, view label, consultas con
 * accessCheck, el plugin de Views y que el tema de administracion no entra.
 * Lo borra al final aunque falle a mitad.
 */

use Drupal\tec_portal\CustomerCompany;
use Drupal\user\Entity\User;

const MARCA = 'ZZPORTAL';

$gestor = \Drupal::entityTypeManager();
$companies = \Drupal::service('tec_portal.company');
$cambiador = \Drupal::service('account_switcher');
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
};

print "\nCandado del portal\n";
print str_repeat('=', 70) . "\n\n";

$barrer();

try {

  $tipoCliente = $gestor->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'tec_crm_contact_type',
    'name' => 'Customer',
  ]);
  $tipoCliente = $tipoCliente ? reset($tipoCliente) : NULL;
  if (!$tipoCliente) {
    print "No esta el termino Customer en tec_crm_contact_type. Aborto.\n";
    exit(1);
  }

  $crm = $gestor->getStorage('tec_crm');
  $pedidos = $gestor->getStorage('tec_order');
  $lineas = $gestor->getStorage('tec_line_item');
  $productos = $gestor->getStorage('tec_product');
  $terminos = $gestor->getStorage('taxonomy_term');

  $orgA = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa A',
    'field_tec_customer_code' => 'ZZPA',
    'field_tec_contact_type' => $tipoCliente->id(),
  ]));

  $orgB = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa B',
    'field_tec_customer_code' => 'ZZPB',
    'field_tec_contact_type' => $tipoCliente->id(),
  ]));

  $marcaA = $guardar($terminos->create(['vid' => 'tec_brands', 'name' => MARCA . ' marca A']));
  $marcaB = $guardar($terminos->create(['vid' => 'tec_brands', 'name' => MARCA . ' marca B']));

  $orgA->set('field_tec_brands', [$marcaA->id()]);
  $orgA->save();
  $orgB->set('field_tec_brands', [$marcaB->id()]);
  $orgB->save();

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

  $pass = \Drupal::hasService('password_generator')
    ? \Drupal::service('password_generator')->generate(16)
    : 'zzportal-pass-' . bin2hex(random_bytes(8));

  $userA = User::create([
    'name' => MARCA . '_a',
    'mail' => 'zzportal-a@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userA->set(CustomerCompany::PERSON_FIELD, $personaA->id());
  $guardar($userA);

  $userB = User::create([
    'name' => MARCA . '_b',
    'mail' => 'zzportal-b@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $userB->set(CustomerCompany::PERSON_FIELD, $personaB->id());
  $guardar($userB);

  $userSuelto = User::create([
    'name' => MARCA . '_suelto',
    'mail' => 'zzportal-suelto@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => [CustomerCompany::ROLE],
  ]);
  $guardar($userSuelto);

  $userMixto = User::create([
    'name' => MARCA . '_mixto',
    'mail' => 'zzportal-mixto@example.com',
    'status' => 1,
    'pass' => $pass,
    'roles' => ['tec_supervisor', CustomerCompany::ROLE],
  ]);
  $userMixto->set(CustomerCompany::PERSON_FIELD, $personaA->id());
  $guardar($userMixto);

  $pedidoA = $guardar($pedidos->create([
    'type' => 'tec_sales_order',
    'title' => MARCA . ' venta A',
    'field_tec_customer' => $orgA->id(),
    'status' => 1,
  ]));

  $pedidoB = $guardar($pedidos->create([
    'type' => 'tec_sales_order',
    'title' => MARCA . ' venta B',
    'field_tec_customer' => $orgB->id(),
    'status' => 1,
  ]));

  $compra = $guardar($pedidos->create([
    'type' => 'tec_purchase_order',
    'title' => MARCA . ' compra',
    'field_tec_vendor' => $orgA->id(),
    'status' => 1,
  ]));

  $lineaA = $guardar($lineas->create([
    'type' => 'tec_sales_order_line_item',
    'title' => MARCA . ' linea A',
    'field_tec_order' => $pedidoA->id(),
  ]));

  $lineaB = $guardar($lineas->create([
    'type' => 'tec_sales_order_line_item',
    'title' => MARCA . ' linea B',
    'field_tec_order' => $pedidoB->id(),
  ]));

  $prodA = $guardar($productos->create([
    'type' => 'tec_product',
    'title' => MARCA . ' producto A',
    'field_tec_brand' => $marcaA->id(),
  ]));

  $prodB = $guardar($productos->create([
    'type' => 'tec_product',
    'title' => MARCA . ' producto B',
    'field_tec_brand' => $marcaB->id(),
  ]));

  $colorA = $guardar($productos->create([
    'type' => 'tec_color_variation',
    'title' => MARCA . ' color A',
    'field_tec_product' => $prodA->id(),
  ]));

  $colorB = $guardar($productos->create([
    'type' => 'tec_color_variation',
    'title' => MARCA . ' color B',
    'field_tec_product' => $prodB->id(),
  ]));

  $dueno = $gestor->getStorage('user')->load(1);

  print "1. El enlace usuario → persona → empresa\n";
  print str_repeat('-', 70) . "\n";
  $mirar('A esta atado a la empresa A', $companies->id($userA) === (int) $orgA->id(), (string) $companies->id($userA));
  $mirar('B esta atado a la empresa B', $companies->id($userB) === (int) $orgB->id());
  $mirar('un Customer sin persona no tiene empresa', $companies->id($userSuelto) === NULL);
  $mirar('A es cliente de portal', $companies->isPortalCustomer($userA));
  $mirar('B es cliente de portal', $companies->isPortalCustomer($userB));
  $mirar('el usuario 1 no es cliente de portal', !$companies->isPortalCustomer($dueno));
  $mirar('supervisor con Customer no es portal (manda la fabrica)', !$companies->isPortalCustomer($userMixto));
  $mirar('las marcas de A son las de su ficha', $companies->brandIds($userA) === [(int) $marcaA->id()]);
  $mirar('el plugin de Views lee la empresa de A', (static function () use ($userA, $orgA, $cambiador) {
    $cambiador->switchTo($userA);
    try {
      $plugin = \Drupal::service('plugin.manager.views.argument_default')->createInstance('tec_portal_company');
      return $plugin->getArgument() === (string) $orgA->id();
    }
    finally {
      $cambiador->switchBack();
    }
  })());
  $mirar('el plugin de Views sin empresa devuelve 0', (static function () use ($userSuelto, $cambiador) {
    $cambiador->switchTo($userSuelto);
    try {
      $plugin = \Drupal::service('plugin.manager.views.argument_default')->createInstance('tec_portal_company');
      return $plugin->getArgument() === '0';
    }
    finally {
      $cambiador->switchBack();
    }
  })());

  print "\n2. Ver pedidos\n";
  print str_repeat('-', 70) . "\n";
  $mirar('A ve su pedido de venta', $pedidoA->access('view', $userA));
  $mirar('A ve la etiqueta de su pedido', $pedidoA->access('view label', $userA));
  $mirar('A no ve el pedido de B', !$pedidoB->access('view', $userA));
  $mirar('A no ve ni la etiqueta del pedido de B', !$pedidoB->access('view label', $userA));
  $mirar('B no ve el pedido de A', !$pedidoA->access('view', $userB));
  $mirar('A no ve un pedido de compra aunque el proveedor sea su empresa', !$compra->access('view', $userA));
  $mirar('quien no tiene empresa no ve el pedido de A', !$pedidoA->access('view', $userSuelto));
  $mirar('A no puede editar su pedido (aun)', !$pedidoA->access('update', $userA));
  $mirar('A no puede borrar su pedido', !$pedidoA->access('delete', $userA));
  $mirar('A ve la linea de su pedido', $lineaA->access('view', $userA));
  $mirar('A no ve la linea del pedido de B', !$lineaB->access('view', $userA));
  $mirar('el dueno de fabrica sigue viendo el pedido de B', $pedidoB->access('view', $dueno));
  $mirar('el mixto supervisor+Customer sigue viendo el pedido de B', $pedidoB->access('view', $userMixto));
  $mirar('A no puede crear un pedido', !$gestor->getAccessControlHandler('tec_order')->createAccess('tec_sales_order', $userA));
  $mirar('A no puede crear un pedido de compra', !$gestor->getAccessControlHandler('tec_order')->createAccess('tec_purchase_order', $userA));

  print "\n3. Ver empresas, marcas y productos\n";
  print str_repeat('-', 70) . "\n";
  $mirar('A ve su ficha de empresa', $orgA->access('view', $userA));
  $mirar('A no ve la ficha de B', !$orgB->access('view', $userA));
  $mirar('A ve a su persona de contacto', $personaA->access('view', $userA));
  $mirar('A no ve a la persona de B', !$personaB->access('view', $userA));
  $mirar('A ve su producto', $prodA->access('view', $userA));
  $mirar('A no ve el producto de B', !$prodB->access('view', $userA));
  $mirar('A ve el color de su producto', $colorA->access('view', $userA));
  $mirar('A no ve el color del producto de B', !$colorB->access('view', $userA));
  $mirar('A no ve la marca como termino (hace falta access content, y no lo tiene)', !$marcaA->access('view', $userA));
  $mirar('A no ve la marca de B', !$marcaB->access('view', $userA));

  $nodo = $gestor->getStorage('node')->load(1);
  if ($nodo) {
    $mirar('A no ve un nodo', !$nodo->access('view', $userA));
  }
  $medias = $gestor->getStorage('media')->getQuery()->accessCheck(FALSE)->range(0, 1)->execute();
  if ($medias) {
    $media = $gestor->getStorage('media')->load(reset($medias));
    $mirar('A no ve un media (Authenticated si puede; el gancho lo quita)', !$media->access('view', $userA));
  }
  $inventarios = $gestor->getStorage('tec_inventory')->getQuery()->accessCheck(FALSE)->range(0, 1)->execute();
  if ($inventarios) {
    $inventario = $gestor->getStorage('tec_inventory')->load(reset($inventarios));
    $mirar('A no ve inventario', !$inventario->access('view', $userA));
  }

  print "\n4. Consultas con accessCheck\n";
  print str_repeat('-', 70) . "\n";
  $cambiador->switchTo($userA);
  try {
    $vistos = $pedidos->getQuery()
      ->accessCheck(TRUE)
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $mirar('la lista de A solo trae su pedido de venta', array_values($vistos) === [(string) $pedidoA->id()], implode(',', $vistos));

    $compras = $pedidos->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'tec_purchase_order')
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $mirar('la lista de A no trae compras', $compras === []);

    $lineasVistas = $lineas->getQuery()
      ->accessCheck(TRUE)
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $mirar('la lista de lineas de A solo trae la suya', array_values($lineasVistas) === [(string) $lineaA->id()], implode(',', $lineasVistas));

    $crmVistos = $crm->getQuery()
      ->accessCheck(TRUE)
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $crmIds = array_map('strval', $crmVistos);
    sort($crmIds);
    $esperados = [(string) $orgA->id(), (string) $personaA->id()];
    sort($esperados);
    $mirar('la lista de CRM de A es su empresa y su persona', $crmIds === $esperados, implode(',', $crmIds));

    $prods = $productos->getQuery()
      ->accessCheck(TRUE)
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $prodIds = array_map('strval', $prods);
    sort($prodIds);
    $esperadosProd = [(string) $prodA->id(), (string) $colorA->id()];
    sort($esperadosProd);
    $mirar('la lista de productos de A es su producto y su color', $prodIds === $esperadosProd, implode(',', $prodIds));

    $sinFiltro = $pedidos->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $mirar('accessCheck FALSE no se reescribe aunque el usuario sea A', count($sinFiltro) === 3, (string) count($sinFiltro));
  }
  finally {
    $cambiador->switchBack();
  }

  $cambiador->switchTo($dueno);
  try {
    $todas = $pedidos->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $mirar('el dueno con accessCheck FALSE sigue viendo los tres pedidos', count($todas) === 3, (string) count($todas));
  }
  finally {
    $cambiador->switchBack();
  }

  print "\n5. El rol Customer\n";
  print str_repeat('-', 70) . "\n";
  $rol = $gestor->getStorage('user_role')->load(CustomerCompany::ROLE);
  $permisos = $rol ? $rol->getPermissions() : [];
  $prohibidos = [
    'access content',
    'access tec start page',
    'view the administration theme',
    'create tec_product entities of bundle tec_product',
    'create tec_order entities of bundle tec_sales_order',
    'create tec_inventory entities of bundle tec_inventory_transaction',
    'update any tec_order entities of bundle tec_sales_order',
    'delete any tec_order entities of bundle tec_sales_order',
    'view any tec_order entities of bundle tec_purchase_order',
    'view any tec_inventory entities of bundle tec_inventory_item',
    'administer eck entity types',
    'access toolbar',
    'masquerade as any user',
  ];
  foreach ($prohibidos as $permiso) {
    $mirar("Customer no tiene '$permiso'", !in_array($permiso, $permisos, TRUE));
  }
  $obligatorios = [
    'view any tec_order entities of bundle tec_sales_order',
    'view any tec_line_item entities of bundle tec_sales_order_line_item',
    'view any tec_crm entities of bundle tec_contact_organization',
    'view any tec_crm entities of bundle tec_contact_person',
    'view any tec_product entities of bundle tec_product',
    'view any tec_product entities of bundle tec_color_variation',
    'view any tec_product entities of bundle tec_size_variation',
  ];
  foreach ($obligatorios as $permiso) {
    $mirar("Customer tiene '$permiso'", in_array($permiso, $permisos, TRUE));
  }
  $soloEsos = $permisos;
  $esperados = $obligatorios;
  sort($soloEsos);
  sort($esperados);
  $mirar('Customer no tiene nada mas que esos 7 permisos', $soloEsos === $esperados, implode(', ', $permisos));
  $autenticado = $gestor->getStorage('user_role')->load('authenticated');
  $mirar('Authenticated tampoco da el tema de administracion', $autenticado && !in_array('view the administration theme', $autenticado->getPermissions(), TRUE));
  $mirar('A no tiene el tema de administracion', !$userA->hasPermission('view the administration theme'));
  $mirar('A no tiene access content, no entra en /start ni en inventario', !$userA->hasPermission('access content'));
  $mirar('A no tiene la portada de fabrica', !$userA->hasPermission('access tec start page'));
  $mirar('el mixto supervisor si tiene la portada', $userMixto->hasPermission('access tec start page'));

  print "\n6. El campo de la cuenta\n";
  print str_repeat('-', 70) . "\n";
  $campo = $userA->get(CustomerCompany::PERSON_FIELD);
  $mirar('el campo existe en el usuario', $userA->hasField(CustomerCompany::PERSON_FIELD));
  $mirar('A no puede ver el enlace (lo pone la fabrica)', $campo->access('view', $userA) === FALSE);
  $mirar('A no puede cambiar el enlace', $campo->access('edit', $userA) === FALSE);
  $mirar('el dueno si puede cambiar el enlace', $campo->access('edit', $dueno) === TRUE);

}
catch (\Throwable $e) {
  $problemas++;
  print "\nEXCEPCION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

print "\n";
$barrer();
print $problemas === 0
  ? "Todo bien. El candado sostiene las dos empresas aparte.\n\n"
  : "Fallaron $problemas comprobaciones.\n\n";
if ($problemas !== 0) {
  throw new \RuntimeException("Fallaron $problemas comprobaciones del candado.");
}
