<?php

/**
 * @file
 * Borra las fichas que deja el-color-nuevo-aparece-en-el-producto.php si el
 * delete al final no llega a correr.
 *
 *   php vendor/bin/drush.php scr scripts/borrar-pruebas-color-en-ficha.php
 */

$almacen = \Drupal::entityTypeManager()->getStorage('tec_product');

$ids = $almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('title', 'PRUEBA ', 'STARTS_WITH')
  ->execute();

if (!$ids) {
  print "Nada que borrar: no hay fichas PRUEBA.\n";
  return;
}

$fichas = $almacen->loadMultiple($ids);
$borradas = 0;
foreach ($fichas as $ficha) {
  $etiqueta = $ficha->bundle() . ' ' . $ficha->id() . ' ' . $ficha->label();
  try {
    $ficha->delete();
    $borradas++;
    print "  borrada $etiqueta\n";
  }
  catch (\Throwable $e) {
    print "  FALLO $etiqueta: " . $e->getMessage() . "\n";
  }
}
print "Borradas $borradas de " . count($fichas) . ".\n";
