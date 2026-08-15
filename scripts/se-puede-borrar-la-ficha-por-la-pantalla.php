<?php

/**
 * @file
 * Prueba las piezas de la pantalla de Feeds sobre las fichas huerfanas.
 *
 * Uso: php vendor/bin/drush.php scr scripts/se-puede-borrar-la-ficha-por-la-pantalla.php
 *
 * El borrado por codigo de la ficha 5 no revienta -Feeds se traga el error y
 * sigue-, pero eso no es lo que hace una persona: una persona entra en la lista
 * de importaciones y pulsa Borrar. Interesa saber si esa via existe.
 *
 * Pedir las paginas por HTTP desde drush no sirve en este sitio, porque el
 * modulo masquerade se cae en cualquier peticion interna sin sesion de verdad y
 * devuelve 500 hasta para las fichas sanas. Asi que se prueban las dos piezas
 * que construyen la pantalla, sin pasar por el enrutador: la fila del listado y
 * el formulario de confirmacion del borrado.
 *
 * No toca nada: construir un formulario no lo envia.
 */

use Drupal\feeds\Entity\Feed;

$gestor = \Drupal::entityTypeManager();
$listado = $gestor->getListBuilder('feeds_feed');
$constructorFormularios = \Drupal::service('entity.form_builder');

\Drupal::currentUser()->setAccount(\Drupal\user\Entity\User::load(1));

print "\n";
printf("  %-6s %-26s %-30s %s\n", 'ficha', 'titulo', 'fila del listado', 'formulario de borrado');
print '  ' . str_repeat('-', 100) . "\n";

foreach ([4, 5, 6] as $id) {
  $ficha = Feed::load($id);

  if (!$ficha) {
    printf("  %-6s no existe\n", $id);
    continue;
  }

  // La fila del listado. FeedListBuilder::buildRow pide el tipo para escribir
  // su etiqueta en la columna "Type", y ahi es donde se rompe.
  try {
    $listado->buildRow($ficha);
    $fila = 'se construye';
  }
  catch (\Throwable $e) {
    $fila = 'SE CAE: ' . (new \ReflectionClass($e))->getShortName();
  }

  // El formulario de confirmacion del borrado.
  try {
    $constructorFormularios->getForm($ficha, 'delete');
    $formulario = 'se construye';
  }
  catch (\Throwable $e) {
    $formulario = 'SE CAE: ' . (new \ReflectionClass($e))->getShortName();
  }

  printf("  %-6s %-26s %-30s %s\n", $id, substr($ficha->label(), 0, 26), $fila, $formulario);
}

// Y el detalle del error, una sola vez, para dejarlo escrito.
print "\n  el error, tal cual:\n\n";
try {
  $listado->buildRow(Feed::load(5));
  print "    no hay error\n";
}
catch (\Throwable $e) {
  printf("    %s\n    %s\n    %s linea %d\n",
    get_class($e),
    $e->getMessage(),
    str_replace(\Drupal::root() . DIRECTORY_SEPARATOR, '', $e->getFile()),
    $e->getLine());
}

print "\n";
