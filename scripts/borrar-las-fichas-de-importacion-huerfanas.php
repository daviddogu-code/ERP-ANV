<?php

/**
 * @file
 * Borra por SQL las fichas de importacion 5 y 6, las que no tienen tipo.
 *
 * Uso: php vendor/bin/drush.php scr scripts/borrar-las-fichas-de-importacion-huerfanas.php
 *      php vendor/bin/drush.php scr scripts/borrar-las-fichas-de-importacion-huerfanas.php -- --de-verdad
 *
 * Sin --de-verdad no cambia nada: cuenta lo que haria y escribe la copia de
 * seguridad de las filas. Con --de-verdad borra.
 *
 * Que son estas dos fichas: "Primo products" (fid 5) y "Trust products" (fid 6),
 * creadas en abril de 2024, del tipo de importador tec_products_csv_importer, que
 * ya no existe. Con el tipo borrado, la lista de importaciones de /admin/content/feed
 * se cae entera -FeedListBuilder pide la etiqueta del tipo de cada fila-, y con
 * ella se va el boton de borrar. Por eso se hace a mano.
 *
 * Por que a mano y no con la API: el borrado por codigo si funciona, pero Feeds
 * se come el error y salta los complementos de limpieza, o sea que las filas de
 * las tablas *__feeds_item y el registro de uso del fichero subido se quedarian
 * donde estan. Aqui se comprueba una por una que no hay ninguna, y se borra
 * sabiendo lo que se borra.
 *
 * Antes de borrar deja en backups/ un fichero .sql con las filas exactas, con
 * las columnas binarias en hexadecimal, listo para volver a meterlas.
 */

const FICHAS = [5, 6];

// El lote de borrado que alguien dejo a medias en mayo de 2024 sobre la ficha 5.
// No es una dependencia de feeds_feed, es una fila de la tabla batch del nucleo,
// pero nombra a la ficha y su unico trabajo era vaciarla.
const LOTE_A_MEDIAS = 99;

$deVerdad = FALSE;
foreach ((array) ($extra ?? []) as $argumento) {
  if (in_array($argumento, ['--de-verdad', 'de-verdad'], TRUE)) {
    $deVerdad = TRUE;
  }
}

$bd = \Drupal::database();
$esquema = $bd->schema();
$raiz = \Drupal::root();

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad
  ? " DE VERDAD: se borra\n"
  : " ENSAYO: solo se cuenta lo que haria. Anade -- --de-verdad para hacerlo\n";
echo str_repeat('=', 82) . "\n";

// ---------------------------------------------------------------------------
// Guardias. Si algo no esta como se comprobo, no se sigue.
// ---------------------------------------------------------------------------
echo "\n  --- guardias ---\n\n";

$abortar = [];

// 1. Las dos fichas tienen que existir y ser las que se comprobaron.
$esperadas = [
  5 => ['type' => 'tec_products_csv_importer', 'title' => 'Primo products', 'uuid' => '1de4b0a7-1840-4b8c-96de-aacc93fb5e85'],
  6 => ['type' => 'tec_products_csv_importer', 'title' => 'Trust products', 'uuid' => 'e921ef39-d021-4908-8956-2216509ae34d'],
];

$filas = $bd->query('SELECT * FROM {feeds_feed} WHERE fid IN (:fids[])', [':fids[]' => FICHAS])
  ->fetchAllAssoc('fid', \PDO::FETCH_ASSOC);

foreach ($esperadas as $fid => $esperada) {
  if (!isset($filas[$fid])) {
    $abortar[] = sprintf('la ficha %d no esta en feeds_feed', $fid);
    continue;
  }
  foreach ($esperada as $columna => $valor) {
    if ($filas[$fid][$columna] !== $valor) {
      $abortar[] = sprintf('la ficha %d tiene %s = "%s" y se esperaba "%s"', $fid, $columna, $filas[$fid][$columna], $valor);
    }
  }
}
if (count($filas) === count(FICHAS) && !$abortar) {
  echo "    las dos fichas son las que se comprobaron: correcto\n";
}

// 2. El tipo de importador tiene que seguir sin existir. Si alguien lo hubiera
// devuelto importando configuracion vieja, esto ya no seria una huerfana y el
// borrado tendria que pasar por la API para que limpiara lo suyo.
$tipos = \Drupal::configFactory()->listAll('feeds.feed_type.');
if (in_array('feeds.feed_type.tec_products_csv_importer', $tipos, TRUE)) {
  $abortar[] = 'el tipo tec_products_csv_importer ha vuelto: entonces no son huerfanas';
}
else {
  echo "    tec_products_csv_importer sigue sin existir: correcto\n";
}

