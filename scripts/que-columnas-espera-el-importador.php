<?php

/**
 * @file
 * Radiografia del importador de materiales: columnas de origen y destinos.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-columnas-espera-el-importador.php
 *
 * El importador tec_inventory_csv_importer esta a medio hacer: declara quince
 * columnas de origen y solo tres llegan a un campo. Este guion pone las tres
 * cosas una al lado de la otra -las columnas que declara, los destinos que
 * tiene disponibles y los mapeos que hay hechos- para poder decidir que falta.
 *
 * Los nombres de columna se imprimen entre corchetes y con los espacios
 * marcados, porque el problema de un origen duplicado con un espacio de mas no
 * se ve a simple vista y es justo el que rompe la importacion.
 *
 * No toca nada.
 */

const IMPORTADOR = 'tec_inventory_csv_importer';

$tipo = \Drupal::entityTypeManager()->getStorage('feeds_feed_type')->load(IMPORTADOR);

if (!$tipo) {
  print "\n  no existe el importador " . IMPORTADOR . "\n\n";
  return;
}

// Marca los espacios para que un origen con un espacio detras se vea.
$visible = static function (?string $texto): string {
  if ($texto === NULL) {
    return '(nulo)';
  }
  return '[' . str_replace([' ', "\t"], ['.', '\t'], $texto) . ']';
};

print "\n";
printf("  importador: %s (%s)\n", $tipo->label(), $tipo->id());
printf("  lector:     %s\n", $tipo->getFetcher()->getPluginId());
printf("  analizador: %s\n", $tipo->getParser()->getPluginId());
printf("  procesador: %s\n", $tipo->getProcessor()->getPluginId());
printf("  periodo:    %s\n", $tipo->getImportPeriod() === -1 ? '-1 (apagado)' : $tipo->getImportPeriod());

$configAnalizador = $tipo->getParser()->getConfiguration();
printf("  limite de lineas por pasada: %s\n", $configAnalizador['line_limit'] ?? '(sin definir)');
printf("  separador: %s   sin cabeceras: %s\n",
  $visible($configAnalizador['delimiter'] ?? NULL),
  !empty($configAnalizador['no_headers']) ? 'si' : 'no');

// ---------------------------------------------------------------------------
// 1. Las columnas de origen que declara.
// ---------------------------------------------------------------------------
print "\n  --- 1. columnas de origen declaradas ---\n\n";

$origenes = $tipo->getCustomSources();
printf("      %d declaradas\n\n", count($origenes));

printf("      %-20s %-34s %-34s %s\n", 'clave', 'value (la columna del fichero)', 'label', 'tipo');
print '      ' . str_repeat('-', 110) . "\n";

// Agrupadas por el nombre de columna real, para cazar los duplicados.
$porColumna = [];

foreach ($origenes as $clave => $origen) {
  printf("      %-20s %-34s %-34s %s\n",
    $clave,
    $visible($origen['value'] ?? NULL),
    $visible($origen['label'] ?? NULL),
    $origen['type'] ?? '?');

  $valor = (string) ($origen['value'] ?? '');
  $porColumna[$valor][] = $clave;
}

print "\n      columnas del fichero que se piden mas de una vez:\n\n";
$hayDuplicados = FALSE;
foreach ($porColumna as $valor => $claves) {
  if (count($claves) > 1) {
    $hayDuplicados = TRUE;
    printf("        %-30s la piden %s\n", $visible($valor), implode(', ', $claves));
  }
}
if (!$hayDuplicados) {
  print "        ninguna\n";
}

print "\n      columnas que solo se diferencian en los espacios o en las mayusculas:\n\n";
$hayParecidos = FALSE;
$normalizadas = [];
foreach ($porColumna as $valor => $claves) {
  $normal = strtolower(preg_replace('/[\s_]+/', '', $valor));
  $normalizadas[$normal][$valor] = $claves;
}
foreach ($normalizadas as $normal => $variantes) {
  if (count($variantes) > 1) {
    $hayParecidos = TRUE;
    printf("        se parecen entre si:\n");
    foreach ($variantes as $valor => $claves) {
      printf("          %-30s la piden %s\n", $visible($valor), implode(', ', $claves));
    }
  }
}
if (!$hayParecidos) {
  print "        ninguna\n";
}

// ---------------------------------------------------------------------------
// 2. Los mapeos que hay.
// ---------------------------------------------------------------------------
print "\n  --- 2. mapeos hechos ---\n\n";

