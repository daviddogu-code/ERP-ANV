<?php

/**
 * @file
 * Como fue la ultima vuelta del cron. No cambia nada: solo mira y cuenta.
 *
 * Se lanza dos veces, antes y despues de ejecutar el cron a mano, y la
 * diferencia entre las dos salidas es la prueba de que la vuelta hizo lo que
 * tenia que hacer y nada mas.
 *
 * Mira cuatro cosas, y las cuatro por un motivo concreto:
 *
 *   1. La carpeta privada. Es donde el trabajo de copias volcaba la base de
 *      datos entera cada noche, y lo que dejo 178 volcados y 111 MB dentro del
 *      arbol de ficheros. Si despues de la vuelta hay un fichero nuevo ahi, el
 *      trabajo no estaba desarmado.
 *   2. Que trabajos corrieron, cuanto tardo cada uno y con que resultado. Lo
 *      cuenta el registro propio de ultimate_cron, no el de Drupal.
 *   3. Las colas. Si una crece, alguien encolo trabajo; si una baja, se vacio.
 *   4. Los avisos que dejo en el registro de Drupal, por canal.
 *
 * Uso: php vendor/bin/drush.php scr scripts/como-fue-la-vuelta-del-cron.php
 */

$bd = \Drupal::database();
$etm = \Drupal::entityTypeManager();
$fechas = \Drupal::service('date.formatter');

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n\n";
}

// -----------------------------------------------------------------------------
// 1. La carpeta privada: el sitio donde caian los volcados.
// -----------------------------------------------------------------------------
seccion('1. la carpeta privada');

$privada = \Drupal::service('file_system')->realpath('private://');
printf("  ruta: %s\n\n", $privada ?: '(no configurada)');

$ficheros = 0;
$bytes = 0;
$masNuevo = 0;
$nombreMasNuevo = '';

if ($privada && is_dir($privada)) {
  $recorrido = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($privada, FilesystemIterator::SKIP_DOTS)
  );
  foreach ($recorrido as $fichero) {
    if (!$fichero->isFile()) {
      continue;
    }
    $ficheros++;
    $bytes += $fichero->getSize();
    if ($fichero->getMTime() > $masNuevo) {
      $masNuevo = $fichero->getMTime();
      $nombreMasNuevo = $fichero->getFilename();
    }
  }
}

printf("  %d ficheros, %s\n", $ficheros, number_format($bytes / 1048576, 1) . ' MB');
printf("  el mas reciente: %s  %s\n",
  $nombreMasNuevo ?: '(ninguno)',
  $masNuevo ? $fechas->format($masNuevo, 'custom', 'Y-m-d H:i:s') : '');

// El destino de las copias es la carpeta `backup_migrate` de dentro de la
// privada. Lo que importa no es que la carpeta exista -existe desde siempre-,
// sino que siga sin un solo fichero dentro.
$destino = $privada . DIRECTORY_SEPARATOR . 'backup_migrate';
printf("\n  destino de las copias: %s\n", is_dir($destino) ? $destino : '(la carpeta no existe)');

$volcados = [];
if (is_dir($destino)) {
  foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($destino, FilesystemIterator::SKIP_DOTS)
  ) as $fichero) {
    if ($fichero->isFile()) {
      $volcados[] = $fichero->getFilename() . ' (' . number_format($fichero->getSize() / 1048576, 1) . ' MB, '
        . date('Y-m-d H:i:s', $fichero->getMTime()) . ')';
    }
  }
}

printf("  volcados dentro: %d%s\n", count($volcados), $volcados ? '   <<< OJO' : '   (vacia, como debe estar)');
foreach ($volcados as $volcado) {
  printf("      %s\n", $volcado);
}

// -----------------------------------------------------------------------------
// 2. Que corrio, cuanto tardo y con que resultado.
// -----------------------------------------------------------------------------
seccion('2. los trabajos: ultima vuelta de cada uno');

printf("  system.cron_last: %s\n\n",
  ($ultima = (int) \Drupal::state()->get('system.cron_last'))
    ? $fechas->format($ultima, 'custom', 'Y-m-d H:i:s')
    : 'nunca');

printf("  %-24s %-21s %-10s %s\n", 'trabajo', 'arranco', 'tardo', 'resultado');

$trabajos = $etm->getStorage('ultimate_cron_job')->loadMultiple();
ksort($trabajos);

