<?php

/**
 * Borra las fotos que se quedaron sin dueno al vaciar los datos de prueba.
 *
 * El reparto es el mismo que hizo scripts/mirar-los-ficheros.php, y se vuelve a
 * calcular aqui en vez de traerse una lista de identificadores: si algo ha
 * cambiado entre el reconocimiento y el borrado, cambia tambien el reparto, y no
 * se borra por una lista vieja.
 *
 * Se queda fuera del borrado, por este orden:
 *
 * 1. Lo que tiene usos. Ahi estan los once iconos de la portada, los iconos de
 *    la aplicacion movil y unos cuantos ficheros de importacion.
 * 2. Lo que nombra cualquier objeto de configuracion. Hoy no protege nada,
 *    porque las cinco imagenes de marca del ERP -los dos logotipos, los dos
 *    favicon y el fondo del login- resulta que no tienen ficha en la base: el
 *    formulario de ajustes del tema las dejo en el disco sin registrarlas. Se
 *    deja la regla puesta porque el dia que alguien cambie el logotipo por el
 *    camino normal, si la tendra.
 * 3. Los iconos de las portadas, por su ficha y no por su contador.
 * 4. Lo que no es una foto: las fuentes del tema, dos hojas de estilo y el
 *    monton de CSV y Excel de las importaciones de escandallo. Eso es otra
 *    conversacion y no se toca.
 *
 * Antes de borrar, las fotografias que valen algo se copian a una carpeta con
 * nombre. Estan todas en la copia de seguridad de la carpeta de ficheros, pero
 * enterradas entre miles y con fecha en la ruta; el dia que se cargue el
 * catalogo de verdad, alguien va a querer las fotos de los guantes y tiene que
 * poder encontrarlas.
 *
 * Uso: php vendor/bin/drush.php scr scripts/borrar-las-fotos-huerfanas.php
 */

// Donde van las fotografias que se rescatan.
const CARPETA_RESCATE = 'C:\\laragon\\backups\\fotos-de-producto-anv';

// Que se rescata: lo que lleva el nombre de la casa o de una marca de cliente,
// y toda foto de mas de un mega, que a ese tamano ya no es una captura de
// pantalla sino una fotografia de camara.
const NOMBRES_QUE_SE_RESCATAN = ['acta-boxing', 'trust-logos'];
const TAMANO_DE_FOTOGRAFIA = 1048576;

$etm = \Drupal::entityTypeManager();
$conexion = \Drupal::database();
$sistemaFicheros = \Drupal::service('file_system');

print "\n";

// -----------------------------------------------------------------------------
// 1. El reparto, calculado otra vez.
// -----------------------------------------------------------------------------
$ficheros = $etm->getStorage('file')->loadMultiple();

$usos = array_flip($conexion->select('file_usage', 'u')
  ->fields('u', ['fid'])
  ->groupBy('u.fid')
  ->execute()
  ->fetchCol());

$nombradosEnConfig = [];
foreach (\Drupal::configFactory()->listAll() as $nombre) {
  $plano = print_r(\Drupal::config($nombre)->getRawData(), TRUE);
  if (preg_match_all('#(public|private)://[^\'"\s\]]+#', $plano, $encontrados)) {
    foreach ($encontrados[0] as $uri) {
      if (pathinfo($uri, PATHINFO_EXTENSION) !== '') {
        $nombradosEnConfig[$uri] = TRUE;
      }
    }
  }
}

$iconosDePortada = [];
foreach ($etm->getStorage('node')->loadByProperties(['type' => 'tec_landing_page']) as $portada) {
  if ($portada->hasField('field_tec_icon') && !$portada->get('field_tec_icon')->isEmpty()) {
    foreach ($portada->get('field_tec_icon') as $elemento) {
      if (!empty($elemento->target_id)) {
        $iconosDePortada[$elemento->target_id] = TRUE;
      }
    }
  }
}

$candidatas = [];
$seQuedan = 0;
foreach ($ficheros as $fid => $fichero) {
  if (isset($usos[$fid]) || isset($nombradosEnConfig[$fichero->getFileUri()]) || isset($iconosDePortada[$fid])) {
    $seQuedan++;
    continue;
  }
  if (strpos((string) $fichero->getMimeType(), 'image/') !== 0) {
    $seQuedan++;
    continue;
  }
  $candidatas[$fid] = $fichero;
}

print "  Habia " . count($ficheros) . " fichas de fichero.\n";
print "  Se quedan " . $seQuedan . " y sobran " . count($candidatas) . " fotos.\n\n";

if (!$candidatas) {
  print "  No hay nada que borrar.\n\n";
  return;
}

// -----------------------------------------------------------------------------
// 2. Rescatar las fotografias que valen algo.
// -----------------------------------------------------------------------------
if (!is_dir(CARPETA_RESCATE)) {
  mkdir(CARPETA_RESCATE, 0777, TRUE);
}

