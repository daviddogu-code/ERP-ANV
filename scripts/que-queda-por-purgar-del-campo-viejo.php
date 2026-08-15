<?php

/**
 * @file
 * Que queda pendiente de purgar despues de borrar un campo.
 *
 *   php vendor\bin\drush.php scr scripts/que-queda-por-purgar-del-campo-viejo.php
 *
 * Al borrar un campo Drupal no tira la tabla de datos en el momento: marca el
 * campo como borrado, lo guarda en el registro de campos borrados y deja la
 * tabla ahi hasta que la purga se la lleva. Si el campo no tenia datos la purga
 * es un tramite, pero conviene ver si queda algo pendiente y de quien es, porque
 * la purga se lleva TODOS los campos pendientes de golpe y en este arbol hay mas
 * de una tarea a medias.
 *
 * Solo lee. No purga nada.
 */

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

$bd = \Drupal::database();

$titulo('Las tablas de datos de los dos campos de proveedor');
foreach (['taxonomy_term__field_tec_suppliers', 'taxonomy_term__field_tec_vendor'] as $tabla) {
  if (!$bd->schema()->tableExists($tabla)) {
    printf("  %-40s ya no existe\n", $tabla);
    continue;
  }
  $filas = (int) $bd->select($tabla, 't')->countQuery()->execute()->fetchField();
  printf("  %-40s sigue ahi, con %d filas\n", $tabla, $filas);
}

$titulo('Campos borrados que esperan la purga');
$registro = \Drupal::service('entity_field.deleted_fields_repository');
$pendientes = array_merge(
  $registro->getFieldStorageDefinitions(),
  $registro->getFieldDefinitions()
);
if (!$pendientes) {
  echo "  ninguno\n";
}
$vistos = [];
foreach ($pendientes as $pendiente) {
  $clave = $pendiente->getTargetEntityTypeId() . '.' . $pendiente->getName();
  if (isset($vistos[$clave])) {
    continue;
  }
  $vistos[$clave] = TRUE;
  $paquete = method_exists($pendiente, 'getTargetBundle') ? $pendiente->getTargetBundle() : NULL;
  printf("  %-52s %s\n", $clave, $paquete ?: '(almacen)');
}

echo "\n";
if (count($vistos) > 1) {
  echo "  OJO: hay mas de un campo esperando. La purga se los lleva todos de golpe,\n";
  echo "  asi que si alguno es de otra tarea no la lances por tu cuenta.\n\n";
}
