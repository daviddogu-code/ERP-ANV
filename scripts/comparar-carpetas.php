<?php

/**
 * @file
 * Compara dos carpetas fichero a fichero.
 *
 *   php scripts/comparar-carpetas.php <carpeta-a> <carpeta-b>
 *
 * Se usa para confirmar que reinstalar un modulo con Composer ha dejado
 * exactamente el mismo codigo que habia antes. Ignora .git, node_modules y los
 * .info.yml, porque drupal.org reescribe ese ultimo al empaquetar y nunca
 * coincide aunque el codigo sea identico.
 *
 * Solo lee.
 */

$a = $argv[1] ?? NULL;
$b = $argv[2] ?? NULL;

if (!$a || !$b) {
  echo "Uso: php scripts/comparar-carpetas.php <carpeta-a> <carpeta-b>\n";
  exit(1);
}

function huellas(string $base): array {
  $salida = [];
  if (!is_dir($base)) {
    return $salida;
  }
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
  );
  foreach ($it as $ruta => $info) {
    if (!$info->isFile()) {
      continue;
    }
    $rel = str_replace('\\', '/', substr($ruta, strlen($base) + 1));
    if (preg_match('#(^|/)(\.git|node_modules)(/|$)#', $rel) || str_ends_with($rel, '.info.yml')) {
      continue;
    }
    $salida[$rel] = md5(str_replace("\r\n", "\n", (string) file_get_contents($ruta)));
  }
  return $salida;
}

$ha = huellas($a);
$hb = huellas($b);

$distintos = [];
foreach ($ha as $rel => $h) {
  if (!isset($hb[$rel])) {
    $distintos[] = "  falta en la nueva: $rel";
  }
  elseif ($hb[$rel] !== $h) {
    $distintos[] = "  DISTINTO:           $rel";
  }
}
foreach ($hb as $rel => $h) {
  if (!isset($ha[$rel])) {
    $distintos[] = "  solo en la nueva:   $rel";
  }
}

printf("  antes: %d ficheros    ahora: %d ficheros\n", count($ha), count($hb));
if (!$distintos) {
  echo "  IDENTICAS\n";
}
else {
  echo '  ' . count($distintos) . " diferencias:\n";
  foreach (array_slice($distintos, 0, 25) as $d) {
    echo "$d\n";
  }
  if (count($distintos) > 25) {
    echo '  ... y ' . (count($distintos) - 25) . " mas\n";
  }
}
