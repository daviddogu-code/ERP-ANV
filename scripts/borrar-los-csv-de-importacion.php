<?php

/**
 * Borra los CSV y Excel de las importaciones de prueba.
 *
 * Son los listados de materias primas y los BoM que se subieron entre
 * marzo de 2024 y julio de 2025 para probar los dos importadores: un fichero de
 * 857 materias con dieciocho columnas, y BoM uno por producto y talla.
 * Todo aquello era ensayo, y se borra.
 *
 * Lo que NO toca, aunque tampoco sean fotos: las fuentes tipograficas del tema y
 * las dos hojas de estilo. Se filtra por extension y no por tipo de fichero,
 * porque unos cuantos Excel quedaron registrados como CSV y otros como
 * "octet-stream", igual que las fuentes.
 *
 * Antes de borrar comprueba dos cosas, y si alguna no sale limpia se para sin
 * tocar nada:
 *
 * 1. Quien los usa. Cuatro tienen usos registrados y lo esperable es que sean
 *    los tres importadores, que guardan el ultimo fichero subido. Si aparece
 *    cualquier otro tipo de dueno, hay algo que no sabiamos y mejor mirarlo.
 * 2. Si alguno esta nombrado dentro del contenido. Un enlace de descarga puesto
 *    a mano en un texto no cuenta como uso, y borrar el fichero dejaria el
 *    enlace roto sin que nada avise.
 *
 * Los ficheros estan todos en la copia de
 * C:\laragon\backups\ficheros-antes-del-borrado-20260814, hecha esta misma
 * noche antes del borrado de las fotos, asi que esto es reversible.
 *
 * Uso: php vendor/bin/drush.php scr scripts/borrar-los-csv-de-importacion.php
 */

const EXTENSIONES = ['csv', 'xls', 'xlsx', 'tsv'];

// Duenos de uso que se dan por buenos: los importadores. Feeds no registra el
// uso a nombre del importador, sino del tipo de complemento que lo lee, y para
// un lector de ficheros eso es `fetcher`; el identificador es el UUID del
// importador. Esta en UploadFetcher::deleteFile(), que llama al servicio de usos
// con `$this->pluginType()`.
const DUENOS_ESPERADOS = ['fetcher'];

const TABLAS_QUE_NO_MIRAR = [
  'watchdog', 'sessions', 'semaphore', 'queue', 'flood',
  'locales_source', 'locales_target', 'locale_file',
  'key_value_expire', 'search_index', 'search_dataset', 'search_total',
  'file_managed', 'file_usage', 'config',
  // Los lotes a medias de abril y mayo de 2024, de importaciones que nunca
  // terminaron. Ahi dentro esta el nombre del fichero que se subio, y no
  // significa que nadie lo use: es basura que `system_cron` se lleva cuando el
  // cron eche a andar. Comprobado uno por uno con scripts/donde-se-nombran.php.
  'batch',
];

$etm = \Drupal::entityTypeManager();
$conexion = \Drupal::database();
$sistemaFicheros = \Drupal::service('file_system');

print "\n";

// -----------------------------------------------------------------------------
// 1. Cuales son.
// -----------------------------------------------------------------------------
$candidatos = [];
$seQuedan = [];
foreach ($etm->getStorage('file')->loadMultiple() as $fid => $fichero) {
  $extension = strtolower(pathinfo($fichero->getFileUri(), PATHINFO_EXTENSION));
  if (in_array($extension, EXTENSIONES, TRUE)) {
    $candidatos[$fid] = $fichero;
  }
  else {
    $seQuedan[$fid] = $extension;
  }
}

print "  Fichas de fichero: " . (count($candidatos) + count($seQuedan)) . "\n";
print "  De hoja de calculo: " . count($candidatos) . "\n";
print "  De otra clase, que no se tocan: " . count($seQuedan) . "\n\n";

$porExtension = [];
foreach ($seQuedan as $extension) {
  $porExtension[$extension ?: '(sin extension)'] = ($porExtension[$extension ?: '(sin extension)'] ?? 0) + 1;
}
ksort($porExtension);
print "  Lo que se queda, por extension: ";
$trozos = [];
foreach ($porExtension as $extension => $cuantos) {
  $trozos[] = $cuantos . ' ' . $extension;
}
print implode(', ', $trozos) . "\n\n";

// La medida de las dos carpetas se toma aqui, antes de tocar nada, porque las
// cuentas del final la necesitan aunque no haya ninguna ficha que borrar: este
// script se puede volver a pasar, y entonces lo unico que hace es la limpieza de
// los apartados 6, 7 y 8.
$medir = function ($carpeta) use ($sistemaFicheros) {
  $ruta = $sistemaFicheros->realpath($carpeta);
  if (!$ruta || !is_dir($ruta)) {
    return [0, 0];
  }
  $cuenta = 0;
  $bytes = 0;
  foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS)) as $archivo) {
    if ($archivo->isFile()) {
      $cuenta++;
      $bytes += $archivo->getSize();
    }
  }
  return [$cuenta, $bytes];
};

$antes = [$medir('public://'), $medir('private://')];

