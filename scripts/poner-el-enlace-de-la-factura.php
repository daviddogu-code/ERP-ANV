<?php

/**
 * @file
 * Pone el enlace de la factura en las listas y en la ficha del pedido.
 *
 *   php vendor\bin\drush.php scr scripts/poner-el-enlace-de-la-factura.php
 *
 * Filing the invoice takes the row off Purchase Control. The file stays on the
 * order, but there was no way back to it: the card hid the field and the lists
 * only kept Print. This hangs a download next to Print, empty when nothing is
 * filed, so the same button row works for open and closed orders.
 */

use Drupal\layout_builder\SectionComponent;
use Drupal\views\Entity\View;

$problemas = 0;
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

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
  'hide_empty' => TRUE,
  'empty_zero' => FALSE,
  'hide_alter_empty' => TRUE,
];

$campo = function (string $relacion, bool $con_palabra) use ($sin_reescribir, $envoltorio): array {
  return [
    'id' => 'tec_invoice',
    'table' => 'tec_order_field_data',
    'field' => 'tec_invoice',
    'relationship' => $relacion,
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'tec_purchase_invoice',
    'label' => '',
    'exclude' => TRUE,
    'alter' => $sin_reescribir,
  ] + $envoltorio + [
    'show_label' => $con_palabra,
  ];
};

$meter = function (array $campos, string $antes, string $relacion, bool $con_palabra) use ($campo): array {
  $nuevos = [];
  $puesto = FALSE;
  foreach ($campos as $id => $def) {
    if ($id === 'tec_invoice') {
      continue;
    }
    if ($id === $antes && !$puesto) {
      $nuevos['tec_invoice'] = $campo($relacion, $con_palabra);
      $puesto = TRUE;
    }
    $nuevos[$id] = $def;
  }
  if (!$puesto) {
    $nuevos['tec_invoice'] = $campo($relacion, $con_palabra);
  }
  return $nuevos;
};

$token = function (string $texto): string {
  if (str_contains($texto, '{{ tec_invoice }}')) {
    return $texto;
  }
  return rtrim($texto) . ' {{ tec_invoice }}';
};

$icono = function (string $enlace, string $fichero, string $titulo, bool $nueva_ventana = FALSE): string {
  return '<a class="tec-control" href="' . $enlace . '"'
    . ($nueva_ventana ? ' target="blank"' : '') . ' title="' . $titulo . '">'
    . '<img src="/sites/default/files/' . $fichero . '"></a>';
};

echo "\n";
echo "Listas de pedidos de compra\n";
echo str_repeat('-', 78) . "\n";

foreach ([
  ['tec_supplier_orders', 'default', '/supplier-orders'],
  ['tec_supplier_orders', 'block_3', 'bloque en la ficha del proveedor'],
  ['tec_orders_orders', 'block_3', 'pestana de pedidos del proveedor'],
] as [$nombre, $display, $donde]) {
  $vista = View::load($nombre);
  if (!$vista) {
    $mirar("$nombre existe", FALSE);
    continue;
  }
  $mostrar = $vista->get('display');
  $campos = $mostrar[$display]['display_options']['fields'] ?? [];
  if (!$campos) {
    $mirar("$donde tiene campos", FALSE);
    continue;
  }
  $campos = $meter($campos, 'nothing', 'none', FALSE);
  if (isset($campos['nothing']['alter']['text'])) {
    $campos['nothing']['alter']['text'] = $token($campos['nothing']['alter']['text']);
  }
  if (isset($campos['nothing_1']['alter']['text'])) {
    $campos['nothing_1']['alter']['text'] = $token($campos['nothing_1']['alter']['text']);
  }
  elseif (isset($campos['views_conditional_field']) && ($campos['views_conditional_field']['or'] ?? '') === '') {
    $cerrado = $icono('/po/{{ id }}/print', '000-print-16.png', 'Print/PDF', TRUE)
      . ' {{ tec_invoice }}';
    $campos['views_conditional_field']['or'] = $cerrado;
  }
  $mostrar[$display]['display_options']['fields'] = $campos;
  $vista->set('display', $mostrar);
  $vista->save();

  $guardados = $vista->get('display')[$display]['display_options']['fields'];
  $mirar("$donde tiene el campo de factura", isset($guardados['tec_invoice']));
  $mirar("$donde lo pone al lado de imprimir en abierto",
    str_contains($guardados['nothing']['alter']['text'] ?? '', '{{ tec_invoice }}'));
  $cerrado_ok = str_contains($guardados['nothing_1']['alter']['text'] ?? '', '{{ tec_invoice }}')
    || str_contains($guardados['views_conditional_field']['or'] ?? '', '{{ tec_invoice }}');
  $mirar("$donde tambien en cerrado", $cerrado_ok);
}

