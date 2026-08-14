<?php

/**
 * Saca en limpio que calcula cada columna de las vistas de materiales.
 *
 * Estas vistas son las que dicen a produccion y a compras cuanto material hace
 * falta para un pedido. Estan montadas con dos modulos: uno que calcula con
 * formulas escritas a mano, y otro que ensena una columna u otra segun el valor
 * de un campo. Los campos que mandan en esos condicionales son las casillas del
 * sistema de unidades opcionales, asi que antes de quitarlas hay que saber que
 * decide cada una.
 *
 * Se lee de config/sync, que es la copia en fichero, para no depender de que
 * Drupal arranque.
 *
 * Uso: php scripts/que-calculan-las-vistas.php
 */

$carpeta = dirname(__DIR__) . '/config/sync';

$vistas = [
  'tec_order_material_calculation_terms' => 'Materiales por pedido, una fila por material',
  'tec_order_material_calculation_summary' => 'Materiales por pedido, resumen',
  'tec_order_sales_order_line_items' => 'Lineas del pedido de venta',
  'tec_inventory' => 'Listado de materiales',
  'tec_inventory_elements' => 'Piezas del listado de materiales',
];

// Los campos del sistema de unidades opcionales. Si aparecen en una formula o
// en un condicional, esa columna hay que rehacerla.
$deOscar = [
  'field_tec_split_inventory',
  'field_tec_package_units',
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
  'field_tec_stock_unit_check',
  'field_tec_price_by_package',
  'field_tec_price_uou',
  'field_tec_moq_package',
];

$totalTocadas = 0;

foreach ($vistas as $vista => $paraQueSirve) {
  $fichero = "$carpeta/views.view.$vista.yml";
  if (!is_file($fichero)) {
    echo "\nNo existe la vista $vista.\n";
    continue;
  }
  $datos = leerYaml($fichero);

  echo "\n";
  echo str_repeat('=', 78) . "\n";
  echo " $vista\n";
  echo " $paraQueSirve\n";
  echo str_repeat('=', 78) . "\n";

  foreach ($datos['display'] ?? [] as $nombreDisplay => $display) {
    $campos = $display['display_options']['fields'] ?? [];
    if (!$campos) {
      continue;
    }

    $formulas = [];
    $condicionales = [];

    foreach ($campos as $id => $campo) {
      $plugin = $campo['plugin_id'] ?? '';
      $etiqueta = trim((string) ($campo['label'] ?? '')) ?: '(sin etiqueta)';

      if ($plugin === 'field_views_simple_math_field') {
        $formula = $campo['fieldset_one']['formula'] ?? '';
        $formulas[] = [
          'id' => $id,
          'etiqueta' => $etiqueta,
          'formula' => $formula,
          'oculta' => !empty($campo['exclude']),
        ];
      }

      if ($plugin === 'views_conditional_field') {
        $condicionales[] = [
          'id' => $id,
          'etiqueta' => $etiqueta,
          'si' => $campo['if'] ?? '',
          'condicion' => $campo['condition'] ?? '',
          'valor' => $campo['equalto'] ?? '',
          'entonces' => $campo['then'] ?? '',
          'sino' => $campo['or'] ?? '',
          'oculta' => !empty($campo['exclude']),
        ];
      }
    }

    if (!$formulas && !$condicionales) {
      continue;
    }

    echo "\n  Display: $nombreDisplay\n";

    if ($formulas) {
      echo "\n    Columnas calculadas con formula:\n\n";
      foreach ($formulas as $f) {
        $tocada = tocaAOscar($f['formula'], $deOscar);
        if ($tocada) {
          $totalTocadas++;
        }
        printf("      %s%s\n", $f['etiqueta'], $f['oculta'] ? '  [oculta, alimenta a otra]' : '');
        printf("        %s\n", $f['formula'] === '' ? '(sin formula)' : $f['formula']);
        if ($tocada) {
          printf("        REHACER: usa %s\n", implode(', ', $tocada));
        }
        echo "\n";
      }
    }

    if ($condicionales) {
      echo "    Columnas que cambian segun una casilla:\n\n";
      foreach ($condicionales as $c) {
        $tocada = tocaAOscar($c['si'] . ' ' . $c['entonces'] . ' ' . $c['sino'], $deOscar);
        if ($tocada) {
          $totalTocadas++;
        }
        printf("      %s%s\n", $c['etiqueta'], $c['oculta'] ? '  [oculta, alimenta a otra]' : '');
        printf("        si %s %s %s\n", $c['si'], nombreCondicion($c['condicion']), $c['valor']);
        printf("        entonces: %s\n", $c['entonces'] === '' ? '(nada)' : $c['entonces']);
        printf("        si no:    %s\n", $c['sino'] === '' ? '(nada)' : $c['sino']);
        if ($tocada) {
          printf("        REHACER: decide con %s\n", implode(', ', $tocada));
        }
        echo "\n";
      }
    }
  }
}

echo "\n";
echo str_repeat('-', 78) . "\n";
echo "Columnas que dependen del sistema de unidades opcionales: $totalTocadas\n";
echo str_repeat('-', 78) . "\n";

/**
 * Devuelve los campos de Oscar que aparecen en un texto.
 */
function tocaAOscar(string $texto, array $deOscar): array {
  $encontrados = [];
  foreach ($deOscar as $campo) {
    if (str_contains($texto, $campo)) {
      $encontrados[] = $campo;
    }
  }
  return $encontrados;
}

/**
 * Traduce el numero de condicion del modulo a palabras.
 */
function nombreCondicion($condicion): string {
  $nombres = [
    1 => 'esta vacio',
    2 => 'no esta vacio',
    3 => 'vale',
    4 => 'no vale',
    5 => 'es mayor que',
    6 => 'es menor que',
    7 => 'contiene',
    8 => 'no contiene',
  ];
  return $nombres[(int) $condicion] ?? "condicion $condicion";
}

/**
 * Lee un YAML sin depender de Drupal.
 */
function leerYaml(string $fichero): array {
  if (function_exists('yaml_parse_file')) {
    $datos = yaml_parse_file($fichero);
    if (is_array($datos)) {
      return $datos;
    }
  }
  require_once dirname(__DIR__) . '/vendor/autoload.php';
  return \Symfony\Component\Yaml\Yaml::parseFile($fichero) ?: [];
}
