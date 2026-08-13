<?php

/**
 * @file
 * Para cada modulo y tema del proyecto, pregunta a drupal.org si existe una
 * version publicada que admita Drupal 11.
 *
 *   php scripts/hay-version-para-11.php
 *   php scripts/hay-version-para-11.php --csv > salida.csv
 *
 * Esta es la mitad de la pregunta que el informe de upgrade_status no responde.
 * Aquel mira el codigo que tenemos y dice si usa cosas que la 11 haya quitado;
 * este mira lo que hay publicado y dice si podemos actualizar. Un modulo puede
 * salir impecable en el informe y no tener ninguna version compatible, y
 * entonces bloquea igual.
 *
 * La columna que mas importa no es si hay version para la 11, sino si esa misma
 * version admite tambien la 10. Cuando la admite, el modulo se puede actualizar
 * *antes* de mover el nucleo, con el ERP funcionando y comprobando entre paso y
 * paso. Cuando no, hay que moverlo a la vez que el nucleo, y eso convierte el
 * salto en una operacion de muchas variables a la vez, que es justo lo que hace
 * imposible depurar una actualizacion.
 *
 * Solo lee. No toca ni el proyecto ni drupal.org mas alla de descargar el
 * historial publico de versiones de cada proyecto.
 */

require __DIR__ . '/../vendor/autoload.php';

use Composer\Semver\Semver;

const NUCLEO_11 = '11.4.0';
const NUCLEO_10 = '10.6.15';

$raiz = dirname(__DIR__);
$csv = in_array('--csv', $argv, TRUE);

// -----------------------------------------------------------------------------
// Que paquetes hay que preguntar.
// -----------------------------------------------------------------------------
$lock = json_decode(file_get_contents("$raiz/composer.lock"), TRUE);

$nuestros = [];
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $p) {
  if (!str_starts_with($p['name'], 'drupal/') || str_starts_with($p['name'], 'drupal/core')) {
    continue;
  }
  $nuestros[substr($p['name'], 7)] = $p['version'];
}
ksort($nuestros);

// -----------------------------------------------------------------------------
// Utilidades.
// -----------------------------------------------------------------------------

/**
 * Descarga el historial de versiones de un proyecto.
 */
function historial(string $proyecto): ?string {
  $ch = curl_init("https://updates.drupal.org/release-history/$proyecto/current");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => TRUE,
    CURLOPT_USERAGENT => 'tec-erp-upgrade-check',
  ]);
  $xml = curl_exec($ch);
  $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return ($codigo === 200 && $xml) ? $xml : NULL;
}

/**
 * Dice si una cadena de compatibilidad admite una version del nucleo.
 *
 * Las cadenas vienen en varios dialectos: semver normal ("^9.5 || ^10 || ^11"),
 * rangos con desigualdades (">=8.9 <11") y la forma antigua de Drupal 8 ("8.x"),
 * que semver no entiende. Esta ultima siempre es un no.
 */
function admite(string $compatibilidad, string $nucleo): bool {
  $compatibilidad = trim($compatibilidad);
  if ($compatibilidad === '' || preg_match('/^\d+\.x$/', $compatibilidad)) {
    return FALSE;
  }
  try {
    return Semver::satisfies($nucleo, $compatibilidad);
  }
  catch (\Throwable $e) {
    return FALSE;
  }
}

/**
 * Cuanto de asentada esta una version. Mas alto es mejor.
 */
function madurez(string $version): int {
  $v = strtolower($version);
  return match (TRUE) {
    str_contains($v, 'dev') => 0,
    str_contains($v, 'alpha') => 1,
    str_contains($v, 'beta') => 2,
    str_contains($v, 'rc') => 3,
    default => 4,
  };
}

/**
 * Deja una version comparable: 8.x-1.5 y 1.5.0 son la misma.
 */
function normaliza(string $v): string {
  $v = ltrim(preg_replace('/^8\.x-/', '', trim($v)), 'v');
  if (!preg_match('/^(\d+(?:\.\d+)*)(.*)$/', $v, $p)) {
    return $v;
  }
  $n = explode('.', $p[1]);
  while (count($n) < 3) {
    $n[] = '0';
  }
  return implode('.', $n) . $p[2];
}

// -----------------------------------------------------------------------------
// La consulta.
// -----------------------------------------------------------------------------
$resultados = [];
$fallos = [];
$total = count($nuestros);
$i = 0;

