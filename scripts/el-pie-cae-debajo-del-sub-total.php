<?php

/**
 * @file
 * En la ficha del pedido, el pie cae debajo del Sub total.
 *
 * Las cifras del pie -Subtotal, VAT y Total- se pegan al borde derecho de la
 * tabla, asi que caen bajo la ultima columna, sea cual sea. Con Received y su
 * marca de "closed short" al final, caian bajo una columna que no habla de
 * dinero, y el ojo tenia que saltar de una punta a otra para comprobar que la
 * suma de la derecha era la suma de esa otra columna.
 *
 *   antes   ... | Quantity | Purchase UoM | Purchase Cost | Sub total | Received | (marca)
 *   ahora   ... | Quantity | Received | (marca) | Purchase UoM | Purchase Cost | Sub total
 *
 * No hay forma honesta de alinear una tabla con una columna que no sea la
 * ultima, asi que la manera de que el pie cuadre con el Sub total es que el Sub
 * total sea el ultimo. Received se va detras de Quantity, que ademas es donde
 * mejor se lee: cuanto se pidio y cuanto ha llegado, una al lado de la otra.
 *
 * Esto deshace lo que hizo scripts/las-dos-pantallas-de-compra-se-parecen.php,
 * que las habia puesto al final por ser lo que se mira cuando llega la
 * mercancia. Aquel script queda actualizado para que repetirlo no las devuelva.
 *
 * Run: php vendor\bin\drush.php scr scripts/el-pie-cae-debajo-del-sub-total.php
 */

use Drupal\views\Entity\View;

const VISTA = 'tec_order_sales_order_line_items';
const FICHA = 'block_2';

/**
 * Las dos columnas que se mueven, y detras de cual se ponen.
 */
const DETRAS_DE = 'field_tec_quantity';
const SE_MUEVEN = ['field_tec_quantity_received', 'field_tec_no_more_expected'];

$vista = View::load(VISTA);
if (!$vista) {
  print "No existe la vista " . VISTA . ".\n";
  return;
}
$displays = $vista->get('display');
$campos = $displays[FICHA]['display_options']['fields'];

/**
 * Los rotulos visibles de una lista de columnas, en su orden.
 */
$rotulosDe = function (array $campos): string {
  $rotulos = [];
  foreach ($campos as $campo) {
    if (empty($campo['exclude'])) {
      $rotulos[] = ($campo['label'] ?? '') === '' ? '(sin nombre)' : $campo['label'];
    }
  }
  return implode(' | ', $rotulos);
};

printf("Como estaba\n%s\n  %s\n", str_repeat('-', 78), $rotulosDe($campos));

foreach (array_merge(SE_MUEVEN, [DETRAS_DE]) as $clave) {
  if (!isset($campos[$clave])) {
    printf("\n  [MAL] la ficha no tiene la columna %s. No se ha tocado nada.\n", $clave);
    return;
  }
}

// -----------------------------------------------------------------------------
// El orden nuevo.
// -----------------------------------------------------------------------------
$nuevos = [];
foreach ($campos as $clave => $config) {
  if (in_array($clave, SE_MUEVEN, TRUE)) {
    continue;
  }
  $nuevos[$clave] = $config;
  if ($clave === DETRAS_DE) {
    foreach (SE_MUEVEN as $mudanza) {
      $nuevos[$mudanza] = $campos[$mudanza];
    }
  }
}

// Views solo deja a un campo leer los que se declararon antes que el, y cuando
// eso se rompe no revienta: sale la columna en blanco y nadie se entera hasta
// que lo ve alguien de fuera. Aqui el riesgo es Picture, que se dibuja leyendo
// las dos columnas ocultas de la imagen.
$puestos = array_flip(array_keys($nuevos));
foreach ($nuevos as $clave => $config) {
  $escrito = json_encode($config);
  foreach ($puestos as $otro => $suPuesto) {
    if ($suPuesto <= $puestos[$clave]) {
      continue;
    }
    if (str_contains($escrito, '{{ ' . $otro . ' }}') || str_contains($escrito, '@' . $otro)) {
      printf("\n  [MAL] con este orden, %s leeria a %s, que va detras. No se ha tocado nada.\n", $clave, $otro);
      return;
    }
  }
}

$displays[FICHA]['display_options']['fields'] = $nuevos;
$vista->set('display', $displays);
$vista->save();

// -----------------------------------------------------------------------------
// Como ha quedado.
// -----------------------------------------------------------------------------
$vista = View::load(VISTA);
$campos = $vista->getDisplay(FICHA)['display_options']['fields'];
printf("\nComo queda\n%s\n  %s\n", str_repeat('-', 78), $rotulosDe($campos));

$visibles = array_keys(array_filter($campos, fn($campo) => empty($campo['exclude'])));
$ultima = end($visibles);
printf("\n  La ultima columna es %s, asi que el pie cae debajo de %s.\n",
  $ultima, $ultima === 'field_tec_line_item_total_number' ? 'Sub total' : '[MAL] no del Sub total');
