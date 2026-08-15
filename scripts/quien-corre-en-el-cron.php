<?php

/**
 * @file
 * Quien corre en el cron de este ERP. No cambia nada: solo mira y cuenta.
 *
 * El cron esta pendiente de encender. Antes de poner la llamada del sistema hay
 * que saber tres cosas, y ninguna se puede suponer:
 *
 *   1. Quien dispara el cron hoy. Si lo dispara el trafico de visitas
 *      (automated_cron) la primera persona que entre por la manana se come toda
 *      la cola, y encima con el limite de tiempo de PHP web.
 *   2. Que se ejecuta de verdad. No solo los trabajos que ultimate_cron tiene
 *      apuntados, sino tambien las implementaciones de hook_cron, las colas que
 *      se vacian en cron y los procesos de ECA que escuchan el evento de cron.
 *   3. Cuales de esos trabajos son peligrosos y si siguen desarmados.
 *
 * Y de paso mira las fichas de Feeds: si alguna tiene importacion periodica
 * encendida, ejecutar el cron a mano lanzaria una importacion, y el importador
 * de materiales esta a medio arreglar.
 *
 * Uso: php vendor/bin/drush.php scr scripts/quien-corre-en-el-cron.php
 */

$etm = \Drupal::entityTypeManager();
$gestorModulos = \Drupal::moduleHandler();
$fechas = \Drupal::service('date.formatter');

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n\n";
}

function cuando(?int $sello): string {
  if (!$sello) {
    return 'nunca';
  }
  $hace = \Drupal::time()->getRequestTime() - $sello;
  return \Drupal::service('date.formatter')->format($sello, 'custom', 'Y-m-d H:i:s')
    . ' (hace ' . \Drupal::service('date.formatter')->formatInterval(max($hace, 1), 2) . ')';
}

// -----------------------------------------------------------------------------
// 1. Quien dispara el cron hoy.
// -----------------------------------------------------------------------------
seccion('1. quien dispara el cron hoy');

$automatico = $gestorModulos->moduleExists('automated_cron');
$ultimate = $gestorModulos->moduleExists('ultimate_cron');

printf("  automated_cron instalado    %s\n", $automatico ? 'SI  <<< lo dispara el trafico de visitas' : 'no');

if ($automatico) {
  $intervalo = \Drupal::config('automated_cron.settings')->get('interval');
  printf("  automated_cron intervalo    %s\n", $intervalo === NULL ? 'sin definir' : $intervalo . ' s' . ($intervalo == 0 ? ' (a cero: no dispara)' : ''));
}
else {
  // Aunque el modulo este fuera, la ficha de configuracion puede quedarse
  // guardada de una instalacion anterior y volver a la vida si se reinstala.
  $huella = \Drupal::configFactory()->get('automated_cron.settings')->getRawData();
  printf("  automated_cron.settings     %s\n", $huella ? 'la ficha existe todavia: ' . json_encode($huella) : 'no existe');
}

printf("  ultimate_cron instalado     %s\n", $ultimate ? 'SI (es el que reparte los trabajos)' : 'no');
printf("  system.cron_last            %s\n", cuando((int) \Drupal::state()->get('system.cron_last')));
printf("  system.cron logging         %s\n", \Drupal::config('system.cron')->get('logging') ? 'si' : 'no');

$clave = \Drupal::state()->get('system.cron_key');
printf("  clave de cron               %s\n", $clave ? 'existe (' . strlen($clave) . ' caracteres)' : 'NO EXISTE');
printf("  ruta con clave              /cron/%s\n", $clave ? substr($clave, 0, 8) . '...' : '?');

// La ruta /cron/{key} la sirve el nucleo (system.cron). Si ademas existe la
// ruta de ultimate_cron conviene saberlo, porque no es la misma.
$rutas = \Drupal::service('router.route_provider');
foreach (['system.cron', 'ultimate_cron.cron'] as $nombre) {
  try {
    printf("  ruta %-22s %s\n", $nombre, $rutas->getRouteByName($nombre)->getPath());
  }
  catch (\Throwable $e) {
    printf("  ruta %-22s no existe\n", $nombre);
  }
}

// -----------------------------------------------------------------------------
// 2. Los trabajos que ultimate_cron tiene apuntados.
// -----------------------------------------------------------------------------
seccion('2. los trabajos apuntados en ultimate_cron');

$reglaPorDefecto = \Drupal::config('ultimate_cron.settings')->get('scheduler.crontab.rules');
printf("  regla por defecto: %s\n\n", $reglaPorDefecto ? implode(' ', $reglaPorDefecto) : '(ninguna)');

$trabajos = [];
if ($ultimate) {
  $trabajos = $etm->getStorage('ultimate_cron_job')->loadMultiple();
  ksort($trabajos);
}

