<?php

/**
 * @file
 * El borrador y la ficha de un pedido de compra se leen igual.
 *
 * Eran la misma informacion escrita de dos maneras. La misma columna se llamaba
 * "Unit of Purchase (UoP)" en una pantalla y "Unit" en la otra; el coste era
 * "Cost by UoP" aqui y "Cost per UoP" alli; la cantidad, "Quantity" y "Qty.".
 * Quien mira las dos seguidas tiene que traducir, y traducir es donde se
 * equivoca uno.
 *
 * Se quedan los nombres cortos que pidio el dueno, que son los que usa la
 * empresa al hablar: Purchase UoM y Purchase Cost.
 *
 * Tres cosas mas, todas de aspecto:
 *
 *   1. La casilla de cantidad del borrador ocupaba el ancho entero de su celda
 *      y era lo mas gordo de la fila para meter cuatro cifras.
 *   2. Encima de cada casilla ponia "Quantity", que ya lo dice la cabecera. La
 *      regla que lo tapaba existia desde siempre pero solo cogia la pantalla de
 *      ventas: Views le pone un -1 detras a la segunda copia del campo, y la de
 *      compras es la segunda.
 *   3. En la ficha, Received se va a la derecha del todo, con su marca de
 *      "closed short" pegada detras, porque son lo que se mira despues de que
 *      llegue la mercancia y no mientras se lee el pedido.
 *
 * Run: php vendor\bin\drush.php scr scripts/las-dos-pantallas-de-compra-se-parecen.php
 */

use Drupal\views\Entity\View;

const VISTA = 'tec_order_sales_order_line_items';

/**
 * Como se llama cada columna, pantalla por pantalla.
 */
const NOMBRES = [
  'page_1' => [
    'field_tec_unit_purchase_1' => 'Purchase UoM',
    'field_tec_cost' => 'Purchase Cost',
  ],
  'block_2' => [
    'field_tec_quantity' => 'Quantity',
    'field_tec_unit_purchase' => 'Purchase UoM',
    'field_tec_cost' => 'Purchase Cost',
    'field_views_simple_math_field' => 'Sub total',
  ],
];

/**
 * El orden de la ficha, copiado del borrador y con Received al final.
 *
 * Los ocultos de en medio no son adorno. La columna Picture la dibuja un
 * complemento que lee {{ field_tec_inventory_image }} para la miniatura y
 * {{ field_tec_inventory_image_1 }} para la ampliacion, y Views solo deja a un
 * campo leer los que se declararon antes, asi que los dos tienen que ir
 * delante o la columna sale en blanco. Por lo mismo, Sub total multiplica
 * @field_tec_quantity por @field_tec_cost y no puede adelantarlos.
 */
const ORDEN_DE_LA_FICHA = [
  'id',                             // Oculto.
  'counter',                        // Item.
  'field_tec_inventory_image',      // Oculto, la miniatura.
  'field_tec_inventory_image_1',    // Oculto, la ampliacion.
  'simple_popup_views_field',       // Picture.
  'name',                           // Material name.
  'field_tec_quantity',             // Quantity.
  'field_tec_unit_purchase',        // Purchase UoM.
  'field_tec_cost',                 // Purchase Cost.
  'field_views_simple_math_field',  // Sub total, que multiplica las dos de arriba.
  'field_tec_quantity_received',    // Received.
  'field_tec_no_more_expected',     // La marca de "closed short", pegada a Received.
  'view_tec_order',                 // Oculto.
  'field_tec_vendor',               // Oculto.
  'name_1',                         // Oculto, es por quien ordena la lista.
];

/**
 * Como se alinea cada columna de la ficha, igual que en el borrador.
 */
const ALINEADO_DE_LA_FICHA = [
  'counter' => 'views-align-center',
  'simple_popup_views_field' => 'views-align-center',
  'name' => 'views-align-left',
  'field_tec_quantity' => 'views-align-right',
  'field_views_simple_math_field' => 'views-align-right',
  'field_tec_quantity_received' => 'views-align-right',
  'field_tec_no_more_expected' => 'views-align-center',
];

