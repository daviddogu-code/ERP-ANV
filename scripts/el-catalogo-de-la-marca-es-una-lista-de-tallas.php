<?php

/**
 * @file
 * El catalogo de una marca pasa a ser una lista de tallas, con el precio de
 * venta editable ahi mismo y lo que cuesta fabricar cada una.
 *
 *   php vendor\bin\drush.php scr scripts/el-catalogo-de-la-marca-es-una-lista-de-tallas.php
 *
 * Hasta ahora la ficha de una marca ensenaba una fila por producto, y las
 * variaciones dentro de la celda, en un recuadro. Servia para saber que productos
 * hay y para nada mas: el precio de venta y el coste **no viven en el producto,
 * viven en la talla**, asi que una fila por producto no puede ensenar ni uno ni
 * otro sin inventarse una media.
 *
 * Ahora hay una fila por talla, como las lineas de un pedido, con siete
 * columnas: foto, producto, material, variacion, talla, precio de venta y coste
 * de materiales.
 *
 * Cinco decisiones que no son evidentes:
 *
 * - **Las relaciones son las de siempre, las de vuelta.** Se sube de la talla a
 *   su color y del color a su producto por la lista del padre
 *   (`field_tec_size_variations`, `field_tec_color_variations`) y no por los
 *   punteros que la talla tiene hacia arriba. La lista del padre es la que manda:
 *   es la que se ve en la ficha y la que se arrastra en la pantalla de ordenar.
 *   Los punteros de la talla son copias que mantiene una ECA. Y de paso es como
 *   listan las tallas las otras cuatro pantallas de esta misma vista.
 * - **El argumento se queda donde estaba**, el termino de la marca colgando de la
 *   relacion `field_tec_brand`, para que el campo de la ficha de la marca siga
 *   funcionando sin tocarlo. Esta pantalla se dibuja desde
 *   `field_tec_brand_catalogue`, un campo de vista puesto en la ficha del
 *   termino.
 * - **El orden de las tallas es el peso de su termino**, o sea el que el dueno
 *   arrastra en el vocabulario de tallas. Por nombre saldria L, M, S, XL, XS, que
 *   no es ningun orden.
 * - **El precio se edita con el mismo modulo que las lineas de un pedido**
 *   borrador, `views_entity_form_field`, y **sin ajax**, con el boton de guardar
 *   que pone Views. Con ajax encendido ese modulo hace otra cosa: guarda cada
 *   casilla al salir de ella y quita el boton. Suena mejor, pero para encontrar la
 *   fila que hay que guardar busca por el numero de la talla dentro de una lista
 *   numerada por posicion, asi que acierta solo cuando los dos numeros coinciden.
 *   Con precios no es sitio para eso. Las dos pantallas donde este ERP ya edita
 *   una tabla por dentro, /o/draft y /po/draft, lo hacen con el boton.
 * - **El mapa de columnas se escribe de cero.** El que habia nombraba diecinueve,
 *   entre ellas `field_tec_sales_price`, `draggableviews` y
 *   `simple_popup_views_field`, que no existen en esta pantalla: restos de haberla
 *   copiado de otra. Un mapa con columnas fantasma no rompe nada, y por eso llevaba
 *   ahi tanto tiempo.
 *
 * La foto, el nombre del producto y el color se copian de la pantalla maestra de
 * la vista, con su estilo de imagen y su enlace de editar, para que se dibujen
 * igual que en el resto de los listados del ERP.
 *
 * Se puede lanzar dos veces: deja la pantalla igual.
 */

const VISTA = 'tec_products';
const PANTALLA = 'brand_catalogue';

// Las relaciones, tal como se llaman en la pantalla maestra.
const REL_COLOR = 'reverse__tec_product__field_tec_size_variations';
const REL_PRODUCTO = 'reverse__tec_product__field_tec_color_variations';
const REL_MARCA = 'field_tec_brand';
const REL_TALLA = 'field_tec_size';

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  print "\n  No existe la vista " . VISTA . ".\n\n";
  return;
}

$pantallas = $vista->get('display');
if (!isset($pantallas[PANTALLA])) {
  print "\n  No existe la pantalla " . PANTALLA . ".\n\n";
  return;
}

