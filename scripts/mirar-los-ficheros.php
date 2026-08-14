<?php

/**
 * Mira los ficheros antes de borrar las fotos que no usa nadie.
 *
 * El vaciado de datos de prueba se llevo los productos, las marcas, los
 * patrones y los 869 materiales, pero no sus imagenes: Drupal solo borra un
 * fichero cuando se lo mandas, y la opcion de marcar como temporales los que
 * nadie usa esta apagada a proposito. Asi que las fotos se quedaron con su
 * ficha en la base y su copia en el disco, sin nadie que las mire.
 *
 * Borrarlas es la idea. El peligro es como se decide cual sobra, porque el
 * contador de usos de Drupal solo cuenta lo que apunta desde un campo de una
 * ficha. No cuenta lo que apunta desde la configuracion, y ahi estan las cinco
 * imagenes de marca del ERP: el logotipo y el favicon del tema de fuera, los
 * del tema de administracion, y el fondo de la pantalla de entrar. Para el
 * contador, esas cinco no las usa nadie.
 *
 * De modo que aqui se protege por dos vias: lo que tiene usos, y lo que
 * aparece nombrado en cualquier objeto de configuracion. Lo segundo se lee de
 * la configuracion en el momento, no de una lista escrita a mano, para que
 * siga valiendo el dia que alguien cambie el logotipo.
 *
 * No toca nada. Solo mira.
 *
 * Uso: php vendor/bin/drush.php scr scripts/mirar-los-ficheros.php
 */

// Tablas donde no tiene sentido buscar una referencia a una foto, y que ademas
// pueden ser grandes. Se saltan para que la busqueda no tarde ni se coma la
// memoria.
const TABLAS_QUE_NO_MIRAR = [
  'watchdog', 'sessions', 'semaphore', 'queue', 'flood',
  'locales_source', 'locales_target', 'locale_file',
  'key_value_expire', 'search_index', 'search_dataset', 'search_total',
  'file_managed', 'file_usage',
];

$etm = \Drupal::entityTypeManager();
$conexion = \Drupal::database();
$almacenFicheros = $etm->getStorage('file');

print "\n";

// -----------------------------------------------------------------------------
// 1. Todo lo que hay, con sus usos.
// -----------------------------------------------------------------------------
$ficheros = $almacenFicheros->loadMultiple();
print "  Ficheros registrados en la base: " . count($ficheros) . "\n\n";

// El contador de usos, de una sola consulta en vez de una por fichero.
$usos = $conexion->select('file_usage', 'u')
  ->fields('u', ['fid'])
  ->groupBy('u.fid')
  ->execute()
  ->fetchCol();
$usos = array_flip($usos);

// -----------------------------------------------------------------------------
// 2. Que ficheros nombra la configuracion.
// -----------------------------------------------------------------------------
$nombradosEnConfig = [];
foreach (\Drupal::configFactory()->listAll() as $nombre) {
  $datos = \Drupal::config($nombre)->getRawData();
  $plano = print_r($datos, TRUE);
  if (preg_match_all('#(public|private)://[^\'"\s\]]+#', $plano, $encontrados)) {
    foreach ($encontrados[0] as $uri) {
      // Los directorios sueltos -la carpeta de feeds, la de las copias- no son
      // ficheros; solo interesan los que tienen extension.
      if (pathinfo($uri, PATHINFO_EXTENSION) !== '') {
        $nombradosEnConfig[$uri][] = $nombre;
      }
    }
  }
}

print "  --- Ficheros nombrados desde la configuracion ---\n\n";
foreach ($nombradosEnConfig as $uri => $donde) {
  print "  " . $uri . "\n      en " . implode(', ', $donde) . "\n";
}
print "\n";

