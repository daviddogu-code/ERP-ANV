<?php

/**
 * @file
 * Cada marca tiene su catalogo, y la pantalla de marcas su boton de editar.
 *
 *   php vendor\bin\drush.php scr scripts/dar-catalogo-a-la-marca.php
 *
 * En /b, la pantalla de marcas, la baldosa entera -logo y nombre- era un enlace
 * al formulario de edicion, y no habia ninguna manera de ver lo que hay dentro
 * de una marca. Se pedia lo contrario: que la baldosa abra el catalogo y que
 * editar sea un boton.
 *
 * Y el catalogo no es una idea nueva. Existio una vista llamada
 * `tec_brands_gui_products_by_brand`, y su nombre sigue fosilizado en la lista
 * de vistas permitidas de siete campos de tipo viewfield, junto con su gemela
 * `tec_crm_contact_customer_products`. Las dos se perdieron antes de agosto de
 * 2026: el diario del dia 14 cuenta que marcas y patrones conservaban tres
 * vistas, y son las tres que hay hoy. O sea que esto no se inventa, se repone.
 *
 * La pantalla no se escribe de cero: se clona `block_4` de la vista de
 * productos, que es la pestaña Products de la ficha del contacto, y solo se le
 * cambia por donde entra. Asi el catalogo de una marca sale con las mismas
 * columnas que ya se conocen, y el dia que se toque una se tocan las dos.
 *
 * Tres detalles del clonado que no son evidentes:
 *
 * - La relacion con el cliente se queda puesta ademas de la nueva con la marca.
 *   Alguna columna del pattern puede colgar de ella, y como es una union por la
 *   izquierda no estorba a los productos que no tengan cliente.
 * - El pattern lleva tres enlaces escritos a mano con `raw_arguments.id` dando por
 *   hecho que el argumento es un cliente. Aqui el argumento es un termino, asi
 *   que los tres pasan a `raw_arguments.tid` y apuntan a la ficha de la marca.
 * - El boton de crear producto del pattern lleva `?target_id=`, y eso NO es
 *   decoracion: el campo de cliente del producto tiene el modulo epp puesto con
 *   `[current-page:query:target_id]`, o sea que rellena el cliente con lo que
 *   venga en ese parametro. Pasarle el numero de una marca escribiria una marca
 *   en la casilla del cliente. Por eso el boton de aqui va sin el.
 *
 * El orden del catalogo queda por nombre, y **eso ya no es lo que hay**: era el
 * paso siguiente del modelo y se dio el 19 de agosto de 2026. El pattern ordenaba
 * por el peso de «Organize products», que estaba guardado por cliente y en una
 * pantalla nueva no ordenaba nada; hoy el orden es de la marca, un campo del
 * producto, y lo escribe la receta que convierte esta pantalla en una lista de
 * tallas -- `el-catalogo-de-la-marca-es-una-lista-de-tallas.php`, que se lanza
 * despues de esta y rehace sus ordenaciones enteras. Si se lanza esta sola, el
 * catalogo sale por nombre hasta que se lance la otra.
 *
 * El destination del + Product a la ficha de la marca **tampoco es lo que hay**.
 * El 19 de agosto de 2026 se quito: el catalogo es una lista de tallas, y un
 * producto recien creado no tiene ninguna, asi que Save te dejaba en una lista
 * donde no estabas. Esta receta ya escribe el boton sin destination y con
 * Organize al lado; `el-producto-nuevo-no-se-pierde-en-la-marca.php` es la que
 * parchea una pantalla que ya existia.
 *
 * Se puede lanzar dos veces: comprueba antes de crear y vuelve a escribir lo
 * mismo si ya estaba.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

const PANTALLA = 'brand_catalogue';
const CAMPO = 'field_tec_brand_catalogue';

$gestor = \Drupal::entityTypeManager();
$hechos = [];

/**
 * Cuenta lo que se ha hecho y lo dice.
 */
