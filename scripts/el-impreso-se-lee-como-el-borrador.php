<?php

/**
 * @file
 * El impreso del pedido de compra se lee como el borrador.
 *
 * Era la tercera manera de escribir lo mismo. El borrador y la ficha ya se
 * habian igualado; el papel que sale al proveedor se quedo con los nombres de
 * siempre -Qty., Price, Unit- y, lo que se ve menos y confunde mas, con otro
 * orden: la cantidad delante del material y el precio delante de la unidad.
 *
 * Son dos cambios de sitio, no solo tres rotulos:
 *
 *   antes   Item | Picture | Qty. | Material name | Price | Unit | Sub total
 *   ahora   Item | Picture | Material name | Quantity | Purchase UoM | Purchase Cost | Sub total
 *
 * El impreso no gana ni pierde ninguna columna: son las mismas siete, dichas
 * igual y puestas en el mismo sitio que en las otras dos pantallas.
 *
 * De paso, Purchase Cost sale alineado a la derecha. Estaba sin alinear, asi que
 * en la misma tabla el precio caia a la izquierda y el Sub total a la derecha.
 *
 * Run: php vendor\bin\drush.php scr scripts/el-impreso-se-lee-como-el-borrador.php
 */

use Drupal\views\Entity\View;

const VISTA = 'tec_order_sales_order_line_items';
const BORRADOR = 'page_1';
const IMPRESO = 'page_3';

/**
 * Como se llama cada columna del impreso, con el nombre del borrador.
 */
const NOMBRES = [
  'field_tec_quantity' => 'Quantity',
  'field_tec_unit_purchase' => 'Purchase UoM',
  'field_tec_price' => 'Purchase Cost',
];

/**
 * El orden del impreso, copiado del borrador.
 *
 * Los ocultos no son adorno y por eso estan nombrados. La columna Picture la
 * dibuja un complemento que lee {{ field_tec_inventory_image }} para la
 * miniatura y {{ field_tec_inventory_image_1 }} para la ampliacion, y Views solo
 * deja a un campo leer los que se declararon antes que el, asi que las dos
 * imagenes tienen que ir delante o la columna sale en blanco.
 */
const ORDEN_DEL_IMPRESO = [
  'id',                               // Oculto.
  'counter',                          // Item.
  'field_tec_inventory_image',        // Oculto, la miniatura.
  'field_tec_inventory_image_1',      // Oculto, la ampliacion.
  'simple_popup_views_field',         // Picture.
  'name',                             // Material name.
  'field_tec_quantity',               // Quantity.
  'field_tec_unit_purchase',          // Purchase UoM.
  'field_tec_price',                  // Purchase Cost.
  'field_tec_line_item_total_number', // Sub total.
  'view_tec_order',                   // Oculto.
  'field_tec_vendor',                 // Oculto.
  'name_1',                           // Oculto, es por quien ordena la lista.
  'field_tec_vendor_2',               // Oculto.
  'field_tec_vendor_1',               // Oculto.
  'title',                            // Oculto.
  'changed',                          // Oculto.
];

/**
 * Como se alinea cada columna, tal cual esta en el borrador.
 *
 * Purchase UoM no aparece a proposito: en el borrador y en la ficha esa columna
 * no lleva alineado ninguno, y la gracia de esto es que las tres se lean igual,
 * no que el papel vaya por su cuenta aunque sea para mejor.
 */
const ALINEADO = [
  'counter' => 'views-align-center',
  'simple_popup_views_field' => 'views-align-center',
  'name' => 'views-align-left',
  'field_tec_quantity' => 'views-align-right',
  'field_tec_price' => 'views-align-right',
  'field_tec_line_item_total_number' => 'views-align-right',
];

$vista = View::load(VISTA);
if (!$vista) {
  print "No existe la vista " . VISTA . ".\n";
  return;
}
$displays = $vista->get('display');

/**
 * Los rotulos visibles de una pantalla, en su orden.
 */
$rotulosDe = function (array $campos): array {
  $rotulos = [];
  foreach ($campos as $campo) {
    if (empty($campo['exclude'])) {
      $rotulos[] = (string) ($campo['label'] ?? '');
    }
  }
  return $rotulos;
};

$campos = $displays[IMPRESO]['display_options']['fields'];

print "Como estaba\n" . str_repeat('-', 78) . "\n";
printf("  borrador  %s\n", implode(' | ', $rotulosDe($displays[BORRADOR]['display_options']['fields'])));
printf("  impreso   %s\n", implode(' | ', $rotulosDe($campos)));