// -----------------------------------------------------------------------------
// 3. Los iconos de las once portadas, que se protegen aparte y por su ficha.
// -----------------------------------------------------------------------------
$iconosDePortada = [];
$portadas = $etm->getStorage('node')->loadByProperties(['type' => 'tec_landing_page']);
foreach ($portadas as $portada) {
  if ($portada->hasField('field_tec_icon') && !$portada->get('field_tec_icon')->isEmpty()) {
    foreach ($portada->get('field_tec_icon') as $elemento) {
      if (!empty($elemento->target_id)) {
        $iconosDePortada[$elemento->target_id] = $portada->label();
      }
    }
  }
}
print "  --- Iconos de las " . count($portadas) . " portadas ---\n\n";
print "  Son " . count($iconosDePortada) . " ficheros. ";
$iconosSinUso = array_diff(array_keys($iconosDePortada), array_keys($usos));
if ($iconosSinUso) {
  print "OJO: " . count($iconosSinUso) . " no figuran en el contador de usos (" . implode(', ', $iconosSinUso) . ").\n\n";
}
else {
  print "Todos figuran en el contador de usos, como debe ser.\n\n";
}

// -----------------------------------------------------------------------------
// 4. Repartir: lo que se queda y lo que sobra.
// -----------------------------------------------------------------------------
$seQuedan = [];
$candidatas = [];
$noSonFotos = [];

foreach ($ficheros as $fid => $fichero) {
  $uri = $fichero->getFileUri();
  $mime = (string) $fichero->getMimeType();
  $ficha = [
    'fid' => $fid,
    'nombre' => $fichero->getFilename(),
    'uri' => $uri,
    'bytes' => (int) $fichero->getSize(),
    'mime' => $mime,
    'temporal' => !$fichero->isPermanent(),
    'creado' => date('Y-m-d', $fichero->getCreatedTime()),
  ];

  if (isset($usos[$fid])) {
    $ficha['motivo'] = 'tiene usos';
    $seQuedan[$fid] = $ficha;
    continue;
  }
  if (isset($nombradosEnConfig[$uri])) {
    $ficha['motivo'] = 'lo nombra la configuracion';
    $seQuedan[$fid] = $ficha;
    continue;
  }
  if (isset($iconosDePortada[$fid])) {
    $ficha['motivo'] = 'icono de portada';
    $seQuedan[$fid] = $ficha;
    continue;
  }
  if (strpos($mime, 'image/') !== 0) {
    $noSonFotos[$fid] = $ficha;
    continue;
  }
  $candidatas[$fid] = $ficha;
}

