<?php

/**
 * @file
 * Arregla la columna del estado y los botones de /supplier-orders, y pone la
 * columna de entrega en las dos listas de pedidos de compra.
 *
 *   php vendor\bin\drush.php scr scripts/arreglar-la-lista-de-pedidos-de-compra.php
 *
 * La pantalla /supplier-orders tenia una columna llamada "Order status" que
 * salia vacia siempre, y no por falta de datos: preguntaba por
 * field_tec_order_status, que es el estado de los pedidos de VENTA. Un pedido de
 * compra no tiene ese campo -tiene cinco y ese no es ninguno-, y la pantalla
 * esta filtrada para enseñar solo pedidos de compra, asi que la columna estaba
 * vacia el cien por cien de las veces desde el dia que se hizo.
 *
 * El mismo campo inexistente alimentaba el condicional que decide los botones de
 * la fila, asi que el lapiz de editar no salia nunca. Y el enlace que habia
 * detras apuntaba a /o/draft, el borrador de un pedido de venta, que tampoco era
 * el sitio. Tres sintomas, una sola causa: este display se copio de una lista de
 * pedidos de venta y nadie termino de cambiarle las preguntas.
 *
 * Se le añade ademas la columna de entrega, que dice si no ha llegado nada, ha
 * llegado parte o esta completo. Sale del campo de vista propio del modulo, que
 * llama a Purchasing::receiptState(): el mismo calculo que usan el tablero y el
 * guardian, para que las pantallas no puedan contradecirse.
 *
 * Los tres bloques de esa vista tienen su propia lista de campos, asi que este
 * cambio solo toca el display por defecto y la pagina que lo hereda. Del estilo,
 * que si es compartido, solo se añaden entradas: quitar la del estado de ventas
 * le estropearia la alineacion al bloque de pedidos de venta.
 */

use Drupal\views\Entity\View;

$problemas = 0;

$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

// Los dos bloques de opciones que todo campo de vista arrastra, sin nada puesto.
$sin_reescribir = [
  'alter_text' => FALSE,
  'text' => '',
  'make_link' => FALSE,
  'path' => '',
  'absolute' => FALSE,
  'external' => FALSE,
  'replace_spaces' => FALSE,
  'path_case' => 'none',
  'trim_whitespace' => FALSE,
  'alt' => '',
  'rel' => '',
  'link_class' => '',
  'prefix' => '',
  'suffix' => '',
  'target' => '',
  'nl2br' => FALSE,
  'max_length' => 0,
  'word_boundary' => TRUE,
  'ellipsis' => TRUE,
  'more_link' => FALSE,
  'more_link_text' => '',
  'more_link_path' => '',
  'strip_tags' => FALSE,
  'trim' => FALSE,
  'preserve_tags' => '',
  'html' => FALSE,
];
$envoltorio = [
  'element_type' => '',
  'element_class' => '',
  'element_label_type' => '',
  'element_label_class' => '',
  'element_label_colon' => FALSE,
  'element_wrapper_type' => '',
  'element_wrapper_class' => '',
  'element_default_classes' => TRUE,
  'empty' => '',
  'hide_empty' => FALSE,
  'empty_zero' => FALSE,
  'hide_alter_empty' => TRUE,
];

/**
 * La columna de entrega, que no viene de ningun campo guardado.
 */
$entrega = [
  'id' => 'tec_receipt_state',
  'table' => 'tec_order_field_data',
  'field' => 'tec_receipt_state',
  'relationship' => 'none',
  'group_type' => 'group',
  'admin_label' => '',
  'plugin_id' => 'tec_purchase_receipt_state',
  'label' => 'Delivery',
  'exclude' => FALSE,
  'alter' => $sin_reescribir,
] + $envoltorio;

/**
 * Un icono de los que ya usan estas listas.
 */
$icono = function (string $enlace, string $fichero, string $titulo, bool $nueva_ventana = FALSE): string {
  return '<a class="tec-control" href="' . $enlace . '"'
    . ($nueva_ventana ? ' target="blank"' : '') . ' title="' . $titulo . "\">\r\n"
    . '    <img src="/sites/default/files/' . $fichero . "\">\r\n"
    . '  </a>';
};

echo "\n";
echo "La lista de /supplier-orders\n";
echo str_repeat('-', 78) . "\n";

$vista = View::load('tec_supplier_orders');
if (!$vista) {
  echo "  No encuentro la vista tec_supplier_orders.\n";
  return;
}

$mostrar = $vista->get('display');
$campos = $mostrar['default']['display_options']['fields'];

if (!isset($campos['field_tec_order_status'])) {
  echo "  Ya no esta el campo del estado de ventas. Puede que esto ya se corriera.\n";
}

// El estado de compra, calcado del de ventas que habia para no perder ni la
// etiqueta ni el envoltorio con la clase, que es de donde salen los colores.
$plantilla = $campos['field_tec_order_status'] ?? $campos['field_tec_po_status'] ?? NULL;
if (!$plantilla) {
  echo "  No hay campo de estado del que partir. Se para aqui.\n";
  return;
}
$plantilla['table'] = 'tec_order__field_tec_po_status';
$plantilla['field'] = 'field_tec_po_status';

$estado_visible = $plantilla;
$estado_visible['id'] = 'field_tec_po_status';
$estado_visible['label'] = 'Order status';
$estado_visible['exclude'] = FALSE;
$estado_visible['alter']['alter_text'] = TRUE;
$estado_visible['alter']['text'] = '<div class="{{ field_tec_po_status }}">{{ field_tec_po_status }}</div>';