echo "\nFicha del pedido\n";
echo str_repeat('-', 78) . "\n";

$vista = View::load('tec_order_sales_order_line_items');
if (!$vista) {
  echo "  No encuentro la vista de lineas.\n";
  return;
}
$mostrar = $vista->get('display');
$campos = $mostrar['attachment_6']['display_options']['fields'] ?? [];
$campos = $meter($campos, 'views_conditional_field', 'field_tec_order', TRUE);
if (isset($campos['views_conditional_field']['then'])) {
  $campos['views_conditional_field']['then'] = $token($campos['views_conditional_field']['then']);
}
if (isset($campos['views_conditional_field']['or'])) {
  $campos['views_conditional_field']['or'] = $token($campos['views_conditional_field']['or']);
}
$mostrar['attachment_6']['display_options']['fields'] = $campos;
$vista->set('display', $mostrar);
$vista->save();

$ficha = $vista->get('display')['attachment_6']['display_options']['fields'];
$mirar('la ficha tiene el campo de factura', ($ficha['tec_invoice']['plugin_id'] ?? '') === 'tec_purchase_invoice');
$mirar('y lo enseña con la palabra Invoice', !empty($ficha['tec_invoice']['show_label']));
$mirar('abierto: editar, recibir, imprimir y factura',
  str_contains($ficha['views_conditional_field']['then'] ?? '', '/receive')
  && str_contains($ficha['views_conditional_field']['then'] ?? '', '{{ tec_invoice }}'));
$mirar('cerrado: imprimir y factura, sin recibir',
  !str_contains($ficha['views_conditional_field']['or'] ?? '', '/receive')
  && str_contains($ficha['views_conditional_field']['or'] ?? '', '/print')
  && str_contains($ficha['views_conditional_field']['or'] ?? '', '{{ tec_invoice }}'));

echo "\nBloque Invoice en la ficha\n";
echo str_repeat('-', 78) . "\n";

$uuid = '7f3a9c21-4b8e-4d16-9a70-2e5c8f1b0d44';
$plugin = 'extra_field_block:tec_order:tec_purchase_order:tec_invoice';
$pantalla = \Drupal::entityTypeManager()->getStorage('entity_view_display')
  ->load('tec_order.tec_purchase_order.default');
$ya = FALSE;
foreach ($pantalla->getSections() as $seccion) {
  foreach ($seccion->getComponents() as $bloque) {
    if ($bloque->getPluginId() === $plugin) {
      $ya = TRUE;
    }
  }
}
if (!$ya) {
  $bloque = new SectionComponent($uuid, 'blb_region_col_1', [
    'id' => $plugin,
    'label' => 'Invoice',
    'label_display' => 'visible',
    'provider' => 'layout_builder',
    'context_mapping' => ['entity' => 'layout_builder.entity'],
  ]);
  $bloque->setWeight(6);
  $secciones = $pantalla->getSections();
  $secciones[1]->appendComponent($bloque);
  $pantalla->save();
}
$plugins = [];
$pantalla = \Drupal::entityTypeManager()->getStorage('entity_view_display')
  ->load('tec_order.tec_purchase_order.default');
foreach ($pantalla->getSections() as $seccion) {
  foreach ($seccion->getComponents() as $bloque) {
    $plugins[] = $bloque->getPluginId();
  }
}
$mirar('la ficha tiene el bloque de la factura', in_array($plugin, $plugins, TRUE));
$impreso = \Drupal::entityTypeManager()->getStorage('entity_view_display')
  ->load('tec_order.tec_purchase_order.pdf');
$mirar('y el pdf no', $impreso && $impreso->getComponent('tec_invoice') === NULL);

echo "\n";
echo $problemas === 0
  ? "El enlace de la factura esta en las listas y en la ficha.\n\n"
  : "$problemas cosa(s) mal.\n\n";