// -----------------------------------------------------------------------------
// 5. La comprobacion cruzada: buscar los nombres en el contenido.
// -----------------------------------------------------------------------------
// El contador de usos no lo sabe todo. Una foto puesta a mano dentro de un
// texto, o dentro de una seccion de diseno de las portadas, se ve en pantalla y
// para el contador no la usa nadie. Se buscan los nombres de las candidatas en
// todas las columnas de texto de la base.
$columnasDeTexto = $conexion->query("
  SELECT TABLE_NAME, COLUMN_NAME
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND (DATA_TYPE IN ('text', 'mediumtext', 'longtext', 'blob', 'longblob')
         OR (DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH >= 255))
")->fetchAll();

$pajar = '';
$columnasMiradas = 0;
foreach ($columnasDeTexto as $columna) {
  $tabla = $columna->TABLE_NAME;
  if (in_array($tabla, TABLAS_QUE_NO_MIRAR, TRUE) || strpos($tabla, 'cache') === 0) {
    continue;
  }
  try {
    $valores = $conexion->query("SELECT `" . $columna->COLUMN_NAME . "` FROM `" . $tabla . "`")->fetchCol();
    $pajar .= implode("\n", array_filter($valores));
    $columnasMiradas++;
  }
  catch (\Exception $e) {
    print "  (no se pudo leer " . $tabla . "." . $columna->COLUMN_NAME . ")\n";
  }
}

print "  --- Comprobacion cruzada ---\n\n";
print "  Miradas " . $columnasMiradas . " columnas de texto, " . number_format(strlen($pajar)) . " caracteres.\n";

$aparecenEnContenido = [];
foreach ($candidatas as $fid => $ficha) {
  $base = basename($ficha['uri']);
  if ($base !== '' && strpos($pajar, $base) !== FALSE) {
    $aparecenEnContenido[$fid] = $ficha;
  }
}
if ($aparecenEnContenido) {
  print "  OJO: " . count($aparecenEnContenido) . " candidatas aparecen nombradas en el contenido:\n";
  foreach ($aparecenEnContenido as $fid => $ficha) {
    print "      " . $fid . "  " . $ficha['nombre'] . "\n";
  }
}
else {
  print "  Ninguna candidata aparece nombrada en ningun texto ni en las secciones de diseno.\n";
}
print "\n";

// -----------------------------------------------------------------------------
// 6. Las cuentas.
// -----------------------------------------------------------------------------
$suma = function (array $lista) {
  $bytes = 0;
  foreach ($lista as $ficha) {
    $bytes += $ficha['bytes'];
  }
  return round($bytes / 1048576, 1);
};

print "  --- Se quedan: " . count($seQuedan) . " ficheros, " . $suma($seQuedan) . " MB ---\n\n";
foreach ($seQuedan as $fid => $ficha) {
  print sprintf("  %-5s %-42s %-26s %s%s\n",
    $fid,
    substr($ficha['nombre'], 0, 40),
    $ficha['motivo'],
    $ficha['creado'],
    $ficha['temporal'] ? '   [TEMPORAL]' : ''
  );
}

print "\n  --- No son fotos, no se tocan: " . count($noSonFotos) . " ficheros, " . $suma($noSonFotos) . " MB ---\n\n";
foreach ($noSonFotos as $fid => $ficha) {
  print sprintf("  %-5s %-42s %-20s %s\n", $fid, substr($ficha['nombre'], 0, 40), $ficha['mime'], $ficha['creado']);
}

print "\n  --- Candidatas a borrar: " . count($candidatas) . " fotos, " . $suma($candidatas) . " MB ---\n\n";
$porAno = [];
foreach ($candidatas as $ficha) {
  $porAno[substr($ficha['creado'], 0, 7)][] = $ficha;
}
ksort($porAno);
foreach ($porAno as $mes => $lista) {
  print "  " . $mes . ": " . count($lista) . " fotos, " . $suma($lista) . " MB\n";
}

print "\n  Muestra de veinte, para que se vea que son:\n\n";
$muestra = array_slice($candidatas, 0, 20, TRUE);
foreach ($muestra as $fid => $ficha) {
  print sprintf("  %-5s %-52s %8s KB  %s\n",
    $fid,
    substr($ficha['nombre'], 0, 50),
    number_format($ficha['bytes'] / 1024, 0),
    $ficha['creado']
  );
}

// -----------------------------------------------------------------------------
// 7. Las versiones recortadas, que es donde esta el volumen de verdad.
// -----------------------------------------------------------------------------
$derivadas = 0;
$bytesDerivados = 0;
$basesCandidatas = [];
foreach ($candidatas as $ficha) {
  $basesCandidatas[basename($ficha['uri'])] = TRUE;
}
foreach (['public://styles', 'private://styles'] as $carpeta) {
  $ruta = \Drupal::service('file_system')->realpath($carpeta);
  if (!$ruta || !is_dir($ruta)) {
    continue;
  }
  $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS));
  foreach ($iterador as $archivo) {
    if ($archivo->isFile() && isset($basesCandidatas[$archivo->getFilename()])) {
      $derivadas++;
      $bytesDerivados += $archivo->getSize();
    }
  }
}

print "\n  --- Versiones recortadas de esas candidatas ---\n\n";
print "  " . $derivadas . " ficheros, " . round($bytesDerivados / 1048576, 1) . " MB. Se van solos al borrar el original.\n";
print "\n  Total que se libera: " . round(($suma($candidatas) * 1048576 + $bytesDerivados) / 1048576, 1) . " MB\n\n";