function anotar(array &$hechos, string $que, string $como): void {
  $hechos[] = $que;
  printf("  %-58s %s\n", $que, $como);
}

print "\n";
print "=====================================================================\n";
print "  Cada marca con su catalogo\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. La pantalla del catalogo, clonada de la pestaña Products del contacto.
// ---------------------------------------------------------------------------
print "La pantalla\n";
print str_repeat('-', 70) . "\n";

$productos = $gestor->getStorage('view')->load('tec_products');
$pantallas = $productos->get('display');

if (!isset($pantallas['block_4'])) {
  print "  No esta block_4, que es el pattern. No sigo.\n\n";
  return;
}

$nueva = $pantallas['block_4'];
$nueva['id'] = PANTALLA;
$nueva['display_title'] = 'Brand catalogue';
$nueva['display_plugin'] = 'embed';
$nueva['position'] = 1 + max(array_map(
  static fn(array $p): int => (int) ($p['position'] ?? 0),
  $pantallas
));

$opciones = &$nueva['display_options'];
$opciones['title'] = 'Products';
unset($opciones['block_description']);

// La marca por la que entra. La relacion del cliente se queda: alguna columna
// del pattern puede colgar de ella y sobra sin molestar.
$opciones['relationships']['field_tec_brand'] = [
  'id' => 'field_tec_brand',
  'table' => 'tec_product__field_tec_brand',
  'field' => 'field_tec_brand',
  'relationship' => 'none',
  'group_type' => 'group',
  'admin_label' => 'field_tec_brand: Taxonomy term',
  'plugin_id' => 'standard',
  'required' => TRUE,
];

$opciones['arguments'] = [
  'tid' => [
    'id' => 'tid',
    'table' => 'taxonomy_term_field_data',
    'field' => 'tid',
    'relationship' => 'field_tec_brand',
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'taxonomy_term',
    'entity_field' => 'tid',
    'plugin_id' => 'taxonomy',
    'default_action' => 'default',
    'exception' => [
      'value' => 'all',
      'title_enable' => FALSE,
      'title' => 'All',
    ],
    'title_enable' => FALSE,
    'title' => '',
    // Si nadie le pasa la marca, la coge de la direccion. Asi la pantalla se
    // sostiene sola dentro de la ficha de un termino.
    'default_argument_type' => 'taxonomy_tid',
    'default_argument_options' => [],
    'summary_options' => [
      'base_path' => '',
      'count' => TRUE,
      'override' => FALSE,
      'items_per_page' => 25,
    ],
    'summary' => [
      'sort_order' => 'asc',
      'number_of_records' => 0,
      'format' => 'default_summary',
    ],
    'specify_validation' => FALSE,
    'validate' => ['type' => 'none', 'fail' => 'not found'],
    'validate_options' => [],
    'break_phrase' => FALSE,
    'not' => FALSE,
  ],
];

$opciones['sorts'] = [
  'title' => [
    'id' => 'title',
    'table' => 'tec_product_field_data',
    'field' => 'title',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'tec_product',
    'entity_field' => 'title',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'expose' => ['label' => '', 'field_identifier' => ''],
    'exposed' => FALSE,
  ],
];

$volver = '/taxonomy/term/{{ raw_arguments.tid }}';
// `brand_id` y no `target_id`: la clave `target_id` ya esta cogida por el campo
// de cliente del producto, que la lee para rellenarse solo. Pasarle por ahi el
// numero de una marca intentaria escribir una marca en la casilla del cliente,
// y aunque el modulo lo rechaza al validar, dejaria un aviso en el registro por
// cada producto que se cree desde aqui.
//
// Sin destination a la marca: Save tiene que dejar en la ficha del producto,
// que es donde estan + Variation y + Size. Con destination, un producto nuevo
// desaparecia de la lista a la que te mandaban. Organize si lleva destination,
// porque desde Organize no hay nada que completar.
$crear = '/admin/content/tec_product/add/tec_product'
  . '?brand_id={{ raw_arguments.tid }}';
