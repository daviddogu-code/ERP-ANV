<?php

/**
 * @file
 * El pedido del portal: el cliente pide lo de su marca, no lo de la otra.
 *
 *   php vendor/bin/drush.php scr scripts/el-pedido-del-portal.php
 *
 * Paso 5. Dos empresas, dos marcas, un producto con talla cada una. A rellena
 * el formulario con cantidad en su talla y tambien en la de B. Solo nace un
 * pedido de A, con su linea. Los contadores de numero de pedido se restauran.
 */

use Drupal\Core\Form\FormState;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\Form\PlaceOrderForm;
use Drupal\tec_production\OrderNumber;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZPEDIDO';

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

print "\nEl pedido del portal\n";
print str_repeat('=', 70) . "\n\n";

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
    'field_tec_customer_code' => 'ZZOA',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marcaA->id()],
    'field_tec_address' => [['country_code' => 'TH']],
  ]));
  $orgB = $guardar($crm->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' empresa B',
    'field_tec_customer_code' => 'ZZOB',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_brands' => [$marcaB->id()],
    'field_tec_address' => [['country_code' => 'IT']],
  ]));

  $personaA = $guardar($crm->create([
    'type' => 'tec_contact_person',
    'title' => MARCA . ' persona A',
    'field_tec_customer_code' => 'ZZOA-P',
    'field_tec_contact_type' => $tipoCliente->id(),
    'field_tec_works_at' => $orgA->id(),
  ]));

  $pass = 'zzpedido-pass-' . bin2hex(random_bytes(4));
  $userA = User::create([
    'name' => MARCA . '_a',
    'mail' => 'zzpedido-a@example.com',
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

  print "1. El formulario /my/order/new\n";
  print str_repeat('-', 70) . "\n";
  $pagina = $pedir('/my/order/new', $userA);
  $mirar('A entra a pedir', $pagina['code'] === 200, (string) $pagina['code']);
  $mirar('A ve su producto', stripos($pagina['body'], MARCA . ' producto A') !== FALSE);
  $mirar('A no ve el producto de B', stripos($pagina['body'], MARCA . ' producto B') === FALSE);
  $mirar('una sola lista, no fieldset de marca', !str_contains($pagina['body'], 'b' . $marcaA->id()) && substr_count($pagina['body'], 'tec-portal__table') === 1);
  $mirar('un solo Total, no Grand total', str_contains($pagina['body'], '>Total<') && !str_contains($pagina['body'], 'Grand total'));
  $mirar('el formulario trae la talla de A', str_contains($pagina['body'], 'lines[' . $tallaA->id() . ']') && str_contains($pagina['body'], (string) $tallaA->id()));
  $mirar('el formulario no trae la talla de B', !str_contains($pagina['body'], 'lines[' . $tallaB->id() . ']'));
  $mirar('el listado /my ofrece pedir', str_contains($pedir('/my', $userA)['body'], 'Place an order'));
  $mirar('A sigue sin createAccess de ECK', !$gestor->getAccessControlHandler('tec_order')->createAccess('tec_sales_order', $userA));

  $dueno = $gestor->getStorage('user')->load(1);
  $mirar('la fabrica no usa /my/order/new', !PlaceOrderForm::access($dueno)->isAllowed());

  print "\n2. Colocar el pedido\n";
  print str_repeat('-', 70) . "\n";
  $antes = $pedidos->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_tec_customer', $orgA->id())
    ->execute();

  $cambiador->switchTo($userA);
  try {
    $formulario = PlaceOrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado);
    $estado->setValue('lines', [
      $tallaA->id() => ['qty' => 4],
      $tallaB->id() => ['qty' => 9],
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
  $mirar('nacio un pedido de A', count($nuevos) === 1, (string) count($nuevos));

  $pedido = $nuevos ? $pedidos->load(reset($nuevos)) : NULL;
  if ($pedido) {
    $basura[] = $pedido;
    $idsCliente = [];
    foreach ($pedido->get('field_tec_customer') as $item) {
      $idsCliente[] = (int) $item->target_id;
    }
    $mirar('el pedido es de la empresa A, no de B', $idsCliente === [(int) $orgA->id()], implode(',', $idsCliente));
    $mirar('el pedido tiene numero (no titulo vacio)', OrderNumber::isNumber($pedido->label()), (string) $pedido->label());
    $lineas = $pedido->get('field_tec_line_items')->referencedEntities();
    $mirar('tiene una sola linea (la de B no cuelo)', count($lineas) === 1, (string) count($lineas));
    if ($lineas) {
      $linea = reset($lineas);
      $basura[] = $linea;
      $mirar('la linea es la talla de A', (int) $linea->get('field_tec_size_variation')->target_id === (int) $tallaA->id());
      $mirar('la cantidad es 4', (int) $linea->get('field_tec_quantity')->value === 4);
      $mirar('el total es 400', (float) $linea->get('field_tec_line_item_total_number')->value === 400.0);
    }

    $deB = $pedidos->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_tec_customer', $orgB->id())
      ->condition('title', MARCA, 'STARTS_WITH')
      ->execute();
    $mirar('B no recibio un pedido colado', $deB === []);

    $detalle = $pedir('/my/order/' . $pedido->id(), $userA);
    $mirar('A ve el pedido colocado en /my/order', $detalle['code'] === 200 && str_contains($detalle['body'], '4'));
  }

  print "\n3. Cero no crea pedido\n";
  print str_repeat('-', 70) . "\n";
  $antesCero = $pedidos->getQuery()->accessCheck(FALSE)->condition('field_tec_customer', $orgA->id())->execute();
  $cambiador->switchTo($userA);
  try {
    $formulario = PlaceOrderForm::create(\Drupal::getContainer());
    $estado = new FormState();
    $construido = $formulario->buildForm([], $estado);
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
}
catch (\Throwable $e) {
  $problemas++;
  print "\nEXCEPCION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

print "\n";
$barrer();
print $problemas === 0
  ? "Todo bien. El cliente pide lo suyo y no lo de la otra empresa.\n\n"
  : "Fallaron $problemas comprobaciones.\n\n";
if ($problemas !== 0) {
  throw new \RuntimeException("Fallaron $problemas comprobaciones del pedido.");
}
