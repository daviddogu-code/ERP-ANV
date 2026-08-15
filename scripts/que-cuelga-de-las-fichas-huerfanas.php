<?php

/**
 * @file
 * Busca en toda la base que filas apuntan a las fichas de importacion 5 y 6.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-cuelga-de-las-fichas-huerfanas.php
 *
 * Antes de borrar a mano por SQL hay que saber que se lleva por delante, y no
 * se puede preguntar a Drupal: las dos fichas declaran un tipo de importador
 * que ya no existe, y eso deja fuera de juego a los complementos que se encargan
 * de limpiar al borrar.
 *
 * Asi que se recorre el esquema entero. Para cada tabla se miran las columnas
 * que pueden guardar la referencia a una ficha -fid, feed, feed_id,
 * feeds_item_target_id, y entity_id en las tablas de campos de feeds_feed- y se
 * cuentan las filas con el 5 o el 6. Tambien se mira el almacen de clave-valor,
 * donde Feeds guarda el estado de cada importacion en una coleccion por ficha.
 *
 * Ademas comprueba lo primero que hay que comprobar cuando otro agente puede
 * lanzar el cron: que ninguna ficha tiene importacion periodica encendida.
 *
 * No toca nada. Solo mira.
 */

const FICHAS = [5, 6];

$bd = \Drupal::database();
$esquema = $bd->schema();

print "\n";

// ---------------------------------------------------------------------------
// 1. Importacion periodica: tiene que estar apagada en los dos sitios.
// ---------------------------------------------------------------------------
// Feeds la decide en dos niveles. El tipo de importador lleva import_period, y
// cada ficha lleva la columna next con la marca de tiempo de la proxima pasada.
// El cron de Feeds solo coge fichas con next distinto de -1, o sea que -1 en
// las dos es la unica combinacion segura.
print "  --- 1. importacion periodica ---\n\n";

foreach (\Drupal::configFactory()->listAll('feeds.feed_type.') as $nombre) {
  $datos = \Drupal::configFactory()->get($nombre)->getRawData();
  $periodo = $datos['import_period'] ?? NULL;
  printf("      tipo %-30s import_period = %-6s %s\n",
    $datos['id'] ?? '?',
    var_export($periodo, TRUE),
    ((int) $periodo === -1) ? 'APAGADA' : 'ENCENDIDA: la pasaria el cron');
}

print "\n";
foreach ($bd->select('feeds_feed', 'f')->fields('f', ['fid', 'title', 'type', 'status', 'next', 'queued'])->execute() as $f) {
  printf("      ficha %-3s %-26s next = %-12s queued = %-3s status = %s   %s\n",
    $f->fid, substr($f->title, 0, 26), $f->next, $f->queued, $f->status,
    ((int) $f->next === -1) ? 'no la coge el cron' : 'LA COGERIA EL CRON');
}

// Y los trabajos de cron de Feeds, que es la otra puerta por la que entra.
print "\n";
foreach (\Drupal::configFactory()->listAll('ultimate_cron.job.') as $nombre) {
  if (!str_contains($nombre, 'feeds')) {
    continue;
  }
  $datos = \Drupal::configFactory()->get($nombre)->getRawData();
  printf("      trabajo de cron %-24s %s\n",
    $datos['id'] ?? '?',
    !empty($datos['status']) ? 'ENCENDIDO' : 'apagado');
}

// ---------------------------------------------------------------------------
// 2. Toda la base, tabla por tabla, buscando el 5 y el 6.
// ---------------------------------------------------------------------------
// Las columnas candidatas se deciden por el nombre y por la tabla, no a dedo:
// asi aparecen tambien las tablas que nadie recuerda.
print "\n  --- 2. filas que apuntan a las fichas " . implode(' y ', FICHAS) . " ---\n\n";

$tablas = $bd->query('SHOW TABLES')->fetchCol();
printf("      %d tablas en la base\n\n", count($tablas));

$encontrado = [];

foreach ($tablas as $tabla) {
  $columnas = array_map(static fn($c) => $c->Field, $bd->query("SHOW COLUMNS FROM {$tabla}")->fetchAll());

  foreach ($columnas as $columna) {
    $candidata = FALSE;

    // La clave de la propia ficha, y la de las tablas de Feeds que la nombran.
    if ($tabla === 'feeds_feed' && $columna === 'fid') {
      $candidata = TRUE;
    }
    // Las tablas de campos de feeds_feed usan entity_id y revision_id.
    if (str_starts_with($tabla, 'feeds_feed__') && in_array($columna, ['entity_id', 'revision_id'], TRUE)) {
      $candidata = TRUE;
    }
    // Las tablas de Feeds que guardan la referencia con otro nombre.
    if (str_starts_with($tabla, 'feeds') && in_array($columna, ['feed_id', 'feed', 'fid'], TRUE)) {
      $candidata = TRUE;
    }
    // Y el campo feeds_item que Feeds cuelga de cada entidad importada.
    if ($columna === 'feeds_item_target_id') {
      $candidata = TRUE;
    }

    if (!$candidata) {
      continue;
    }

    $consulta = $bd->select($tabla, 't');
    $consulta->condition($columna, FICHAS, 'IN');
    $n = (int) $consulta->countQuery()->execute()->fetchField();

    $total = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();

    printf("      %-44s %-24s %4d de %d filas%s\n",
      $tabla, $columna, $n, $total, $n ? '   <-- HAY QUE LIMPIAR' : '');

    if ($n) {
      $encontrado[] = ['tabla' => $tabla, 'columna' => $columna, 'filas' => $n];
    }
  }
}