$organizar = $volver . '/organize?destination=' . $volver;

$opciones['header'] = [
  'area_text_custom' => [
    'id' => 'area_text_custom',
    'table' => 'views',
    'field' => 'area_text_custom',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'text_custom',
    'empty' => TRUE,
    'content' => '<div class="text-align-right"><span class="btn-default">'
      . '<strong><a href="' . $crear . '">+ Product</a></strong>'
      . '</span> <span class="btn-default"><strong>'
      . '<a href="' . $organizar . '">Organize</a></strong></span></div>',
    'tokenize' => TRUE,
  ],
];

if (isset($opciones['empty']['area_text_custom'])) {
  $opciones['empty']['area_text_custom']['content'] =
    '<div class="text-align-center"><h4>Nothing to show here yet</h4>'
    . '<div>A product joins this list once it has a colour and that colour has a size.</div></div>'
    . '<div class="text-align-center"><div><strong><a href="' . $crear . '">'
    . '+ Product</a></strong></div></div>';
}

// La columna de la marca diria lo mismo en todas las filas, que es justo el
// titulo de la pagina donde esta.
if (isset($opciones['fields']['field_tec_brand'])) {
  $opciones['fields']['field_tec_brand']['exclude'] = TRUE;
}

if (isset($opciones['fields']['edit_tec_product']['alter']['text'])) {
  $opciones['fields']['edit_tec_product']['alter']['text'] = str_replace(
    '/tec_crm/{{ raw_arguments.id }}',
    $volver,
    $opciones['fields']['edit_tec_product']['alter']['text']
  );
}

unset($opciones);

$pantallas[PANTALLA] = $nueva;
$productos->set('display', $pantallas);
$productos->save();

anotar($hechos, 'la pantalla del catalogo, clonada de block_4',
  PANTALLA . ', ' . count($nueva['display_options']['fields']) . ' columnas');
print "    columnas: " . implode(', ', array_keys($nueva['display_options']['fields'])) . "\n";

// ---------------------------------------------------------------------------
// 2. El campo que mete esa pantalla dentro de la ficha de la marca.
// ---------------------------------------------------------------------------
print "\nEl campo que la mete en la ficha\n";
print str_repeat('-', 70) . "\n";

if (!FieldStorageConfig::loadByName('taxonomy_term', CAMPO)) {
  FieldStorageConfig::create([
    'field_name' => CAMPO,
    'entity_type' => 'taxonomy_term',
    'type' => 'viewfield',
    'cardinality' => 1,
  ])->save();
  anotar($hechos, 'el almacen del campo', 'creado');
}
else {
  anotar($hechos, 'el almacen del campo', 'ya estaba');
}

if (!FieldConfig::loadByName('taxonomy_term', 'tec_brands', CAMPO)) {
  FieldConfig::create([
    'field_name' => CAMPO,
    'entity_type' => 'taxonomy_term',
    'bundle' => 'tec_brands',
    'label' => 'Catalogue',
    'required' => FALSE,
    // `target_uuid` es como se guarda una referencia en configuracion, y al
    // cargarse se convierte en el numero de la vista. Se escribe asi para que
    // el fichero exportado no lleve un numero que cambia de una base a otra.
    'default_value' => [
      [
        'target_uuid' => $productos->uuid(),
        'display_id' => PANTALLA,
        'arguments' => '[term:tid]',
        'items_to_display' => '',
      ],
    ],
    'settings' => [
      // Nadie elige la vista marca por marca: la lleva puesta de fabrica.
      'force_default' => TRUE,
      'allowed_views' => ['tec_products' => 'tec_products'],
      'allowed_display_types' => ['embed' => 'embed'],
      'handler' => 'default:view',
      'handler_settings' => [],
    ],
  ])->save();
  anotar($hechos, 'el campo en el vocabulario de marcas', 'creado');
}
else {
  anotar($hechos, 'el campo en el vocabulario de marcas', 'ya estaba');
}