// 3. Ninguna de las dos puede estar en marcha ni encolada.
foreach ($filas as $fid => $fila) {
  if ((int) $fila['queued'] !== 0) {
    $abortar[] = sprintf('la ficha %d esta encolada', $fid);
  }
  if ((int) $fila['next'] !== -1) {
    $abortar[] = sprintf('la ficha %d tiene importacion periodica (next = %s)', $fid, $fila['next']);
  }
}
echo "    ninguna encolada y ninguna con importacion periodica: correcto\n";

// 4. Todo lo que cuelga de ellas tiene que estar vacio. Estas son las tablas de
// verdad, sacadas del esquema y del codigo de Feeds, no adivinadas: las de sus
// campos configurables, las de su modulo de registro, las de suscripcion y las
// del campo feeds_item que Feeds cuelga de cada entidad importada.
$dependencias = [
  ['feeds_clean_list', 'feed_id'],
  ['feeds_subscription', 'fid'],
  ['feeds_import_log', 'feed'],
  ['feeds_import_log_entry', 'feed_id'],
];
foreach ($bd->query("SHOW TABLES LIKE 'feeds_feed__%'")->fetchCol() as $tabla) {
  $dependencias[] = [$tabla, 'entity_id'];
}
foreach ($bd->query("SHOW TABLES LIKE '%\\_\\_feeds_item'")->fetchCol() as $tabla) {
  $dependencias[] = [$tabla, 'feeds_item_target_id'];
}

$aLimpiar = [];
foreach ($dependencias as [$tabla, $columna]) {
  if (!$esquema->tableExists($tabla)) {
    continue;
  }
  $n = (int) $bd->select($tabla, 't')->condition($columna, FICHAS, 'IN')->countQuery()->execute()->fetchField();
  if ($n) {
    $aLimpiar[] = [$tabla, $columna, $n];
  }
}
printf("    %d tablas dependientes revisadas, %d con filas de las dos fichas\n", count($dependencias), count($aLimpiar));
foreach ($aLimpiar as [$tabla, $columna, $n]) {
  printf("      %s.%s: %d filas\n", $tabla, $columna, $n);
}

if ($abortar) {
  echo "\n  NO SE SIGUE:\n";
  foreach ($abortar as $motivo) {
    echo '    - ' . $motivo . "\n";
  }
  echo "\n";
  return;
}

// ---------------------------------------------------------------------------
// 1. La copia de seguridad de las filas.
// ---------------------------------------------------------------------------
// Se escribe siempre, tambien en ensayo: el fichero es lo que permite volver
// atras, y quererlo solo cuando ya se ha borrado es tarde.
echo "\n  --- 1. copia de seguridad de las filas ---\n\n";

$copia = sprintf('%s/backups/feeds-huerfanas-fid-5-y-6-%s.sql', $raiz, date('Y-m-d'));

// Las columnas binarias se escriben en hexadecimal para que el fichero se pueda
// volver a pasar por mysql sin que se estropee el contenido serializado.
$tipos = [];
foreach ($bd->query('SHOW COLUMNS FROM {feeds_feed}')->fetchAll() as $c) {
  $tipos[$c->Field] = strtolower($c->Type);
};
$esBinaria = static function (string $columna) use ($tipos): bool {
  $tipo = $tipos[$columna] ?? '';
  return str_contains($tipo, 'blob') || str_contains($tipo, 'binary');
};

$lineas = [];
$lineas[] = '-- Filas borradas de feeds_feed el ' . date('Y-m-d H:i') . '.';
$lineas[] = '--';
$lineas[] = '-- Las fichas de importacion 5 "Primo products" y 6 "Trust products", del tipo';
$lineas[] = '-- tec_products_csv_importer, que ya no existe. Para devolverlas:';
$lineas[] = '--   mysql -u root actatec < ' . basename($copia);
$lineas[] = '--';
$lineas[] = '-- Las columnas binarias van en hexadecimal.';
$lineas[] = '';

foreach ($filas as $fid => $fila) {
  $columnas = [];
  $valores = [];
  foreach ($fila as $columna => $valor) {
    $columnas[] = '`' . $columna . '`';
    if ($valor === NULL) {
      $valores[] = 'NULL';
    }
    elseif ($esBinaria($columna)) {
      $valores[] = '0x' . bin2hex((string) $valor);
    }
    else {
      $valores[] = "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $valor) . "'";
    }
  }
  $lineas[] = sprintf('-- fid %d: %s', $fid, $fila['title']);
  $lineas[] = sprintf('INSERT INTO `feeds_feed` (%s) VALUES (%s);', implode(', ', $columnas), implode(', ', $valores));
  $lineas[] = '';
}

