<?php

/**
 * @file
 * Busca en el historial de una rama el commit exacto del que salio la carpeta
 * que hay en el sitio.
 *
 *   php scripts/commit-de-rama.php eck 2.x
 *   php scripts/commit-de-rama.php eck 2.x 400 C:/ruta/al/repo/.git
 *
 * Complementa a version-de-rama.php. Aquel dice si la carpeta coincide con una
 * version publicada; este sirve cuando no coincide con ninguna porque es una
 * instantanea a medio camino entre dos. Saber el commit permite anclarlo en
 * composer.json y que una instalacion desde cero reproduzca exactamente lo que
 * corre hoy, sin cambiar ni un byte.
 *
 * Funciona comparando la huella Git de cada fichero, que es lo mismo que hace
 * Git por dentro, asi que no hay que extraer nada: basta con leer el arbol de
 * cada commit.
 *
 * Necesita el historial del modulo. Se consigue clonando el proyecto en una
 * carpeta aparte y lanzando alli un `composer install`: Composer clona los
 * paquetes anclados a un commit y deja su repositorio completo. El cuarto
 * argumento apunta a ese .git; si no se pasa, se busca en las rutas de abajo.
 *
 * Solo lee. No toca ni el repositorio ni la carpeta del sitio.
 */

$modulo = $argv[1] ?? NULL;
$rama = $argv[2] ?? NULL;
$limite = (int) ($argv[3] ?? 400);
$gitDir = $argv[4] ?? NULL;

if (!$modulo || !$rama) {
  echo "Uso: php scripts/commit-de-rama.php <modulo> <rama> [cuantos-commits] [ruta-al-.git]\n";
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

if (!is_dir($sitio)) {
  echo "No encuentro la carpeta del sitio: $sitio\n";
  exit(1);
}
if (!$gitDir || !is_dir($gitDir)) {
  echo "No encuentro el repositorio Git de $modulo.\n";
  echo "Clona el proyecto en una carpeta aparte, lanza alli composer install y\n";
  echo "pasa la ruta a su .git como cuarto argumento.\n";
  exit(1);
}

/**
 * La huella que Git le da a un fichero.
 *
 * Es sha1 sobre la cabecera "blob <bytes>\0" mas el contenido. Calcularla aqui
 * evita llamar a git una vez por fichero, que en Windows es lentisimo.
 */
function huellaGit(string $contenido): string {
  return sha1('blob ' . strlen($contenido) . "\0" . $contenido);
}

// Huellas de lo que hay en el sitio, calculadas una sola vez.
$mias = [];
$it = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($sitio, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $ruta => $info) {
  if (!$info->isFile()) {
    continue;
  }
  $rel = str_replace('\\', '/', substr($ruta, strlen($sitio) + 1));
  if (preg_match('#(^|/)(\.git|node_modules)(/|$)#', $rel) || str_ends_with($rel, '.info.yml')) {
    continue;
  }
  $mias[$rel] = huellaGit((string) file_get_contents($ruta));
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

exec(sprintf('%s log --format=%%H:%%cd --date=short -n %d %s 2>&1', $git, $limite, escapeshellarg("origin/$rama")), $commits);
if (!$commits || str_contains($commits[0], 'unknown revision')) {
  $commits = [];
  exec(sprintf('%s log --format=%%H:%%cd --date=short -n %d %s 2>&1', $git, $limite, escapeshellarg($rama)), $commits);
}

echo "\n$modulo: " . count($mias) . " ficheros en el sitio.\n";
echo 'Recorriendo ' . count($commits) . " commits de la rama $rama.\n\n";

$mejor = NULL;
$mejorTotal = PHP_INT_MAX;

foreach ($commits as $linea) {
  [$hash, $fecha] = explode(':', trim($linea), 2);
  if (strlen($hash) !== 40) {
    continue;
  }

  $arbol = [];
  exec(sprintf('%s ls-tree -r %s 2>&1', $git, $hash), $salida);
  foreach ($salida as $l) {
    // Formato: <modo> blob <sha>\t<ruta>
    if (!preg_match('/^\d+ blob ([0-9a-f]{40})\t(.+)$/', $l, $p)) {
      continue;
    }
    if (str_ends_with($p[2], '.info.yml')) {
      continue;
    }
    $arbol[$p[2]] = $p[1];
  }
  unset($salida);

  if (!$arbol) {
    continue;
  }

  $distintos = 0;
  foreach ($arbol as $rel => $h) {
    if (!isset($mias[$rel]) || $mias[$rel] !== $h) {
      $distintos++;
    }
  }
  $sobran = count(array_diff_key($mias, $arbol));
  $total = $distintos + $sobran;

  if ($total < $mejorTotal) {
    $mejorTotal = $total;
    $mejor = [$hash, $fecha, $distintos, $sobran];
    printf("  %s  %s  %3d distintos, %2d de mas  = %3d%s\n",
      substr($hash, 0, 12), $fecha, $distintos, $sobran, $total,
      $total === 0 ? '   <-- ES ESTE' : '');
  }

  if ($total === 0) {
    break;
  }
}

echo "\n";
if ($mejor && $mejorTotal === 0) {
  echo "  Commit exacto: {$mejor[0]}\n";
  echo "  Fecha:         {$mejor[1]}\n";
  echo "  Para composer.json:  \"drupal/$modulo\": \"dev-$rama#{$mejor[0]}\"\n";
}
elseif ($mejor) {
  echo "  Ninguno coincide del todo. El mas cercano es {$mejor[0]} ({$mejor[1]}) con $mejorTotal diferencias.\n";
}
echo "\n";
