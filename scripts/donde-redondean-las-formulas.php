<?php

/**
 * @file
 * Todas las formulas de las vistas, y con cuantos decimales llega cada dato.
 *
 * El modulo de calculo de las vistas no lee la base: lee lo que la columna
 * dibuja. Asi que si una columna ensena 0,01 porque su formateador tiene dos
 * decimales, la formula multiplica 0,01, no el valor guardado. Ahi es donde el
 * redondeo se cuela en el dinero sin que nadie lo vea.
 *
 * Este guion lista cada formula, de que columnas se alimenta, y con cuantos
 * decimales se dibuja cada una de esas columnas.
 *
 * Uso: drush php:script scripts/donde-redondean-las-formulas
 */

$almacen = \Drupal::entityTypeManager()->getStorage('view');

echo "\n";
echo str_repeat('=', 86) . "\n";
echo " Las formulas de las vistas y los decimales de lo que se comen\n";
echo str_repeat('=', 86) . "\n";

$cuantas = 0;
$sospechosas = [];

foreach ($almacen->loadMultiple() as $vista) {
  $displays = $vista->get('display') ?? [];
  $porDefecto = $displays['default']['display_options']['fields'] ?? [];

  foreach ($displays as $nombre => $display) {
    $campos = $display['display_options']['fields'] ?? $porDefecto;
    if (!$campos) {
      continue;
    }

    $formulas = [];
    foreach ($campos as $id => $campo) {
      if (($campo['plugin_id'] ?? '') !== 'field_views_simple_math_field') {
        continue;
      }
      $formulas[$id] = (string) ($campo['fieldset_one']['formula'] ?? '');
    }
    if (!$formulas) {
      continue;
    }

    echo "\n  " . $vista->id() . " / $nombre\n";

    foreach ($formulas as $id => $formula) {
      $cuantas++;
      $decimalesPropios = $campos[$id]['fieldset_two']['round'] ?? NULL;
      printf("      %s = %s%s\n", $id, $formula === '' ? '(vacia)' : $formula,
        $decimalesPropios !== NULL ? "   [redondea a $decimalesPropios]" : '');

      if (!preg_match_all('/@([a-z0-9_]+)/i', $formula, $encontrados)) {
        continue;
      }
      foreach (array_unique($encontrados[1]) as $token) {
        if (!isset($campos[$token])) {
          printf("          @%-26s no existe esa columna\n", $token);
          continue;
        }
        $come = $campos[$token];
        $esCalculada = ($come['plugin_id'] ?? '') === 'field_views_simple_math_field';
        $decimales = $esCalculada
          ? ($come['fieldset_two']['round'] ?? NULL)
          : ($come['settings']['scale'] ?? NULL);
        printf(
          "          @%-26s %s, %s decimales\n",
          $token,
          $esCalculada ? 'calculada' : (string) ($come['type'] ?? '?'),
          $decimales === NULL || $decimales === '' ? 'sin recortar' : $decimales
        );

        // Un dato de dinero con pocos decimales, multiplicado, es donde se
        // pierden bahts sin que se note.
        $deQueCampo = (string) ($come['field'] ?? '');
        if (in_array($deQueCampo, ['field_tec_price', 'field_tec_price_uos', 'field_tec_cost'], TRUE)
          && $decimales !== NULL && (int) $decimales <= 2) {
          $sospechosas[] = $vista->id() . " / $nombre: $id multiplica @$token ($deQueCampo), dibujada con $decimales decimales.";
        }
      }
    }
  }
}

echo "\n" . str_repeat('-', 86) . "\n";
echo " Formulas repasadas: $cuantas.\n";
if ($sospechosas) {
  echo "\n Donde una formula multiplica dinero ya redondeado:\n";
  foreach (array_unique($sospechosas) as $aviso) {
    echo "   - $aviso\n";
  }
}
else {
  echo " Ninguna formula multiplica una columna de dinero recortada a dos decimales.\n";
}
echo str_repeat('-', 86) . "\n\n";
