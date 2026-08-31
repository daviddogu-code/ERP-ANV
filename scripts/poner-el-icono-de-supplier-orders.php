<?php

/**
 * @file
 * Le pone a la portada el icono de Supplier Orders, el archivo de compras.
 *
 *   php vendor\bin\drush.php scr scripts/poner-el-icono-de-supplier-orders.php
 *   php vendor\bin\drush.php scr scripts/poner-el-icono-de-supplier-orders.php -- --de-verdad
 *
 * La pantalla vive en /supplier-orders y el menu ya lleva a ella. Sin el icono
 * en /start solo se llega por la barra: PO CONTROL es la cola, esta es donde
 * siguen los pedidos cuando salen de ella.
 *
 * Sin "--de-verdad" solo cuenta lo que haria.
 *
 * La imagen vive en private://, fuera de git. Hay que copiarla al servidor o
 * el icono sale roto.
 */

use Drupal\tec_production\EventSubscriber\QueueTileRedirectSubscriber;

const ICONO = 'private://2026-08/isometric-supplier-orders-100.png';
const TITULO = 'Supplier Orders';
const DESTINO = 'internal:/supplier-orders';
const ORDEN = 17;
const AJUSTE = 'supplier_orders_tile_nid';

$deVerdad = (bool) array_intersect(['de-verdad', '--de-verdad'], $extra ?? []);

$gestor = \Drupal::entityTypeManager();
$nodos = $gestor->getStorage('node');

echo "\n";

$ruta = \Drupal::service('file_system')->realpath(ICONO);
if (!$ruta || !file_exists($ruta)) {
  printf("  No esta la imagen en %s\n", ICONO);
  printf("  Copiala ahi y vuelve a lanzar esto.\n\n");
  return;
}
printf("  imagen: %s (%s KB)\n", ICONO, number_format(filesize($ruta) / 1024, 0));

$existentes = $nodos->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_landing_page')
  ->condition('field_tec_target.uri', DESTINO)
  ->execute();

$ficheros = $gestor->getStorage('file');
$fids = $ficheros->getQuery()->accessCheck(FALSE)->condition('uri', ICONO)->execute();
$fichero = $fids ? $ficheros->load(reset($fids)) : NULL;
printf("  imagen registrada: %s\n", $fichero ? 'ficha ' . $fichero->id() : 'todavia no');

if ($existentes) {
  $nodo = $nodos->load(reset($existentes));
  printf("  Ya hay un icono apuntando a %s (ficha %s, titulo \"%s\").\n", DESTINO, $nodo->id(), $nodo->label());
}
else {
  printf("  icono nuevo: \"%s\" -> %s, orden %d\n", TITULO, DESTINO, ORDEN);
}

if (!$deVerdad) {
  echo "\n  Simulacion. Con -- --de-verdad se crea o se engancha.\n\n";
  return;
}

if (!$fichero) {
  $fichero = $ficheros->create([
    'uri' => ICONO,
    'filename' => basename(ICONO),
    'status' => 1,
    'uid' => 1,
  ]);
  $fichero->save();
  printf("  registrada la imagen, ficha de fichero %s\n", $fichero->id());
}

if (!$existentes) {
  $nodo = $nodos->create([
    'type' => 'tec_landing_page',
    'title' => TITULO,
    'status' => 1,
    'uid' => 1,
    'field_tec_target' => ['uri' => DESTINO],
    'field_tec_icon' => ['target_id' => $fichero->id(), 'alt' => TITULO],
    'field_icon_order' => ORDEN,
  ]);
  $nodo->save();
  printf("  creado el icono, ficha %s\n", $nodo->id());
}
else {
  $nodo = $nodos->load(reset($existentes));
  $cambio = FALSE;
  if ($nodo->label() !== TITULO) {
    $nodo->setTitle(TITULO);
    $cambio = TRUE;
  }
  if ((int) $nodo->get('field_icon_order')->value !== ORDEN) {
    $nodo->set('field_icon_order', ORDEN);
    $cambio = TRUE;
  }
  if ($nodo->get('field_tec_icon')->isEmpty() || (int) $nodo->get('field_tec_icon')->target_id !== (int) $fichero->id()) {
    $nodo->set('field_tec_icon', ['target_id' => $fichero->id(), 'alt' => TITULO]);
    $cambio = TRUE;
  }
  if ($cambio) {
    $nodo->save();
    printf("  actualizado el icono, ficha %s\n", $nodo->id());
  }
}

if (!isset(QueueTileRedirectSubscriber::TILES[AJUSTE])) {
  print "  FALLO: falta " . AJUSTE . " en QueueTileRedirectSubscriber::TILES\n\n";
  return;
}

$ajustes = \Drupal::configFactory()->getEditable('tec_production.settings');
if ((int) $ajustes->get(AJUSTE) !== (int) $nodo->id()) {
  $ajustes->set(AJUSTE, (int) $nodo->id())->save();
  printf("  %s = %s\n", AJUSTE, $nodo->id());
}

print "\n";
