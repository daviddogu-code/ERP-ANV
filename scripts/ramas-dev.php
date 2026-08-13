<?php

/**
 * @file
 * Compara los paquetes de rama de desarrollo entre lo que apunta composer.lock
 * y lo que hay en disco.
 *
 * Un paquete de rama no lleva numero de version en su .info.yml, asi que el
 * inventario normal no puede decir si el disco y el lock coinciden. La unica
 * pista fiable es el commit que guarda el lock, y compararlo con el contenido
 * real de la carpeta.
 *
 * Se ejecuta con:
 *   php scripts/ramas-dev.php [ruta-del-proyecto]
 *
 * No modifica nada. Solo imprime.
 */

$raiz = $argv[1] ?? __DIR__ . '/..';
$raiz = realpath($raiz);

$lock = json_decode(file_get_contents("$raiz/composer.lock"), TRUE);

echo "Paquetes de rama de desarrollo en $raiz\n";
echo str_repeat('=', 78) . "\n\n";

$total = 0;
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $p) {
  if (!str_contains($p['version'], 'dev')) {
    continue;
  }
  $total++;
  $nombre = $p['name'];
  $corto = str_starts_with($nombre, 'drupal/') ? substr($nombre, 7) : $nombre;

  $ref = $p['source']['reference'] ?? $p['dist']['reference'] ?? '?';
  $fecha = substr($p['time'] ?? '?', 0, 10);

  // Cuantos ficheros hay realmente en disco, para poder comparar dos copias.
  $carpeta = NULL;
  foreach (['modules/contrib', 'themes/contrib'] as $c) {
    if (is_dir("$raiz/$c/$corto")) {
      $carpeta = "$raiz/$c/$corto";
      break;
    }
  }

  $ficheros = 0;
  $bytes = 0;
  if ($carpeta) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
      if ($f->isFile()) {
        $ficheros++;
        $bytes += $f->getSize();
      }
    }
  }

  printf(
    "%-28s %-18s\n    commit %s del %s\n    disco: %s\n\n",
    $corto,
    $p['version'],
    substr($ref, 0, 12),
    $fecha,
    $carpeta ? "$ficheros ficheros, " . round($bytes / 1024) . " KB" : 'NO esta en disco'
  );
}

echo str_repeat('=', 78) . "\n";
echo "$total paquetes colgando de una rama.\n";