foreach ($nuestros as $nombre => $versionNuestra) {
  $i++;
  if (!$csv) {
    fprintf(STDERR, "\r  consultando %d/%d  %-30s", $i, $total, $nombre);
  }

  $xml = historial($nombre);
  if (!$xml) {
    $fallos[$nombre] = 'no responde drupal.org';
    continue;
  }

  $doc = @simplexml_load_string($xml);
  if (!$doc || !isset($doc->releases)) {
    $fallos[$nombre] = 'sin historial de versiones';
    continue;
  }

  // La mejor candidata: admite la 11, y entre las que la admiten preferimos la
  // mas asentada y, a igualdad, la mas nueva.
  $mejor = NULL;
  $mejorPuente = NULL;

  foreach ($doc->releases->release as $r) {
    if ((string) $r->status !== 'published') {
      continue;
    }
    $compat = (string) ($r->core_compatibility ?? '');
    if (!admite($compat, NUCLEO_11)) {
      continue;
    }

    $candidata = [
      'version' => (string) $r->version,
      'compat' => $compat,
      'segura' => isset($r->security['covered']) && (string) $r->security['covered'] === '1',
      'madurez' => madurez((string) $r->version),
      'puente' => admite($compat, NUCLEO_10),
      'fecha' => date('Y-m-d', (int) $r->date),
    ];

    $gana = fn(?array $a, array $b) => $a === NULL
      || $b['madurez'] > $a['madurez']
      || ($b['madurez'] === $a['madurez'] && version_compare(normaliza($b['version']), normaliza($a['version']), '>'));

    if ($gana($mejor, $candidata)) {
      $mejor = $candidata;
    }
    // Una version puente admite la 10 y la 11 a la vez: permite actualizar sin
    // haber movido todavia el nucleo.
    if ($candidata['puente'] && $gana($mejorPuente, $candidata)) {
      $mejorPuente = $candidata;
    }
  }

  $resultados[$nombre] = [
    'nuestra' => $versionNuestra,
    'mejor' => $mejor,
    'puente' => $mejorPuente,
  ];
}

if (!$csv) {
  fprintf(STDERR, "\r%-60s\r", '');
}

// -----------------------------------------------------------------------------
// Salida.
// -----------------------------------------------------------------------------
if ($csv) {
  echo "modulo,version_nuestra,mejor_para_11,compatibilidad,estable,cubre_avisos,sirve_de_puente,fecha\n";
  foreach ($resultados as $n => $r) {
    $m = $r['mejor'];
    printf(
      "%s,%s,%s,\"%s\",%s,%s,%s,%s\n",
      $n,
      $r['nuestra'],
      $m['version'] ?? '',
      $m['compat'] ?? '',
      isset($m) && $m['madurez'] === 4 ? 'si' : 'no',
      isset($m) && $m['segura'] ? 'si' : 'no',
      $r['puente'] ? 'si' : 'no',
      $m['fecha'] ?? ''
    );
  }
  exit(0);
}

$puente = [];
$soloCon11 = [];
$sinNada = [];
$yaEsta = [];

foreach ($resultados as $n => $r) {
  if (!$r['mejor']) {
    $sinNada[$n] = $r;
  }
  elseif (normaliza($r['nuestra']) === normaliza($r['mejor']['version'])) {
    $yaEsta[$n] = $r;
  }
  elseif ($r['puente']) {
    $puente[$n] = $r;
  }
  else {
    $soloCon11[$n] = $r;
  }
}

function titulo(string $t): void {
  echo "\n" . $t . "\n" . str_repeat('=', strlen($t)) . "\n";
}

function tabla(array $lista, bool $usarPuente = TRUE): void {
  printf("  %-30s %-18s %-18s %-6s %s\n", 'modulo', 'ahora', 'para la 11', 'aviso', 'compatibilidad');
  echo '  ' . str_repeat('-', 100) . "\n";
  foreach ($lista as $n => $r) {
    $m = ($usarPuente && $r['puente']) ? $r['puente'] : $r['mejor'];
    printf(
      "  %-30s %-18s %-18s %-6s %s\n",
      substr($n, 0, 30),
      substr($r['nuestra'], 0, 18),
      substr($m['version'], 0, 18),
      $m['segura'] ? 'si' : ($m['madurez'] < 4 ? 'NO*' : 'no'),
      $m['compat']
    );
  }
}

titulo('RESUMEN');
printf("  paquetes consultados                              %d\n", count($resultados));
printf("  ya estan en una version que admite la 11          %d\n", count($yaEsta));
printf("  hay version que admite la 10 y la 11 a la vez     %d\n", count($puente));
printf("  solo hay version que exige la 11                  %d\n", count($soloCon11));
printf("  NO hay ninguna version para la 11                 %d\n", count($sinNada));
if ($fallos) {
  printf("  no se han podido consultar                        %d\n", count($fallos));
}

titulo('SE PUEDEN ACTUALIZAR YA, SOBRE DRUPAL 10');
echo "Admiten la 10 y la 11 a la vez, asi que se mueven antes del salto de nucleo.\n\n";
tabla($puente);

if ($soloCon11) {
  titulo('HAY QUE MOVERLOS A LA VEZ QUE EL NUCLEO');
  echo "Su version para la 11 ya no admite la 10, asi que no se pueden probar antes.\n\n";
  tabla($soloCon11, FALSE);
}

if ($sinNada) {
  titulo('SIN VERSION PARA LA 11 - ESTOS SON LOS QUE DECIDEN');
  printf("  %-30s %-18s\n", 'modulo', 'ahora');
  echo '  ' . str_repeat('-', 50) . "\n";
  foreach ($sinNada as $n => $r) {
    printf("  %-30s %-18s\n", substr($n, 0, 30), $r['nuestra']);
  }
}

if ($fallos) {
  titulo('NO SE HAN PODIDO CONSULTAR');
  foreach ($fallos as $n => $motivo) {
    printf("  %-30s %s\n", $n, $motivo);
  }
}

titulo('YA ESTAN LISTOS');
echo '  ' . implode(', ', array_keys($yaEsta)) . "\n";

echo "\n  (*) NO = la version no esta cubierta por la politica de avisos de seguridad,\n";
echo "      porque es alfa, beta o candidata. Se puede usar, pero sin red.\n\n";