$mapeos = $tipo->getMappings();
printf("      %d mapeos\n\n", count($mapeos));

$origenesUsados = [];
foreach ($mapeos as $delta => $mapeo) {
  printf("      %d. destino %-32s\n", $delta, $mapeo['target']);
  foreach ($mapeo['map'] as $columna => $origen) {
    $existe = $origen === '' || isset($origenes[$origen]) || str_starts_with((string) $origen, 'parent:');
    printf("           %-14s <- %-22s %s\n",
      $columna,
      $origen === '' ? '(vacio)' : $origen,
      $existe ? '' : 'ESE ORIGEN NO ESTA DECLARADO');
    if ($origen !== '') {
      $origenesUsados[$origen] = TRUE;
      if (isset($origenes[$origen])) {
        printf("           %-14s    lee la columna %s del fichero\n", '', $visible($origenes[$origen]['value'] ?? NULL));
      }
    }
  }
  if (!empty($mapeo['unique'])) {
    printf("           unico por: %s\n", implode(', ', array_keys(array_filter($mapeo['unique']))));
  }
  if (!empty($mapeo['settings'])) {
    printf("           ajustes: %s\n", json_encode($mapeo['settings']));
  }
}

print "\n      columnas declaradas que NO llegan a ningun destino:\n\n";
$huerfanas = array_diff(array_keys($origenes), array_keys($origenesUsados));
foreach ($huerfanas as $clave) {
  printf("        %-20s %s\n", $clave, $visible($origenes[$clave]['value'] ?? NULL));
}
printf("\n      %d de %d columnas sin destino\n", count($huerfanas), count($origenes));

// ---------------------------------------------------------------------------
// 3. Los destinos disponibles.
// ---------------------------------------------------------------------------
// Esta es la lista de la que hay que elegir. Feeds la calcula preguntando a
// cada campo del material si tiene un complemento de destino que lo sepa
// rellenar, asi que aqui esta lo que de verdad se puede mapear.
print "\n  --- 3. destinos disponibles en un material ---\n\n";

$destinos = $tipo->getMappingTargets();
printf("      %d destinos\n\n", count($destinos));

printf("      %-34s %-30s %-4s %s\n", 'destino', 'etiqueta', 'obl', 'propiedades');
print '      ' . str_repeat('-', 110) . "\n";

$definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'tec_inventory');

foreach ($destinos as $nombre => $destino) {
  $definicion = $definiciones[$nombre] ?? NULL;
  $propiedades = method_exists($destino, 'getProperties') ? array_keys($destino->getProperties()) : [];
  printf("      %-34s %-30s %-4s %s\n",
    $nombre,
    substr((string) $destino->getLabel(), 0, 30),
    ($definicion && $definicion->isRequired()) ? 'SI' : '-',
    implode(', ', $propiedades));
}

// ---------------------------------------------------------------------------
// 4. Los campos obligatorios del material y si estan cubiertos.
// ---------------------------------------------------------------------------
// Un campo obligatorio sin mapear hace que Feeds rechace la fila entera al
// validar, asi que esta es la lista corta que no puede quedar coja.
print "\n  --- 4. campos obligatorios del material ---\n\n";

$destinosMapeados = array_column($mapeos, 'target');

printf("      %-34s %-32s %-14s %s\n", 'campo', 'etiqueta', 'tipo', 'mapeado');
print '      ' . str_repeat('-', 100) . "\n";

$sinCubrir = [];
foreach ($definiciones as $nombre => $definicion) {
  if (!$definicion->isRequired()) {
    continue;
  }
  // vid lo pone el procesador en su configuracion, no hace falta mapearlo.
  if ($nombre === 'vid') {
    printf("      %-34s %-32s %-14s lo pone el procesador\n", $nombre, $definicion->getLabel(), $definicion->getType());
    continue;
  }
  $mapeado = in_array($nombre, $destinosMapeados, TRUE);
  printf("      %-34s %-32s %-14s %s\n",
    $nombre,
    substr((string) $definicion->getLabel(), 0, 32),
    $definicion->getType(),
    $mapeado ? 'si' : 'NO');
  if (!$mapeado) {
    $sinCubrir[] = $nombre;
  }
}

printf("\n      %d campos obligatorios sin mapear: %s\n", count($sinCubrir), implode(', ', $sinCubrir) ?: 'ninguno');

print "\n";
