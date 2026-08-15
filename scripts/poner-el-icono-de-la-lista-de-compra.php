<?php

/**
 * @file
 * Le pone a la portada el icono que falta: el de la lista de compra.
 *
 *   php vendor\bin\drush.php scr scripts/poner-el-icono-de-la-lista-de-compra.php
 *   php vendor\bin\drush.php scr scripts/poner-el-icono-de-la-lista-de-compra.php -- de-verdad
 *
 * Sin esto /purchase existe pero no hay manera de llegar: los doce iconos de
 * /start son nodos de tipo tec_landing_page con un destino, una imagen y un
 * orden, y la pantalla nueva no tiene el suyo. Una pantalla a la que solo se
 * llega escribiendo la direccion a mano es una pantalla que nadie usa.
 *
 * Sin "de-verdad" solo cuenta lo que haria.
 *
 * Ojo al desplegar: la imagen vive en sites/default/private, que esta fuera de
 * git a proposito, igual que los otros once iconos. Hay que subirla al servidor
 * a mano o el icono sale roto.
 */

const ICONO = 'private://2026-08/isometric-purchase-100.png';
const TITULO = 'Purchase List';
const DESTINO = 'internal:/purchase';
const ORDEN = 15;

$deVerdad = (bool) array_intersect(['de-verdad', '--de-verdad'], $extra ?? []);

$gestor = \Drupal::entityTypeManager();
$nodos = $gestor->getStorage('node');

echo "\n";

// 1. La imagen tiene que estar en su sitio antes de registrarla.
$ruta = \Drupal::service('file_system')->realpath(ICONO);
if (!$ruta || !file_exists($ruta)) {
  printf("  No esta la imagen en %s\n", ICONO);
  printf("  Copiala ahi y vuelve a lanzar esto.\n\n");
  return;
}
printf("  imagen: %s (%s KB)\n", ICONO, number_format(filesize($ruta) / 1024, 0));

// 2. Si ya hay un icono apuntando a la pantalla, no se duplica.
$existentes = $nodos->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_landing_page')
  ->condition('field_tec_target.uri', DESTINO)
  ->execute();
if ($existentes) {
  printf("  Ya hay un icono apuntando a %s (ficha %s). No se toca nada.\n\n", DESTINO, implode(', ', $existentes));
  return;
}

// 3. El fichero como entidad, que es lo que el campo de imagen guarda.
$ficheros = $gestor->getStorage('file');
$fids = $ficheros->getQuery()->accessCheck(FALSE)->condition('uri', ICONO)->execute();
if ($fids) {
  $fichero = $ficheros->load(reset($fids));
  printf("  la imagen ya estaba registrada, ficha de fichero %s\n", $fichero->id());
}
else {
  printf("  la imagen no esta registrada todavia%s\n", $deVerdad ? ', se registra' : '');
  $fichero = NULL;
}

printf("\n  icono nuevo: \"%s\" -> %s, orden %d\n", TITULO, DESTINO, ORDEN);

if (!$deVerdad) {
  echo "\n  Simulacion. Con -- de-verdad se crea.\n\n";
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

$nodo = $nodos->create([
  'type' => 'tec_landing_page',
  'title' => TITULO,
  'status' => 1,
  'uid' => 1,
  'field_tec_target' => ['uri' => DESTINO],
  'field_tec_icon' => ['target_id' => $fichero->id()],
  'field_icon_order' => ORDEN,
]);
$nodo->save();

printf("  creado el icono, ficha %s\n\n", $nodo->id());
