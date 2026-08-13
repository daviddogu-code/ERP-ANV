<?php

/**
 * @file
 * Averigua de que version publicada salio un modulo que esta en una rama de
 * desarrollo.
 *
 *   php scripts/version-de-rama.php eca 1.1
 *   php scripts/version-de-rama.php eca 1.1 C:/ruta/al/repo/.git
 *
 * Los paquetes de rama no llevan numero de version en su .info.yml, asi que ni
 * el inventario ni el comparador normal pueden decir si el disco coincide con
 * lo que apunta composer.lock. Este script usa el repositorio Git que Composer
 * deja al clonar un paquete de rama: extrae cada etiqueta publicada y la
 * compara fichero a fichero con la carpeta del sitio.
 *
 * Hace falta, por tanto, un sitio del que sacar ese repositorio. La forma de
 * conseguirlo es clonar el proyecto en una carpeta aparte y lanzar alli un
 * `composer install`: Composer clona los paquetes anclados a un commit y deja
 * su historial completo. El tercer argumento apunta a ese .git; si no se pasa,
 * se busca en las rutas de abajo.
 *
 * Solo lee. No toca ni el repositorio ni la carpeta del sitio.
 */

$modulo = $argv[1] ?? NULL;
$rama = $argv[2] ?? NULL;
$gitDir = $argv[3] ?? NULL;

if (!$modulo) {
  echo "Uso: php scripts/version-de-rama.php <modulo> [prefijo-de-rama] [ruta-al-.git]\n";
  exit(1);
}

$sitio = dirname(__DIR__) . "/modules/contrib/$modulo";

if (!$gitDir) {
  foreach (glob('C:/laragon/tmp/clon-prueba*') ?: [] as $clon) {
    if (is_dir("$clon/modules/contrib/$modulo/.git")) {
      $gitDir = "$clon/modules/contrib/$modulo/.git";
      break;
    }
  }
}
$gitDir = $gitDir ?? '';

if (!is_dir($sitio)) {
  echo "No encuentro la carpeta del sitio: $sitio\n";
  exit(1);
}
if (!is_dir($gitDir)) {
  echo "No encuentro el repositorio Git de $modulo.\n";
  echo "Clona el proyecto en una carpeta aparte, lanza alli composer install y\n";
  echo "pasa la ruta a su .git como tercer argumento.\n";
  exit(1);
}

/**
 * Huellas de todos los ficheros, normalizando los finales de linea.
 *
 * Se salta .git, node_modules y el .info.yml, porque ese ultimo lo reescribe
 * drupal.org al empaquetar y nunca coincide.
 */
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

/**
 * Devuelve con que hay que invocar a Git.
 *
 * No basta con poner "git" y confiar en el PATH. En Windows el PATH se trunca a
 * 8191 caracteres, y en una sesion larga de consola donde se ha ido anadiendo
 * rutas es facil pasarse sin enterarse: la orden deja de encontrarse y el
 * script falla de una forma que no se parece en nada a la causa. Con la ruta
 * completa esto no puede pasar.
 */
function ordenGit(): string {
  foreach (['C:/laragon/bin/git/cmd/git.exe', 'C:/Program Files/Git/cmd/git.exe'] as $ruta) {
    if (file_exists($ruta)) {
      return sprintf('"%s"', $ruta);
    }
  }
  return 'git';
}

$git = sprintf('%s --git-dir="%s"', ordenGit(), $gitDir);
exec("$git tag --list 2>&1", $etiquetas);

$candidatas = [];
foreach ($etiquetas as $t) {
  $t = trim($t);
  if ($t === '' || ($rama && !str_starts_with($t, $rama))) {
    continue;
  }
  $candidatas[] = $t;
}
usort($candidatas, 'version_compare');

$mias = huellas($sitio);
echo "\n$modulo: " . count($mias) . " ficheros en el sitio.\n";
echo 'Probando ' . count($candidatas) . " versiones publicadas.\n\n";

$temporal = sys_get_temp_dir() . '/tec-ramas';
@mkdir($temporal, 0777, TRUE);

$tarBin = file_exists('C:/Windows/System32/tar.exe') ? 'C:/Windows/System32/tar.exe' : 'tar';
$resultados = [];

foreach ($candidatas as $t) {
  $dir = "$temporal/$modulo-$t";
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, TRUE);
    $tar = "$temporal/$modulo-$t.tar";
    exec(sprintf('%s archive --format=tar -o "%s" %s 2>&1', $git, $tar, escapeshellarg($t)));
    if (!file_exists($tar) || filesize($tar) < 500) {
      printf("  %-14s no se puede extraer\n", $t);
      continue;
    }
    exec(sprintf('"%s" -xf "%s" -C "%s" 2>&1', $tarBin, $tar, $dir));
    @unlink($tar);
  }

  $suyas = huellas($dir);
  if (!$suyas) {
    printf("  %-14s vacio\n", $t);
    continue;
  }

  $distintos = 0;
  foreach ($suyas as $rel => $h) {
    if (!isset($mias[$rel]) || $mias[$rel] !== $h) {
      $distintos++;
    }
  }
  $sobran = count(array_diff_key($mias, $suyas));
  $total = $distintos + $sobran;
  $resultados[$t] = $total;

  printf(
    "  %-14s %4d distintos, %3d de mas   = %4d %s\n",
    $t, $distintos, $sobran, $total, $total === 0 ? '  <-- ES ESTA' : ''
  );
}

if ($resultados) {
  asort($resultados);
  $mejor = array_key_first($resultados);
  echo "\n  La que mas se parece: $mejor ({$resultados[$mejor]} diferencias)\n";
}
echo "\n";
