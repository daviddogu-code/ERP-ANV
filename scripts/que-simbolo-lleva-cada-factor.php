<?php

/**
 * @file
 * Censo de los parentesis que llevan un factor con el simbolo de una unidad.
 *
 * Un factor solo significa algo si al lado va la unidad de destino. El factor
 * de compra a inventario tiene que llevar el simbolo de la unidad de
 * inventario, y el de inventario a consumo el de la unidad de consumo. Y el
 * simbolo que sale es el de la unidad a la que apunte la relacion de la que
 * cuelga el campo, asi que hay que mirar la relacion, no el nombre del campo.
 *
 * Este guion recorre todas las vistas, busca las reescrituras que juntan un
 * factor con un simbolo, y dice si el simbolo es el que toca.
 *
 * Uso: drush php:script scripts/que-simbolo-lleva-cada-factor
 */

// Que unidad tiene que acompanar a cada factor, y por que relacion se llega a
// su simbolo.
$parejas = [
  'field_tec_units' => ['field_tec_uos', 'unidad de inventario'],
  'field_tec_split_into' => ['field_tec_unit_use', 'unidad de consumo'],
];

$almacen = \Drupal::entityTypeManager()->getStorage('view');

echo "\n";
echo str_repeat('=', 86) . "\n";
echo " Los parentesis que juntan un factor con el simbolo de una unidad\n";
echo str_repeat('=', 86) . "\n";

$bien = 0;
$mal = 0;

foreach ($almacen->loadMultiple() as $vista) {
  $displays = $vista->get('display') ?? [];
  $porDefecto = $displays['default']['display_options'] ?? [];

  foreach ($displays as $nombre => $display) {
    $opciones = $display['display_options'] ?? [];
    // Un display que no tiene lista propia usa la del maestro.
    $campos = $opciones['fields'] ?? ($porDefecto['fields'] ?? []);
    $relaciones = $opciones['relationships'] ?? ($porDefecto['relationships'] ?? []);

    foreach ($campos as $id => $campo) {
      if (empty($campo['alter']['alter_text'])) {
        continue;
      }
      $texto = (string) ($campo['alter']['text'] ?? '');
      if ($texto === '') {
        continue;
      }

      foreach ($parejas as $factor => [$relacionBuena, $comoSeLlama]) {
        // El factor puede salir como field_tec_units o field_tec_units_1.
        if (!preg_match('/\{\{\s*(' . preg_quote($factor, '/') . '(?:_\d+)?)\s*\}\}/', $texto, $cualFactor)) {
          continue;
        }
        // Y a su lado, el simbolo.
        if (!preg_match('/\{\{\s*(field_tec_unit_symbol(?:_\d+)?)\s*\}\}/', $texto, $cualSimbolo)) {
          continue;
        }
        $simbolo = $cualSimbolo[1];
        if (!isset($campos[$simbolo])) {
          echo "\n  " . $vista->id() . " / $nombre / $id\n";
          echo "      el parentesis nombra $simbolo, que no esta en la lista de columnas\n";
          $mal++;
          continue;
        }

        $relacion = (string) ($campos[$simbolo]['relationship'] ?? 'none');
        $correcto = $relacion === $relacionBuena;
        $correcto ? $bien++ : $mal++;

        echo "\n  " . ($correcto ? '[BIEN]' : '[MAL] ') . ' ' . $vista->id() . " / $nombre / $id\n";
        echo "      factor  {$cualFactor[1]}, que es " . ($factor === 'field_tec_units'
          ? 'de compra a inventario'
          : 'de inventario a consumo') . "\n";
        echo "      simbolo $simbolo, que cuelga de " . ($relacion === 'none' ? 'nada, o sea del propio material' : $relacion) . "\n";
        if (!$correcto) {
          echo "      tendria que colgar de $relacionBuena, que es la $comoSeLlama\n";
          echo '      hay relacion a ' . $relacionBuena . '? ' . (isset($relaciones[$relacionBuena]) ? 'si' : 'NO, hay que crearla') . "\n";
        }
      }
    }
  }
}

echo "\n" . str_repeat('-', 86) . "\n";
echo " Parentesis correctos: $bien. Con el simbolo equivocado: $mal.\n";
echo str_repeat('-', 86) . "\n\n";