$total = 0.0;
$corrieron = 0;

foreach ($trabajos as $trabajo) {
  if (!$trabajo->status()) {
    printf("  %-24s %s\n", $trabajo->id(), '(apagado)');
    continue;
  }

  $registro = NULL;
  try {
    $registro = $trabajo->loadLatestLogEntry();
  }
  catch (\Throwable $e) {
    // Sin registro todavia.
  }

  if (!$registro || !$registro->start_time) {
    printf("  %-24s %s\n", $trabajo->id(), 'sin registro');
    continue;
  }

  $duracion = $registro->end_time ? ($registro->end_time - $registro->start_time) : 0;
  $total += $duracion;
  $corrieron++;

  $mensaje = trim((string) ($registro->message ?? ''));
  if ($mensaje === '') {
    $mensaje = $registro->end_time ? 'termino sin decir nada' : 'SIN TERMINAR';
  }

  printf("  %-24s %-21s %-10s %s\n",
    $trabajo->id(),
    $fechas->format((int) $registro->start_time, 'custom', 'Y-m-d H:i:s'),
    number_format($duracion, 2) . ' s',
    mb_substr(preg_replace('/\s+/', ' ', $mensaje), 0, 60));
}

printf("\n  %d trabajos con registro, %s en total\n", $corrieron, number_format($total, 2) . ' s');

// -----------------------------------------------------------------------------
// 3. Las colas.
// -----------------------------------------------------------------------------
seccion('3. las colas');

$colaFactory = \Drupal::service('queue');
foreach (\Drupal::service('plugin.manager.queue_worker')->getDefinitions() as $id => $definicion) {
  printf("  %-50s %4d pendientes  %s\n",
    $id,
    (int) $colaFactory->get($id)->numberOfItems(),
    empty($definicion['cron']) ? '' : '(se vacia en cron)');
}

// -----------------------------------------------------------------------------
// 4. El registro de Drupal, por canal.
// -----------------------------------------------------------------------------
seccion('4. el registro de drupal');

printf("  %d filas en total\n\n", (int) $bd->select('watchdog', 'w')->countQuery()->execute()->fetchField());

// Solo la ultima media hora: es lo que puede haber dejado la vuelta de prueba.
$desde = \Drupal::time()->getRequestTime() - 1800;

$consulta = $bd->select('watchdog', 'w');
$consulta->addField('w', 'type');
$consulta->addField('w', 'severity');
$consulta->addExpression('COUNT(*)', 'n');
$consulta->condition('w.timestamp', $desde, '>=');
$consulta->groupBy('w.type');
$consulta->groupBy('w.severity');
$consulta->orderBy('n', 'DESC');

$gravedad = [
  0 => 'emergencia', 1 => 'alerta', 2 => 'critico', 3 => 'error',
  4 => 'aviso', 5 => 'atencion', 6 => 'info', 7 => 'depuracion',
];

$filas = $consulta->execute()->fetchAll();
if (!$filas) {
  print "  nada nuevo en la ultima media hora\n";
}
foreach ($filas as $fila) {
  printf("  %-24s %-12s %d\n",
    $fila->type,
    $gravedad[(int) $fila->severity] ?? $fila->severity,
    (int) $fila->n);
}

// Y los mensajes de gravedad error o peor, uno por uno, que son los que importan.
$graves = $bd->select('watchdog', 'w')
  ->fields('w', ['type', 'severity', 'message', 'variables', 'timestamp'])
  ->condition('w.timestamp', $desde, '>=')
  ->condition('w.severity', 3, '<=')
  ->orderBy('w.wid', 'DESC')
  ->range(0, 15)
  ->execute()->fetchAll();

if ($graves) {
  print "\n  los graves de la ultima media hora:\n\n";
  foreach ($graves as $fila) {
    $vars = @unserialize($fila->variables) ?: [];
    $texto = is_array($vars) ? strtr((string) $fila->message, array_map('strval', array_filter($vars, 'is_scalar'))) : (string) $fila->message;
    printf("      [%s] %-18s %s\n",
      $fechas->format((int) $fila->timestamp, 'custom', 'H:i:s'),
      $fila->type,
      mb_substr(preg_replace('/\s+/', ' ', strip_tags($texto)), 0, 100));
  }
}

print "\n";