$encendidos = 0;
foreach ($trabajos as $trabajo) {
  $reglas = $trabajo->get('scheduler')['configuration']['rules'] ?? [];
  $cuando = $reglas ? implode(' ', $reglas) : '(por defecto)';

  if ($trabajo->status()) {
    $encendidos++;
  }

  $ultima = 'nunca';
  try {
    $registro = $trabajo->loadLatestLogEntry();
    if ($registro && $registro->start_time) {
      $ultima = $fechas->format((int) $registro->start_time, 'custom', 'Y-m-d H:i');
    }
  }
  catch (\Throwable $e) {
    $ultima = 'sin registro';
  }

  printf("  [%s] %-24s %-18s %-18s %s\n",
    $trabajo->status() ? 'ON ' : 'off',
    $trabajo->id(),
    $cuando,
    $ultima,
    $trabajo->get('module') ?? '');
}

printf("\n  %d trabajos apuntados, %d encendidos, %d apagados\n",
  count($trabajos), $encendidos, count($trabajos) - $encendidos);

// -----------------------------------------------------------------------------
// 3. Las implementaciones de hook_cron de verdad.
// -----------------------------------------------------------------------------
// Esta es la lista que importa: es lo que Drupal llama cuando corre el cron,
// independientemente de lo que ultimate_cron tenga apuntado.
seccion('3. implementaciones de hook_cron');

$conCron = [];
$gestorModulos->invokeAllWith('cron', function (callable $gancho, string $modulo) use (&$conCron) {
  $conCron[] = $modulo;
});
sort($conCron);

$rutasModulos = [];
foreach ($conCron as $modulo) {
  $ruta = $gestorModulos->getModule($modulo)->getPath();
  $donde = 'nucleo';
  if (str_contains($ruta, 'modules/custom')) {
    $donde = 'PROPIO';
  }
  elseif (str_contains($ruta, 'modules/contrib')) {
    $donde = 'contrib';
  }
  $rutasModulos[$modulo] = $donde;
  printf("  %-24s %-8s %s\n", $modulo, $donde, $ruta);
}

printf("\n  %d modulos implementan hook_cron\n", count($conCron));

// -----------------------------------------------------------------------------
// 4. Las colas que se vacian en cron.
// -----------------------------------------------------------------------------
seccion('4. colas que se procesan en cron');

$gestorColas = \Drupal::service('plugin.manager.queue_worker');
$colaFactory = \Drupal::service('queue');
$enCron = 0;

foreach ($gestorColas->getDefinitions() as $id => $definicion) {
  $esDeCron = !empty($definicion['cron']);
  $pendientes = 0;
  try {
    $pendientes = (int) $colaFactory->get($id)->numberOfItems();
  }
  catch (\Throwable $e) {
    $pendientes = -1;
  }

  if ($esDeCron) {
    $enCron++;
  }

  printf("  [%s] %-46s %4d pendientes  %s\n",
    $esDeCron ? 'cron' : '    ',
    $id,
    $pendientes,
    $esDeCron ? 'limite ' . ($definicion['cron']['time'] ?? 15) . ' s' : '');
}

printf("\n  %d colas se vacian en cron, de %d definidas\n", $enCron, count($gestorColas->getDefinitions()));

// -----------------------------------------------------------------------------
// 5. Los procesos de ECA que escuchan el cron.
// -----------------------------------------------------------------------------
seccion('5. procesos de eca con evento de cron');

$modelos = $etm->getStorage('eca')->loadMultiple();
$conCronEca = 0;

foreach ($modelos as $modelo) {
  foreach ($modelo->get('events') ?? [] as $evento) {
    $plugin = $evento['plugin'] ?? '';
    if (str_contains($plugin, 'cron')) {
      $conCronEca++;
      printf("  [%s] %-40s %s  %s\n",
        $modelo->status() ? 'ON ' : 'off',
        $modelo->label(),
        $plugin,
        json_encode($evento['configuration'] ?? []));
    }
  }
}

printf("\n  %d procesos de ECA reaccionan al cron, de %d modelos (%d encendidos)\n",
  $conCronEca,
  count($modelos),
  count(array_filter($modelos, fn($m) => $m->status())));

// -----------------------------------------------------------------------------
// 6. Los dos peligrosos: siguen desarmados?
// -----------------------------------------------------------------------------
seccion('6. los dos peligrosos');

// 6a. Las copias de seguridad de backup_migrate: es el que dejo 178 volcados y
// 111 MB dentro del arbol de ficheros. La bandera que manda es `enabled`, no el
// `status` de la ficha: Schedule::run() lee esa y solo esa.
print "  6a. horarios de backup_migrate\n\n";