$comoSeVe = $gestor->getStorage('entity_view_display')
  ->load('taxonomy_term.tec_brands.default');
$contenido = $comoSeVe->get('content');
$contenido[CAMPO] = [
  'type' => 'viewfield_default',
  'label' => 'hidden',
  'settings' => [
    'view_title' => 'hidden',
    // Dibujarla aunque no devuelva ninguna fila. Por omision el modulo se calla
    // cuando la vista sale vacia, y eso dejaba a una marca recien creada sin
    // nada en su ficha: ni el aviso de que no tiene productos, ni el boton de
    // crear el primero. La marca sin productos no es un caso raro, es el caso
    // normal el dia que se abre.
    'always_build_output' => TRUE,
    'empty_view_title' => 'hidden',
  ],
  'third_party_settings' => [],
  'weight' => 10,
  'region' => 'content',
];
$comoSeVe->set('content', $contenido);
$escondidos = $comoSeVe->get('hidden') ?: [];
unset($escondidos[CAMPO]);
$comoSeVe->set('hidden', $escondidos);
$comoSeVe->save();
anotar($hechos, 'puesto en la ficha de la marca, debajo de todo', 'peso 10');

// El valor va fijo, asi que la casilla en el formulario solo estorbaria.
$comoSeEdita = $gestor->getStorage('entity_form_display')
  ->load('taxonomy_term.tec_brands.default');
$contenidoForm = $comoSeEdita->get('content');
unset($contenidoForm[CAMPO]);
$comoSeEdita->set('content', $contenidoForm);
$escondidosForm = $comoSeEdita->get('hidden') ?: [];
$escondidosForm[CAMPO] = TRUE;
$comoSeEdita->set('hidden', $escondidosForm);
$comoSeEdita->save();
anotar($hechos, 'y fuera del formulario, que su valor no se toca', 'escondido');

// ---------------------------------------------------------------------------
// 3. La pantalla de marcas: la baldosa abre el catalogo, editar es un boton.
// ---------------------------------------------------------------------------
print "\nLa pantalla de marcas\n";
print str_repeat('-', 70) . "\n";

$marcas = $gestor->getStorage('view')->load('tec_brands');
$pantallasMarcas = $marcas->get('display');
$campos = $pantallasMarcas['default']['display_options']['fields'];

// El boton estaba hecho desde el principio, con su icono y su clase, y puesto a
// no dibujarse nunca. Alguien lo escribio y lo escondio, probablemente porque la
// baldosa ya llevaba a editar y sobraba.
$campos['edit_taxonomy_term']['exclude'] = FALSE;

foreach (['field_tec_logo', 'name'] as $cual) {
  if (isset($campos[$cual]['alter'])) {
    $campos[$cual]['alter']['make_link'] = TRUE;
    $campos[$cual]['alter']['path'] = '/taxonomy/term/{{ tid }}';
  }
}

// El lapiz al final de la baldosa, debajo del nombre, que es donde el ERP pone
// siempre los controles.
$orden = ['tid', 'field_tec_logo', 'name', 'edit_taxonomy_term'];
$ordenados = [];
foreach ($orden as $cual) {
  if (isset($campos[$cual])) {
    $ordenados[$cual] = $campos[$cual];
  }
}
foreach ($campos as $cual => $campo) {
  if (!isset($ordenados[$cual])) {
    $ordenados[$cual] = $campo;
  }
}

$pantallasMarcas['default']['display_options']['fields'] = $ordenados;
$marcas->set('display', $pantallasMarcas);
$marcas->save();

anotar($hechos, 'el lapiz de editar, encendido', 'al final de la baldosa');
anotar($hechos, 'la baldosa lleva a la ficha de la marca', '/taxonomy/term/{{ tid }}');

print "\n=====================================================================\n";
printf("  %d cosas puestas. Queda exportar la configuracion.\n", count($hechos));
print "=====================================================================\n\n";
