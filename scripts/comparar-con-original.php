<?php

/**
 * @file
 * Compara cada modulo y tema de contrib con el paquete oficial de esa misma
 * version, para saber cuales estan retocados a mano.
 *
 *   drush scr scripts/comparar-con-original.php
 *   drush scr scripts/comparar-con-original.php -- token ds smtp
 *
 * Por que hace falta: durante anos se actualizaron modulos copiando carpetas a
 * mano, sin pasar por Composer. Algunos de esos modulos llevan parches encima
 * que no estan registrados en ningun sitio. Si ahora dejamos que Composer los
 * reinstale, esos parches desaparecen sin avisar. Ya paso con verf en la
 * Fase 2. Este script los encuentra ANTES, no despues.
 *
 * No modifica el proyecto. Solo descarga a una carpeta temporal y compara.
 */

$raiz = DRUPAL_ROOT;
$temporal = sys_get_temp_dir() . '/tec-originales';
@mkdir($temporal, 0777, TRUE);

$soloEstos = array_slice($extra ?? [], 0);

/**
 * La .info.yml que descargas de drupal.org lleva un bloque que anade el
 * empaquetador y que nunca esta en el repositorio. Compararlo da falsos
 * positivos en todos los modulos, asi que se quita de los dos lados.
 */
function limpiaInfo(string $texto): string {
  $lineas = preg_split('/\r\n|\r|\n/', $texto);
  $fuera = [];
  foreach ($lineas as $linea) {
    if (preg_match('/^(version|project|datestamp|generator)\s*:/', $linea)) {
      continue;
    }
    if (str_starts_with(trim($linea), '# Information added by Drupal.org packaging script')) {
      continue;
    }
    if (trim($linea) === '') {
      continue;
    }
    $fuera[] = rtrim($linea);
  }
  return implode("\n", $fuera);
}

/**
 * Lista los ficheros de una carpeta, con su huella, en rutas relativas.
 */
function huellas(string $base): array {
  $salida = [];
  if (!is_dir($base)) {
    return $salida;
  }
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );
  foreach ($it as $ruta => $info) {
    if ($info->isDir()) {
      continue;
    }
    $rel = str_replace('\\', '/', substr($ruta, strlen($base) + 1));
    // Ruido que no viene del paquete oficial.
    if (preg_match('#(^|/)(\.git|node_modules|\.DS_Store|Thumbs\.db)(/|$)#', $rel)) {
      continue;
    }
    $contenido = file_get_contents($ruta);
    if (str_ends_with($rel, '.info.yml')) {
      $contenido = limpiaInfo($contenido);
    }
    else {
      // Los finales de linea cambian solos en Windows y no son un cambio real.
      $contenido = str_replace("\r\n", "\n", $contenido);
    }
    $salida[$rel] = md5($contenido);
  }
  return $salida;
}

function descarga(string $proyecto, string $version, string $destino): ?string {
  $fichero = "$destino/$proyecto-$version.tar.gz";
  if (file_exists($fichero) && filesize($fichero) > 500) {
    return $fichero;
  }
  $url = "https://ftp.drupal.org/files/projects/$proyecto-$version.tar.gz";
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_FOLLOWLOCATION => TRUE,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_SSL_VERIFYPEER => FALSE,
  ]);
  $datos = curl_exec($ch);
  $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($codigo !== 200 || strlen((string) $datos) < 500) {
    return NULL;
  }
  file_put_contents($fichero, $datos);
  return $fichero;
}

function extrae(string $tar, string $destino): bool {
  if (is_dir($destino)) {
    return TRUE;
  }
  @mkdir($destino, 0777, TRUE);
  $tarBin = file_exists('C:/Windows/System32/tar.exe') ? 'C:/Windows/System32/tar.exe' : 'tar';
  $orden = sprintf('"%s" -xzf "%s" -C "%s" 2>&1', $tarBin, $tar, $destino);
  exec($orden, $salida, $codigo);
  return $codigo === 0;
}