if ($candidatos) {

// -----------------------------------------------------------------------------
// 2. Quien los usa.
// -----------------------------------------------------------------------------
$usos = [];
foreach ($conexion->select('file_usage', 'u')
  ->fields('u', ['fid', 'module', 'type', 'id', 'count'])
  ->condition('u.fid', array_keys($candidatos), 'IN')
  ->execute() as $fila) {
  $usos[$fila->fid][] = $fila;
}

print "  --- Los que tienen usos ---\n\n";
$duenosRaros = [];
if (!$usos) {
  print "  Ninguno.\n";
}
foreach ($usos as $fid => $filas) {
  foreach ($filas as $fila) {
    print sprintf("  %-5s %-48s  lo usa %s %s (modulo %s, %s vez/veces)\n",
      $fid,
      substr($candidatos[$fid]->getFilename(), 0, 46),
      $fila->type,
      $fila->id,
      $fila->module,
      $fila->count
    );
    if (!in_array($fila->type, DUENOS_ESPERADOS, TRUE)) {
      $duenosRaros[] = $fid . ' lo usa ' . $fila->type . ' ' . $fila->id;
    }
  }
}
print "\n";

// -----------------------------------------------------------------------------
// 3. Si alguno esta nombrado en el contenido.
// -----------------------------------------------------------------------------
$columnas = $conexion->query("
  SELECT TABLE_NAME, COLUMN_NAME
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND (DATA_TYPE IN ('text', 'mediumtext', 'longtext', 'blob', 'longblob')
         OR (DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH >= 255))
")->fetchAll();

$pajar = '';
$columnasMiradas = 0;
foreach ($columnas as $columna) {
  $tabla = $columna->TABLE_NAME;
  if (in_array($tabla, TABLAS_QUE_NO_MIRAR, TRUE) || strpos($tabla, 'cache') === 0
    || strpos($tabla, 'feeds_') === 0) {
    continue;
  }
  try {
    $valores = $conexion->query("SELECT `" . $columna->COLUMN_NAME . "` FROM `" . $tabla . "`")->fetchCol();
    $pajar .= implode("\n", array_filter($valores));
    $columnasMiradas++;
  }
  catch (\Exception $e) {
    // Una columna que no se puede leer no aporta nada al recuento.
  }
}

$nombradosEnContenido = [];
foreach ($candidatos as $fid => $fichero) {
  $base = basename($fichero->getFileUri());
  if ($base !== '' && strpos($pajar, $base) !== FALSE) {
    $nombradosEnContenido[$fid] = $fichero->getFilename();
  }
}

print "  --- Comprobacion cruzada ---\n\n";
print "  Miradas " . $columnasMiradas . " columnas de texto, " . number_format(strlen($pajar)) . " caracteres.\n";
print "  Se salta la tabla de configuracion y las del propio importador, que es donde\n";
print "  tienen que aparecer y no significa nada.\n";
if ($nombradosEnContenido) {
  print "  OJO: " . count($nombradosEnContenido) . " aparecen nombrados en el contenido:\n";
  foreach ($nombradosEnContenido as $fid => $nombre) {
    print "      " . $fid . "  " . $nombre . "\n";
  }
}
else {
  print "  Ninguno aparece nombrado en ningun texto.\n";
}
print "\n";

// -----------------------------------------------------------------------------
// 4. La parada, si algo no cuadra.
// -----------------------------------------------------------------------------
if ($duenosRaros || $nombradosEnContenido) {
  print "  ================================================================\n";
  print "  NO SE BORRA NADA. Hay algo que mirar antes:\n";
  foreach ($duenosRaros as $aviso) {
    print "      dueno inesperado: " . $aviso . "\n";
  }
  foreach ($nombradosEnContenido as $fid => $nombre) {
    print "      nombrado en el contenido: " . $fid . " " . $nombre . "\n";
  }
  print "  ================================================================\n\n";
  return;
}

// -----------------------------------------------------------------------------
// 5. Borrar.
// -----------------------------------------------------------------------------
$bytesBorrados = 0;
$borrados = 0;
$fallos = [];
foreach ($candidatos as $fid => $fichero) {
  $bytes = (int) $fichero->getSize();
  try {
    $fichero->delete();
    $borrados++;
    $bytesBorrados += $bytes;
  }
  catch (\Exception $e) {
    $fallos[$fid] = $e->getMessage();
  }
}

print "  --- Borrado ---\n\n";
print "  " . $borrados . " fichas borradas de " . count($candidatos) . ", " . round($bytesBorrados / 1048576, 1) . " MB.\n";
foreach ($fallos as $fid => $mensaje) {
  print "      fallo en " . $fid . ": " . $mensaje . "\n";
}

// Borrar la ficha de un fichero no borra su linea en la tabla de usos: nadie en
// el nucleo se encarga de eso. Si no se limpia, quedan cuatro lineas apuntando a
// fichas que ya no existen.
$usosHuerfanos = $conexion->delete('file_usage')
  ->condition('fid', array_keys($candidatos), 'IN')
  ->execute();
print "  Lineas de uso que quedaban sueltas y se han quitado: " . $usosHuerfanos . "\n";
print "\n";

}
else {
  print "  No queda ninguna hoja de calculo con ficha en la base. Se pasa a la limpieza.\n\n";
}

// -----------------------------------------------------------------------------
// 6. Los tres importadores, que no apunten a un fantasma.
// -----------------------------------------------------------------------------
// Dos de los tres estan huerfanos, y es anterior a este borrado: son de tipo
// `tec_products_csv_importer`, un importador de productos cuya configuracion ya
// no existe. Guardarlos lanza una excepcion, porque lo primero que hace la ficha
// al guardarse es pedir su tipo. Asi que se avisa y se sigue, en vez de parar
// aqui: el fichero ya esta borrado y lo que queda es limpieza.
print "  --- Los importadores ---\n\n";
$tiposQueExisten = array_keys($etm->getStorage('feeds_feed_type')->loadMultiple());
foreach ($etm->getStorage('feeds_feed')->loadMultiple() as $fidImportador => $importador) {
  $origen = (string) $importador->get('source')->value;
  $tipo = $importador->bundle();
  $ruta = $origen !== '' ? $sistemaFicheros->realpath($origen) : NULL;
  $existe = ($ruta && is_file($ruta));

  print "  " . $fidImportador . "  " . $importador->label() . "  (tipo " . $tipo . ")\n";

  if (!in_array($tipo, $tiposQueExisten, TRUE)) {
    print "      HUERFANO: su tipo no existe en la configuracion, no se puede ni ejecutar\n";
    print "      ni guardar. Apunta a " . ($origen ?: 'ningun fichero');
    print $origen !== '' ? ($existe ? ', que esta en el disco' : ', que ya no esta') : '';
    print "\n";
    continue;
  }
  if ($origen === '') {
    print "      no apunta a ningun fichero\n";
    continue;
  }
  if ($existe) {
    print "      sigue apuntando a " . $origen . ", que esta en el disco\n";
    continue;
  }
  $importador->setSource('');
  $importador->save();
  print "      apuntaba a " . $origen . ", que ya no esta. Se le quita el origen.\n";
}
print "\n";

// -----------------------------------------------------------------------------
// 7. Los sueltos del disco, que ya no tienen ficha.
// -----------------------------------------------------------------------------
// Al borrar una ficha, Drupal se lleva su archivo. Pero en private://feeds hay
// tambien archivos de fichas que se borraron hace tiempo, y esos no se los llevo
// nadie. Solo se miran las hojas de calculo, y solo en esa carpeta.
$carpetaFeeds = $sistemaFicheros->realpath('private://feeds');
$sueltos = 0;
$bytesSueltos = 0;
if ($carpetaFeeds && is_dir($carpetaFeeds)) {
  $registrados = [];
  foreach ($conexion->select('file_managed', 'f')->fields('f', ['uri'])->execute()->fetchCol() as $uri) {
    $rutaRegistrada = $sistemaFicheros->realpath($uri);
    if ($rutaRegistrada) {
      $registrados[strtolower($rutaRegistrada)] = TRUE;
    }
  }
  foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpetaFeeds, \FilesystemIterator::SKIP_DOTS)) as $archivo) {
    if (!$archivo->isFile()) {
      continue;
    }
    $extension = strtolower($archivo->getExtension());
    if (!in_array($extension, EXTENSIONES, TRUE)) {
      continue;
    }
    if (isset($registrados[strtolower($archivo->getPathname())])) {
      continue;
    }
    $bytes = $archivo->getSize();
    if (unlink($archivo->getPathname())) {
      $sueltos++;
      $bytesSueltos += $bytes;
    }
  }
}
print "  --- Sueltos del disco, sin ficha en la base ---\n\n";
print "  " . $sueltos . " archivos borrados, " . round($bytesSueltos / 1048576, 1) . " MB.\n\n";

// -----------------------------------------------------------------------------
// 8. Las cuentas finales.
// -----------------------------------------------------------------------------
$despues = [$medir('public://'), $medir('private://')];

print "  --- Antes y despues ---\n\n";
print "  public:   " . $antes[0][0] . " archivos y " . round($antes[0][1] / 1048576, 1) . " MB";
print "   ->  " . $despues[0][0] . " y " . round($despues[0][1] / 1048576, 1) . " MB\n";
print "  private:  " . $antes[1][0] . " archivos y " . round($antes[1][1] / 1048576, 1) . " MB";
print "   ->  " . $despues[1][0] . " y " . round($despues[1][1] / 1048576, 1) . " MB\n";

$menos = ($antes[0][0] + $antes[1][0]) - ($despues[0][0] + $despues[1][0]);
$liberado = ($antes[0][1] + $antes[1][1]) - ($despues[0][1] + $despues[1][1]);
print "\n  Se han ido " . $menos . " archivos y " . round($liberado / 1048576, 1) . " MB.\n";

$quedan = (int) $conexion->select('file_managed', 'f')->countQuery()->execute()->fetchField();
print "  Fichas de fichero que quedan: " . $quedan . "\n\n";