// ---------------------------------------------------------------------------
// 3. El almacen de clave-valor.
// ---------------------------------------------------------------------------
// Feeds guarda el estado de cada importacion en la coleccion feeds_feed.<fid>.
// Eso no es una tabla propia, son filas en key_value, y una busqueda por
// columnas no las ve.
print "\n  --- 3. estado guardado en key_value ---\n\n";

foreach (['key_value', 'key_value_expire'] as $tabla) {
  if (!$esquema->tableExists($tabla)) {
    continue;
  }
  $filas = $bd->select($tabla, 'k')
    ->fields('k', ['collection', 'name'])
    ->condition('collection', 'feeds%', 'LIKE')
    ->execute()
    ->fetchAll();

  if (!$filas) {
    printf("      %-20s ninguna coleccion de feeds\n", $tabla);
    continue;
  }
  foreach ($filas as $fila) {
    $deLasHuerfanas = in_array($fila->collection, array_map(static fn($f) => 'feeds_feed.' . $f, FICHAS), TRUE);
    printf("      %-20s %-24s %-30s %s\n", $tabla, $fila->collection, $fila->name,
      $deLasHuerfanas ? '<-- HAY QUE LIMPIAR' : '');
    if ($deLasHuerfanas) {
      $encontrado[] = ['tabla' => $tabla, 'columna' => 'collection=' . $fila->collection, 'filas' => 1];
    }
  }
}

// ---------------------------------------------------------------------------
// 4. Los ficheros subidos que declaran las dos fichas.
// ---------------------------------------------------------------------------
// El fetcher de subida guarda el fid del fichero dentro de la columna config y
// registra su uso en file_usage. Si la ficha del fichero ya no existe, lo que
// puede quedar es el registro de uso apuntando al vacio.
print "\n  --- 4. ficheros de origen y su registro de uso ---\n\n";

foreach ($bd->query('SELECT fid, title, config FROM {feeds_feed} WHERE fid IN (:fids[])', [':fids[]' => FICHAS]) as $f) {
  $config = @unserialize($f->config) ?: [];
  $fidFichero = $config['fetcher']['fid'] ?? NULL;
  $uso = $config['fetcher']['usage_id'] ?? NULL;
  printf("      ficha %s: fichero fid %s, usage_id %s\n", $f->fid, $fidFichero ?: '(ninguno)', $uso ?: '(ninguno)');

  if ($fidFichero) {
    $existe = (int) $bd->select('file_managed', 'm')->condition('fid', $fidFichero)->countQuery()->execute()->fetchField();
    printf("        ficha del fichero en file_managed: %s\n", $existe ? 'existe' : 'ya no existe');

    $usos = $bd->select('file_usage', 'u')->fields('u')->condition('fid', $fidFichero)->execute()->fetchAll();
    printf("        filas en file_usage: %d\n", count($usos));
    foreach ($usos as $u) {
      printf("          module=%s type=%s id=%s count=%s\n", $u->module, $u->type, $u->id, $u->count);
    }
  }
}

// Y cualquier fila de file_usage que diga que la usa una ficha de Feeds.
$usosFeeds = $bd->select('file_usage', 'u')->fields('u')
  ->condition('type', 'feeds_feed')
  ->execute()->fetchAll();
printf("\n      filas de file_usage con type=feeds_feed: %d\n", count($usosFeeds));
foreach ($usosFeeds as $u) {
  $marca = in_array((int) $u->id, FICHAS, TRUE) ? '   <-- HAY QUE LIMPIAR' : '';
  printf("        fid=%s module=%s id=%s count=%s%s\n", $u->fid, $u->module, $u->id, $u->count, $marca);
  if ($marca) {
    $encontrado[] = ['tabla' => 'file_usage', 'columna' => 'type=feeds_feed id', 'filas' => 1];
  }
}

// ---------------------------------------------------------------------------
// 5. Restos sueltos: el lote de borrado que alguien dejo a medias en 2024.
// ---------------------------------------------------------------------------
print "\n  --- 5. lotes a medias en la tabla batch ---\n\n";

if ($esquema->tableExists('batch')) {
  $lotes = $bd->select('batch', 'b')->fields('b', ['bid', 'timestamp'])->execute()->fetchAll();
  printf("      %d lotes guardados\n", count($lotes));
  foreach ($lotes as $lote) {
    $datos = (string) $bd->select('batch', 'b')->fields('b', ['batch'])->condition('bid', $lote->bid)->execute()->fetchField();
    $pistas = [];
    foreach (['Primo products', 'Trust products', 'Deleting items'] as $pista) {
      if (str_contains($datos, $pista)) {
        $pistas[] = $pista;
      }
    }
    printf("        bid %-6s del %s   %s\n", $lote->bid, date('Y-m-d', (int) $lote->timestamp),
      $pistas ? 'menciona: ' . implode(', ', $pistas) : '');
  }
}

// ---------------------------------------------------------------------------
print "\n" . str_repeat('=', 82) . "\n";
printf(" %d sitios que limpiar\n", count($encontrado));
print str_repeat('=', 82) . "\n\n";
foreach ($encontrado as $e) {
  printf("   %-44s %-32s %d filas\n", $e['tabla'], $e['columna'], $e['filas']);
}
print "\n";
