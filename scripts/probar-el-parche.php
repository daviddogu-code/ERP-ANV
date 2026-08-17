<?php

/**
 * Comprueba que el parche de views_simple_math_field calla los avisos.
 *
 * El parche es de una linea y no cambia lo que la pagina hace: el valor acaba
 * siendo nulo con parche y sin el. Lo unico que cambia es que deja de escribir dos
 * avisos por cada campo vacio y por cada fila. Eso no se puede comprobar leyendo
 * el codigo, hay que pedir la pantalla y mirar el registro.
 *
 * Y para pedir la pantalla hace falta un pedido con lineas, que desde la limpieza
 * del 14 de agosto no existe. Asi que se fabrica uno, con una linea a proposito sin
 * cantidad, que es el caso que dispara los avisos. Al terminar se borra todo.
 *
 * Uso: php vendor/bin/drush.php scr scripts/probar-el-parche.php
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$etm = \Drupal::entityTypeManager();
$db = \Drupal::database();
$marca = 'PRUEBA PARCHE ' . date('His');
$creadas = [];

print "\n";

// -----------------------------------------------------------------------------
// 1. El pedido.
// -----------------------------------------------------------------------------
print "  --- 1. se fabrica un pedido ---\n\n";

$unidad = reset($etm->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)->condition('vid', 'tec_units')->range(0, 1)->execute());

$material = $etm->getStorage('taxonomy_term')->create([
  'vid' => 'tec_inventory',
  'name' => $marca . ' material',
  'field_tec_units' => $unidad ? ['target_id' => $unidad] : [],
  'field_tec_stock_level' => 0,
]);
$material->save();
$creadas[] = ['taxonomy_term', $material->id()];

// La talla con su BoM: sin esto el proceso de ECA que calcula el total no
// se despierta, y entonces la prueba no estaria pidiendo lo mismo que un pedido
// de verdad.
$talla = $etm->getStorage('tec_product')->create([
  'type' => 'tec_size_variation',
  'title' => $marca . ' talla',
]);
$talla->save();
$creadas[] = ['tec_product', $talla->id()];

$bom = $etm->getStorage('tec_inventory')->create([
  'type' => 'tec_bom_item',
  'title' => '',
  'field_tec_inventory' => ['target_id' => $material->id()],
  'field_tec_quantity' => 1.5,
  'field_tec_size_variation' => ['target_id' => $talla->id()],
]);
$bom->save();
$creadas[] = ['tec_inventory', $bom->id()];

$pedido = $etm->getStorage('tec_order')->create([
  'type' => 'tec_sales_order',
  'title' => $marca,
  'field_tec_order_status' => 'open',
]);
$pedido->save();
$creadas[] = ['tec_order', $pedido->id()];

// Dos lineas: una normal y otra sin cantidad. La segunda es la prueba; la primera
// esta para que la pantalla tenga algo que sumar y no sea un caso degenerado.
$lineas = [];
foreach ([['cantidad' => 4, 'que' => 'con cantidad'], ['cantidad' => NULL, 'que' => 'SIN cantidad']] as $i => $datos) {
  $linea = $etm->getStorage('tec_line_item')->create([
    'type' => 'tec_sales_order_line_item',
    'title' => $marca . ' linea ' . ($i + 1),
    'field_tec_price' => 12.5,
    'field_tec_quantity' => $datos['cantidad'],
    'field_tec_size_variation' => ['target_id' => $talla->id()],
    'field_tec_order' => ['target_id' => $pedido->id()],
  ]);
  $linea->save();
  $lineas[] = $linea;
  printf("      linea %d, %s (id %s)\n", $i + 1, $datos['que'], $linea->id());
}

$pedido->set('field_tec_line_items', array_map(fn($l) => ['target_id' => $l->id()], $lineas));
$pedido->save();

printf("\n      pedido %s con %d lineas\n\n", $pedido->id(), count($lineas));

// -----------------------------------------------------------------------------
// 2. Se pide la pantalla.
// -----------------------------------------------------------------------------
print "  --- 2. se pide la pantalla de lineas ---\n\n";

$marcaRegistro = (int) $db->select('watchdog', 'w')->fields('w', ['wid'])
  ->orderBy('wid', 'DESC')->range(0, 1)->execute()->fetchField();

$kernel = \Drupal::service('http_kernel');
$anterior = \Drupal::currentUser()->getAccount();
\Drupal::currentUser()->setAccount($etm->getStorage('user')->load(1));

$rutas = ['/o/draft/' . $pedido->id(), '/o/pf/' . $pedido->id() . '/print'];
$codigos = [];

foreach ($rutas as $ruta) {
  $peticion = Request::create($ruta, 'GET');
  try {
    $peticion->setSession(\Drupal::service('session'));
  }
  catch (\Throwable $e) {
  }

  $t0 = microtime(TRUE);
  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    $codigo = $respuesta->getStatusCode();
    $tamano = strlen($respuesta->getContent());
  }
  catch (\Throwable $e) {
    $codigo = 'EXCEPCION: ' . $e->getMessage();
    $tamano = 0;
  }

  $codigos[$ruta] = $codigo;
  printf("      %-28s %-12s %6d ms  %d KB\n", $ruta, $codigo,
    (microtime(TRUE) - $t0) * 1000, $tamano / 1024);
}

\Drupal::currentUser()->setAccount($anterior);

print "\n";

// -----------------------------------------------------------------------------
// 3. Que ha dejado en el registro.
// -----------------------------------------------------------------------------
print "  --- 3. el registro despues de pedirla ---\n\n";

$nuevos = $db->select('watchdog', 'w')
  ->fields('w', ['type', 'message', 'variables', 'severity'])
  ->condition('wid', $marcaRegistro, '>')
  ->execute()
  ->fetchAll();

$deLaSuma = 0;
$otros = [];

foreach ($nuevos as $entrada) {
  $variables = @unserialize($entrada->variables) ?: [];
  $texto = strtr($entrada->message, is_array($variables) ? $variables : []);

  if (str_contains($texto, 'SimpleMathField')
    || str_contains($texto, 'Undefined array key 0')
    || str_contains($texto, 'array offset on null')) {
    $deLaSuma++;
    continue;
  }

  $otros[] = '[' . $entrada->type . '] ' . mb_substr(preg_replace('/\s+/', ' ', $texto), 0, 150);
}

printf("      entradas nuevas en total: %d\n", count($nuevos));
printf("      de ellas, del campo de sumas: %d\n\n", $deLaSuma);

if ($otros) {
  print "      las demas:\n";
  foreach (array_slice($otros, 0, 12) as $linea) {
    print '          ' . $linea . "\n";
  }
  if (count($otros) > 12) {
    printf("          ... y %d mas\n", count($otros) - 12);
  }
  print "\n";
}

print $deLaSuma === 0
  ? "      El parche funciona: ni un aviso del campo de sumas.\n\n"
  : "      SIGUE AVISANDO: el parche no ha servido.\n\n";

// -----------------------------------------------------------------------------
// 4. Se borra todo.
// -----------------------------------------------------------------------------
// Al reves del orden de creacion, y quitando primero las banderas y los
// BoM de linea que ECA crea por su cuenta al guardar una linea.
print "  --- 4. se borra lo fabricado ---\n\n";

$dePropina = [];
foreach ($lineas as $linea) {
  $linea = $etm->getStorage('tec_line_item')->loadUnchanged($linea->id());
  if (!$linea) {
    continue;
  }
  $dePropina = array_merge($dePropina,
    array_column($linea->get('field_tec_line_item_bom')->getValue(), 'target_id'));
  foreach ($etm->getStorage('flagging')->loadByProperties(['entity_id' => $linea->id()]) as $bandera) {
    $bandera->delete();
  }
  $linea->delete();
}

foreach ($etm->getStorage('tec_inventory')->loadMultiple($dePropina) as $sobra) {
  $sobra->delete();
}
printf("      lineas y %d BoM de linea que creo ECA\n", count($dePropina));

foreach (array_reverse($creadas) as [$tipo, $id]) {
  foreach ($etm->getStorage('flagging')->loadByProperties(['entity_id' => $id]) as $bandera) {
    $bandera->delete();
  }
  if ($e = $etm->getStorage($tipo)->load($id)) {
    $e->delete();
  }
}
print "      el resto\n\n";

// Que no quede nada: es el mismo recuento que vigila comprobacion.php.
foreach (['tec_order', 'tec_line_item', 'tec_product', 'tec_inventory'] as $tipo) {
  $n = (int) $etm->getStorage($tipo)->getQuery()->accessCheck(FALSE)->count()->execute();
  printf("      %-16s %d\n", $tipo, $n);
}
$n = (int) $etm->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)->condition('vid', 'tec_inventory')->count()->execute();
printf("      %-16s %d\n\n", 'materiales', $n);
