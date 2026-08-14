<?php

/**
 * @file
 * Que las formulas multipliquen el coste entero, no el coste ya redondeado.
 *
 * El modulo de calculo de las vistas no lee la base: lee lo que la columna
 * dibuja. La columna del coste de consumo se dibujaba con dos decimales, asi
 * que la formula del total de material multiplicaba 0,01 donde la base guarda
 * 0,008. Redondear el dinero al final esta bien -y eso ya lo hace la formula,
 * a dos decimales con el simbolo del baht-; redondearlo antes de multiplicar,
 * no.
 *
 * Solo toca columnas escondidas, que son las que existen unicamente para
 * alimentar una formula: subirles los decimales no cambia nada en pantalla. Si
 * encuentra una que se ve, avisa y no la toca, porque ahi el cambio si se
 * notaria y es una decision de como se ensena, no de como se calcula.
 *
 * Por defecto solo cuenta lo que haria. Para hacerlo de verdad:
 *   drush php:script scripts/que-la-formula-multiplique-el-coste-entero -- de-verdad
 */

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

// Los campos de dinero que se calculan y por eso pueden tener mas decimales de
// los que se ensenan. El coste de compra no esta: ese se teclea, y con los
// decimales que se teclea es exacto.
$calculados = ['field_tec_price', 'field_tec_price_uos'];

$definiciones = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('taxonomy_term');
$almacen = \Drupal::entityTypeManager()->getStorage('view');

echo "\n";
echo str_repeat('=', 82) . "\n";
echo ' Los decimales con los que las formulas leen el coste' . ($deVerdad ? '' : '   (solo mirando)') . "\n";
echo str_repeat('=', 82) . "\n";

$cambiadas = 0;
$avisos = [];

foreach ($almacen->loadMultiple() as $vista) {
  $displays = $vista->get('display') ?? [];
  $algoCambia = FALSE;

  foreach ($displays as $nombre => $display) {
    $campos = $display['display_options']['fields'] ?? NULL;
    if ($campos === NULL) {
      continue;
    }

    // Que columnas se come alguna formula de este display.
    $comidas = [];
    foreach ($campos as $campo) {
      if (($campo['plugin_id'] ?? '') !== 'field_views_simple_math_field') {
        continue;
      }
      $formula = (string) ($campo['fieldset_one']['formula'] ?? '');
      if (preg_match_all('/@([a-z0-9_]+)/i', $formula, $encontrados)) {
        foreach ($encontrados[1] as $token) {
          $comidas[$token] = TRUE;
        }
      }
    }
    if (!$comidas) {
      continue;
    }

    foreach ($campos as $id => $campo) {
      if (!isset($comidas[$id])) {
        continue;
      }
      $deQueCampo = (string) ($campo['field'] ?? '');
      if (!in_array($deQueCampo, $calculados, TRUE)) {
        continue;
      }

      $guarda = isset($definiciones[$deQueCampo])
        ? (int) $definiciones[$deQueCampo]->getSetting('scale')
        : NULL;
      if ($guarda === NULL) {
        continue;
      }
      $dibuja = isset($campo['settings']['scale']) ? (int) $campo['settings']['scale'] : NULL;
      if ($dibuja === NULL || $dibuja >= $guarda) {
        continue;
      }

      if (empty($campo['exclude'])) {
        $avisos[] = $vista->id() . " / $nombre: $id ($deQueCampo) alimenta una formula con $dibuja decimales"
          . " y se ve en pantalla; subirla a $guarda cambiaria lo que se ensena.";
        continue;
      }

      $displays[$nombre]['display_options']['fields'][$id]['settings']['scale'] = $guarda;
      $algoCambia = TRUE;
      $cambiadas++;
      echo "\n  " . $vista->id() . " / $nombre\n";
      echo "      $id ($deQueCampo), escondida: $dibuja decimales pasan a $guarda\n";
    }
  }

  if ($algoCambia && $deVerdad) {
    $vista->set('display', $displays);
    $vista->save();
    echo "      guardada\n";
  }
}

echo "\n" . str_repeat('-', 82) . "\n";
echo $cambiadas === 0
  ? " Ninguna columna escondida leia el coste con menos decimales de los que guarda.\n"
  : " Columnas cambiadas: $cambiadas.\n";
if ($avisos) {
  echo "\n Para decidir a mano:\n";
  foreach (array_unique($avisos) as $aviso) {
    echo "   - $aviso\n";
  }
}
if (!$deVerdad && $cambiadas) {
  echo "\n Para hacerlo de verdad:\n";
  echo "   drush php:script scripts/que-la-formula-multiplique-el-coste-entero -- de-verdad\n";
}
echo str_repeat('-', 82) . "\n\n";
