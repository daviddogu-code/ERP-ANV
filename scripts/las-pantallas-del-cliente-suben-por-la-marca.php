<?php

/**
 * @file
 * Las pantallas de productos del cliente suben por la marca, y se les quita la
 * columna de cliente a todas.
 *
 *   php vendor\bin\drush.php scr scripts/las-pantallas-del-cliente-suben-por-la-marca.php
 *
 * Tres pantallas de `tec_products` RECIBEN el numero de un cliente y filtran por el
 * saltando al campo de cliente del producto, que se retira:
 *
 *   block_4   la pestana Products de la ficha del cliente
 *   page_2    /tec_crm/N/reorder, la de ordenar productos a mano
 *   block_2   un duplicado de block_4 que hoy no cuelga de ningun sitio
 *
 * De esas tres **`page_2` ya no existe**: se retiro la noche del 19 de agosto de
 * 2026, cuando el orden de los productos paso a ser de la marca. Sigue nombrada
 * aqui porque este guion la trata como lo que es -- una pantalla que puede no estar
 * -- y porque leerlo sin ella no explicaria por que el argumento de las otras dos
 * quedo como quedo.
 *
 * Las tres pasan a subir por la marca, igual que ya hizo la consulta del pedido de
 * un clic: producto -> su marca -> quien compra esa marca.
 *
 * LO QUE NO SE TOCA, Y ES LO IMPORTANTE. El argumento sigue siendo el numero del
 * CLIENTE. Podria parecer natural que la pantalla de ordenar pasara a recibir la
 * marca, pero seria un error: el modulo draggableviews guarda el orden arrastrado en
 * su propia tabla con una clave que incluye los argumentos de la vista, y hay orden
 * guardado de ocho clientes. Cambiarle el argumento dejaria esas filas huerfanas y el
 * orden hecho a mano se perderia sin que nada avisara. Ademas el orden por cliente es
 * mas expresivo que por marca: dos clientes que compren la misma marca pueden querer
 * sus productos en distinto orden, y por marca solo cabria uno de los dos.
 *
 * Y ADEMAS, en todas las pantallas de la vista, se quita la columna de cliente. Hay
 * que quitarla antes de borrar el campo y no despues: Views arregla sus pantallas
 * solo cuando un campo desaparece, pero lo hace en silencio y por su cuenta, y en
 * las tres de arriba dejaria el argumento apuntando a una relacion que ya no existe.
 *
 * Los botones «+ Product» escritos a mano tambien se van, por dos razones. Rellenaban
 * el cliente del producto nuevo, que es el campo que se retira; y con el modelo nuevo
 * un producto pertenece a UNA marca, asi que un boton en la pantalla del cliente no
 * puede saber a cual de sus marcas va. Los productos se crean desde la marca, que ya
 * trae su boton con la marca rellenada. Uno de esos enlaces llevaba ademas dos «?» en
 * la misma direccion, asi que el destino se colaba dentro del valor del cliente y no
 * volvia a ninguna parte.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir.
 */

const VISTA = 'tec_products';
const CAMPO = 'field_tec_customer';
const TABLA = 'tec_product__field_tec_customer';
const MARCAS = 'tec_product__field_tec_brand';
const NUEVA = 'reverse__tec_crm__field_tec_brands';
const RECIBEN = ['block_4', 'page_2', 'block_2'];

$almacen = \Drupal::entityTypeManager()->getStorage('view');
$vista = $almacen->load(VISTA);
if (!$vista) {
  print "\n  No esta la vista de productos. No sigo.\n\n";
  return;
}

$pantallas = $vista->get('display');
$puestas = 0;

print "\n";
print "=====================================================================\n";
print "  Las pantallas del cliente suben por la marca\n";
print "=====================================================================\n\n";

$ofrecidas = \Drupal::service('views.views_data')->get('taxonomy_term_field_data');
if (!isset($ofrecidas[NUEVA])) {
  print "  Views no ofrece " . NUEVA . ". Sin ese eslabon no hay camino nuevo.\n\n";
  return;
}

print "Las tres que reciben el cliente\n";
print str_repeat('-', 70) . "\n";

