<?php

/**
 * Ensena todos los campos de un material tal y como estan guardados.
 *
 * Sirve para comprobar con datos reales, y no por deduccion, que le hacen al
 * material los procesos de ECA al guardarlo: si las tres unidades sobreviven
 * distintas o si alguna se pisa, y que valores acaban en los dos factores y en
 * los costes derivados.
 *
 * Uso: drush php:script scripts/como-esta-el-material [nombre o tid]
 * Sin argumento saca todos los materiales que haya.
 */

$aguja = $extra['args'][0] ?? ($argv[1] ?? NULL);

$almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$consulta = $almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->sort('tid');
$tids = $consulta->execute();

if (!$tids) {
  echo "No hay ningun material en el inventario.\n";
  return;
}

$materiales = $almacen->loadMultiple($tids);

// Los campos que llevan la cuenta de las unidades y los factores, para poder
// senalar en el listado que es canonico y que es herencia del sistema viejo.
$canonicos = [
  'field_tec_unit_purchase',
  'field_tec_uos',
  'field_tec_unit_use',
  'field_tec_units',
  'field_tec_split_into',
];
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
];

$encontrados = 0;

foreach ($materiales as $material) {
  $nombre = $material->label();
  $tid = $material->id();
  if ($aguja !== NULL) {
    $coincide = (string) $tid === (string) $aguja
      || mb_stripos($nombre, (string) $aguja) !== FALSE;
    if (!$coincide) {
      continue;
    }
  }
  $encontrados++;

  echo "\n";
  echo str_repeat('=', 78) . "\n";
  echo " $nombre   (tid $tid)\n";
  echo str_repeat('=', 78) . "\n";

  $conValor = [];
  $vacios = [];

  foreach ($material->getFields() as $nombreCampo => $lista) {
    if (!str_starts_with($nombreCampo, 'field_')) {
      continue;
    }
    $definicion = $lista->getFieldDefinition();
    $etiqueta = $definicion->getLabel();
    $valor = valorLegible($lista);
    $marca = '   ';
    if (in_array($nombreCampo, $deOscar, TRUE)) {
      $marca = ' x ';
    }
    elseif (in_array($nombreCampo, $canonicos, TRUE)) {
      $marca = ' * ';
    }
    $linea = sprintf('%s%-32s %-26s %s', $marca, $nombreCampo, recortar($etiqueta, 26), $valor);
    if ($valor === '(vacio)') {
      $vacios[] = $linea;
    }
    else {
      $conValor[] = $linea;
    }
  }

  echo "\n Con valor guardado:\n\n";
  foreach ($conValor as $linea) {
    echo "$linea\n";
  }
  echo "\n Vacios (" . count($vacios) . "):\n\n";
  foreach ($vacios as $linea) {
    echo "$linea\n";
  }
}

if ($encontrados === 0) {
  echo "No hay ningun material que se llame o tenga el tid '$aguja'.\n";
  echo "Hay " . count($materiales) . " materiales: ";
  echo implode(', ', array_map(fn($m) => $m->label() . ' (' . $m->id() . ')', $materiales)) . "\n";
  return;
}

echo "\n";
echo " * campo canonico, el que se queda.\n";
echo " x campo del sistema de unidades opcionales, el que sobra.\n";
echo "\n";

/**
 * Convierte el valor de un campo en algo que se pueda leer de un vistazo.
 */
function valorLegible(\Drupal\Core\Field\FieldItemListInterface $lista): string {
  if ($lista->isEmpty()) {
    return '(vacio)';
  }
  $trozos = [];
  foreach ($lista as $elemento) {
    $valores = $elemento->getValue();
    if (isset($valores['target_id'])) {
      $referida = $elemento->entity ?? NULL;
      $trozos[] = $referida
        ? $referida->label() . ' [' . $valores['target_id'] . ']'
        : 'referencia perdida [' . $valores['target_id'] . ']';
      continue;
    }
    if (isset($valores['value'])) {
      $trozos[] = is_scalar($valores['value'])
        ? recortar((string) $valores['value'], 60)
        : json_encode($valores['value']);
      continue;
    }
    $trozos[] = recortar(json_encode($valores), 60);
  }
  return implode(' | ', $trozos);
}

/**
 * Recorta un texto para que la tabla no se descuadre.
 */
function recortar(string $texto, int $largo): string {
  $texto = trim(preg_replace('/\s+/', ' ', $texto));
  if (mb_strlen($texto) <= $largo) {
    return $texto;
  }
  return mb_substr($texto, 0, $largo - 1) . '.';
}
