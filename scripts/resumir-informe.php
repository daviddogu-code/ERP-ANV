<?php

/**
 * @file
 * Resume el informe de upgrade_status, que sale en bruto y ocupa 47.000 lineas.
 *
 *   php scripts/resumir-informe.php C:/laragon/tmp/informe-d11-20260813.txt
 *   php scripts/resumir-informe.php <informe> <proyecto>   (detalle de uno)
 *
 * El informe imprime una tabla por proyecto y cada hallazgo lleva delante una de
 * tres etiquetas: "Fix now" es codigo que Drupal 11 ya no tiene, "Fix later" es
 * codigo que sigue existiendo pero esta marcado para desaparecer, y "Check
 * manually" es lo que el analizador no sabe decidir solo, casi siempre la linea
 * core_version_requirement del .info.yml.
 *
 * Dos avisos sobre como leer las cifras:
 *
 * - Los hallazgos que caen en tests/ y en el propio codigo del modulo no pesan
 *   igual. Un modulo con cuarenta avisos, todos en sus tests, no rompe nada al
 *   ejecutarse. Por eso se cuentan por separado.
 * - "Check manually" en el .info.yml es casi siempre ruido: significa que el
 *   modulo aun no ha declarado compatibilidad con la 11, no que no funcione. Se
 *   arregla actualizando el modulo, no tocando codigo.
 *
 * Solo lee.
 */

$fichero = $argv[1] ?? NULL;
$detalle = $argv[2] ?? NULL;

if (!$fichero || !file_exists($fichero)) {
  echo "Uso: php scripts/resumir-informe.php <ruta-del-informe> [proyecto]\n";
  exit(1);
}

$lineas = file($fichero, FILE_IGNORE_NEW_LINES);

$proyectos = [];
$actual = NULL;
$ficheroActual = '';
// Indice del hallazgo que se esta leyendo, para poder pegarle las lineas de
// continuacion. Con una referencia en vez de un indice, PHP acaba montando una
// estructura que se apunta a si misma.
$ultimo = -1;

foreach ($lineas as $i => $linea) {
  $limpia = rtrim($linea);

  // La cabecera de proyecto es la linea anterior a "Scanned on". Es el unico
  // ancla fiable: solo el primer proyecto va precedido de una fila de iguales,
  // los demas van detras de una de guiones.
  if (str_starts_with(ltrim($limpia), 'Scanned on ') && $i > 0) {
    $cabecera = trim($lineas[$i - 1]);
    if (preg_match('/^(.+?),\s*(\S*)$/', $cabecera, $p)) {
      $actual = $p[1];
      $proyectos[$actual] ??= [
        'version' => $p[2] !== '' ? $p[2] : '-',
        'ahora' => 0,
        'luego' => 0,
        'mano' => 0,
        'ahora_test' => 0,
        'luego_test' => 0,
        'info_yml' => FALSE,
        'motivos' => [],
        'hallazgos' => [],
      ];
      $ultimo = -1;
      // Sin esto, los primeros hallazgos de un proyecto se apuntan al ultimo
      // fichero del proyecto anterior.
      $ficheroActual = '';
    }
    continue;
  }

  if (!$actual) {
    continue;
  }

  if (str_starts_with($limpia, 'FILE:')) {
    $ruta = trim(substr($limpia, 5));

    // Cuando la ruta no cabe en el ancho de la tabla, el informe deja "FILE:"
    // solo en su linea y parte la ruta en trozos por las siguientes, sin
    // sangrarlos ni marcarlos de ninguna forma. Hay que recomponerla leyendo
    // hasta la primera linea en blanco. Sin esto, todos los hallazgos de las
    // rutas largas -que son justo las de tests/, las mas hondas- se quedan sin
    // fichero y parecen estar en el codigo del modulo.
    for ($j = $i + 1; $j < count($lineas); $j++) {
      $trozo = rtrim($lineas[$j]);
      if ($trozo === '' || str_starts_with($trozo, 'STATUS')) {
        break;
      }
      $ruta .= $trozo;
    }

    // El informe mezcla rutas relativas con barra y rutas absolutas de Windows
    // con barra invertida. Se unifican para poder mirarlas con un solo patron.
    $ficheroActual = str_replace('\\', '/', $ruta);
    $ultimo = -1;
    continue;
  }

  // Un hallazgo nuevo.
  if (preg_match('/^(Fix now|Fix later|Check manually)\s+(\d+)\s+(.*)$/', $limpia, $p)) {
    $esTest = (bool) preg_match('#(^|/)(tests?|Tests?)/#i', $ficheroActual);
    $esInfo = str_ends_with($ficheroActual, '.info.yml');

    $clave = ['Fix now' => 'ahora', 'Fix later' => 'luego', 'Check manually' => 'mano'][$p[1]];
    if ($esTest && $clave !== 'mano') {
      $proyectos[$actual][$clave . '_test']++;
    }
    else {
      $proyectos[$actual][$clave]++;
    }

    $proyectos[$actual]['hallazgos'][] = [
      'tipo' => $p[1],
      'linea' => $p[2],
      'texto' => trim($p[3]),
      'fichero' => $ficheroActual,
      'test' => $esTest,
      'info' => $esInfo,
    ];
    $ultimo = count($proyectos[$actual]['hallazgos']) - 1;
    continue;
  }

  // Continuacion: el mensaje se parte en varias lineas, todas sangradas hasta
  // la columna donde empieza la columna MESSAGE.
  if ($ultimo >= 0 && preg_match('/^\s{15,}(\S.*)$/', $limpia, $p)) {
    $proyectos[$actual]['hallazgos'][$ultimo]['texto'] .= ' ' . trim($p[1]);
  }
  else {
    $ultimo = -1;
  }
}

