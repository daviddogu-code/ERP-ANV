<?php

/**
 * @file
 * Radiografia de Feeds leyendo la base de datos, no la API de entidades.
 *
 * Uso: php vendor/bin/drush.php scr scripts/como-esta-feeds-por-dentro.php
 *
 * Hay tres fichas de importacion y solo dos tipos de importador definidos. Dos
 * fichas, la 5 y la 6, declaran un tipo que ya no existe, y eso las vuelve
 * imposibles de cargar: el almacen de entidades pide el subtipo antes de
 * cualquier otra cosa. Por eso aqui no se carga ni una: se leen las tablas.
 *
 * Sirve para dos cosas. Saber que fichas hay y con que tipo, y saber que tablas
 * cuelgan de feeds_feed, que es lo que habra que limpiar a mano cuando se
 * borren las dos huerfanas. No toca nada.
 */

$bd = \Drupal::database();
$esquema = $bd->schema();

print "\n";

// ---------------------------------------------------------------------------
// 1. Las fichas, tal cual estan en la tabla.
// ---------------------------------------------------------------------------
print "  --- 1. filas de feeds_feed ---\n\n";

$columnas = array_map(
  static fn($c) => $c->Field,
  $bd->query('SHOW COLUMNS FROM {feeds_feed}')->fetchAll()
);
printf("      columnas: %s\n\n", implode(', ', $columnas));

$tiposDefinidos = [];
foreach (\Drupal::configFactory()->listAll('feeds.feed_type.') as $nombre) {
  $tiposDefinidos[] = str_replace('feeds.feed_type.', '', $nombre);
}
printf("      tipos de importador definidos: %s\n\n", implode(', ', $tiposDefinidos));

foreach ($bd->select('feeds_feed', 'f')->fields('f')->execute() as $fila) {
  $datos = (array) $fila;
  $tipo = $datos['type'] ?? '?';
  $huerfana = !in_array($tipo, $tiposDefinidos, TRUE);
  printf("      fid %-3s type=%-32s %s\n", $datos['fid'], $tipo, $huerfana ? 'HUERFANA: ese tipo no existe' : 'ok');
  foreach ($datos as $clave => $valor) {
    if (in_array($clave, ['fid', 'type'], TRUE)) {
      continue;
    }
    printf("            %-22s %s\n", $clave, $valor === NULL ? '(nulo)' : $valor);
  }
  print "\n";
}

// Los datos de campo de la ficha viven en feeds_feed_field_data, que es donde
// estan el titulo, el estado y el periodo de importacion.
if ($esquema->tableExists('feeds_feed_field_data')) {
  print "      feeds_feed_field_data:\n\n";
  foreach ($bd->select('feeds_feed_field_data', 'd')->fields('d')->execute() as $fila) {
    foreach ((array) $fila as $clave => $valor) {
      printf("            %-22s %s\n", $clave, $valor === NULL ? '(nulo)' : $valor);
    }
    print "\n";
  }
}

// ---------------------------------------------------------------------------
// 2. Todas las tablas de Feeds, con lo que tienen dentro.
// ---------------------------------------------------------------------------
print "  --- 2. tablas de feeds ---\n\n";

$tablas = $bd->query("SHOW TABLES LIKE 'feeds%'")->fetchCol();
foreach ($tablas as $tabla) {
  $n = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  $cols = array_map(static fn($c) => $c->Field, $bd->query("SHOW COLUMNS FROM {$tabla}")->fetchAll());
  printf("      %-40s %6d filas   %s\n", $tabla, $n, implode(', ', $cols));
}

// ---------------------------------------------------------------------------
// 3. Que tablas declara el nucleo para el tipo de entidad feeds_feed.
// ---------------------------------------------------------------------------
// Esto es lo que de verdad importa para el borrado: no basta con adivinar por
// el nombre, hay que preguntarle al mapa de almacenamiento cuales son las
// tablas de la entidad y cuales las de sus campos.
print "\n  --- 3. mapa de almacenamiento de feeds_feed ---\n\n";

$definicion = \Drupal::entityTypeManager()->getDefinition('feeds_feed');
printf("      tabla base:      %s\n", $definicion->getBaseTable() ?: '(ninguna)');
printf("      tabla de datos:  %s\n", $definicion->getDataTable() ?: '(ninguna)');
printf("      tabla de revs:   %s\n", $definicion->getRevisionTable() ?: '(ninguna)');
printf("      tabla revs datos:%s\n", $definicion->getRevisionDataTable() ?: '(ninguna)');
printf("      clave:           %s\n", $definicion->getKey('id'));

$mapa = \Drupal::service('entity_type.manager')->getStorage('feeds_feed')->getTableMapping();
print "\n      tablas segun el mapa:\n";
foreach ($mapa->getTableNames() as $tabla) {
  $campos = $mapa->getFieldNames($tabla);
  $existe = $esquema->tableExists($tabla);
  $n = $existe ? (int) $bd->select($tabla)->countQuery()->execute()->fetchField() : -1;
  printf("        %-44s %s  %s\n",
    $tabla,
    $existe ? sprintf('%6d filas', $n) : '   SIN TABLA',
    implode(', ', $campos));
}

// Y los campos configurables que alguien haya anadido a las fichas.
print "\n      campos configurables de feeds_feed:\n";
$hay = FALSE;
foreach (\Drupal::entityTypeManager()->getStorage('field_config')->loadMultiple() as $campo) {
  if ($campo->getTargetEntityTypeId() === 'feeds_feed') {
    $hay = TRUE;
    printf("        %-44s tipo %s\n", $campo->id(), $campo->getType());
  }
}
if (!$hay) {
  print "        ninguno\n";
}

// ---------------------------------------------------------------------------
// 4. feeds_item: que entidades importo cada ficha.
// ---------------------------------------------------------------------------
// Esta es la pista buena. feeds_item es la tabla que Feeds usa para recordar
// que entidad vino de que ficha. Si las fichas 5 y 6 llenaban tec_gui, aqui
// estaria escrito, o al menos estaria el nombre de la tabla que lo guardaba.
print "\n  --- 4. feeds_item, quien importo que ---\n\n";

if (!$esquema->tableExists('feeds_item')) {
  print "      no existe la tabla feeds_item\n";
}
else {
  $cols = array_map(static fn($c) => $c->Field, $bd->query('SHOW COLUMNS FROM {feeds_item}')->fetchAll());
  printf("      columnas: %s\n\n", implode(', ', $cols));

  $filas = $bd->query('SELECT * FROM {feeds_item}')->fetchAll();
  printf("      %d filas en total\n\n", count($filas));

  // Agrupado por ficha y por tipo de entidad de destino.
  $porFicha = [];
  foreach ($filas as $fila) {
    $d = (array) $fila;
    $clave = ($d['target_id'] ?? '?') . ' | ' . ($d['entity_type'] ?? '?');
    $porFicha[$clave] = ($porFicha[$clave] ?? 0) + 1;
  }
  foreach ($porFicha as $clave => $n) {
    printf("      ficha %-30s %6d filas\n", $clave, $n);
  }
}

// Y las tablas de tipo *__feeds_item, que es la forma vieja en la que Feeds
// colgaba de cada entidad un campo con su procedencia.
print "\n      tablas *feeds_item que existan hoy:\n";
$conFeedsItem = $bd->query("SHOW TABLES LIKE '%feeds_item%'")->fetchCol();
foreach ($conFeedsItem as $tabla) {
  $n = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  printf("        %-44s %6d filas\n", $tabla, $n);
}

print "\n";
