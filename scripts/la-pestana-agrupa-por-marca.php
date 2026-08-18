<?php

/**
 * @file
 * La pestana Products del cliente sale agrupada por marca, en su orden de marcas y
 * con un boton de crear producto por marca.
 *
 *   php vendor\bin\drush.php scr scripts/la-pestana-agrupa-por-marca.php
 *
 * Un cliente que compra tres marcas veia una lista larga y plana en la que las
 * marcas se mezclaban. Ahora se parte en bloques, uno por marca.
 *
 * EL ORDEN DE LOS BLOQUES es lo suyo, no el del alfabeto: la lista de marcas de la
 * ficha del cliente se arrastra a mano y los bloques salen en ese orden. Eso NO se
 * hace aqui, porque no se puede hacer desde la vista. Se probo: ordenar por la
 * posicion de la marca en la lista obliga a unir la lista una segunda vez, esa union
 * no va atada a la marca de la fila, y cada producto volvia una vez por cada marca
 * que compra el cliente. Tres productos, seis filas. Asi que el orden de los bloques
 * lo pone el modulo tec_crm_ux al terminar la consulta, donde no hay uniones que
 * multipliquen, y lo explica alli.
 *
 * EL BOTON POR BLOQUE es la otra mitad. Antes habia un «+ Product» arriba que
 * rellenaba el cliente; con el modelo nuevo un producto pertenece a una marca, y un
 * boton en la pantalla del cliente no puede saber a cual. Puesto DENTRO del titulo de
 * cada bloque si lo sabe: es la marca de ese bloque. Se consigue agrupando por un
 * campo de texto propio en el que se escribe el titulo del bloque -el nombre de la
 * marca, que lleva a su ficha, y el boton-, en lugar de agrupar por el nombre pelado.
 *
 * Y se retira la columna de marca de la tabla, que a partir de ahora repetiria en
 * cada fila lo que ya dice el titulo del bloque.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir.
 */

const VISTA = 'tec_products';
const PANTALLA = 'block_4';
const MARCA = 'field_tec_brand';
const NUMERO = 'field_tec_brand_1';
const TITULO = 'por_marca';
const POSICION = 'delta';
const ALCLIENTE = 'reverse__tec_crm__field_tec_brands';

$almacen = \Drupal::entityTypeManager()->getStorage('view');
$vista = $almacen->load(VISTA);
$pantallas = $vista->get('display');
$opciones = &$pantallas[PANTALLA]['display_options'];

print "\n";
print "=====================================================================\n";
print "  La pestana Products del cliente, agrupada por marca\n";
print "=====================================================================\n\n";

if (($opciones['relationships'][ALCLIENTE] ?? NULL) === NULL) {
  print "  Esta pantalla todavia no sube por la marca. Primero lo otro.\n\n";
  return;
}

$puestas = [];

// El nombre de la marca hace falta como pieza del titulo, no como columna.
if (isset($opciones['fields'][MARCA]) && empty($opciones['fields'][MARCA]['exclude'])) {
  $opciones['fields'][MARCA]['exclude'] = TRUE;
  $opciones['fields'][MARCA]['label'] = '';
  $puestas[] = 'la columna de marca se retira de la tabla';
}

// El numero de la marca, para poder escribir sus dos direcciones.
if (!isset($opciones['fields'][NUMERO])) {
  $opciones['fields'][NUMERO] = [
    'id' => NUMERO,
    'table' => 'tec_product__' . MARCA,
    'field' => MARCA,
    'relationship' => 'none',
    'label' => '',
    'exclude' => TRUE,
    'type' => 'entity_reference_entity_id',
    'settings' => [],
    'plugin_id' => 'field',
    'entity_type' => 'tec_product',
    'entity_field' => MARCA,
  ];
  $puestas[] = 'el numero de la marca, escondido, para las direcciones';
}

// El titulo del bloque. Va el ultimo de la lista de campos porque un campo de texto
// solo puede nombrar a los que vienen antes que el.
$escrito = '<a href="/taxonomy/term/{{ ' . NUMERO . ' }}">{{ ' . MARCA . ' }}</a>'
  . ' <span class="btn-default"><strong><a href="/admin/content/tec_product/add/tec_product'
  . '?brand_id={{ ' . NUMERO . ' }}&destination=/tec_crm/{{ raw_arguments.id }}">+ Product</a></strong></span>';

if (($opciones['fields'][TITULO]['alter']['text'] ?? '') !== $escrito) {
  unset($opciones['fields'][TITULO]);
  $opciones['fields'][TITULO] = [
    'id' => TITULO,
    'table' => 'views',
    'field' => 'nothing',
    'relationship' => 'none',
    'label' => '',
    'exclude' => TRUE,
    'alter' => [
      'alter_text' => TRUE,
      'text' => $escrito,
      'make_link' => FALSE,
      'trim' => FALSE,
    ],
    'element_label_colon' => FALSE,
    'plugin_id' => 'custom',
  ];
  $puestas[] = 'el titulo del bloque, con el nombre de la marca y su boton';
}

// Agrupar por ese titulo, sin quitarle el HTML: si se le quitara, el boton se
// quedaria en texto suelto.
$agrupado = [['field' => TITULO, 'rendered' => TRUE, 'rendered_strip' => FALSE]];
if (($opciones['style']['options']['grouping'] ?? []) !== $agrupado) {
  $opciones['style']['options']['grouping'] = $agrupado;
  $puestas[] = 'la tabla se parte en un bloque por marca';
}

// El orden de la vista se queda como estaba, que es el que arrastro el usuario para
// los productos. El de los bloques lo pone el modulo despues. Si un intento anterior
// dejo aqui el orden por posicion, se retira: es el que multiplicaba las filas.
if (isset($opciones['sorts'][POSICION])) {
  unset($opciones['sorts'][POSICION]);
  $puestas[] = 'fuera el orden por posicion, que duplicaba cada producto';
}

$vista->set('display', $pantallas);
$vista->save();

foreach ($puestas as $puesta) {
  print '  ' . $puesta . "\n";
}
if ($puestas === []) {
  print "  Ya estaba todo puesto.\n";
}

print "\n=====================================================================\n";
printf("  %d cosas puestas. Queda exportar la configuracion.\n", count($puestas));
print "=====================================================================\n\n";