$maestra = $pantallas['default']['display_options'] ?? [];
$o = &$pantallas[PANTALLA]['display_options'];

/**
 * Un campo copiado de la pantalla maestra, para no reinventar como se dibuja.
 */
$copiar = static function (string $cual) use ($maestra): array {
  $campo = $maestra['fields'][$cual] ?? NULL;
  if (!$campo) {
    throw new \RuntimeException('La pantalla maestra ya no tiene el campo ' . $cual);
  }
  return $campo;
};

// Ver la cabecera del fichero: con ajax, el modulo que hace editable el precio
// guarda al salir de la casilla y quita el boton, y busca la fila por un numero
// que no siempre es el que usa.
$o['use_ajax'] = FALSE;

// ---------------------------------------------------------------------------
// Las relaciones: talla -> color -> producto -> marca, y la talla a su termino
// para poder ordenar por el peso.
// ---------------------------------------------------------------------------
$o['relationships'] = [
  REL_COLOR => $maestra['relationships'][REL_COLOR],
  REL_PRODUCTO => $maestra['relationships'][REL_PRODUCTO],
  REL_MARCA => $maestra['relationships'][REL_MARCA],
  REL_TALLA => [
    'id' => REL_TALLA,
    'table' => 'tec_product__field_tec_size',
    'field' => 'field_tec_size',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => 'Size term',
    'plugin_id' => 'standard',
    // No obligatoria, y esto se probo primero al contrario. Una talla sin termino
    // de talla es una talla a medio crear, y con la relacion obligatoria
    // desaparecia del catalogo sin decir nada: la fila que hay que arreglar es
    // justo la que no se veia. Sale, con la casilla de talla vacia, y se ordena
    // con las demas de su color.
    'required' => FALSE,
  ],
];

// ---------------------------------------------------------------------------
// Las filas son tallas.
// ---------------------------------------------------------------------------
$o['filters'] = [
  'type' => [
    'id' => 'type',
    'table' => 'tec_product_field_data',
    'field' => 'type',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'tec_product',
    'entity_field' => 'type',
    'plugin_id' => 'bundle',
    'operator' => 'in',
    'value' => ['tec_size_variation' => 'tec_size_variation'],
    'group' => 1,
    'exposed' => FALSE,
  ],
];
$o['filter_groups'] = [
  'operator' => 'AND',
  'groups' => [1 => 'AND'],
];

// ---------------------------------------------------------------------------
// El orden: producto, variacion y talla. La talla por el peso de su termino.
// ---------------------------------------------------------------------------
$o['sorts'] = [
  'field_product_name_value' => [
    'id' => 'field_product_name_value',
    'table' => 'tec_product__field_product_name',
    'field' => 'field_product_name_value',
    'relationship' => REL_PRODUCTO,
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'expose' => ['label' => ''],
    'exposed' => FALSE,
  ],
  'title' => [
    'id' => 'title',
    'table' => 'tec_product_field_data',
    'field' => 'title',
    'relationship' => REL_COLOR,
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'tec_product',
    'entity_field' => 'title',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'expose' => ['label' => ''],
    'exposed' => FALSE,
  ],
  'weight' => [
    'id' => 'weight',
    'table' => 'taxonomy_term_field_data',
    'field' => 'weight',
    'relationship' => REL_TALLA,
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'taxonomy_term',
    'entity_field' => 'weight',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'expose' => ['label' => ''],
    'exposed' => FALSE,
  ],
];

// ---------------------------------------------------------------------------
// Las siete columnas.
// ---------------------------------------------------------------------------
$campos = [];

// La foto es del color: una talla no tiene foto propia.
$campos['field_tec_images'] = $copiar('field_tec_images');

// El enlace de editar, escondido, porque el nombre del producto lo lleva dentro.
$campos['edit_tec_product'] = $copiar('edit_tec_product');
$campos['field_product_name_1'] = $copiar('field_product_name_1');

$campos['field_tec_product_material'] = [
  'id' => 'field_tec_product_material',
  'table' => 'tec_product__field_tec_product_material',
  'field' => 'field_tec_product_material',
  'relationship' => REL_PRODUCTO,
  'group_type' => 'group',
  'admin_label' => '',
  'plugin_id' => 'field',
  'label' => 'Material',
  'exclude' => FALSE,
  'element_label_colon' => TRUE,
  'type' => 'list_default',
  'settings' => [],
];