// Y una copia escondida sin reescribir, porque views_conditional compara con lo
// que el campo pinta: el visible pinta '<div class="Open">Open</div>' y no
// 'Open'. Es el mismo truco que ya usaba el bloque del proveedor.
$estado_oculto = $plantilla;
$estado_oculto['id'] = 'field_tec_po_status_1';
$estado_oculto['label'] = '';
$estado_oculto['exclude'] = TRUE;
$estado_oculto['alter'] = $sin_reescribir;

$abierto = ' ' . $icono('/po/draft/{{ id }}?destination=/supplier-orders', '000-edit-16.png', 'Edit order')
  . "\r\n\r\n" . $icono('/po/{{ id }}/print', '000-print-16.png', 'Print/PDF', TRUE);
$cerrado = $icono('/po/{{ id }}/print', '000-print-16.png', 'Print/PDF', TRUE);

$botones_abierto = $campos['nothing'];
$botones_abierto['admin_label'] = 'Buttons for an open purchase order';
$botones_abierto['exclude'] = TRUE;
$botones_abierto['alter']['alter_text'] = TRUE;
$botones_abierto['alter']['text'] = $abierto;

$botones_cerrado = $botones_abierto;
$botones_cerrado['id'] = 'nothing_1';
$botones_cerrado['admin_label'] = 'Buttons for a closed purchase order';
$botones_cerrado['alter']['text'] = $cerrado;

$condicional = $campos['views_conditional_field'];
$condicional['if'] = 'field_tec_po_status_1';
$condicional['condition'] = 'eq';
$condicional['equalto'] = 'Open';
$condicional['then'] = '{{ nothing }}';
$condicional['or'] = '{{ nothing_1 }}';

// El orden importa: el condicional tiene que ir detras de todo lo que mira.
$nuevos = [
  'id' => $campos['id'],
  'field_tec_po_status_1' => $estado_oculto,
  'title' => $campos['title'],
  'field_tec_vendor' => $campos['field_tec_vendor'],
  'field_tec_po_status' => $estado_visible,
  'tec_receipt_state' => $entrega,
  'field_tec_priority' => $campos['field_tec_priority'],
  'uid' => $campos['uid'],
  'created' => $campos['created'],
  'changed' => $campos['changed'],
  'edit_tec_order' => $campos['edit_tec_order'],
  'nothing' => $botones_abierto,
  'nothing_1' => $botones_cerrado,
  'views_conditional_field' => $condicional,
];
$mostrar['default']['display_options']['fields'] = $nuevos;

// Del estilo solo se añade. Los tres bloques heredan este estilo, y uno de ellos
// es de pedidos de venta y sigue usando el campo viejo.
$estilo = $mostrar['default']['display_options']['style']['options'];
$estilo['columns']['field_tec_po_status'] = 'field_tec_po_status';
$estilo['columns']['tec_receipt_state'] = 'tec_receipt_state';
$alineacion = $estilo['info']['field_tec_order_status'] ?? [
  'sortable' => FALSE,
  'default_sort_order' => 'asc',
  'align' => 'views-align-left',
  'separator' => '',
  'empty_column' => FALSE,
  'responsive' => '',
];
$estilo['info']['field_tec_po_status'] = $alineacion;
$estilo['info']['tec_receipt_state'] = $alineacion;
$mostrar['default']['display_options']['style']['options'] = $estilo;

$vista->set('display', $mostrar);
$vista->save();

$guardados = $vista->get('display')['default']['display_options']['fields'];
$mirar('el estado de ventas ya no esta', !isset($guardados['field_tec_order_status']));
$mirar('la columna del estado lee el campo de compra', isset($guardados['field_tec_po_status']));
$mirar('hay copia escondida para el condicional', !empty($guardados['field_tec_po_status_1']['exclude']));
$mirar('esta la columna de entrega', isset($guardados['tec_receipt_state']));
$mirar('el condicional pregunta por el estado de compra',
  ($guardados['views_conditional_field']['if'] ?? '') === 'field_tec_po_status_1',
  (string) ($guardados['views_conditional_field']['if'] ?? 'nada'));
$mirar('el lapiz apunta al borrador de compra',
  str_contains($guardados['nothing']['alter']['text'] ?? '', '/po/draft/'));
$mirar('el pedido cerrado conserva imprimir y pierde editar',
  str_contains($guardados['nothing_1']['alter']['text'] ?? '', '/print')
  && !str_contains($guardados['nothing_1']['alter']['text'] ?? '', '/po/draft/'));

echo "\n";
echo "La pestana de pedidos de la ficha del proveedor\n";
echo str_repeat('-', 78) . "\n";

$vista = View::load('tec_orders_orders');
if (!$vista) {
  echo "  No encuentro la vista tec_orders_orders.\n";
  return;
}

$mostrar = $vista->get('display');
$campos = $mostrar['block_3']['display_options']['fields'];

// Detras del estado, que es la columna a la que matiza.
$nuevos = [];
foreach ($campos as $nombre => $campo) {
  if ($nombre === 'tec_receipt_state') {
    continue;
  }
  $nuevos[$nombre] = $campo;
  if ($nombre === 'field_tec_po_status') {
    $nuevos['tec_receipt_state'] = $entrega;
  }
}
$mostrar['block_3']['display_options']['fields'] = $nuevos;

$vista->set('display', $mostrar);
$vista->save();

$guardados = $vista->get('display')['block_3']['display_options']['fields'];
$mirar('esta la columna de entrega', isset($guardados['tec_receipt_state']));
$posiciones = array_keys($guardados);
$mirar('y va detras del estado',
  array_search('tec_receipt_state', $posiciones, TRUE) === array_search('field_tec_po_status', $posiciones, TRUE) + 1,
  implode(', ', array_slice($posiciones, 5, 5)));

echo "\n";
echo $problemas === 0
  ? "Las dos listas dicen ya el estado de compra y como va la entrega.\n\n"
  : "$problemas cosa(s) mal.\n\n";
