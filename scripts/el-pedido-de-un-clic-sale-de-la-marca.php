<?php

/**
 * @file
 * La consulta del «+ Order» sube por la marca y no por el cliente del
 * producto.
 *
 *   php vendor\bin\drush.php scr scripts/el-pedido-de-un-clic-sale-de-la-marca.php
 *
 * La bandera «+ Order» de la ficha de un cliente crea de un clic un borrador
 * de pedido con una linea por cada talla de cada producto de ese cliente. Por
 * dentro son dos piezas: una ECA que la bandera dispara, y esta vista, que la ECA
 * consulta por su nombre pasandole el numero del cliente.
 *
 * El camino de la vista era:
 *
 *   talla -> su color -> su producto -> el CLIENTE del producto -> ¿es este?
 *
 * y pasa a ser:
 *
 *   talla -> su color -> su producto -> su MARCA -> ¿quien compra esta marca?
 *
 * El eslabon nuevo es una relacion inversa: desde el termino de la marca hacia
 * los contactos que la tienen apuntada en su lista. Views la ofrece sola en cuanto
 * existe el campo, `reverse__tec_crm__field_tec_brands`, y esa es la pieza que
 * hacia falta comprobar antes de prometer nada.
 *
 * Lo que se gana no es solo quitar un campo: dos clientes que compren la misma
 * marca reciben ahora los dos su pedido de un clic, y antes solo lo recibia el que
 * estuviera escrito en el producto.
 *
 * La ECA no se toca: sigue llamando a la misma vista con el mismo numero. Cambia
 * por donde busca la vista, no quien pregunta.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir.
 */

const VISTA = 'views.view.tec_order_eca_create_draft_sales';
const PANTALLA = 'default';
const NUEVA = 'reverse__tec_crm__field_tec_brands';
const VIEJA = 'field_tec_customer';

$config = \Drupal::configFactory()->getEditable(VISTA);
if ($config->isNew()) {
  print "\n  No esta la vista del pedido de un clic. No sigo.\n\n";
  return;
}

$pantallas = $config->get('display');
$opciones = &$pantallas[PANTALLA]['display_options'];

$puestas = 0;

print "\n";
print "=====================================================================\n";
print "  El pedido de un clic sale de la marca\n";
print "=====================================================================\n\n";

print "El eslabon nuevo\n";
print str_repeat('-', 70) . "\n";

// Que exista de verdad, y no darlo por hecho: si el campo de marcas del cliente
// se retirara, esta relacion desapareceria y la vista se quedaria sin camino.
$ofrecidas = \Drupal::service('views.views_data')->get('taxonomy_term_field_data');
if (!isset($ofrecidas[NUEVA])) {
  print "  Views no ofrece " . NUEVA . ". Sin ese eslabon no hay camino nuevo.\n\n";
  return;
}
printf("  %-58s ofrecida\n", 'la relacion inversa desde la marca al cliente');

if (isset($opciones['relationships'][NUEVA])) {
  printf("  %-58s ya estaba\n", 'puesta en la pantalla que consulta la ECA');
}
else {
  // Cuelga de la relacion que ya llevaba a la marca del producto, asi que tiene
  // que ir detras de ella: Views monta las uniones en el orden de la lista.
  $opciones['relationships'][NUEVA] = [
    'id' => NUEVA,
    'table' => 'taxonomy_term_field_data',
    'field' => NUEVA,
    'relationship' => 'field_tec_brand',
    'group_type' => 'group',
    'admin_label' => 'Customers that buy the brand',
    'entity_type' => 'taxonomy_term',
    'plugin_id' => 'entity_reverse',
    'required' => FALSE,
  ];
  printf("  %-58s puesta\n", 'puesta en la pantalla que consulta la ECA');
  $puestas++;
}

print "\nPor donde busca\n";
print str_repeat('-', 70) . "\n";

$argumento = &$opciones['arguments']['id'];
if (($argumento['relationship'] ?? '') === NUEVA) {
  printf("  %-58s ya estaba\n", 'el numero del cliente se busca por la marca');
}
elseif (($argumento['relationship'] ?? '') === VIEJA) {
  $argumento['relationship'] = NUEVA;
  printf("  %-58s cambiado\n", 'el numero del cliente se busca por la marca');
  $puestas++;
}
else {
  printf("  %-58s NO ES LO ESPERADO: %s\n", 'el argumento', $argumento['relationship'] ?? '(ninguna)');
  print "\n  No sigo: alguien ha cambiado esto por otro camino.\n\n";
  return;
}

print "\nEl eslabon viejo\n";
print str_repeat('-', 70) . "\n";

// Nadie mas lo usaba: era solo el argumento. Las otras pantallas lo heredaban sin
// mirarlo.
$loUsaAlguien = FALSE;
foreach (['fields', 'filters', 'sorts', 'arguments', 'relationships'] as $seccion) {
  foreach ($pantallas as $cual => $pantalla) {
    foreach ($pantalla['display_options'][$seccion] ?? [] as $id => $definicion) {
      if (is_array($definicion) && ($definicion['relationship'] ?? '') === VIEJA) {
        $loUsaAlguien = TRUE;
        printf("  %-58s %s / %s / %s\n", 'LO SIGUE USANDO', $cual, $seccion, $id);
      }
    }
  }
}

if ($loUsaAlguien) {
  print "  Lo dejo puesto, que alguien lo usa.\n";
}
elseif (isset($opciones['relationships'][VIEJA])) {
  unset($opciones['relationships'][VIEJA]);
  printf("  %-58s fuera\n", 'el salto al cliente del producto, que ya no hace falta');
  $puestas++;
}
else {
  printf("  %-58s ya no estaba\n", 'el salto al cliente del producto');
}

$config->set('display', $pantallas)->save();

print "\n=====================================================================\n";
printf("  %d cosas puestas. Queda exportar la configuracion.\n", $puestas);
print "=====================================================================\n\n";