$vista = View::load(VISTA);
if (!$vista) {
  print "No existe la vista " . VISTA . ".\n";
  return;
}
$displays = $vista->get('display');

// -----------------------------------------------------------------------------
// 1. Los nombres de las columnas.
// -----------------------------------------------------------------------------
print "Los nombres\n" . str_repeat('-', 78) . "\n";
foreach (NOMBRES as $pantalla => $nombres) {
  foreach ($nombres as $campo => $nombre) {
    if (!isset($displays[$pantalla]['display_options']['fields'][$campo])) {
      printf("  [MAL] %s no tiene la columna %s\n", $pantalla, $campo);
      continue;
    }
    $antes = (string) ($displays[$pantalla]['display_options']['fields'][$campo]['label'] ?? '');
    $displays[$pantalla]['display_options']['fields'][$campo]['label'] = $nombre;
    printf("  %-8s %-32s %-24s -> %s\n", $pantalla, $campo, '"' . $antes . '"', '"' . $nombre . '"');
  }
}

// -----------------------------------------------------------------------------
// 2. El dinero, escrito igual en las dos.
// -----------------------------------------------------------------------------
//
// El simbolo del baht no lo pone la vista: lo lleva el propio campo en su
// definicion y la vista solo lo respeta. Pero el Sub total de la ficha no es un
// campo, es una cuenta de la propia vista -cantidad por coste-, asi que ahi hay
// que escribirlo a mano o sale un numero pelado. Salia "3,998.5" al lado de un
// "฿ 799.70", con la misma columna llamandose igual en la otra pantalla.
print "\nEl dinero\n" . str_repeat('-', 78) . "\n";

$suma = &$displays['block_2']['display_options']['fields']['field_views_simple_math_field'];
$suma['set_precision'] = TRUE;
$suma['precision'] = '2';
$suma['decimal'] = '.';
$suma['separator'] = ',';
// Escapado en vez de escrito, que este fichero no tiene por que depender de
// sobrevivir a un editor que adivine codificaciones.
$suma['prefix'] = "\u{0E3F} ";
unset($suma);
print "  ficha    Sub total: dos decimales, millares con coma y su simbolo\n";

// Y al coste del borrador le faltaba la coma de los millares, asi que un coste
// de mil doscientos se leia "1200.00" en una pantalla y "1,200.00" en la otra.
$displays['page_1']['display_options']['fields']['field_tec_cost']['settings']['thousand_separator'] = ',';
print "  borrador Purchase Cost: millares con coma, como en la ficha\n";

// -----------------------------------------------------------------------------
// 3. El orden de la ficha.
// -----------------------------------------------------------------------------
print "\nEl orden de la ficha\n" . str_repeat('-', 78) . "\n";
$campos = $displays['block_2']['display_options']['fields'];

// Antes negarse que adivinar. Si la pantalla ha ganado o perdido una columna
// desde que se escribio esta lista, reordenar contra una lista vieja tiraria por
// el camino lo que no este nombrado aqui.
$sobran = array_diff(array_keys($campos), ORDEN_DE_LA_FICHA);
$faltan = array_diff(ORDEN_DE_LA_FICHA, array_keys($campos));
if ($sobran || $faltan) {
  print "  La ficha ya no coincide con esta lista, asi que no se ha tocado el orden.\n";
  if ($sobran) {
    print "    en pantalla y no en la lista: " . implode(', ', $sobran) . "\n";
  }
  if ($faltan) {
    print "    en la lista y no en pantalla: " . implode(', ', $faltan) . "\n";
  }
  return;
}

$ordenados = [];
foreach (ORDEN_DE_LA_FICHA as $campo) {
  $ordenados[$campo] = $campos[$campo];
}
$displays['block_2']['display_options']['fields'] = $ordenados;
print "  Puestas en el orden del borrador, con Received al final.\n";

