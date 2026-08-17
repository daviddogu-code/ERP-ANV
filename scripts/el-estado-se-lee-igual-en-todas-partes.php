<?php

/**
 * @file
 * Las cuatro pantallas que ensenan un estado de compra ensenan el mismo.
 *
 *   php vendor\bin\drush.php scr scripts/el-estado-se-lee-igual-en-todas-partes.php
 *   php vendor\bin\drush.php scr scripts/el-estado-se-lee-igual-en-todas-partes.php -- --aplicar
 *
 * Sin --aplicar solo cuenta lo que haria.
 *
 * El problema, en un ejemplo: un pedido servido entero al que aun no ha llegado
 * la factura. /supplier-orders decia "Closed", la ficha decia "Closed", el
 * impreso decia "Closed" y la pantalla de control decia "Delivered". Ninguna
 * mentia -- todas leian el campo open/closed, que es una pregunta distinta --
 * pero quien miraba dos pantallas seguidas veia dos respuestas.
 *
 * A partir de aqui las cuatro preguntan a Purchasing::progress(), que es el
 * unico sitio donde se decide. Siete lecturas: Open, los tres escalones que
 * mueve una persona, y Delivered, Closed y Cancelled, que se leen de los
 * albaranes, de la factura y de la bandera de cerrado.
 *
 * El campo open/closed no se toca y no se va a ninguna parte. Sigue siendo lo
 * que miran los botones para saber si un pedido se puede editar, y lo que mira
 * el tablero de stock para saber si aun viene material. Lo que deja de ser es
 * la palabra que lee una persona.
 *
 * Cuatro sitios:
 *
 *   1. /supplier-orders y su bloque.
 *   2. La pestana de pedidos dentro de la ficha del proveedor.
 *   3. La ficha del pedido, /tec_order/943, que va por Layout Builder.
 *   4. El impreso en PDF, que va por Display Suite. Ademas de la palabra le
 *      quita el formateador editable: un campo que se puede escribir dentro de
 *      un documento que se manda al proveedor no tenia ningun sentido.
 *
 * Se puede lanzar dos veces.
 */

$aplicar = in_array('--aplicar', $extra ?? [], TRUE) || in_array('--aplicar', $_SERVER['argv'] ?? [], TRUE);

const VIEJO = 'field_tec_po_status';
const NUEVO = 'tec_progress';
const MODELO = 'tec_receipt_state';
const ETIQUETA = 'Order status';

/**
 * La columna, escrita entera.
 *
 * Solo hace falta donde no hay una columna Delivery de la que copiar la forma,
 * que es el caso de la pestana de proveedores de una de las dos vistas. Son las
 * opciones que trae de serie cualquier campo de vistas; lo unico propio de esta
 * son las cuatro primeras lineas.
 */
