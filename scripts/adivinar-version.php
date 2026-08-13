<?php

/**
 * @file
 * Averigua de que version publicada salio una carpeta de modulo.
 *
 *   drush scr scripts/adivinar-version.php -- quicktabs
 *   drush scr scripts/adivinar-version.php -- quicktabs 8
 *
 * Hace falta porque parte de los modulos de este sitio se instalaron copiando
 * carpetas a mano, sin Composer y sin dejar rastro de que version eran. El
 * .info.yml no lo dice, asi que el unico modo de saberlo es descargar las
 * versiones publicadas y ver cual encaja con lo que hay en el disco.
 *
 * El segundo argumento limita cuantas versiones recientes se prueban. Por
 * defecto, diez.
 */

$argumentos = $extra ?? [];
$modulo = $argumentos[0] ?? NULL;
$cuantas = (int) ($argumentos[1] ?? 10);

if (!$modulo) {
  echo "Falta el nombre del modulo.\n";
  return;
}

$raiz = DRUPAL_ROOT;
$carpeta = NULL;
foreach (['modules/contrib', 'themes/contrib'] as $c) {
  if (is_dir("$raiz/$c/$modulo")) {
    $carpeta = "$raiz/$c/$modulo";
  }
}
if (!$carpeta) {
  echo "No encuentro $modulo en el disco.\n";
  return;
}

$temporal = sys_get_temp_dir() . '/tec-originales';
@mkdir($temporal, 0777, TRUE);

function bajar(string $url): ?string {
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
  return ($codigo === 200 && strlen((string) $datos) > 100) ? $datos : NULL;
}

/**
 * Las huellas de los ficheros, ignorando lo que cambia solo al empaquetar.
 */
function huellasDe(string $base): array {
  $salida = [];
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
  );
  foreach ($it as $ruta => $info) {
    $rel = str_replace('\\', '/', substr($ruta, strlen($base) + 1));
    if (preg_match('#(^|/)(\.git|node_modules)(/|$)#', $rel) || str_ends_with($rel, '.info.yml')) {
      continue;
    }
    $salida[$rel] = md5(str_replace("\r\n", "\n", file_get_contents($ruta)));
  }
  return $salida;
}

$meta = bajar("https://packages.drupal.org/8/p2/drupal/$modulo.json");
if (!$meta) {
  echo "drupal.org no me da la lista de versiones de $modulo.\n";
  return;
}
$versiones = [];
foreach (json_decode($meta, TRUE)['packages']["drupal/$modulo"] ?? [] as $p) {
  $v = $p['version'] ?? '';
  if ($v !== '' && !str_contains($v, 'dev')) {
    $versiones[] = $v;
  }
}
$versiones = array_slice($versiones, 0, $cuantas);

$mias = huellasDe($carpeta);
echo "\n$modulo: " . count($mias) . " ficheros en el disco.\n";
echo "Probando " . count($versiones) . " versiones publicadas.\n\n";

$resultados = [];
foreach ($versiones as $v) {
  $tar = "$temporal/g-$modulo-$v.tar.gz";
  if (!file_exists($tar) || filesize($tar) < 500) {
    $datos = bajar("https://ftp.drupal.org/files/projects/$modulo-$v.tar.gz");
    if (!$datos) {
      printf("  %-18s no hay paquete\n", $v);
      continue;
    }
    file_put_contents($tar, $datos);
  }
  $dir = "$temporal/g-$modulo-$v";
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, TRUE);
    $tarBin = file_exists('C:/Windows/System32/tar.exe') ? 'C:/Windows/System32/tar.exe' : 'tar';
    exec(sprintf('"%s" -xzf "%s" -C "%s" 2>&1', $tarBin, $tar, $dir));
  }
  if (!is_dir("$dir/$modulo")) {
    printf("  %-18s no se descomprime\n", $v);
    continue;
  }
  $suyas = huellasDe("$dir/$modulo");
  $distintos = 0;
  foreach ($suyas as $rel => $h) {
    if (!isset($mias[$rel]) || $mias[$rel] !== $h) {
      $distintos++;
    }
  }
  $sobran = count(array_diff_key($mias, $suyas));
  $total = $distintos + $sobran;
  $resultados[$v] = $total;
  printf("  %-18s %2d ficheros distintos, %d de mas   %s\n",
    $v, $distintos, $sobran, $total === 0 ? '<-- ES ESTA' : '');
}

if ($resultados) {
  asort($resultados);
  $mejor = array_key_first($resultados);
  echo "\nLa que mas se parece: $mejor (" . $resultados[$mejor] . " diferencias)\n";
}
echo "\n";