foreach (RECIBEN as $cual) {
  if (!isset($pantallas[$cual])) {
    printf("  %-14s no esta\n", $cual);
    continue;
  }
  $opciones = &$pantallas[$cual]['display_options'];

  // El salto a la marca: si la pantalla ya tiene uno, se aprovecha, que para eso
  // esta. block_2 lo llama `field_tec_brand_1`.
  $aLaMarca = '';
  foreach ($opciones['relationships'] ?? [] as $id => $relacion) {
    if (($relacion['table'] ?? '') === MARCAS && ($relacion['relationship'] ?? 'none') === 'none') {
      $aLaMarca = (string) $id;
      break;
    }
  }
  if ($aLaMarca === '') {
    $aLaMarca = 'field_tec_brand';
    $opciones['relationships'][$aLaMarca] = [
      'id' => $aLaMarca,
      'table' => MARCAS,
      'field' => 'field_tec_brand',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => 'Brand',
      'plugin_id' => 'standard',
      'required' => FALSE,
    ];
    $puestas++;
  }

  // Y de la marca a quien la compra. Cuelga del salto anterior, asi que va detras:
  // Views monta las uniones en el orden de la lista.
  if (!isset($opciones['relationships'][NUEVA])) {
    $opciones['relationships'][NUEVA] = [
      'id' => NUEVA,
      'table' => 'taxonomy_term_field_data',
      'field' => NUEVA,
      'relationship' => $aLaMarca,
      'group_type' => 'group',
      'admin_label' => 'Customers that buy the brand',
      'entity_type' => 'taxonomy_term',
      'plugin_id' => 'entity_reverse',
      'required' => FALSE,
    ];
    $puestas++;
  }

  $antes = $opciones['arguments']['id']['relationship'] ?? '(ninguno)';
  if ($antes === CAMPO) {
    $opciones['arguments']['id']['relationship'] = NUEVA;
    $puestas++;
  }

  printf("  %-14s %-30s %s\n", $cual,
    mb_substr((string) ($pantallas[$cual]['display_title'] ?? ''), 0, 29),
    $antes === CAMPO ? 'ahora busca por la marca' : ($antes === NUEVA ? 'ya estaba' : 'OJO, buscaba por ' . $antes));
}

print "\nLa columna de cliente, en todas\n";
print str_repeat('-', 70) . "\n";

foreach ($pantallas as $cual => $pantalla) {
  $opciones = &$pantallas[$cual]['display_options'];
  $fuera = [];

  // Lo que sale del campo del producto: primero se apunta que relaciones son suyas,
  // porque lo que cuelga de ellas se va con ellas.
  $suyas = [];
  foreach ($opciones['relationships'] ?? [] as $id => $relacion) {
    if (is_array($relacion) && ($relacion['table'] ?? '') === TABLA) {
      $suyas[$id] = $id;
    }
  }

  foreach (['fields', 'filters', 'sorts', 'relationships'] as $seccion) {
    foreach ($opciones[$seccion] ?? [] as $id => $trozo) {
      if (!is_array($trozo)) {
        continue;
      }
      $esSuyo = ($trozo['table'] ?? '') === TABLA || isset($suyas[$trozo['relationship'] ?? '']);
      // El argumento ya esta recableado y no cuelga de nada suyo, pero por si acaso:
      // no se toca ningun argumento aqui.
      if ($esSuyo) {
        unset($opciones[$seccion][$id]);
        $fuera[] = $seccion . ' ' . $id;
      }
    }
  }

  // Y su columna en la tabla, con los ajustes que la acompanan.
  foreach (['columns', 'info'] as $donde) {
    $lista = $opciones['style']['options'][$donde] ?? NULL;
    if (!is_array($lista)) {
      continue;
    }
    foreach ($lista as $columna => $valor) {
      if (str_starts_with((string) $columna, CAMPO) || (is_string($valor) && str_starts_with($valor, CAMPO))) {
        unset($opciones['style']['options'][$donde][$columna]);
        $fuera[] = 'columna ' . $columna;
      }
    }
  }

  if ($fuera !== []) {
    $puestas += count($fuera);
    printf("  %-18s %s\n", $cual, implode(', ', array_unique($fuera)));
  }
}

print "\nLos botones escritos a mano\n";
print str_repeat('-', 70) . "\n";

// Sin productos no se ofrece un boton que no sabria a que marca mandarlos: se dice
// donde se crean y se abre la puerta.
$sinProductos = '<div class="text-align-center"><h4>No products yet</h4></div>' . "\r\n"
  . '<div class="text-align-center">A product belongs to a brand. Add it from the brand page.<br>' . "\r\n"
  . '<strong><a href="/b">Brands</a></strong></div>';

// Solo el mensaje de «no hay productos». Aqui se reescribia tambien la cabecera, con
// un boton «Organize products» que llevaba a /tec_crm/N/reorder; esa pantalla se
// retiro la noche del 19 de agosto de 2026 -- el orden de los productos es de la
// marca y no del cliente -- y las cabeceras que la citaban se borraron. Escribirlo
// otra vez seria devolver un boton a una direccion que ya no responde. Donde se
// ordena ahora se llega desde el titulo de cada grupo de marca de esta misma
// pestaña, y eso lo escribe scripts/la-pestana-agrupa-por-marca.php.
foreach (RECIBEN as $cual) {
  if (!isset($pantallas[$cual])) {
    continue;
  }
  $opciones = &$pantallas[$cual]['display_options'];
  foreach ($opciones['empty'] ?? [] as $id => $trozo) {
    if (!is_array($trozo) || ($trozo['plugin_id'] ?? '') !== 'text_custom') {
      continue;
    }
    $dice = (string) ($trozo['content'] ?? '');
    if (!str_contains($dice, 'tec_product/add') || $dice === $sinProductos) {
      continue;
    }
    $opciones['empty'][$id]['content'] = $sinProductos;
    $puestas++;
    printf("  %-18s empty/%s reescrito\n", $cual, $id);
  }
}

$vista->set('display', $pantallas);
$vista->save();

print "\n=====================================================================\n";
printf("  %d cosas puestas. Queda exportar la configuracion.\n", $puestas);
print "=====================================================================\n\n";