$campos['field_tec_colors'] = $copiar('field_tec_colors');
$campos['field_tec_colors']['label'] = 'Variation';

$campos['field_tec_size'] = $copiar('field_tec_size');
$campos['field_tec_size']['label'] = 'Size';
$campos['field_tec_size']['exclude'] = FALSE;

// El precio, editable. La casilla y el boton de guardar los pone Views; aqui
// solo se dice que campo es y con que aspecto.
$campos['form_field_field_tec_price'] = [
  'id' => 'form_field_field_tec_price',
  'table' => 'tec_product_field_data',
  'field' => 'form_field_field_tec_price',
  'relationship' => 'none',
  'group_type' => 'group',
  'admin_label' => '',
  'entity_type' => 'tec_product',
  'plugin_id' => 'entity_form_field',
  'label' => 'Sales price',
  'exclude' => FALSE,
  'element_label_colon' => TRUE,
  'plugin' => [
    'hide_title' => 1,
    'hide_description' => 1,
    'type' => 'number',
    'settings' => ['placeholder' => ''],
    'third_party_settings' => [],
  ],
];

// El coste no es un campo guardado: lo suma MaterialCost, el mismo sitio que
// cierra la tabla de BoM en la ficha de la talla.
$campos['tec_size_material_cost'] = [
  'id' => 'tec_size_material_cost',
  'table' => 'tec_product_field_data',
  'field' => 'tec_size_material_cost',
  'relationship' => 'none',
  'group_type' => 'group',
  'admin_label' => '',
  'plugin_id' => 'tec_size_material_cost',
  'label' => 'Material cost',
  'exclude' => FALSE,
  'element_label_colon' => TRUE,
];

$o['fields'] = $campos;

// ---------------------------------------------------------------------------
// La tabla. El mapa de columnas, de cero y solo con las que existen.
// ---------------------------------------------------------------------------
$alineacion = [
  'field_tec_images' => 'views-align-center',
  'field_product_name_1' => 'views-align-left',
  'field_tec_product_material' => '',
  'field_tec_colors' => 'views-align-center',
  'field_tec_size' => 'views-align-center',
  'form_field_field_tec_price' => 'views-align-right',
  'tec_size_material_cost' => 'views-align-right',
];

$columnas = [];
$fichas = [];
foreach ($alineacion as $cual => $alineado) {
  $columnas[$cual] = $cual;
  $fichas[$cual] = [
    'sortable' => FALSE,
    'default_sort_order' => 'asc',
    'align' => $alineado,
    'separator' => '',
    'empty_column' => FALSE,
    'responsive' => '',
  ];
}

$o['style'] = [
  'type' => 'table',
  'options' => [
    'grouping' => [],
    'row_class' => '',
    'default_row_class' => TRUE,
    'columns' => $columnas,
    'default' => '-1',
    'info' => $fichas,
    'override' => TRUE,
    'sticky' => FALSE,
    'summary' => '',
    'empty_table' => FALSE,
    'caption' => '',
    'description' => '',
    'class' => '',
  ],
];
$o['row'] = ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]];

unset($o);

$vista->set('display', $pantallas);
$vista->save();

// ---------------------------------------------------------------------------
// Lo que ha quedado.
// ---------------------------------------------------------------------------
$puestos = array_keys($pantallas[PANTALLA]['display_options']['fields']);
$visibles = [];
foreach ($pantallas[PANTALLA]['display_options']['fields'] as $id => $campo) {
  if (empty($campo['exclude'])) {
    $visibles[] = $campo['label'] ?? $id;
  }
}

print "\n";
print "=====================================================================\n";
print "  El catalogo de la marca es una lista de tallas\n";
print "=====================================================================\n\n";
printf("  Filas: una por talla\n");
printf("  Columnas: %s\n", implode(' | ', $visibles));
printf("  Campos puestos: %s\n", implode(', ', $puestos));
printf("  Orden: producto, variacion, y talla por el peso de su termino\n");
printf("  Argumento: el termino de la marca, por %s\n", REL_MARCA);
print "\n  Queda exportar la configuracion.\n\n";
