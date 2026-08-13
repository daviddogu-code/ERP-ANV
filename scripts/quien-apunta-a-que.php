<?php

/**
 * Segunda pasada del reconocimiento previo al borrado. No modifica nada.
 *
 * La primera pasada preguntaba a la configuracion quien referencia a los
 * materiales, y salio incompleta: `tec_bom_item` apunta a materiales y no
 * aparecia, porque su campo no declara `target_bundles` igual que los otros
 * dos. Asi que aqui no se pregunta a la configuracion, se cuenta contra los
 * datos: para cada campo de referencia del sitio, cuantas de sus filas apuntan
 * de verdad a algo que vamos a borrar.
 *
 * Es la comprobacion que sostiene el orden de borrado: los materiales van al
 * final, y para que eso sea seguro hay que estar seguro de que todo lo que los
 * referencia se borra antes.
 *
 * Ademas identifica lo que la primera pasada destapo y no estaba en la lista:
 * catorce nodos, catorce registros de produccion, cincuenta importaciones y
 * treinta y tres redirecciones.
 *
 * Uso: php vendor/bin/drush.php scr scripts/quien-apunta-a-que.php
 */

$etm = \Drupal::entityTypeManager();
$bd = \Drupal::database();

/** Vocabularios que se borran enteros. */
const VOCABULARIOS_A_BORRAR = ['tec_inventory', 'tec_brands', 'tec_patterns'];

/** Tipos de entidad cuyo contenido se borra entero. */
const TIPOS_A_BORRAR = ['tec_inventory', 'tec_line_item', 'tec_order', 'tec_product', 'tec_crm'];

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n";
}

// --- 1. Quien apunta a los terminos que se borran --------------------------
seccion('quien apunta a los terminos que se borran (contado sobre los datos)');

$aBorrar = $bd->select('taxonomy_term_field_data', 't')
  ->fields('t', ['tid'])
  ->condition('t.vid', VOCABULARIOS_A_BORRAR, 'IN')
  ->execute()->fetchCol();

printf("  terminos marcados para borrar: %d\n\n", count($aBorrar));

$almacenes = $etm->getStorage('field_storage_config')->loadMultiple();
$sinDeclarar = [];

foreach ($almacenes as $almacen) {
  if (!in_array($almacen->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
    continue;
  }
  if ($almacen->getSetting('target_type') !== 'taxonomy_term') {
    continue;
  }

  $tipoEntidad = $almacen->getTargetEntityTypeId();
  $campo = $almacen->getName();

  try {
    $tabla = $etm->getStorage($tipoEntidad)->getTableMapping()->getFieldTableName($campo);
  }
  catch (\Throwable $e) {
    continue;
  }
  if (!$bd->schema()->tableExists($tabla)) {
    continue;
  }

  $columna = $campo . '_target_id';
  $total = (int) $bd->select($tabla, 'f')->countQuery()->execute()->fetchField();

  $consulta = $bd->select($tabla, 'f');
  $consulta->innerJoin('taxonomy_term_field_data', 't', 't.tid = f.' . $columna);
  $consulta->condition('t.vid', VOCABULARIOS_A_BORRAR, 'IN');
  $apuntan = (int) $consulta->countQuery()->execute()->fetchField();

  if ($apuntan === 0) {
    continue;
  }

  // Reparto por vocabulario, para saber de que se trata.
  $porVid = $bd->select($tabla, 'f');
  $porVid->innerJoin('taxonomy_term_field_data', 't', 't.tid = f.' . $columna);
  $porVid->fields('t', ['vid']);
  $porVid->addExpression('COUNT(*)', 'n');
  $porVid->condition('t.vid', VOCABULARIOS_A_BORRAR, 'IN');
  $porVid->groupBy('t.vid');
  $detalle = [];
  foreach ($porVid->execute()->fetchAll() as $fila) {
    $detalle[] = $fila->vid . '=' . $fila->n;
  }

  printf("  %-16s %-34s %5d de %5d filas   %s\n", $tipoEntidad, $campo, $apuntan, $total, implode(' ', $detalle));

  // Reparto por paquete de la entidad que apunta, que es lo que fija el orden.
  $claves = $etm->getDefinition($tipoEntidad)->getKeys();
  if (!empty($claves['bundle'])) {
    $porPaquete = $bd->select($tabla, 'f');
    $porPaquete->fields('f', ['bundle']);
    $porPaquete->addExpression('COUNT(*)', 'n');
    $porPaquete->innerJoin('taxonomy_term_field_data', 't', 't.tid = f.' . $columna);
    $porPaquete->condition('t.vid', VOCABULARIOS_A_BORRAR, 'IN');
    $porPaquete->groupBy('f.bundle');
    foreach ($porPaquete->execute()->fetchAll() as $fila) {
      printf("        desde %-30s %5d\n", $fila->bundle, $fila->n);
    }
  }
}

// --- 2. Quien apunta a las entidades que se borran ------------------------
seccion('quien apunta a las entidades que se borran');

foreach ($almacenes as $almacen) {
  if (!in_array($almacen->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
    continue;
  }
  $destino = $almacen->getSetting('target_type');
  if (!in_array($destino, TIPOS_A_BORRAR, TRUE)) {
    continue;
  }

  $tipoEntidad = $almacen->getTargetEntityTypeId();
  $campo = $almacen->getName();

  try {
    $tabla = $etm->getStorage($tipoEntidad)->getTableMapping()->getFieldTableName($campo);
  }
  catch (\Throwable $e) {
    continue;
  }
  if (!$bd->schema()->tableExists($tabla)) {
    continue;
  }

  $n = (int) $bd->select($tabla, 'f')->countQuery()->execute()->fetchField();
  $aviso = in_array($tipoEntidad, TIPOS_A_BORRAR, TRUE) || $tipoEntidad === 'taxonomy_term' ? '' : '   <<< DESDE FUERA DE LA LISTA';
  printf("  %-16s %-34s -> %-14s %5d filas%s\n", $tipoEntidad, $campo, $destino, $n, $aviso);
}

// --- 3. Lo que aparecio y no estaba en la lista ---------------------------
seccion('los 14 nodos: que son');

foreach ($etm->getStorage('node')->loadMultiple() as $nodo) {
  printf("  %-5s %-12s %-46s %s\n", $nodo->id(), $nodo->bundle(), mb_substr($nodo->label(), 0, 45), $nodo->isPublished() ? 'publicado' : 'sin publicar');
}

seccion('los registros de produccion');

$almacenProd = $etm->getStorage('tec_production_entry');
foreach ($almacenProd->loadMultiple() as $entrada) {
  printf("  %-5s %s\n", $entrada->id(), mb_substr((string) $entrada->label(), 0, 60));
}

seccion('las importaciones de feeds');

foreach ($etm->getStorage('feeds_feed')->loadMultiple() as $feed) {
  printf("  %-5s %-30s %-30s %s\n", $feed->id(), $feed->bundle(), mb_substr($feed->label(), 0, 29), $feed->getState(\Drupal\feeds\StateInterface::PROCESS)->total ?? '');
}

seccion('las redirecciones');

$n = 0;
foreach ($bd->select('redirect', 'r')->fields('r', ['rid', 'redirect_source__path', 'redirect_redirect__uri'])->range(0, 40)->execute()->fetchAll() as $fila) {
  printf("  %-5s %-40s -> %s\n", $fila->rid, mb_substr($fila->redirect_source__path, 0, 39), mb_substr($fila->redirect_redirect__uri, 0, 40));
  $n++;
}
printf("  (%d mostradas)\n", $n);

print "\n";