// -----------------------------------------------------------------------------
$candidatos = [];
foreach (['modules/contrib', 'themes/contrib'] as $carpeta) {
  foreach (glob("$raiz/$carpeta/*", GLOB_ONLYDIR) as $ruta) {
    $nombre = basename($ruta);
    if ($soloEstos && !in_array($nombre, $soloEstos, TRUE)) {
      continue;
    }
    $info = "$ruta/$nombre.info.yml";
    if (!file_exists($info)) {
      continue;
    }
    if (!preg_match("/^version:\s*'?([^'\n\r]+)'?/m", file_get_contents($info), $m)) {
      $candidatos[$nombre] = ['ruta' => $ruta, 'version' => NULL];
      continue;
    }
    $candidatos[$nombre] = ['ruta' => $ruta, 'version' => trim($m[1], " '\"")];
  }
}
ksort($candidatos);

$limpios = [];
$tocados = [];
$sinComprobar = [];

echo "Comparando " . count($candidatos) . " proyectos con su paquete oficial.\n";
echo "La primera vez tarda, porque hay que descargarlos todos.\n\n";

foreach ($candidatos as $nombre => $datos) {
  $version = $datos['version'];
  if ($version === NULL || str_contains($version, 'dev')) {
    $sinComprobar[$nombre] = $version === NULL ? 'sin version en el .info.yml' : "rama de desarrollo ($version)";
    continue;
  }
  $tar = descarga($nombre, $version, $temporal);
  if ($tar === NULL) {
    $sinComprobar[$nombre] = "no hay paquete oficial $version en drupal.org";
    continue;
  }
  $carpetaOriginal = "$temporal/x-$nombre-$version";
  if (!extrae($tar, $carpetaOriginal) || !is_dir("$carpetaOriginal/$nombre")) {
    $sinComprobar[$nombre] = 'no se ha podido descomprimir';
    continue;
  }

  $aqui = huellas($datos['ruta']);
  $alli = huellas("$carpetaOriginal/$nombre");

  $cambiados = [];
  foreach ($alli as $rel => $huella) {
    if (!isset($aqui[$rel])) {
      $cambiados[] = "  falta:    $rel";
    }
    elseif ($aqui[$rel] !== $huella) {
      $cambiados[] = "  DISTINTO: $rel";
    }
  }
  foreach ($aqui as $rel => $huella) {
    if (!isset($alli[$rel])) {
      $cambiados[] = "  sobra:    $rel";
    }
  }

  if ($cambiados) {
    $tocados[$nombre] = ['version' => $version, 'lista' => $cambiados];
    echo "TOCADO  $nombre ($version): " . count($cambiados) . " diferencias\n";
  }
  else {
    $limpios[$nombre] = $version;
    echo "limpio  $nombre ($version)\n";
  }
}

echo "\n";
echo str_repeat('=', 78) . "\n";
echo "RETOCADOS A MANO - hay que capturar el cambio antes de dejar que Composer\n";
echo "los reinstale, o se pierde\n";
echo str_repeat('=', 78) . "\n";
if (!$tocados) {
  echo "Ninguno.\n";
}
foreach ($tocados as $nombre => $d) {
  echo "\n$nombre  ({$d['version']})\n";
  foreach ($d['lista'] as $linea) {
    echo "$linea\n";
  }
}

echo "\n";
echo str_repeat('=', 78) . "\n";
echo "NO SE HAN PODIDO COMPROBAR - hay que mirarlos a mano\n";
echo str_repeat('=', 78) . "\n";
foreach ($sinComprobar as $nombre => $motivo) {
  printf("  %-38s %s\n", $nombre, $motivo);
}

echo "\n";
printf("Limpios: %d    Retocados: %d    Sin comprobar: %d\n",
  count($limpios), count($tocados), count($sinComprobar));
echo "Los paquetes descargados se quedan en $temporal por si hacen falta.\n";
