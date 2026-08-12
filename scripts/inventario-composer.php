<?php

/**
 * @file
 * Inventario para la Fase 1. Se ejecuta con:
 *   drush scr scripts/inventario-composer.php
 *
 * Cruza cuatro fuentes que hoy no coinciden entre si:
 *   - lo que pide composer.json
 *   - lo que tiene apuntado composer.lock
 *   - lo que hay en modules/contrib y themes/contrib
 *   - lo que Drupal tiene realmente instalado
 *
 * No modifica nada. Solo imprime.
 */

$raiz = DRUPAL_ROOT;

$json = json_decode(file_get_contents("$raiz/composer.json"), TRUE);
$lock = json_decode(file_get_contents("$raiz/composer.lock"), TRUE);

// Lo que pide composer.json, solo paquetes de modulos y temas de Drupal.
$pedidos = [];
foreach ($json['require'] as $paquete => $version) {
  if (str_starts_with($paquete, 'drupal/') && !str_starts_with($paquete, 'drupal/core')) {
    $pedidos[substr($paquete, 7)] = $version;
  }
}
ksort($pedidos);

// Lo que composer.lock tiene apuntado.
$apuntados = [];
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $p) {
  if (str_starts_with($p['name'], 'drupal/') && !str_starts_with($p['name'], 'drupal/core')) {
    $apuntados[substr($p['name'], 7)] = $p['version'];
  }
}
ksort($apuntados);

// Lo que hay en disco.
$enDisco = [];
foreach (['modules/contrib', 'themes/contrib'] as $carpeta) {
  foreach (glob("$raiz/$carpeta/*", GLOB_ONLYDIR) as $ruta) {
    $enDisco[basename($ruta)] = $carpeta;
  }
}
ksort($enDisco);

// Lo que Drupal tiene instalado de verdad.
$instalados = array_keys(\Drupal::service('extension.list.module')->getAllInstalledInfo());
$temas = array_keys(\Drupal::service('theme_handler')->listInfo());
$activos = array_merge($instalados, $temas);

function titulo(string $t): void {
  echo "\n" . $t . "\n" . str_repeat('=', strlen($t)) . "\n";
}

/**
 * Un paquete puede cubrir varios modulos (los submodulos viven dentro).
 */
function cubierto(string $paquete, array $activos, string $raiz): bool {
  if (in_array($paquete, $activos)) {
    return TRUE;
  }
  foreach (['modules/contrib', 'themes/contrib'] as $c) {
    if (!is_dir("$raiz/$c/$paquete")) {
      continue;
    }
    foreach ($activos as $a) {
      if (glob("$raiz/$c/$paquete/**/$a.info.yml") || file_exists("$raiz/$c/$paquete/modules/$a/$a.info.yml")) {
        return TRUE;
      }
    }
  }
  return FALSE;
}

// -----------------------------------------------------------------------------
titulo('PASO 6 - Se piden en composer.json pero no estan en disco');
$fantasmas = [];
foreach ($pedidos as $n => $v) {
  if (!isset($enDisco[$n])) {
    $fantasmas[] = $n;
  }
}
echo count($fantasmas) . " paquetes:\n";
foreach ($fantasmas as $n) {
  echo "  drupal/$n  ($pedidos[$n])\n";
}

// -----------------------------------------------------------------------------
titulo('PASO 6b - Estan en disco y se piden, pero Drupal no los usa');
$sinUsar = [];
foreach ($pedidos as $n => $v) {
  if (isset($enDisco[$n]) && !cubierto($n, $activos, $raiz)) {
    $sinUsar[] = $n;
  }
}
echo count($sinUsar) . " paquetes (codigo muerto en disco):\n";
foreach ($sinUsar as $n) {
  echo "  drupal/$n  ($pedidos[$n])\n";
}

// -----------------------------------------------------------------------------
titulo('PASO 5 - Llegan de rebote: en el lock pero no en composer.json');
$rebote = [];
foreach ($apuntados as $n => $v) {
  if (!isset($pedidos[$n])) {
    $rebote[$n] = $v;
  }
}
echo count($rebote) . " paquetes:\n";
foreach ($rebote as $n => $v) {
  $uso = cubierto($n, $activos, $raiz) ? 'EN USO' : 'sin usar';
  $disco = isset($enDisco[$n]) ? 'en disco' : 'NO en disco';
  printf("  %-40s %-14s %-12s %s\n", "drupal/$n", $v, $disco, $uso);
}

// -----------------------------------------------------------------------------
titulo('PASO 9 - En disco y en uso, pero Composer no sabe que existen');
$huerfanos = [];
foreach ($enDisco as $n => $carpeta) {
  if (!isset($pedidos[$n]) && !isset($apuntados[$n])) {
    $huerfanos[$n] = cubierto($n, $activos, $raiz);
  }
}
$enUso = array_filter($huerfanos);
$muertos = array_diff_key($huerfanos, $enUso);
echo count($enUso) . " en uso (hay que meterlos):\n";
foreach (array_keys($enUso) as $n) {
  $v = 'sin version';
  foreach (['modules/contrib', 'themes/contrib'] as $c) {
    $f = "$raiz/$c/$n/$n.info.yml";
    if (file_exists($f) && preg_match("/^version:\s*'?([^'\n]+)'?/m", file_get_contents($f), $m)) {
      $v = trim($m[1]);
    }
  }
  printf("  %-40s %s\n", $n, $v);
}
echo "\n" . count($muertos) . " en disco sin usar (candidatos a borrar):\n";
foreach (array_keys($muertos) as $n) {
  echo "  $n\n";
}

// -----------------------------------------------------------------------------
titulo('PASO 10 - Comodines y ramas de desarrollo en composer.json');
$flojos = [];
foreach ($json['require'] as $paquete => $version) {
  if ($version === '*' || str_contains($version, 'dev')) {
    $flojos[$paquete] = $version;
  }
}
echo count($flojos) . " restricciones sin fijar:\n";
foreach ($flojos as $p => $v) {
  $n = str_starts_with($p, 'drupal/') ? substr($p, 7) : $p;
  $instalada = $apuntados[$n] ?? '?';
  $estado = isset($enDisco[$n]) ? (cubierto($n, $activos, $raiz) ? 'EN USO' : 'sin usar') : 'NO en disco';
  printf("  %-45s %-18s -> lock: %-20s %s\n", $p, $v, $instalada, $estado);
}

// -----------------------------------------------------------------------------
titulo('RESUMEN');
printf("  composer.json pide          %d modulos/temas\n", count($pedidos));
printf("  composer.lock tiene         %d\n", count($apuntados));
printf("  en disco hay                %d\n", count($enDisco));
printf("  Drupal usa                  %d modulos + %d temas\n", count($instalados), count($temas));
echo "\n";
printf("  a quitar de composer.json   %d fantasmas + %d sin usar\n", count($fantasmas), count($sinUsar));
printf("  a anclar (llegan de rebote) %d\n", count(array_filter($rebote, fn($v, $n) => cubierto($n, $activos, $raiz), ARRAY_FILTER_USE_BOTH)));
printf("  a meter (huerfanos en uso)  %d\n", count($enUso));
printf("  a fijar (comodines y dev)   %d\n", count($flojos));
echo "\n";