$rescatadas = 0;
$bytesRescatados = 0;
print "  --- Fotografias que se copian a " . CARPETA_RESCATE . " ---\n\n";
foreach ($candidatas as $fid => $fichero) {
  $nombre = $fichero->getFilename();
  $bytes = (int) $fichero->getSize();
  $porNombre = FALSE;
  foreach (NOMBRES_QUE_SE_RESCATAN as $trozo) {
    if (stripos($nombre, $trozo) !== FALSE) {
      $porNombre = TRUE;
    }
  }
  // Las capturas de pantalla no son fotografias aunque pesen.
  $esCaptura = stripos($nombre, 'screenshot') !== FALSE;
  if (!$porNombre && ($esCaptura || $bytes < TAMANO_DE_FOTOGRAFIA)) {
    continue;
  }

  $origen = $sistemaFicheros->realpath($fichero->getFileUri());
  if (!$origen || !is_file($origen)) {
    print sprintf("  %-5s %-56s (no esta en el disco, no se copia)\n", $fid, substr($nombre, 0, 54));
    continue;
  }
  // Hay nombres repetidos entre fichas distintas, asi que se antepone el
  // identificador para no pisar una copia con otra.
  $destino = CARPETA_RESCATE . DIRECTORY_SEPARATOR . $fid . '-' . $nombre;
  if (copy($origen, $destino)) {
    $rescatadas++;
    $bytesRescatados += $bytes;
    print sprintf("  %-5s %-56s %6s KB\n", $fid, substr($nombre, 0, 54), number_format($bytes / 1024, 0));
  }
  else {
    print sprintf("  %-5s %-56s NO SE PUDO COPIAR\n", $fid, substr($nombre, 0, 54));
  }
}
print "\n  " . $rescatadas . " fotografias copiadas, " . round($bytesRescatados / 1048576, 1) . " MB.\n\n";

// -----------------------------------------------------------------------------
// 3. Medir las carpetas antes, para saber cuanto se libera de verdad.
// -----------------------------------------------------------------------------
$medir = function ($carpeta) use ($sistemaFicheros) {
  $ruta = $sistemaFicheros->realpath($carpeta);
  if (!$ruta || !is_dir($ruta)) {
    return [0, 0];
  }
  $cuenta = 0;
  $bytes = 0;
  $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS));
  foreach ($iterador as $archivo) {
    if ($archivo->isFile()) {
      $cuenta++;
      $bytes += $archivo->getSize();
    }
  }
  return [$cuenta, $bytes];
};

$antes = [
  'public' => $medir('public://'),
  'private' => $medir('private://'),
];

// -----------------------------------------------------------------------------
// 4. Borrar.
// -----------------------------------------------------------------------------
$borradas = 0;
$fallos = [];
foreach ($candidatas as $fid => $fichero) {
  try {
    $fichero->delete();
    $borradas++;
  }
  catch (\Exception $e) {
    $fallos[$fid] = $e->getMessage();
  }
}

print "  --- Borrado ---\n\n";
print "  " . $borradas . " fichas borradas de " . count($candidatas) . ".\n";
if ($fallos) {
  print "  " . count($fallos) . " fallaron:\n";
  foreach ($fallos as $fid => $mensaje) {
    print "      " . $fid . ": " . $mensaje . "\n";
  }
}
print "\n";

// -----------------------------------------------------------------------------
// 5. Que queda.
// -----------------------------------------------------------------------------
$despues = [
  'public' => $medir('public://'),
  'private' => $medir('private://'),
];

foreach (['public', 'private'] as $donde) {
  print "  " . $donde . ": " . $antes[$donde][0] . " ficheros y " . round($antes[$donde][1] / 1048576, 1) . " MB";
  print "  ->  " . $despues[$donde][0] . " ficheros y " . round($despues[$donde][1] / 1048576, 1) . " MB\n";
}
$liberado = ($antes['public'][1] + $antes['private'][1]) - ($despues['public'][1] + $despues['private'][1]);
$menosFicheros = ($antes['public'][0] + $antes['private'][0]) - ($despues['public'][0] + $despues['private'][0]);
print "\n  Se han ido " . $menosFicheros . " archivos del disco y " . round($liberado / 1048576, 1) . " MB.\n";

$quedan = $conexion->select('file_managed', 'f')->countQuery()->execute()->fetchField();
print "  Fichas de fichero que quedan en la base: " . $quedan . "\n\n";

// -----------------------------------------------------------------------------
// 6. Las cinco imagenes de marca, que es lo que no se podia romper.
// -----------------------------------------------------------------------------
print "  --- Las cinco imagenes de marca, en el disco ---\n\n";
foreach ($nombradosEnConfig as $uri => $nada) {
  $ruta = $sistemaFicheros->realpath($uri);
  $esta = ($ruta && is_file($ruta));
  print sprintf("  %-44s %s\n", $uri, $esta ? 'sigue ahi, ' . number_format(filesize($ruta) / 1024, 0) . ' KB' : 'NO ESTA');
}
print "\n";