if ($gestorModulos->moduleExists('backup_migrate')) {
  $horarios = $etm->getStorage('backup_migrate_schedule')->loadMultiple();
  if (!$horarios) {
    print "      no hay ningun horario definido\n";
  }
  foreach ($horarios as $horario) {
    printf("      %-16s enabled=%-3s status=%-3s cada %6d s, guarda %2d, a %s\n",
      $horario->id(),
      $horario->get('enabled') ? 'SI' : 'no',
      $horario->status() ? 'SI' : 'no',
      (int) $horario->get('period'),
      (int) $horario->get('keep'),
      $horario->get('destination_id'));
  }
  printf("\n      trabajo backup_migrate_cron: %s\n",
    ($t = $etm->getStorage('ultimate_cron_job')->load('backup_migrate_cron'))
      ? ($t->status() ? 'ENCENDIDO <<< OJO' : 'apagado')
      : 'no existe');
}
else {
  print "      backup_migrate no esta instalado\n";
}

print "\n  6b. el trabajo de ECA (eca_base_cron)\n\n";

$eca = $etm->getStorage('ultimate_cron_job')->load('eca_base_cron');
printf("      trabajo eca_base_cron: %s\n",
  $eca ? ($eca->status() ? 'ENCENDIDO <<< OJO' : 'apagado') : 'no existe');
printf("      procesos de ECA que lo usarian: %d\n", $conCronEca);

// -----------------------------------------------------------------------------
// 7. Feeds: hay importacion periodica encendida?
// -----------------------------------------------------------------------------
// Esto es el freno de mano antes de ejecutar el cron a mano. Si una ficha tiene
// import_period distinto de -1, el cron la importa. Y el importador de
// materiales esta ahora mismo a medio arreglar.
seccion('7. feeds: importacion periodica (solo consulta)');