// Normaliza los motivos ya con el texto completo.
foreach ($proyectos as $nombre => &$d) {
  foreach ($d['hallazgos'] as $h) {
    if ($h['info'] && str_contains($h['texto'], 'core_version_requirement')) {
      $d['info_yml'] = TRUE;
      continue;
    }
    if ($h['tipo'] === 'Check manually' && str_contains($h['texto'], 'is not installed')) {
      continue;
    }
    $m = preg_replace('/\. Deprecated in.*$/', '', $h['texto']);
    $m = preg_replace('/\s+/', ' ', $m);
    $m = substr($m, 0, 100);
    $d['motivos'][$m] = ($d['motivos'][$m] ?? 0) + 1;
  }
}
unset($d);

// -----------------------------------------------------------------------------
// Detalle de un proyecto concreto.
// -----------------------------------------------------------------------------
if ($detalle) {
  $encontrado = NULL;
  foreach ($proyectos as $n => $d) {
    if (stripos($n, $detalle) !== FALSE) {
      $encontrado = $n;
      break;
    }
  }
  if (!$encontrado) {
    echo "No encuentro ningun proyecto que contenga '$detalle'.\n";
    exit(1);
  }
  $d = $proyectos[$encontrado];
  echo "\n$encontrado ({$d['version']})\n" . str_repeat('=', 78) . "\n";
  $porFichero = [];
  foreach ($d['hallazgos'] as $h) {
    $porFichero[$h['fichero']][] = $h;
  }
  foreach ($porFichero as $f => $hs) {
    echo "\n  $f\n";
    foreach ($hs as $h) {
      printf("    %-14s L%-6s %s\n", $h['tipo'], $h['linea'], substr($h['texto'], 0, 120));
    }
  }
  echo "\n";
  exit(0);
}

// -----------------------------------------------------------------------------
// Clasificacion.
// -----------------------------------------------------------------------------
$limpios = [];
$soloEtiqueta = [];
$soloTests = [];
$conTrabajo = [];

foreach ($proyectos as $nombre => $d) {
  $reales = $d['ahora'] + $d['luego'];
  $enTests = $d['ahora_test'] + $d['luego_test'];

  if ($reales > 0) {
    $conTrabajo[$nombre] = $d;
  }
  elseif ($enTests > 0) {
    $soloTests[$nombre] = $d;
  }
  elseif ($d['info_yml']) {
    $soloEtiqueta[$nombre] = $d;
  }
  else {
    $limpios[$nombre] = $d;
  }
}

uasort($conTrabajo, fn($a, $b) => ($b['ahora'] + $b['luego']) <=> ($a['ahora'] + $a['luego']));

function titulo(string $t): void {
  echo "\n" . $t . "\n" . str_repeat('=', strlen($t)) . "\n";
}

function enColumnas(array $nombres, int $porFila = 3, int $ancho = 26): void {
  sort($nombres);
  foreach (array_chunk($nombres, $porFila) as $fila) {
    echo '  ' . implode('', array_map(fn($n) => str_pad(substr($n, 0, $ancho - 2), $ancho), $fila)) . "\n";
  }
}

titulo('RESUMEN');
printf("  proyectos analizados                      %d\n", count($proyectos));
printf("  sin nada que tocar                        %d\n", count($limpios));
printf("  solo la etiqueta del .info.yml            %d\n", count($soloEtiqueta));
printf("  avisos solo en sus tests                  %d\n", count($soloTests));
printf("  con codigo que hay que tocar              %d\n", count($conTrabajo));

titulo('CON CODIGO QUE HAY QUE TOCAR');
printf("  %-32s %-14s %6s %6s   %s\n", 'proyecto', 'version', 'ahora', 'luego', '(en tests)');
echo '  ' . str_repeat('-', 76) . "\n";
foreach ($conTrabajo as $nombre => $d) {
  $tests = $d['ahora_test'] + $d['luego_test'];
  printf(
    "  %-32s %-14s %6d %6d   %s\n",
    substr($nombre, 0, 32),
    substr($d['version'], 0, 14),
    $d['ahora'],
    $d['luego'],
    $tests > 0 ? "+$tests" : ''
  );
}

titulo('LOS MOTIVOS MAS REPETIDOS');
$todos = [];
foreach ($conTrabajo as $nombre => $d) {
  foreach ($d['hallazgos'] as $h) {
    if ($h['test'] || $h['info'] || $h['tipo'] === 'Check manually') {
      continue;
    }
    $m = preg_replace('/\s+/', ' ', preg_replace('/\. Deprecated in.*$/', '', $h['texto']));
    $m = substr($m, 0, 95);
    $todos[$m]['veces'] = ($todos[$m]['veces'] ?? 0) + 1;
    $todos[$m]['donde'][$nombre] = TRUE;
  }
}
uasort($todos, fn($a, $b) => $b['veces'] <=> $a['veces']);
foreach (array_slice($todos, 0, 20, TRUE) as $m => $d) {
  printf("  %3dx en %2d  %s\n", $d['veces'], count($d['donde']), $m);
}

titulo('AVISOS SOLO EN SUS TESTS (no afectan al sitio funcionando)');
enColumnas(array_keys($soloTests));

titulo('SOLO LES FALTA DECLARAR LA 11 EN EL .INFO.YML');
enColumnas(array_keys($soloEtiqueta));

titulo('SIN NADA QUE TOCAR');
enColumnas(array_keys($limpios));
echo "\n";
