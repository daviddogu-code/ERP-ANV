<?php

/**
 * @file
 * Que definiciones de entidad estan descuadradas. Se ejecuta con:
 *   drush scr scripts/que-esta-descuadrado.php
 *
 * Drupal avisa de "Mismatched entity and/or field definitions" cuando lo que
 * hay guardado en la base de datos no coincide con lo que dice el codigo. Ese
 * aviso, tal cual, no dice cual ni por que. Esto lo desglosa.
 *
 * Importa distinguir dos casos. Si lo descuadrado es una entidad nuestra o un
 * campo con datos dentro, hay que arreglarlo antes de seguir. Si es un modulo
 * que ha cambiado una definicion y basta con anotarla, se resuelve solo.
 */

$gestor = \Drupal::entityDefinitionUpdateManager();
$cambios = $gestor->getChangeSummary();

if (!$cambios) {
  print "\n  No hay nada descuadrado.\n\n";
  return;
}

printf("\n  %d tipos de entidad con algo descuadrado:\n\n", count($cambios));

foreach ($cambios as $tipo => $lineas) {
  printf("    %s\n", $tipo);
  foreach ($lineas as $linea) {
    printf("      %s\n", strip_tags((string) $linea));
  }
  print "\n";
}

// Y ahora, si hay campos afectados, cuantas filas tienen datos. Es la
// diferencia entre un arreglo tranquilo y uno que hay que pensar.
$conexion = \Drupal::database();
print "  --- cuanto dato hay en juego ---\n\n";

foreach (array_keys($cambios) as $tipo) {
  try {
    $definicion = \Drupal::entityTypeManager()->getDefinition($tipo);
    $tabla = $definicion->getBaseTable();
    if ($tabla && $conexion->schema()->tableExists($tabla)) {
      $filas = $conexion->select($tabla)->countQuery()->execute()->fetchField();
      printf("    %-34s %8d filas en %s\n", $tipo, $filas, $tabla);
    }
    else {
      printf("    %-34s sin tabla base\n", $tipo);
    }
  }
  catch (\Throwable $e) {
    printf("    %-34s no se pudo mirar: %s\n", $tipo, $e->getMessage());
  }
}

print "\n";
