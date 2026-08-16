<?php

/**
 * @file
 * Pone el boton de recibir en la ficha del pedido de compra.
 *
 *   php vendor\bin\drush.php scr scripts/poner-el-boton-de-recibir-en-la-ficha.php
 *
 * La pestana de Receive existia y funcionaba, pero no se veia: DXPR saca la
 * barra de pestanas del flujo de la pagina -posicion absoluta, centrada y subida
 * su propia altura entera- y la deja flotando encima de la cabecera pegajosa,
 * donde queda ilegible. Y ademas ese bloque tiene una condicion de rol que solo
 * deja pasar a administrator, asi que quien firma el albaran no la veria nunca
 * aunque el tema la pintase bien.
 *
 * Asi que el boton va donde la gente ya mira: al lado de Edit order y Print/PDF,
 * que salen de attachment_6 de la vista de lineas.
 *
 * De paso los tres botones pasan por un condicional sobre el estado del pedido,
 * igual que ya hacen attachment_5 con los pedidos de venta y la lista de
 * proveedores con los de compra: abierto ensena los tres, cerrado conserva
 * imprimir -que es la copia para contabilidad- y pierde editar y recibir. Hasta
 * ahora un pedido cerrado seguia ofreciendo editar en su propia ficha, aunque la
 * lista de proveedores ya se lo negaba.
 */

use Drupal\views\Entity\View;

$vista = View::load('tec_order_sales_order_line_items');
if (!$vista) {
  echo "No encuentro la vista tec_order_sales_order_line_items.\n";
  return;
}

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

$boton = function (string $enlace, string $icono, string $texto, bool $nueva_ventana = FALSE): string {
  return "<div class=\"btn-default\">\r\n"
    . "    <strong>\r\n"
    . '        <a href="' . $enlace . '"' . ($nueva_ventana ? ' target="_blank"' : '') . ">\r\n"
    . '            <img src="/sites/default/files/' . $icono . '" alt="' . $texto . '"> ' . $texto . "\r\n"
    . "        </a>\r\n"
    . "    </strong>\r\n"
    . "</div>";
};

$editar = $boton('/po/draft/{{ id }}?destination=/tec_order/{{ id }}', '000-edit-16.png', 'Edit order');
$recibir = $boton('/tec_order/{{ id }}/receive', '000-ok-16.png', 'Receive');
$imprimir = $boton('/po/{{ id }}/print', '000-print-16.png', 'Print/PDF', TRUE);

$abierto = $editar . "\r\n\r\n" . $recibir . "\r\n\r\n" . $imprimir;
$cerrado = $imprimir;

$mostrar = $vista->get('display');
$mostrar['attachment_6']['display_options']['fields'] = [
  // El estado, escondido: solo esta para que el condicional pueda mirarlo. Con
  // el formateador de lista sale la etiqueta, "Open" o "Closed", que es contra
  // lo que compara views_conditional.
  'field_tec_po_status' => [
    'id' => 'field_tec_po_status',
    'table' => 'tec_order__field_tec_po_status',
    'field' => 'field_tec_po_status',
    'relationship' => 'field_tec_order',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'field',
    'label' => '',
    'exclude' => TRUE,
    'alter' => $sin_reescribir,
  ] + $envoltorio + [
    'click_sort_column' => 'value',
    'type' => 'list_default',
    'settings' => [],
    'group_column' => 'value',
    'group_columns' => [],
    'group_rows' => TRUE,
    'delta_limit' => 0,
    'delta_offset' => 0,
    'delta_reversed' => FALSE,
    'delta_first_last' => FALSE,
    'multi_type' => 'separator',
    'separator' => ', ',
    'field_api_classes' => FALSE,
  ],
  // La ficha del pedido, escondida, para poder escribir las direcciones.
  'id' => [
    'id' => 'id',
    'table' => 'tec_order_field_data',
    'field' => 'id',
    'relationship' => 'field_tec_order',
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'tec_order',
    'entity_field' => 'id',
    'plugin_id' => 'field',
    'label' => '',
    'exclude' => TRUE,
    'alter' => $sin_reescribir,
  ] + $envoltorio + [
    'click_sort_column' => 'value',
    'type' => 'number_integer',
    'settings' => [
      'thousand_separator' => '',
      'prefix_suffix' => FALSE,
    ],
    'group_column' => 'value',
    'group_columns' => [],
    'group_rows' => TRUE,
    'delta_limit' => 0,
    'delta_offset' => 0,
    'delta_reversed' => FALSE,
    'delta_first_last' => FALSE,
    'multi_type' => 'separator',
    'separator' => ', ',
    'field_api_classes' => FALSE,
  ],
  'views_conditional_field' => [
    'id' => 'views_conditional_field',
    'table' => 'views_conditional',
    'field' => 'views_conditional_field',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'views_conditional_field',
    'label' => '',
    'exclude' => FALSE,
    'alter' => $sin_reescribir,
  ] + $envoltorio + [
    'if' => 'field_tec_po_status',
    'condition' => 'eq',
    'equalto' => 'Open',
    'then' => $abierto,
    'then_translate' => TRUE,
    'or' => $cerrado,
    'or_translate' => TRUE,
    'strip_tags' => FALSE,
  ],
];

$vista->set('display', $mostrar);
$vista->save();

echo "attachment_6 pasa a tener tres campos y un condicional:\n";
foreach ($vista->get('display')['attachment_6']['display_options']['fields'] as $nombre => $campo) {
  printf("  %-24s %s\n", $nombre, empty($campo['exclude']) ? 'se ve' : 'escondido');
}
echo "\n  abierto: editar, recibir, imprimir\n";
echo "  cerrado: imprimir\n\n";
