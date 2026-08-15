<?php

/**
 * @file
 * Lista los campos de un material y a donde apunta cada uno.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-campos-tiene-un-material.php
 *
 * Un material es un termino del vocabulario tec_inventory. Antes de mapear las
 * quince columnas del importador hay que saber a que se pueden mapear: que
 * campos hay, cuales son obligatorios, cuantos valores admiten y, en los de
 * referencia, a que vocabulario o a que entidad apuntan y si dejan crear el
 * destino al vuelo.
 *
 * Lo ultimo es lo que importa para el importador: un campo de referencia que
 * deje crear al vuelo llena el vocabulario de basura en cuanto el Excel traiga
 * un nombre con un espacio de mas.
 *
 * No toca nada.
 */

const ENTIDAD = 'taxonomy_term';
const PAQUETE = 'tec_inventory';

$gestorCampos = \Drupal::service('entity_field.manager');
$bd = \Drupal::database();

$definiciones = $gestorCampos->getFieldDefinitions(ENTIDAD, PAQUETE);

print "\n";
printf("  %d campos en %s.%s\n\n", count($definiciones), ENTIDAD, PAQUETE);

printf("  %-34s %-30s %-26s %-4s %-4s %s\n", 'campo', 'etiqueta', 'tipo', 'obl', 'card', 'apunta a');
print '  ' . str_repeat('-', 150) . "\n";

$referencias = [];

foreach ($definiciones as $nombre => $definicion) {
  $tipo = $definicion->getType();
  $apunta = '';

  if (in_array($tipo, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
    $ajustes = $definicion->getSettings();
    $destino = $ajustes['target_type'] ?? '?';
    $manejador = $ajustes['handler'] ?? '?';
    $paquetes = $ajustes['handler_settings']['target_bundles'] ?? [];
    $alVuelo = !empty($ajustes['handler_settings']['auto_create']);

    $apunta = $destino;
    if ($paquetes) {
      $apunta .= ' [' . implode(',', array_keys($paquetes)) . ']';
    }
    // El manejador "views" resuelve por una vista, no por una lista de
    // subtipos, y entonces no se puede crear el destino al vuelo.
    if (str_contains((string) $manejador, 'views')) {
      $vista = $ajustes['handler_settings']['view']['view_name'] ?? '?';
      $apunta .= ' por la vista ' . $vista;
    }
    $apunta .= $alVuelo ? '  CREA AL VUELO' : '';

    $referencias[$nombre] = [
      'destino' => $destino,
      'paquetes' => array_keys($paquetes),
      'manejador' => $manejador,
      'al_vuelo' => $alVuelo,
    ];
  }

  // Cuantas filas tiene guardadas hoy, que dice si el campo se usa.
  $tabla = ENTIDAD . '__' . $nombre;
  $filas = $bd->schema()->tableExists($tabla)
    ? (int) $bd->select($tabla)->countQuery()->execute()->fetchField()
    : NULL;

  printf("  %-34s %-30s %-26s %-4s %-4s %-46s %s\n",
    $nombre,
    substr((string) $definicion->getLabel(), 0, 30),
    $tipo,
    $definicion->isRequired() ? 'SI' : '-',
    $definicion->getFieldStorageDefinition()->getCardinality() === -1 ? 'inf' : $definicion->getFieldStorageDefinition()->getCardinality(),
    $apunta,
    $filas === NULL ? '' : $filas . ' filas');
}

// ---------------------------------------------------------------------------
// Los dos campos de proveedor, en detalle.
// ---------------------------------------------------------------------------
// Hay dos, y solo uno vale. Conviene tenerlo escrito para no volver a dudar.
print "\n  --- los dos campos de proveedor ---\n\n";

foreach (['field_tec_vendor', 'field_tec_suppliers'] as $nombre) {
  if (!isset($definiciones[$nombre])) {
    printf("      %-24s no existe en este paquete\n", $nombre);
    continue;
  }
  $d = $definiciones[$nombre];
  $ajustes = $d->getSettings();
  $tabla = ENTIDAD . '__' . $nombre;
  $filas = $bd->schema()->tableExists($tabla)
    ? (int) $bd->select($tabla)->countQuery()->execute()->fetchField()
    : -1;

  printf("      %s\n", $nombre);
  printf("        etiqueta:    %s\n", $d->getLabel());
  printf("        obligatorio: %s\n", $d->isRequired() ? 'SI' : 'no');
  printf("        cardinalidad:%s\n", $d->getFieldStorageDefinition()->getCardinality() === -1 ? ' varios' : ' ' . $d->getFieldStorageDefinition()->getCardinality());
  printf("        destino:     %s\n", $ajustes['target_type'] ?? '?');
  printf("        manejador:   %s\n", $ajustes['handler'] ?? '?');
  printf("        subtipos:    %s\n", implode(',', array_keys($ajustes['handler_settings']['target_bundles'] ?? [])) ?: '(ninguno declarado)');
  printf("        vista:       %s\n", $ajustes['handler_settings']['view']['view_name'] ?? '(ninguna)');
  printf("        crea al vuelo: %s\n", !empty($ajustes['handler_settings']['auto_create']) ? 'SI' : 'no');
  printf("        filas hoy:   %s\n\n", $filas < 0 ? 'sin tabla' : $filas);
}

// ---------------------------------------------------------------------------
// Los vocabularios y entidades a los que apuntan los campos de referencia.
// ---------------------------------------------------------------------------
print "  --- que hay hoy en los destinos de las referencias ---\n\n";

$vistos = [];
foreach ($referencias as $nombre => $r) {
  foreach (($r['paquetes'] ?: ['(todos)']) as $paquete) {
    $clave = $r['destino'] . '.' . $paquete;
    if (isset($vistos[$clave])) {
      continue;
    }
    $vistos[$clave] = TRUE;

    try {
      $consulta = \Drupal::entityTypeManager()->getStorage($r['destino'])->getQuery()->accessCheck(FALSE);
      if ($paquete !== '(todos)') {
        $definicionDestino = \Drupal::entityTypeManager()->getDefinition($r['destino']);
        $claveBundle = $definicionDestino->getKey('bundle');
        if ($claveBundle) {
          $consulta->condition($claveBundle, $paquete);
        }
      }
      $n = (int) $consulta->count()->execute();
      printf("      %-40s %4d fichas\n", $clave, $n);
    }
    catch (\Throwable $e) {
      printf("      %-40s no se puede contar: %s\n", $clave, $e->getMessage());
    }
  }
}

print "\n";
