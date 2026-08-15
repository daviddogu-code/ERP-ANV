<?php

/**
 * @file
 * Que ninguna pantalla dibuje los factores de conversion redondeados.
 *
 *   php vendor\bin\drush.php scr scripts/que-las-pantallas-no-redondeen-los-factores.php
 *   php vendor\bin\drush.php scr scripts/que-las-pantallas-no-redondeen-los-factores.php -- de-verdad
 *
 * De poco sirve que el campo guarde cinco decimales si la columna que lo ensena
 * los recorta. Y con los factores no es solo cuestion de mirar: el modulo de
 * calculo de las vistas no lee la base, lee lo que la columna dibuja, asi que la
 * cifra recortada es la que entra en la formula.
 *
 * Lo que habia el 15 de agosto de 2026, cuando se subieron los factores a cinco
 * decimales, es peor que un redondeo: en cuatro pantallas los factores se
 * dibujaban con el formateador de enteros. Las cuatro dividen por ellos, con la
 * formula
 *
 *     @field_tec_quantity / @field_tec_split_into / @field_tec_units
 *
 * de modo que un factor de 0,4 -perfectamente normal, significa que la unidad de
 * inventario son dos metros y medio- llegaba a la formula como 0. Una division
 * por cero, en la pantalla que dice cuanto material hace falta para un pedido.
 * No hacia falta ningun factor raro para romperla, solo uno menor que uno.
 *
 * Esto recorre todas las vistas y deja los dos factores dibujados como decimales
 * con los mismos cinco decimales que guarda el campo, respetando los separadores
 * que cada columna tuviera puestos.
 *
 * Sin "de-verdad" solo cuenta lo que haria.
 */

const CAMPOS = ['field_tec_units', 'field_tec_split_into'];
const DECIMALES = 5;

$deVerdad = (bool) array_intersect(['de-verdad', '--de-verdad'], $extra ?? []);

$almacen = \Drupal::entityTypeManager()->getStorage('view');
$tocadas = 0;
$columnas = 0;

echo "\n";

foreach ($almacen->loadMultiple() as $vista) {
  $displays = $vista->get('display') ?? [];
  $cambiada = FALSE;

  foreach ($displays as $nombre => $display) {
    foreach ($display['display_options']['fields'] ?? [] as $id => $campo) {
      if (!in_array($campo['field'] ?? '', CAMPOS, TRUE)) {
        continue;
      }
      if (($campo['plugin_id'] ?? '') !== 'field') {
        continue;
      }

      $tipo = $campo['type'] ?? '';
      $antes = $tipo === 'number_decimal'
        ? (int) ($campo['settings']['scale'] ?? 0)
        : NULL;

      // Ya esta bien: decimal y con decimales de sobra.
      if ($tipo === 'number_decimal' && $antes >= DECIMALES) {
        continue;
      }
      // Cualquier otro formateador que no sea numerico no se toca: si alguien
      // puso ahi una plantilla o un texto, sabra por que.
      if (!in_array($tipo, ['number_decimal', 'number_integer'], TRUE)) {
        printf(
          "  %-46s %-22s %s: formateador \"%s\", se deja\n",
          $vista->id(), $nombre, $campo['field'], $tipo
        );
        continue;
      }

      printf(
        "  %-46s %-22s %s: %s -> decimal con %d decimales%s\n",
        $vista->id(),
        $nombre,
        $campo['field'],
        $tipo === 'number_integer' ? 'entero' : ('decimal con ' . $antes),
        DECIMALES,
        !empty($campo['exclude']) ? ' (columna oculta, alimenta formulas)' : ''
      );
      $columnas++;

      if (!$deVerdad) {
        continue;
      }

      $ajustes = $campo['settings'] ?? [];
      $displays[$nombre]['display_options']['fields'][$id]['type'] = 'number_decimal';
      $displays[$nombre]['display_options']['fields'][$id]['settings'] = [
        'thousand_separator' => $ajustes['thousand_separator'] ?? '',
        'decimal_separator' => $ajustes['decimal_separator'] ?? '.',
        'scale' => DECIMALES,
        'prefix_suffix' => $ajustes['prefix_suffix'] ?? FALSE,
      ];
      $cambiada = TRUE;
    }
  }

  if ($cambiada) {
    $vista->set('display', $displays);
    $vista->save();
    $tocadas++;
  }
}

echo "\n";
if (!$columnas) {
  echo "  Ninguna pantalla recorta los factores.\n\n";
  return;
}

if (!$deVerdad) {
  printf("  %d columnas por arreglar. Simulacion: con -- de-verdad se cambian.\n\n", $columnas);
  return;
}

printf("  %d columnas arregladas en %d vistas. Exporta la configuracion.\n\n", $columnas, $tocadas);
