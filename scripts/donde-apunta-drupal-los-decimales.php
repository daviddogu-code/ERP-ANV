<?php

/**
 * @file
 * En cuantos sitios lleva Drupal apuntados los decimales de un campo.
 *
 * Cambiar la columna de la base es la parte facil. La parte que hay que hacer
 * bien es dejar los apuntes de Drupal diciendo lo mismo que la columna, porque
 * si no coinciden, el dia que alguien guarde el campo desde la pantalla, Drupal
 * intentara "arreglar" la columna hacia lo que el cree.
 *
 * Uso: drush php:script scripts/donde-apunta-drupal-los-decimales
 */

$entidad = 'taxonomy_term';
$campo = 'field_tec_price';

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Donde estan apuntados los decimales de $entidad / $campo\n";
echo str_repeat('=', 78) . "\n\n";

// 1. La configuracion exportable: la que viaja por Git.
$config = \Drupal::config("field.storage.$entidad.$campo");
echo "  1. field.storage.$entidad.$campo\n";
echo '     precision ' . var_export($config->get('settings.precision'), TRUE)
  . ', scale ' . var_export($config->get('settings.scale'), TRUE) . "\n\n";

// 2. El esquema que Drupal cree que tienen las tablas.
$esquemaApuntado = \Drupal::keyValue('entity.storage_schema.sql')
  ->get("$entidad.field_schema_data.$campo", []);
echo "  2. entity.storage_schema.sql -> $entidad.field_schema_data.$campo\n";
foreach ($esquemaApuntado as $tabla => $definicion) {
  $spec = $definicion['fields'][$campo . '_value'] ?? [];
  echo "     $tabla: precision " . var_export($spec['precision'] ?? NULL, TRUE)
    . ', scale ' . var_export($spec['scale'] ?? NULL, TRUE) . "\n";
}
echo "\n";

// 3. Por si acaso: la lista de definiciones instaladas, que para los campos
// configurables normalmente no guarda copia, pero conviene mirarlo.
$instaladas = \Drupal::keyValue('entity.definitions.installed')
  ->get("$entidad.field_storage_definitions", []);
echo "  3. entity.definitions.installed -> $entidad.field_storage_definitions\n";
if (!array_key_exists($campo, $instaladas)) {
  echo "     no guarda copia de este campo (son " . count($instaladas) . " definiciones, todas base)\n";
}
else {
  $copia = $instaladas[$campo];
  echo '     guarda copia: precision ' . var_export($copia->getSetting('precision'), TRUE)
    . ', scale ' . var_export($copia->getSetting('scale'), TRUE) . "\n";
}
echo "\n";

// 4. Y la columna de verdad.
$esquema = \Drupal::database()->schema();
echo "  4. Las columnas de verdad\n";
foreach (array_keys($esquemaApuntado) as $tabla) {
  if (!$esquema->tableExists($tabla)) {
    echo "     $tabla: no existe\n";
    continue;
  }
  $fila = \Drupal::database()
    ->query("SHOW COLUMNS FROM {$tabla} LIKE :c", [':c' => $campo . '_value'])
    ->fetchAssoc();
  echo "     $tabla: " . ($fila['Type'] ?? '?') . "\n";
}

echo "\n";
