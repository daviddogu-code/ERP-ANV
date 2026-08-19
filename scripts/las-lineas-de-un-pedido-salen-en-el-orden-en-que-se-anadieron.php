<?php

/**
 * @file
 * Las lineas de un pedido se ordenan por su numero, no por el peso de un producto
 * arrastrado en otra pantalla.
 *
 *   php vendor\bin\drush.php scr scripts/las-lineas-de-un-pedido-salen-en-el-orden-en-que-se-anadieron.php
 *
 * Cuatro pantallas de esta vista -- la pestana Line items de un pedido, el bloque
 * editable, y las dos de /o/draft -- ordenaban por una columna de la tabla de
 * pesos de `draggableviews`, la que guarda el orden que el dueno arrastra en la
 * pantalla de productos de un cliente. La consulta que salia de ahi era esta:
 *
 *   LEFT JOIN draggableviews_structure
 *     ON tec_line_item_field_data.id = draggableviews_structure.entity_id
 *    AND view_name = 'tec_products' AND view_display = 'page_2'
 *   ORDER BY COALESCE(weight, -9223372036854775808)
 *
 * El numero de una **linea** contra el numero de un **producto**. El modulo de
 * arrastrar no sabe ordenar por una entidad que no sea la de la fila: engancha
 * siempre por la clave de la tabla base, y aqui la tabla base son las lineas. La
 * relacion `field_tec_product` que la ordenacion tenia puesta no la mira nadie.
 *
 * Y lo peor de un error asi es que casi siempre acierta. Las lineas nuevas van
 * por el 4600 y en la tabla de pesos no hay ningun producto con ese numero, asi
 * que ninguna fila encuentra peso, todas empatan a menos infinito y MySQL las
 * devuelve como las lee, que es por su numero -- el orden bueno, por casualidad.
 * Donde se ve el fallo es en los pedidos viejos: la linea 195 de un pedido de
 * hace meses **si** encuentra un producto 195 en la tabla, se le pega su peso, y
 * esa linea salta al final de su propio pedido sin motivo. Un orden que solo
 * falla con los datos antiguos es un orden que nadie mira hasta que ya no se
 * acuerda de por que.
 *
 * Ahora se ordena por el numero de la linea, que es el orden en que se anadieron
 * al pedido -- el mismo que el de la lista del pedido, porque cada linea se
 * crea y se engancha a la vez. Es lo que ya se veia; la diferencia es que ahora
 * lo dice la consulta en vez de pasar.
 *
 * **Esto es la mitad del arreglo de los documentos de produccion.** La otra
 * mitad esta en el bloque, que ordenaba los productos por su nombre y ahora los
 * saca por la linea en que aparecen. Las dos mitades tienen que usar la misma
 * regla o volveran a discrepar: la regla es el numero de la linea, aqui y alli.
 *
 * No se ordena por la posicion en la lista del pedido, que seria lo mas fiel,
 * porque para llegar a esa columna Views tendria que enganchar la tabla del campo
 * por segunda vez y una linea empezaria a salir dos veces. Mientras las lineas se
 * anadan al final -- que es lo que hacen -- los dos ordenes son el mismo.
 *
 * Se puede lanzar dos veces: deja las pantallas igual.
 */

const VISTA = 'tec_order_sales_order_line_items';

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  print "\n  No existe la vista " . VISTA . ".\n\n";
  return;
}

// El nombre y el plugin de la ordenacion se leen de los datos de Views y no se
// escriben a mano: si el tipo de entidad cambiara de sitio su clave, esto se
// enteraria en vez de dejar una ordenacion que no engancha con nada.
$datos = \Drupal::service('views.views_data')->get('tec_line_item_field_data');
$plugin = $datos['id']['sort']['id'] ?? NULL;
if (!$plugin) {
  print "\n  La tabla de lineas no ofrece ordenar por su numero. No se toca nada.\n\n";
  return;
}

$porNumero = [
  'id' => 'id',
  'table' => 'tec_line_item_field_data',
  'field' => 'id',
  'relationship' => 'none',
  'group_type' => 'group',
  'admin_label' => '',
  'entity_type' => 'tec_line_item',
  'entity_field' => 'id',
  'plugin_id' => $plugin,
  'order' => 'ASC',
  'expose' => ['label' => '', 'field_identifier' => ''],
  'exposed' => FALSE,
];

print "\n";
print "=====================================================================\n";
print "  Las lineas salen en el orden en que se anadieron\n";
print "=====================================================================\n\n";
printf("  Ordenar por el numero de la linea: plugin %s\n\n", $plugin);

$pantallas = $vista->get('display');
$tocadas = 0;

foreach ($pantallas as $nombre => $pantalla) {
  $ordenes = $pantalla['display_options']['sorts'] ?? NULL;
  if ($ordenes === NULL) {
    // Hereda la de la pantalla maestra; no tiene ordenacion propia que tocar.
    continue;
  }

  $fantasmas = [];
  foreach ($ordenes as $clave => $orden) {
    if (($orden['table'] ?? '') === 'draggableviews_structure') {
      $fantasmas[] = $clave;
    }
  }

  if (!$fantasmas) {
    $tiene = isset($ordenes['id']) ? 'ya ordena por el numero de la linea' : 'ordena por ' . (implode(', ', array_keys($ordenes)) ?: 'nada');
    printf("  %-30s %s\n", $nombre, $tiene);
    continue;
  }

  foreach ($fantasmas as $clave) {
    unset($ordenes[$clave]);
  }
  $ordenes = ['id' => $porNumero] + $ordenes;
  $pantallas[$nombre]['display_options']['sorts'] = $ordenes;
  $tocadas++;
  printf("  %-30s fuera %s, entra el numero de la linea\n", $nombre, implode(' y ', $fantasmas));
}

if ($tocadas) {
  $vista->set('display', $pantallas);
  $vista->save();
}

// ---------------------------------------------------------------------------
// Lo que dice la consulta ahora.
// ---------------------------------------------------------------------------
print "\n";
print "Como queda la consulta de la pestana Line items\n";
print str_repeat('-', 70) . "\n";

$prueba = \Drupal\views\Views::getView(VISTA);
$prueba->setDisplay('block_1');
$prueba->setArguments([0]);
$prueba->build();
$sql = (string) $prueba->query->query();
$prueba->destroy();

printf("  %s\n", trim(substr($sql, strrpos($sql, 'ORDER BY') ?: 0)));
printf("  la tabla de pesos %s en la consulta\n",
  str_contains($sql, 'draggableviews_structure') ? 'SIGUE saliendo' : 'ya no sale');

printf("\n  %d pantallas cambiadas, %d dependen del modulo de arrastrar: %s\n\n",
  $tocadas,
  count(array_filter($vista->getDependencies()['module'] ?? [], fn($m) => $m === 'draggableviews')),
  implode(', ', $vista->getDependencies()['module'] ?? []));
