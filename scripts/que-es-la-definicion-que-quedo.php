<?php

/**
 * @file
 * Reconocimiento: que es exactamente la definicion instalada que quedo suelta.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-es-la-definicion-que-quedo.php
 *
 * El barrido de que-queda-de-app-link.php encontro una sola entrada viva en el
 * almacen de claves: `tec_app_link_type.entity_type` dentro de la coleccion
 * `entity.definitions.installed`. Antes de tocarla hay que saber tres cosas:
 *
 *   1. Que es. Se sospecha que es la definicion del tipo de SUBTIPO que ECK
 *      deriva por cada tipo de entidad (`<tipo>_type`), y que al borrar
 *      tec_app_link se retiro la del tipo pero no la del subtipo.
 *   2. Si es un caso raro o si los tipos vivos tienen su pareja igual. Si
 *      tec_order tiene guardadas las dos, la de app_link es un huerfano claro.
 *   3. Si el nucleo la mira en algun sitio. El informe de estado NO la ve,
 *      porque getChangeList() solo recorre los tipos que existen hoy, pero
 *      EntityDefinitionUpdateManager::getEntityTypes() devuelve TODAS las
 *      instaladas, y ahi si sale el fantasma.
 */

$almacen = \Drupal::keyValue('entity.definitions.installed');
$gestorTipos = \Drupal::entityTypeManager();

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n\n";
}

print "\n";

// ---------------------------------------------------------------------------
seccion('1. que hay guardado');

$valor = $almacen->get('tec_app_link_type.entity_type');

if ($valor === NULL) {
  print "  Ya no esta.\n";
}
else {
  printf("  Clase del objeto guardado: %s\n", get_class($valor));
  if ($valor instanceof \Drupal\Core\Entity\EntityTypeInterface) {
    printf("  id            %s\n", $valor->id());
    printf("  etiqueta      %s\n", (string) $valor->getLabel());
    printf("  clase         %s\n", $valor->getClass());
    printf("  proveedor     %s\n", $valor->getProvider());
    printf("  grupo         %s\n", $valor->get('group') ?? '');
    printf("  bundle_of     %s\n", (string) $valor->getBundleOf());
    printf("  prefijo conf. %s\n", (string) $valor->getConfigPrefix());
  }
}

// ---------------------------------------------------------------------------
seccion('2. lo tienen igual los tipos de ECK que siguen vivos');

// Si los vivos guardan las dos entradas, tipo y subtipo, entonces a app_link le
// falta una y le sobra la otra: es un huerfano y no una forma normal de estar.
$todas = array_keys($almacen->getAll());
$porTipo = [];
foreach ($todas as $clave) {
  if (!str_ends_with($clave, '.entity_type')) {
    continue;
  }
  $porTipo[] = substr($clave, 0, -strlen('.entity_type'));
}

$eck = [];
foreach ($gestorTipos->getStorage('eck_entity_type')->loadMultiple() as $tipo) {
  $eck[] = $tipo->id();
}
sort($eck);

printf("  Tipos de ECK vivos: %s\n\n", implode(', ', $eck));
printf("  %-26s %-14s %s\n", 'tipo', 'tipo instalado', 'subtipo instalado');
foreach ($eck as $id) {
  printf("  %-26s %-14s %s\n",
    $id,
    in_array($id, $porTipo, TRUE) ? 'si' : 'NO',
    in_array($id . '_type', $porTipo, TRUE) ? 'si' : 'NO');
}
printf("  %-26s %-14s %s   <<< el huerfano\n",
  'tec_app_link',
  in_array('tec_app_link', $porTipo, TRUE) ? 'si' : 'NO',
  in_array('tec_app_link_type', $porTipo, TRUE) ? 'si' : 'NO');

// ---------------------------------------------------------------------------
seccion('3. lo ve el nucleo');

$gestorDefiniciones = \Drupal::service('entity.definition_update_manager');

$fantasmas = [];
foreach ($gestorDefiniciones->getEntityTypes() as $id => $definicion) {
  if (!$gestorTipos->hasDefinition($id)) {
    $fantasmas[] = $id;
  }
}
printf("  Tipos instalados que el sitio ya no conoce: %d%s\n",
  count($fantasmas), $fantasmas ? ' -> ' . implode(', ', $fantasmas) : '');

printf("  El informe de estado pide actualizaciones: %s\n",
  $gestorDefiniciones->needsUpdates() ? 'SI' : 'no');

// Tambien conviene saber si queda algo de esquema guardado, que es lo que
// obligaria a tocar tablas.
$esquema = \Drupal::keyValue('entity.storage_schema.sql')->getAll();
$restos = array_values(array_filter(array_keys($esquema),
  static fn(string $c): bool => str_contains($c, 'app_link')));
printf("  Esquema SQL guardado de app_link: %d%s\n",
  count($restos), $restos ? ' -> ' . implode(', ', $restos) : '');

// Y si el subtipo tiene tabla propia, que un tipo de configuracion no deberia.
$prefijo = ($valor instanceof \Drupal\Core\Entity\EntityTypeInterface) ? $valor->getConfigPrefix() : '';
if ($prefijo !== '') {
  $cuantas = count(\Drupal::configFactory()->listAll($prefijo . '.'));
  printf("  Objetos de configuracion con el prefijo %s.: %d\n", $prefijo, $cuantas);
}

print "\n";