function columna_de_estado(): array {
  return [
    'id' => NUEVO,
    'table' => 'tec_order_field_data',
    'field' => NUEVO,
    'plugin_id' => 'tec_purchase_progress',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'label' => ETIQUETA,
    'exclude' => FALSE,
    'alter' => [
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
    ],
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
}

/**
 * Cambia una clave de sitio sin moverla, que en las vistas es el orden de las
 * columnas.
 */
function reemplazar_clave(array $lista, string $vieja, string $nueva, $valor): array {
  $salida = [];
  foreach ($lista as $clave => $existente) {
    if ($clave === $vieja) {
      $salida[$nueva] = $valor;
    }
    else {
      $salida[$clave] = $existente;
    }
  }

  return $salida;
}

$cambios = 0;

print "\n1 y 2. Las dos listas de pedidos a proveedores\n";
print "---------------------------------------------\n";

foreach (['views.view.tec_supplier_orders', 'views.view.tec_orders_orders'] as $nombre) {
  $vista = \Drupal::configFactory()->getEditable($nombre);
  if ($vista->isNew()) {
    print "[MAL ] no existe $nombre\n";
    continue;
  }
  $displays = $vista->get('display') ?: [];
  $tocada = FALSE;

  foreach ($displays as $id => $display) {
    $campos = $display['display_options']['fields'] ?? [];
    if (isset($campos[NUEVO])) {
      print "[ya  ] $nombre / $id ya lee el estado deducido\n";
      continue;
    }
    // Solo la copia que se ve. La escondida, field_tec_po_status_1, es la que
    // deciden los botones, y esos si tienen que seguir el campo de verdad: un
    // pedido se puede editar mientras esta abierto, que no es lo mismo que lo
    // que se le cuenta a quien lo persigue.
    if (!isset($campos[VIEJO]) || !empty($campos[VIEJO]['exclude'])) {
      continue;
    }
    // Se copia la forma de la columna Delivery cuando la hay, porque es el
    // mismo tipo de campo y asi hereda cualquier ajuste que se le haya dado a
    // mano a esa pantalla. Donde no la hay se escribe entera.
    if (isset($campos[MODELO])) {
      $nuevo = $campos[MODELO];
      $nuevo['id'] = NUEVO;
      $nuevo['field'] = NUEVO;
      $nuevo['plugin_id'] = 'tec_purchase_progress';
      $nuevo['label'] = ETIQUETA;
    }
    else {
      $nuevo = columna_de_estado();
    }

    $displays[$id]['display_options']['fields'] = reemplazar_clave($campos, VIEJO, NUEVO, $nuevo);

    foreach (['columns', 'info'] as $trozo) {
      $ruta = &$displays[$id]['display_options']['style']['options'][$trozo];
      if (is_array($ruta) && array_key_exists(VIEJO, $ruta)) {
        $ruta = reemplazar_clave($ruta, VIEJO, NUEVO, $trozo === 'columns' ? NUEVO : $ruta[VIEJO]);
      }
      unset($ruta);
    }

    print sprintf("%s %s / %s\n", $aplicar ? '[hecho]' : '[haria]', $nombre, $id);
    $tocada = TRUE;
    $cambios++;
  }

  if ($tocada && $aplicar) {
    $vista->set('display', $displays)->save();
  }
}

print "\n3. La ficha del pedido (Layout Builder)\n";
print "--------------------------------------\n";

$ficha = \Drupal::configFactory()->getEditable('core.entity_view_display.tec_order.tec_purchase_order.default');
$secciones = $ficha->get('third_party_settings.layout_builder.sections') ?: [];
$tocada = FALSE;

// Un bloque de campo anadido no es un bloque de campo con otro nombre. No lleva
// formateador, porque no hay campo que formatear, y solo declara un contexto:
// la entidad. Arrastrarle el 'view_mode' del bloque anterior tira la ficha
// entera con "Assigned contexts were not satisfied", porque Layout Builder
// exige que cada contexto asignado exista en el bloque que lo recibe.
$bueno = [
  'id' => 'extra_field_block:tec_order:tec_purchase_order:' . NUEVO,
  'label' => ETIQUETA,
  'label_display' => '0',
  'provider' => 'layout_builder',
  'context_mapping' => ['entity' => 'layout_builder.entity'],
];

foreach ($secciones as $s => $seccion) {
  foreach ($seccion['components'] ?? [] as $uuid => $componente) {
    $id = $componente['configuration']['id'] ?? '';
    if ($id !== 'field_block:tec_order:tec_purchase_order:' . VIEJO && $id !== $bueno['id']) {
      continue;
    }
    if ($componente['configuration'] === $bueno) {
      print "[ya  ] la ficha ya lee el estado deducido\n";
      continue;
    }

    $secciones[$s]['components'][$uuid]['configuration'] = $bueno;
    print sprintf("%s la ficha pasa a leer el estado deducido%s\n",
      $aplicar ? '[hecho]' : '[haria]',
      $id === $bueno['id'] ? ' (le sobraban contextos)' : '');
    $tocada = TRUE;
    $cambios++;
  }
}

if ($tocada && $aplicar) {
  $ficha->set('third_party_settings.layout_builder.sections', $secciones)->save();
}

print "\n4. El impreso en PDF (Display Suite)\n";
print "-----------------------------------\n";

$pdf = \Drupal::configFactory()->getEditable('core.entity_view_display.tec_order.tec_purchase_order.pdf');
$contenido = $pdf->get('content') ?: [];
$regiones = $pdf->get('third_party_settings.ds.regions') ?: [];

if (isset($contenido[NUEVO])) {
  print "[ya  ] el impreso ya lee el estado deducido\n";
}
elseif (!isset($contenido[VIEJO])) {
  print "[ya  ] el impreso no ensena ningun estado\n";
}
else {
  $contenido = reemplazar_clave($contenido, VIEJO, NUEVO, [
    'settings' => [],
    'third_party_settings' => [],
    'weight' => $contenido[VIEJO]['weight'] ?? 0,
    'region' => $contenido[VIEJO]['region'] ?? 'top',
  ]);
  foreach ($regiones as $region => $campos) {
    $donde = array_search(VIEJO, $campos, TRUE);
    if ($donde !== FALSE) {
      $regiones[$region][$donde] = NUEVO;
    }
  }

  print sprintf("%s el impreso pasa a leer el estado deducido, y deja de ser editable\n", $aplicar ? '[hecho]' : '[haria]');
  $cambios++;

  if ($aplicar) {
    $pdf->set('content', $contenido)
      ->set('third_party_settings.ds.regions', $regiones)
      ->save();
  }
}

print "\nCambios: $cambios.\n";
if (!$aplicar) {
  print "\nEsto ha sido en seco. Con --aplicar se escribe.\n";
}