// Y el lote a medias, que tambien se va.
$lote = $bd->query('SELECT * FROM {batch} WHERE bid = :bid', [':bid' => LOTE_A_MEDIAS])->fetchAssoc();
if ($lote) {
  $columnas = [];
  $valores = [];
  $tiposLote = [];
  foreach ($bd->query('SHOW COLUMNS FROM {batch}')->fetchAll() as $c) {
    $tiposLote[$c->Field] = strtolower($c->Type);
  }
  foreach ($lote as $columna => $valor) {
    $columnas[] = '`' . $columna . '`';
    $tipo = $tiposLote[$columna] ?? '';
    if ($valor === NULL) {
      $valores[] = 'NULL';
    }
    elseif (str_contains($tipo, 'blob') || str_contains($tipo, 'binary')) {
      $valores[] = '0x' . bin2hex((string) $valor);
    }
    else {
      $valores[] = "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $valor) . "'";
    }
  }
  $lineas[] = sprintf('-- lote a medias bid %d, del %s: "Deleting items from: Primo products"',
    LOTE_A_MEDIAS, date('Y-m-d', (int) $lote['timestamp']));
  $lineas[] = sprintf('INSERT INTO `batch` (%s) VALUES (%s);', implode(', ', $columnas), implode(', ', $valores));
  $lineas[] = '';
}

file_put_contents($copia, implode("\n", $lineas) . "\n");
printf("    escrita: %s (%s bytes)\n", str_replace($raiz . '/', '', strtr($copia, ['\\' => '/'])), number_format(filesize($copia)));
printf("    lleva %d filas de feeds_feed%s\n", count($filas), $lote ? ' y 1 de batch' : '');

// ---------------------------------------------------------------------------
// 2. El borrado.
// ---------------------------------------------------------------------------
echo "\n  --- 2. el borrado ---\n\n";

$hechos = [];

// Primero las dependencias, si hubiera alguna, y despues la ficha. Al reves
// dejaria filas apuntando a un fid que ya no existe.
foreach ($aLimpiar as [$tabla, $columna, $n]) {
  if ($deVerdad) {
    $borradas = $bd->delete($tabla)->condition($columna, FICHAS, 'IN')->execute();
    $hechos[] = sprintf('%s: %d filas borradas', $tabla, $borradas);
  }
  else {
    $hechos[] = sprintf('%s: borraria %d filas', $tabla, $n);
  }
}

if ($deVerdad) {
  $borradas = $bd->delete('feeds_feed')->condition('fid', FICHAS, 'IN')->execute();
  $hechos[] = sprintf('feeds_feed: %d filas borradas', $borradas);
}
else {
  $hechos[] = sprintf('feeds_feed: borraria %d filas', count($filas));
}

// El estado que Feeds guarda en el almacen de clave-valor, una coleccion por
// ficha. No es una tabla suya, son filas de key_value, asi que se borra a mano.
foreach (FICHAS as $fid) {
  $coleccion = 'feeds_feed.' . $fid;
  $n = (int) $bd->select('key_value', 'k')->condition('collection', $coleccion)->countQuery()->execute()->fetchField();
  if (!$n) {
    continue;
  }
  if ($deVerdad) {
    \Drupal::keyValue($coleccion)->deleteAll();
    $hechos[] = sprintf('key_value %s: %d filas borradas', $coleccion, $n);
  }
  else {
    $hechos[] = sprintf('key_value %s: borraria %d filas', $coleccion, $n);
  }
}

if ($lote) {
  if ($deVerdad) {
    $bd->delete('batch')->condition('bid', LOTE_A_MEDIAS)->execute();
    $hechos[] = sprintf('batch: borrado el lote a medias bid %d', LOTE_A_MEDIAS);
  }
  else {
    $hechos[] = sprintf('batch: borraria el lote a medias bid %d', LOTE_A_MEDIAS);
  }
}

foreach ($hechos as $hecho) {
  echo '    ' . $hecho . "\n";
}

// ---------------------------------------------------------------------------
// 3. Como queda.
// ---------------------------------------------------------------------------
echo "\n  --- 3. como queda ---\n\n";

foreach ($bd->select('feeds_feed', 'f')->fields('f', ['fid', 'title', 'type'])->execute() as $f) {
  printf("    fid %-3s %-28s %s\n", $f->fid, $f->title, $f->type);
}
$quedan = (int) $bd->select('feeds_feed')->countQuery()->execute()->fetchField();
printf("\n    %d fichas de importacion\n", $quedan);

echo "\n" . str_repeat('=', 82) . "\n";
printf(" %s: %d cosas\n", $deVerdad ? 'HECHO' : 'SE HARIA', count($hechos));
echo str_repeat('=', 82) . "\n\n";

if ($deVerdad) {
  echo "  Queda: bajar a 1 la cifra de 'importaciones' en scripts/comprobacion.php,\n";
  echo "  vaciar cache y pasar la comprobacion.\n\n";
}
