<?php

/**
 * @file
 * Las dos pantallas que arrastra la recepcion.
 *
 *   php vendor\bin\drush.php scr scripts/poner-lo-recibido-en-las-pantallas.php
 *
 * 1. La tabla de lineas de la ficha del pedido no decia nada de lo recibido, asi
 *    que abrir un pedido a medio entregar no contaba lo que ya habia llegado. Se
 *    le anaden dos columnas justo detras de la cantidad: lo recibido y la marca
 *    de las lineas que se cerraron cortas.
 *
 * 2. En la lista de pedidos de un proveedor, los botones de editar e imprimir
 *    salen dentro de un campo condicional que solo se cumple cuando el estado es
 *    "Open". Mientras hubo un unico estado eso no se notaba, porque siempre se
 *    cumplia. Con el estado nuevo, un pedido cerrado se quedaria sin ninguno de
 *    los dos, y perder el de imprimir es perder la copia para la carpeta de
 *    contabilidad. Asi que el condicional gana su rama del "si no": los cerrados
 *    conservan imprimir y solo pierden editar, que en un pedido terminado es
 *    justo lo que no se quiere que se toque a la ligera.
 *
 * Se puede lanzar dos veces: lo que ya esta hecho se deja como esta.
 */

$storage = \Drupal::entityTypeManager()->getStorage('view');
$cambios = 0;

print "\n1. Lo recibido en la tabla de lineas del pedido\n";
print "----------------------------------------------\n";

$vista = $storage->load('tec_order_sales_order_line_items');
$displays = $vista->get('display');
$campos = $displays['block_2']['display_options']['fields'];

if (isset($campos['field_tec_quantity_received'])) {
  print "[ya  ] las columnas ya estaban\n";
}
else {
  // Se copia la estructura de la cantidad, que es un campo del mismo tipo y ya
  // esta en esta pantalla, en vez de escribir las treinta claves a mano.
  $molde = $campos['field_tec_quantity'];

  $recibido = $molde;
  $recibido['id'] = 'field_tec_quantity_received';
  $recibido['table'] = 'tec_line_item__field_tec_quantity_received';
  $recibido['field'] = 'field_tec_quantity_received';
  $recibido['entity_field'] = 'field_tec_quantity_received';
  $recibido['label'] = 'Received';
  $recibido['exclude'] = FALSE;
  $recibido['empty_zero'] = FALSE;

  $cerrada = $molde;
  $cerrada['id'] = 'field_tec_no_more_expected';
  $cerrada['table'] = 'tec_line_item__field_tec_no_more_expected';
  $cerrada['field'] = 'field_tec_no_more_expected';
  $cerrada['entity_field'] = 'field_tec_no_more_expected';
  $cerrada['label'] = '';
  $cerrada['exclude'] = FALSE;
  $cerrada['type'] = 'boolean';
  // Un "no" en cada fila normal seria ruido: solo se ensena cuando hay algo que
  // decir, y lo que hay que decir es que esa linea ya no espera nada mas.
  $cerrada['settings'] = [
    'format' => 'custom',
    'format_custom_false' => '',
    'format_custom_true' => 'closed short',
  ];

  // Detras de la cantidad, que es con lo que se comparan.
  $nuevos = [];
  foreach ($campos as $clave => $valor) {
    $nuevos[$clave] = $valor;
    if ($clave === 'field_tec_quantity') {
      $nuevos['field_tec_quantity_received'] = $recibido;
      $nuevos['field_tec_no_more_expected'] = $cerrada;
    }
  }
  $displays['block_2']['display_options']['fields'] = $nuevos;
  $vista->set('display', $displays);
  $vista->save();
  print "[hecho] Received y la marca de cierre, detras de la cantidad\n";
  $cambios++;
}

print "\n2. Que un pedido cerrado no pierda el boton de imprimir\n";
print "------------------------------------------------------\n";

$vista = $storage->load('tec_supplier_orders');
$displays = $vista->get('display');
$campos = $displays['block_3']['display_options']['fields'];
$condicional = $campos['views_conditional_field'];

if (!empty($condicional['or'])) {
  print "[ya  ] el condicional ya tenia rama de 'si no': " . $condicional['or'] . "\n";
}
else {
  $solo_imprimir = $campos['nothing'];
  $solo_imprimir['id'] = 'nothing_1';
  $solo_imprimir['alter']['text'] = "<a class=\"tec-control\" href=\"/po/{{ id }}/print\" target=\"blank\" title=\"Print/PDF\">\r\n    <img src=\"/sites/default/files/000-print-16.png\">\r\n  </a>";
  $solo_imprimir['exclude'] = TRUE;

  // El campo nuevo tiene que ir antes del condicional que lo usa, igual que el
  // que ya habia: views resuelve los tokens con lo que ya ha pintado.
  $nuevos = [];
  foreach ($campos as $clave => $valor) {
    if ($clave === 'views_conditional_field') {
      $nuevos['nothing_1'] = $solo_imprimir;
    }
    $nuevos[$clave] = $valor;
  }
  $nuevos['views_conditional_field']['or'] = '{{ nothing_1 }}';

  $displays['block_3']['display_options']['fields'] = $nuevos;
  $vista->set('display', $displays);
  $vista->save();
  print "[hecho] los cerrados conservan imprimir y solo pierden editar\n";
  $cambios++;
}

print "\n";
print $cambios ? "$cambios cambio(s). Hay que exportar la configuracion.\n" : "No habia nada que cambiar.\n";