// -----------------------------------------------------------------------------
// Antes negarse que adivinar.
// -----------------------------------------------------------------------------
$sobran = array_diff(array_keys($campos), ORDEN_DEL_IMPRESO);
$faltan = array_diff(ORDEN_DEL_IMPRESO, array_keys($campos));
if ($sobran || $faltan) {
  print "\n  El impreso ya no coincide con esta lista, asi que no se ha tocado nada.\n";
  if ($sobran) {
    print "    en pantalla y no en la lista: " . implode(', ', $sobran) . "\n";
  }
  if ($faltan) {
    print "    en la lista y no en pantalla: " . implode(', ', $faltan) . "\n";
  }
  return;
}

// Views solo deja a un campo leer los que se declararon antes que el. Reordenar
// puede dejar a alguno mirando hacia adelante, y eso no revienta: sale la
// columna en blanco y nadie se entera hasta que lo ve un proveedor.
foreach (ORDEN_DEL_IMPRESO as $puesto => $clave) {
  $escrito = json_encode($campos[$clave]);
  foreach (ORDEN_DEL_IMPRESO as $otroPuesto => $otro) {
    if ($otroPuesto <= $puesto || $otro === $clave) {
      continue;
    }
    if (str_contains($escrito, '{{ ' . $otro . ' }}') || str_contains($escrito, '@' . $otro)) {
      printf("\n  [MAL] con este orden, %s leeria a %s, que va detras. No se ha tocado nada.\n", $clave, $otro);
      return;
    }
  }
}

// -----------------------------------------------------------------------------
// Los nombres.
// -----------------------------------------------------------------------------
print "\nLos nombres\n" . str_repeat('-', 78) . "\n";
foreach (NOMBRES as $clave => $nombre) {
  $antes = (string) ($campos[$clave]['label'] ?? '');
  $campos[$clave]['label'] = $nombre;
  printf("  %-34s %-16s -> %s\n", $clave, '"' . $antes . '"', '"' . $nombre . '"');
}

// -----------------------------------------------------------------------------
// El orden.
// -----------------------------------------------------------------------------
$ordenados = [];
foreach (ORDEN_DEL_IMPRESO as $clave) {
  $ordenados[$clave] = $campos[$clave];
}
$displays[IMPRESO]['display_options']['fields'] = $ordenados;
print "\nEl orden\n" . str_repeat('-', 78) . "\n";
print "  La cantidad pasa detras del material, y la unidad delante del precio.\n";

// -----------------------------------------------------------------------------
// El alineado.
// -----------------------------------------------------------------------------
print "\nEl alineado\n" . str_repeat('-', 78) . "\n";
$estilo = &$displays[IMPRESO]['display_options']['style']['options']['info'];
foreach (ALINEADO as $clave => $como) {
  // La tabla solo guarda ajustes de las columnas que alguien toco alguna vez, y
  // las demas no tienen ni entrada, asi que hay que crearla completa.
  $ajuste = $estilo[$clave] ?? [
    'sortable' => FALSE,
    'default_sort_order' => 'asc',
    'align' => '',
    'separator' => '',
    'empty_column' => FALSE,
    'responsive' => '',
  ];
  $antes = (string) ($ajuste['align'] ?? '');
  $ajuste['align'] = $como;
  $estilo[$clave] = $ajuste;
  printf("  %-34s %-20s -> %s\n", $clave, $antes === '' ? '(sin alinear)' : $antes, $como);
}
unset($estilo);

$vista->set('display', $displays);
$vista->save();

// -----------------------------------------------------------------------------
// Como ha quedado.
// -----------------------------------------------------------------------------
print "\nLas tres pantallas de compra\n" . str_repeat('=', 78) . "\n";
$vista = View::load(VISTA);
$deCadaUna = [];
foreach ([BORRADOR => 'borrador  po/draft/%', 'block_2' => 'ficha     tec_order/%', IMPRESO => 'impreso   po/%/print'] as $pantalla => $como) {
  $rotulos = $rotulosDe($vista->getDisplay($pantalla)['display_options']['fields']);
  $deCadaUna[$pantalla] = $rotulos;
  printf("  %s\n    %s\n", $como, implode(' | ', array_map(fn($r) => $r === '' ? '(sin nombre)' : $r, $rotulos)));
}

print "\n";
print $deCadaUna[IMPRESO] === $deCadaUna[BORRADOR]
  ? "  El impreso y el borrador dicen exactamente lo mismo, en el mismo orden.\n"
  : "  [MAL] siguen sin coincidir.\n";
