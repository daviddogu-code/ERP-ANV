<?php

/**
 * Dice, campo por campo, si el que se va a borrar guarda algo o esta vacio.
 *
 * Borrar un campo de Drupal se lleva por delante sus datos y no hay vuelta
 * atras. Antes de tocar ninguno hay que saber tres cosas: como se llama en la
 * pantalla, si esta en el formulario de alta y en cuantos materiales tiene un
 * valor escrito. Un campo vacio se borra sin pensar; uno con datos hay que
 * mirarlo antes de decidir.
 *
 * Uso: drush php:script scripts/que-guardan-los-campos-condenados
 */

$condenados = [
  // Los interruptores que decidian si habia conversion.
  'field_tec_split_inventory',
  'field_tec_package_units',
  'field_tec_moq_package',
  'field_tec_stock_unit_check',
  // Unidades y factores del sistema opcional.
  'field_tec_packaging',
  'field_tec_split_unit',
  'field_tec_uop_pu_uou',
  'field_tec_uop_uou',
  'field_tec_uos_pu_uop',
  'field_tec_uos_pu_uou',
  'field_tec_uos_uop',
  'field_tec_uos_uou',
  'field_tec_uou_control',
  'field_tec_uou_split_qty',
  'field_tec_uou_uop',
  'field_tec_uou_uop_pu',
  'field_tec_uou_uos',
  'field_uop_to_uos',
  'field_1_uou_uos_pu',
  // Costes y minimos derivados del empaquetado.
  'field_tec_price_by_package',
  'field_tec_price_uou',
  'field_tec_moq_unit',
  'field_tec_purchasing_moq',
];

// Los que se quedan, para tenerlos delante y no confundirlos.
$canonicos = [
  'field_tec_unit_purchase' => 'unidad de compra',
  'field_tec_uos' => 'unidad de inventario',
  'field_tec_unit_use' => 'unidad de consumo',
  'field_tec_units' => 'factor: 1 compra = x inventario',
  'field_tec_split_into' => 'factor: 1 inventario = x consumo',
  'field_tec_cost' => 'coste de compra',
  'field_tec_price_uos' => 'coste de inventario (derivado)',
  'field_tec_price' => 'coste de consumo (derivado)',
  'field_tec_moq_quantity' => 'pedido minimo',
];

$gestor = \Drupal::entityTypeManager();
$conexion = \Drupal::database();
$definiciones = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('taxonomy_term', 'tec_inventory');

// Cuantos materiales hay en total, para poner el recuento en contexto.
$totalMateriales = (int) $gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->count()
  ->execute();

// Los campos que el formulario de alta ensena de verdad.
$formulario = \Drupal::entityTypeManager()
  ->getStorage('entity_form_display')
  ->load('taxonomy_term.tec_inventory.default');
$enElFormulario = $formulario ? array_keys($formulario->getComponents()) : [];

echo "\n";
echo "Materiales en la base: $totalMateriales\n";
echo "\n";
echo str_repeat('=', 96) . "\n";
echo " Campos que se van\n";
echo str_repeat('=', 96) . "\n\n";
printf(" %-30s %-30s %-12s %-9s %s\n", 'campo', 'etiqueta', 'tipo', 'con dato', 'formulario');
printf(" %s\n", str_repeat('-', 94));

$conDatos = [];
$vacios = [];
$noExisten = [];

foreach ($condenados as $campo) {
  if (!isset($definiciones[$campo])) {
    $noExisten[] = $campo;
    continue;
  }
  $definicion = $definiciones[$campo];
  $tipo = $definicion->getType();
  $etiqueta = (string) $definicion->getLabel();

  // Se cuenta sobre la tabla del campo, que es lo unico que dice la verdad:
  // una consulta de entidades no distingue entre vacio y sin fila.
  $tabla = 'taxonomy_term__' . $campo;
  $cuantos = 0;
  if ($conexion->schema()->tableExists($tabla)) {
    $cuantos = (int) $conexion->select($tabla, 't')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  $linea = sprintf(
    " %-30s %-30s %-12s %-9s %s",
    $campo,
    mb_substr($etiqueta, 0, 30),
    $tipo,
    $cuantos === 0 ? 'vacio' : $cuantos,
    in_array($campo, $enElFormulario, TRUE) ? 'si' : 'no'
  );
  echo $linea . "\n";

  if ($cuantos > 0) {
    $conDatos[$campo] = $cuantos;
  }
  else {
    $vacios[] = $campo;
  }
}

echo "\n";
echo str_repeat('=', 96) . "\n";
echo " Campos que se quedan\n";
echo str_repeat('=', 96) . "\n\n";
printf(" %-30s %-38s %-12s %s\n", 'campo', 'para que', 'tipo', 'con dato');
printf(" %s\n", str_repeat('-', 94));

foreach ($canonicos as $campo => $paraQue) {
  if (!isset($definiciones[$campo])) {
    printf(" %-30s %-38s NO EXISTE\n", $campo, $paraQue);
    continue;
  }
  $tabla = 'taxonomy_term__' . $campo;
  $cuantos = 0;
  if ($conexion->schema()->tableExists($tabla)) {
    $cuantos = (int) $conexion->select($tabla, 't')
      ->countQuery()
      ->execute()
      ->fetchField();
  }
  printf(
    " %-30s %-38s %-12s %s\n",
    $campo,
    $paraQue,
    $definiciones[$campo]->getType(),
    $cuantos === 0 ? 'vacio' : $cuantos
  );
}

echo "\n";
echo str_repeat('-', 96) . "\n";
printf("Se van %d campos: %d vacios y %d con datos.\n", count($condenados) - count($noExisten), count($vacios), count($conDatos));
if ($conDatos) {
  echo "\nCon datos escritos, hay que mirarlos antes de borrar:\n";
  foreach ($conDatos as $campo => $cuantos) {
    echo "  $campo: $cuantos filas\n";
  }
}
if ($noExisten) {
  echo "\nYa no estan en el vocabulario de materiales:\n";
  foreach ($noExisten as $campo) {
    echo "  $campo\n";
  }
}
echo "\n";
