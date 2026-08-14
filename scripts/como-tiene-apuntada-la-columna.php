<?php

/**
 * @file
 * Que especificacion tiene Drupal apuntada para las columnas de un campo.
 *
 * Al cambiar los decimales de un campo hay que darle al cambio de columna la
 * especificacion entera, no solo los dos numeros: si se olvida el "not null" o
 * cualquier otra cosa, la columna cambia y ademas pierde una propiedad. Lo mas
 * seguro es partir de lo que Drupal ya tiene apuntado.
 *
 * Uso: drush php:script scripts/como-tiene-apuntada-la-columna
 */

$campo = 'field_tec_price';
$entidad = 'taxonomy_term';

$guardado = \Drupal::keyValue('entity.storage_schema.sql');
$apuntado = $guardado->get("$entidad.field_schema_data.$campo", []);

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Lo que Drupal tiene apuntado de $entidad / $campo\n";
echo str_repeat('=', 78) . "\n\n";

if (!$apuntado) {
  echo "  Nada apuntado con esa clave.\n\n";
  return;
}

foreach ($apuntado as $tabla => $definicion) {
  echo "  $tabla\n";
  foreach ($definicion['fields'] ?? [] as $columna => $especificacion) {
    if (!str_starts_with($columna, $campo)) {
      continue;
    }
    echo "      $columna: " . str_replace(["\n", '  '], ['', ' '], var_export($especificacion, TRUE)) . "\n";
  }
}

$definicion = \Drupal::service('entity_field.manager')
  ->getFieldStorageDefinitions($entidad)[$campo] ?? NULL;

echo "\n  Decimales de hoy: " . ($definicion ? $definicion->getSetting('scale') : '?');
echo ', cifras: ' . ($definicion ? $definicion->getSetting('precision') : '?') . "\n\n";