if ($gestorModulos->moduleExists('feeds')) {
  // El periodo efectivo sale de dos sitios, y hay que mirar los dos. Manda el
  // del tipo de importacion; solo si el tipo permite periodo por ficha
  // (import_period_per_feed) y la ficha trae un valor distinto de -2, manda el
  // de la ficha. Es Feed::finishImport() quien lo resuelve asi.
  $apagado = \Drupal\feeds\FeedTypeInterface::SCHEDULE_NEVER;
  $heredado = \Drupal\feeds\FeedImportPeriodInterface::USE_FEED_TYPE_IMPORT_PERIOD;

  print "  los tipos de importacion (config activa)\n\n";

  $periodoTipo = [];
  foreach ($etm->getStorage('feeds_feed_type')->loadMultiple() as $tipo) {
    $periodo = (int) $tipo->getImportPeriod();
    $porFicha = $tipo instanceof \Drupal\feeds\FeedTypeImportPeriodPerFeedInterface
      && $tipo->isImportPeriodPerFeedAllowed();
    $periodoTipo[$tipo->id()] = $periodo;

    printf("      %-30s import_period %-10s periodo por ficha: %s%s\n",
      $tipo->id(),
      $periodo === $apagado ? 'off (-1)' : $periodo . ' s',
      $porFicha ? 'SI' : 'no',
      $periodo === $apagado ? '' : '   <<< ENCENDIDO');
  }

  print "\n  las fichas\n\n";

  $fichas = $etm->getStorage('feeds_feed')->loadMultiple();
  $periodicas = 0;

  printf("      %-4s %-26s %-26s %-12s %-12s %s\n",
    'id', 'ficha', 'tipo', 'suyo', 'efectivo', 'estado');

  $huerfanas = [];

  foreach ($fichas as $ficha) {
    $suyo = (int) $ficha->getImportPeriod();

    // Una ficha puede apuntar a un tipo que ya no existe. Aqui hay dos asi,
    // que es justo lo que otro agente esta limpiando ahora mismo. getType()
    // revienta en ese caso, asi que se pregunta con cuidado.
    $tipo = NULL;
    try {
      $tipo = $ficha->getType();
    }
    catch (\Throwable $e) {
      $huerfanas[] = $ficha->id();
    }

    if ($tipo === NULL) {
      // Sin tipo no hay periodo que heredar. La ficha no se puede importar:
      // cualquier intento revienta antes de tocar un dato.
      printf("      %-4s %-26s %-26s %-12s %-12s %s   <<< HUERFANA: su tipo ya no existe\n",
        $ficha->id(),
        mb_substr((string) $ficha->label(), 0, 25),
        $ficha->bundle(),
        $suyo === $heredado ? 'hereda (-2)' : ($suyo === $apagado ? 'off (-1)' : $suyo . ' s'),
        'sin tipo',
        $ficha->isActive() ? 'activa' : 'inactiva');
      continue;
    }

    $efectivo = $periodoTipo[$ficha->bundle()] ?? $apagado;
    if ($tipo instanceof \Drupal\feeds\FeedTypeImportPeriodPerFeedInterface
      && $tipo->isImportPeriodPerFeedAllowed()
      && $suyo !== $heredado) {
      $efectivo = $suyo;
    }

    $esPeriodica = $efectivo !== $apagado;
    if ($esPeriodica) {
      $periodicas++;
    }

    printf("      %-4s %-26s %-26s %-12s %-12s %s%s\n",
      $ficha->id(),
      mb_substr((string) $ficha->label(), 0, 25),
      $ficha->bundle(),
      $suyo === $heredado ? 'hereda (-2)' : ($suyo === $apagado ? 'off (-1)' : $suyo . ' s'),
      $esPeriodica ? $efectivo . ' s' : 'off (-1)',
      $ficha->isActive() ? 'activa' : 'inactiva',
      $esPeriodica ? '   <<< EL CRON LA IMPORTARIA' : '');
  }

  printf("\n  %d fichas, %d con importacion periodica encendida", count($fichas), $periodicas);
  printf(", %d huerfanas%s\n",
    count($huerfanas),
    $huerfanas ? ' (' . implode(', ', $huerfanas) . ')' : '');

  // Quien mira el reloj es feeds_cron: consulta las fichas cuyo `next` ya paso
  // y las encola. Si esta apagado, no encola nada, ni las huerfanas.
  print "\n  la hora a la que cada ficha se cree que le toca (campo `next`)\n\n";

  $bd = \Drupal::database();
  $tabla = $bd->schema()->tableExists('feeds_feed_field_data') ? 'feeds_feed_field_data' : 'feeds_feed';

  foreach ($bd->select($tabla, 'f')
    ->fields('f', ['fid', 'title', 'next', 'queued', 'status'])
    ->execute()->fetchAll() as $fila) {
    printf("      %-4s %-26s next=%-22s queued=%-4s status=%s\n",
      $fila->fid,
      mb_substr((string) $fila->title, 0, 25),
      (int) $fila->next <= 0 ? 'off (' . (int) $fila->next . ')' : cuando((int) $fila->next),
      (int) $fila->queued,
      $fila->status);
  }

  // El trabajo de ultimate_cron que mete las fichas vencidas en la cola.
  $feedsJob = $etm->getStorage('ultimate_cron_job')->load('feeds_cron');
  $feedsEncendido = $feedsJob && $feedsJob->status();
  printf("  trabajo feeds_cron (el que las encola): %s\n",
    $feedsJob ? ($feedsEncendido ? 'ENCENDIDO' : 'apagado') : 'no existe');

  // Las colas de refresco se vacian en processQueues(), que corre en cada
  // vuelta de cron aunque feeds_cron este apagado. Si quedara algo dentro, se
  // importaria. Por eso importa que esten vacias, no solo que el trabajo este
  // apagado.
  $enCola = 0;
  foreach ($gestorColas->getDefinitions() as $id => $definicion) {
    if (str_starts_with($id, 'feeds_feed_refresh:')) {
      $n = (int) $colaFactory->get($id)->numberOfItems();
      $enCola += $n;
      printf("  cola %-46s %d pendientes\n", $id, $n);
    }
  }

  if ($periodicas > 0 || $enCola > 0) {
    print "\n  NO EJECUTAR EL CRON: hay importacion periodica o cola pendiente.\n";
  }
  else {
    print "\n  Se puede ejecutar el cron: ninguna ficha se importa sola y las colas estan vacias.\n";
  }
}
else {
  print "  feeds no esta instalado\n";
}

// -----------------------------------------------------------------------------
// 8. Que tiene pendiente el purgador de campos borrados.
// -----------------------------------------------------------------------------
// field_cron es el unico de los trece que borra datos de verdad. No es un
// riesgo -es el que limpia-, pero conviene saber que va a recoger antes de
// encender el cron, no despues.
seccion('8. campos borrados esperando purga (solo consulta)');

$repositorio = \Drupal::service('entity_field.deleted_fields_repository');
$almacenes = $repositorio->getFieldStorageDefinitions();
$definiciones = $repositorio->getFieldDefinitions();

printf("  %d almacenes y %d campos marcados para purgar\n\n",
  count($almacenes), count($definiciones));

foreach ($almacenes as $definicion) {
  printf("      almacen  %-22s %s\n",
    $definicion->getTargetEntityTypeId(), $definicion->getName());
}

foreach ($definiciones as $definicion) {
  printf("      campo    %-22s %s en %s\n",
    $definicion->getTargetEntityTypeId(),
    $definicion->getName(),
    $definicion->getTargetBundle());
}

print "\n";
