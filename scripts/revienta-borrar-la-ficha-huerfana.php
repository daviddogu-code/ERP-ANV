<?php

/**
 * @file
 * Comprueba que borrar la ficha 5 por la via de Drupal revienta.
 *
 * Uso: php vendor/bin/drush.php scr scripts/revienta-borrar-la-ficha-huerfana.php
 *
 * Antes de borrar dos filas a mano por SQL hay que estar seguro de que no es un
 * rodeo. La ficha 5 declara el tipo de importador tec_products_csv_importer, que
 * ya no existe, y el borrado de Feeds empieza pidiendo el tipo para avisar a sus
 * complementos. Aqui se prueba paso a paso: cargarla, mirar su tipo, contar sus
 * elementos y borrarla.
 *
 * El borrado se hace dentro de una transaccion que se deshace siempre, gane o
 * pierda, para que la prueba no deje la base a medias. En MySQL con InnoDB eso
 * devuelve las filas a su sitio.
 */

use Drupal\feeds\Entity\Feed;

const FICHA = 5;

$bd = \Drupal::database();

print "\n";
printf("  --- la ficha %d, paso a paso ---\n\n", FICHA);

$antes = (int) $bd->select('feeds_feed')->countQuery()->execute()->fetchField();
printf("      filas en feeds_feed antes: %d\n\n", $antes);

// ---------------------------------------------------------------------------
// Paso 1. Cargarla.
// ---------------------------------------------------------------------------
$ficha = NULL;
try {
  $ficha = Feed::load(FICHA);
  printf("      1. cargar          ...  %s\n", $ficha ? 'sale la ficha "' . $ficha->label() . '"' : 'devuelve nulo');
}
catch (\Throwable $e) {
  printf("      1. cargar          ...  REVIENTA: %s: %s\n", get_class($e), $e->getMessage());
}

if (!$ficha) {
  print "\n      sin ficha no hay nada mas que probar\n\n";
  return;
}

// ---------------------------------------------------------------------------
// Paso 2. Su tipo de importador.
// ---------------------------------------------------------------------------
printf("      2. bundle()        ...  %s\n", $ficha->bundle());

try {
  $tipo = $ficha->getType();
  printf("      3. getType()       ...  %s\n", $tipo ? $tipo->id() : 'devuelve NULO, el tipo no existe');
}
catch (\Throwable $e) {
  printf("      3. getType()       ...  REVIENTA: %s: %s\n", get_class($e), $e->getMessage());
  $tipo = NULL;
}

// ---------------------------------------------------------------------------
// Paso 3. Lo que la pantalla de listado necesita de cada ficha.
// ---------------------------------------------------------------------------
foreach (['getItemCount' => 'contar elementos', 'getImportedTime' => 'ultima importacion'] as $metodo => $que) {
  try {
    $valor = $ficha->{$metodo}();
    printf("      4. %-16s...  %s = %s\n", $metodo . '()', $que, is_scalar($valor) ? $valor : gettype($valor));
  }
  catch (\Throwable $e) {
    printf("      4. %-16s...  REVIENTA: %s: %s\n", $metodo . '()', get_class($e), $e->getMessage());
  }
}

// ---------------------------------------------------------------------------
// Paso 4. Borrarla. Dentro de una transaccion que se deshace.
// ---------------------------------------------------------------------------
print "\n      5. delete()        ...  ";

$transaccion = $bd->startTransaction();
try {
  $ficha->delete();
  print "no revienta: la ficha se borra\n";
  $durante = (int) $bd->select('feeds_feed')->countQuery()->execute()->fetchField();
  printf("         filas en feeds_feed dentro de la transaccion: %d\n", $durante);
}
catch (\Throwable $e) {
  printf("REVIENTA: %s\n", get_class($e));
  printf("         mensaje: %s\n", $e->getMessage());
  printf("         en:      %s linea %d\n", str_replace(\Drupal::root() . DIRECTORY_SEPARATOR, '', $e->getFile()), $e->getLine());

  // La primera linea de la pila que sea de Feeds o del nucleo dice quien pidio
  // el tipo que no existe.
  foreach (explode("\n", $e->getTraceAsString()) as $linea) {
    if (str_contains($linea, 'feeds') || str_contains($linea, 'Entity')) {
      printf("         pila:    %s\n", trim(str_replace(\Drupal::root() . DIRECTORY_SEPARATOR, '', $linea)));
    }
  }
}
finally {
  // Se deshace siempre. Esta prueba no cambia nada.
  $transaccion->rollBack();
  unset($transaccion);
}

$despues = (int) $bd->select('feeds_feed')->countQuery()->execute()->fetchField();
printf("\n      filas en feeds_feed despues de deshacer: %d %s\n\n",
  $despues,
  $despues === $antes ? '(igual que antes: la prueba no ha cambiado nada)' : '(OJO: no ha vuelto a su sitio)');
