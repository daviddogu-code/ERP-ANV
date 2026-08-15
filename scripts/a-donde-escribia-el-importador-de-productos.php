<?php

/**
 * @file
 * Busca en los volcados viejos las filas de tec_gui__feeds_item.
 *
 * Uso: php vendor/bin/drush.php scr scripts/a-donde-escribia-el-importador-de-productos.php
 *
 * El volcado del 15 de agosto solo prueba que la tabla tec_gui__feeds_item
 * existia, no que tuviera nada dentro: la noche del 14 se vacio el contenido de
 * pruebas y con el se fueron sus filas. Pero hay dos volcados anteriores a ese
 * vaciado, del 10 y del 11 de agosto, y ahi la tabla tiene que estar llena si de
 * verdad las fichas 5 y 6 escribian en tec_gui.
 *
 * La columna que decide es feeds_item_target_id: Feeds guarda ahi el fid de la
 * ficha que creo cada entidad. Si en tec_gui__feeds_item aparecen los fid 5 y 6,
 * la pista queda confirmada y las 28 + 92 fichas que dicen haber importado eran
 * productos del sistema viejo, ya retirado. No toca nada.
 */

$raiz = \Drupal::root();

// Los tres volcados que hay, del mas nuevo al mas viejo. Los dos ultimos son de
// antes del vaciado del 14 de agosto, y son los que valen para esto.
$volcados = [
  $raiz . '/backups/actatec_antes_de_quitar_gui_2026-08-15.sql.gz',
  $raiz . '/backupsactatec_before_so_cleanup_2026-08-11_121346.sql.gz',
  'C:/laragon/backup/tec-20260810-015026/actatec.sql.gz',
];

// Las tablas feeds_item de cada tipo de entidad. La pregunta es cuales existen
// en cada volcado y cuales llevan filas.
$tablas = [
  'tec_gui__feeds_item',
  'tec_product__feeds_item',
  'tec_inventory__feeds_item',
  'taxonomy_term__feeds_item',
];

print "\n";

foreach ($volcados as $volcado) {
  printf("  === %s ===\n\n", basename($volcado));

  if (!is_file($volcado)) {
    print "      no esta en disco\n\n";
    continue;
  }

  printf("      %s MB\n\n", number_format(filesize($volcado) / 1048576, 1));

  $creadas = [];
  $inserciones = [];
  // De cada insercion se guarda un trozo, para poder leer los target_id.
  $muestras = [];
  $tipoImportador = [];

  $gz = gzopen($volcado, 'rb');
  $resto = '';
  while (!gzeof($gz)) {
    $trozo = $resto . gzread($gz, 1048576);
    $lineas = explode("\n", $trozo);
    $resto = array_pop($lineas);
    foreach ($lineas as $linea) {
      foreach ($tablas as $tabla) {
        if (str_contains($linea, '`' . $tabla . '`')) {
          if (str_starts_with($linea, 'CREATE TABLE')) {
            $creadas[$tabla] = TRUE;
          }
          if (str_starts_with($linea, 'INSERT INTO')) {
            $inserciones[$tabla] = ($inserciones[$tabla] ?? 0) + 1;
            if (!isset($muestras[$tabla])) {
              $muestras[$tabla] = substr($linea, 0, 1400);
            }
          }
        }
      }
      // Cualquier rastro del tipo de importador desaparecido que no sea la
      // propia fila de la ficha.
      if (str_contains($linea, 'tec_products_csv_importer') && !str_starts_with($linea, 'INSERT INTO `feeds_feed`')) {
        $donde = preg_match('/^INSERT INTO `([a-z_0-9]+)`/', $linea, $m) ? $m[1] : substr($linea, 0, 60);
        $tipoImportador[$donde] = ($tipoImportador[$donde] ?? 0) + 1;
      }
      // La linea del batch guarda la operacion de borrado que alguien lanzo
      // sobre esas fichas. Dentro puede estar el nombre de la clase que hacia
      // el trabajo, y esa clase dice a que entidad apuntaba.
      if (str_starts_with($linea, 'INSERT INTO `batch`') && str_contains($linea, 'products')) {
        foreach (['tec_gui', 'tec_product', 'taxonomy_term', 'entity:'] as $pista) {
          if (str_contains($linea, $pista)) {
            $tipoImportador['batch menciona ' . $pista] = ($tipoImportador['batch menciona ' . $pista] ?? 0) + 1;
          }
        }
      }
    }
  }
  gzclose($gz);

  printf("      %-28s %-12s %s\n", 'tabla', 'existe', 'inserciones');
  print '      ' . str_repeat('-', 60) . "\n";
  foreach ($tablas as $tabla) {
    printf("      %-28s %-12s %s\n",
      $tabla,
      isset($creadas[$tabla]) ? 'si' : 'NO',
      $inserciones[$tabla] ?? 0);
  }

  foreach ($muestras as $tabla => $muestra) {
    printf("\n      primera insercion de %s:\n        %s\n", $tabla, $muestra);
  }

  if ($tipoImportador) {
    print "\n      otros rastros de tec_products_csv_importer:\n";
    foreach ($tipoImportador as $donde => $n) {
      printf("        %-30s %d\n", $donde, $n);
    }
  }

  print "\n";
}

print "\n";