// -----------------------------------------------------------------------------
// 4. El alineado de la ficha.
// -----------------------------------------------------------------------------
print "\nEl alineado de la ficha\n" . str_repeat('-', 78) . "\n";
foreach (ALINEADO_DE_LA_FICHA as $campo => $como) {
  // La tabla solo guarda ajustes de las columnas que alguien toco alguna vez, y
  // las demas no tienen ni entrada, asi que hay que crearla completa.
  $antes = $displays['block_2']['display_options']['style']['options']['info'][$campo] ?? [
    'sortable' => FALSE,
    'default_sort_order' => 'asc',
    'align' => '',
    'separator' => '',
    'empty_column' => FALSE,
    'responsive' => '',
  ];
  $antes['align'] = $como;
  $displays['block_2']['display_options']['style']['options']['info'][$campo] = $antes;
  printf("  %-32s %s\n", $campo, $como);
}

$vista->set('display', $displays);
$vista->save();

// -----------------------------------------------------------------------------
// 5. La casilla de cantidad del borrador.
// -----------------------------------------------------------------------------
print "\nLa casilla de cantidad\n" . str_repeat('-', 78) . "\n";
$inyector = \Drupal::configFactory()->getEditable('asset_injector.css.tec_excel_lover_orders');
if ($inyector->isNew()) {
  print "  [MAL] no existe el inyector tec_excel_lover_orders.\n";
}
else {
  $css = <<<'CSS'
/* Excel lover orders */

/*.ajax-progress {
  display: none;
}*/

/* La cabecera de la columna ya dice Quantity; repetirlo encima de cada casilla
   es ruido. La regla existia desde siempre pero con un solo selector, y ese solo
   cogia la pantalla de ventas: Views le pone un -1 detras a la segunda copia del
   campo, y la de compras es la segunda. */
.views-field-form-field-field-tec-quantity label,
.views-field-form-field-field-tec-quantity-1 label {
    display: none;
}

/* Cuatro cifras no necesitan el ancho entero de la celda, y a ancho entero la
   casilla era lo mas gordo de la fila. */
.views-field-form-field-field-tec-quantity input,
.views-field-form-field-field-tec-quantity-1 input {
    width: 5rem;
    max-width: 100%;
}

#edit-actions {
    float: left;
}

#edit-actions-submit {
    position: relative;
    padding: 1px 10px 1px 25px;
    font-weight: bold;
}

#edit-actions-submit::before {
    content: url('/sites/default/files/000-save-16.png');
    position: absolute;
    left: 5px;
    top: 50%;
    transform: translateY(-50%);
}

.view-id-tec_order_sales_order_line_items.view-display-id-attachment_3{
    float: left;
    margin-top: 26px
}

.view-tec-order-sales-order-line-items .view-id-tec_order_sales_order_line_items {
    border-bottom: 0px solid #ccc;
    padding-bottom: 0em;
}
CSS;
  $inyector->set('code', $css)->save();
  print "  Ancho 5rem, y el rotulo de encima tapado en las dos pantallas.\n";
}

// -----------------------------------------------------------------------------
// Como ha quedado.
// -----------------------------------------------------------------------------
print "\nComo se leen ahora las dos pantallas\n" . str_repeat('=', 78) . "\n";
$vista = View::load(VISTA);
foreach (['page_1' => 'borrador  po/draft/%', 'block_2' => 'ficha     tec_order/%'] as $pantalla => $como) {
  $display = $vista->getDisplay($pantalla)['display_options'];
  $visibles = [];
  foreach ($display['fields'] as $clave => $campo) {
    if (empty($campo['exclude'])) {
      $visibles[] = ($campo['label'] ?? '') === '' ? '(sin nombre)' : $campo['label'];
    }
  }
  printf("  %s\n    %s\n", $como, implode(' | ', $visibles));
}
