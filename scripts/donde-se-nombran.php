<?php

/**
 * Dice en que tabla y columna aparece nombrado un fichero.
 *
 * La comprobacion cruzada de scripts/borrar-los-csv-de-importacion.php avisa de
 * que dos de los CSV aparecen escritos en algun sitio del contenido, pero no
 * dice donde. Sin saber donde no se puede decidir si es un enlace de descarga
 * que se rompe al borrar, o el rastro interno de un importador que no importa.
 *
 * No toca nada. Solo mira.
 *
 * Uso: php vendor/bin/drush.php scr scripts/donde-se-nombran.php
 */

const FICHAS = [159, 243];

const TABLAS_QUE_NO_MIRAR = [
  'watchdog', 'sessions', 'semaphore', 'queue', 'flood',
  'locales_source', 'locales_target', 'locale_file',
  'key_value_expire', 'search_index', 'search_dataset', 'search_total',
  'file_managed', 'file_usage', 'config',
];

$conexion = \Drupal::database();

print "\n";

$buscar = [];
foreach (\Drupal::entityTypeManager()->getStorage('file')->loadMultiple(FICHAS) as $fid => $fichero) {
  $uri = $fichero->getFileUri();
  $buscar[$fid] = basename($uri);
  print "  " . $fid . ": " . $uri . "\n";
  print "      se busca la cadena: " . basename($uri) . "\n";
}
print "\n";

$columnas = $conexion->query("
  SELECT TABLE_NAME, COLUMN_NAME
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND (DATA_TYPE IN ('text', 'mediumtext', 'longtext', 'blob', 'longblob')
         OR (DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH >= 255))
")->fetchAll();

foreach ($columnas as $columna) {
  $tabla = $columna->TABLE_NAME;
  $campo = $columna->COLUMN_NAME;
  if (in_array($tabla, TABLAS_QUE_NO_MIRAR, TRUE) || strpos($tabla, 'cache') === 0
    || strpos($tabla, 'feeds_') === 0) {
    continue;
  }
  try {
    $valores = $conexion->query("SELECT `" . $campo . "` FROM `" . $tabla . "`")->fetchCol();
  }
  catch (\Exception $e) {
    continue;
  }
  foreach ($valores as $valor) {
    if (!is_string($valor) || $valor === '') {
      continue;
    }
    foreach ($buscar as $fid => $aguja) {
      $donde = strpos($valor, $aguja);
      if ($donde === FALSE) {
        continue;
      }
      print "  --- " . $fid . " aparece en " . $tabla . "." . $campo . " ---\n\n";
      $desde = max(0, $donde - 220);
      print "  ..." . str_replace(["\n", "\r"], ' ', substr($valor, $desde, 460)) . "...\n\n";
    }
  }
}

// La tabla de valores sueltos guarda el estado de los importadores, y no tiene
// columna de texto larga en todas las versiones, asi que se mira aparte y por su
// nombre de coleccion.
print "  --- Colecciones de key_value donde aparece ---\n\n";
foreach ($conexion->select('key_value', 'k')->fields('k', ['collection', 'name', 'value'])->execute() as $fila) {
  foreach ($buscar as $fid => $aguja) {
    if (strpos((string) $fila->value, $aguja) !== FALSE) {
      print "  " . $fid . " en la coleccion '" . $fila->collection . "', clave '" . $fila->name . "'\n";
    }
  }
}
print "\n";
