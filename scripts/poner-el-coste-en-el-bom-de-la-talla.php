<?php

/**
 * @file
 * La tabla de BoM de cada talla dice lo que cuesta cada material.
 *
 *   php vendor\bin\drush.php scr scripts/poner-el-coste-en-el-bom-de-la-talla.php
 *
 * Hasta hoy la tabla de materiales de una talla contaba cuanto hace falta y en
 * que unidades, y no decia nada de dinero. El coste existia en el ERP, pero solo
 * dentro de un pedido: la vista Material calculation multiplica ahi la cantidad
 * por el coste de consumo del material. O sea que para saber lo que cuesta
 * fabricar una talla habia que pedirla primero.
 *
 * Se pone la misma cuenta en la ficha del producto, con el mismo modulo y la
 * misma formula que en pedidos, para que el dia que se discuta el numero se
 * discuta uno solo:
 *
 *   cantidad calculada de la linea de BoM x coste de consumo del material
 *
 * La tabla queda en cuatro columnas -Material, Requires, UoU y Cost- y cerrada
 * por abajo con el total de lo que cuestan sus materiales.
 *
 * Cinco decisiones que no son evidentes:
 *
 * - La columna multiplica `field_tec_quantity`, el numero que el ERP calcula al
 *   guardar, y no `field_tec_quantity_input`, que es donde se escriben cosas como
 *   `1/12`. Por eso hay que sacar tambien la cantidad calculada a la vista, aunque
 *   sea escondida: el modulo de calculo solo sabe leer campos que estan puestos.
 * - Se llama Cost y no coste de fabricacion, porque suma materiales del BoM y
 *   nada mas: ni mano de obra, ni merma, ni fletes.
 * - UoP se esconde. Un BoM dice lo que una talla consume, y lo que consume esta
 *   escrito en unidades de consumo: la unidad de compra es cosa de la ficha del
 *   material y aqui solo separaba la cantidad de su unidad.
 * - El orden se escribe entero, porque las columnas de una tabla de Views salen
 *   en el orden de sus campos y no en el del mapa de columnas del estilo.
 * - El total del pie no es la suma de la columna, aunque lo parezca: la columna
 *   redondea cada linea al centimo, y un hilo que vale seis diezmilesimas de
 *   baht por centimetro sale a cero linea por linea. Lo suma en crudo la clase
 *   MaterialCost, que es tambien la que usara el catalogo, para que las dos
 *   pantallas no puedan decir numeros distintos.
 *
 * Se puede lanzar dos veces: si la columna ya estaba, la vuelve a escribir igual.
 */

use Drupal\Component\Gettext\PoItem;

const VISTA = 'tec_product_elements';
const PANTALLA = 'embed_7';
const CUENTA = 'field_views_simple_math_field';
const PIE = 'tec_size_material_cost_total';

// Las columnas que se ven, en su orden. Detras de la ultima que se ve van los
// campos que solo existen para la formula, y detras de esos la cuenta.
const VISIBLES = [
  'field_tec_inventory',
  'field_tec_quantity_input',
  'field_tec_unit_use',
];

const ESCONDIDOS = [
  'field_tec_unit_purchase',
  'field_tec_quantity',
  'field_tec_price',
];

print "\n";
print "=====================================================================\n";
print "  El BoM de la talla dice lo que cuesta\n";
print "=====================================================================\n\n";

/**
 * Lo que Views escribe en cualquier campo aunque no se toque nada de eso.
 */
function relleno_de_campo(): array {
  return [
    'group_type' => 'group',
    'admin_label' => '',
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
 * Un campo decimal escondido, de los que solo existen para la formula.
 */
function campo_escondido(string $id, string $tabla, string $relacion, int $escala): array {
  return [
    'id' => $id,
    'table' => $tabla,
    'field' => $id,
    'relationship' => $relacion,
    'plugin_id' => 'field',
    'label' => '',
    'exclude' => TRUE,
    'click_sort_column' => 'value',
    'type' => 'number_decimal',
    'settings' => [
      // Sin marca de miles: el modulo de calculo lee el valor crudo, pero si
      // alguien enciende esta columna algun dia, que no traiga comas dentro.
      'thousand_separator' => '',
      'decimal_separator' => '.',
      'scale' => $escala,
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
  ] + relleno_de_campo();
}

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  print "  No existe la vista " . VISTA . ". No sigo.\n\n";
  return;
}

$pantallas = $vista->get('display');
if (!isset($pantallas[PANTALLA])) {
  print "  No esta la pantalla " . PANTALLA . ". No sigo.\n\n";
  return;
}

$opciones = &$pantallas[PANTALLA]['display_options'];
$campos = $opciones['fields'];

// Las dos relaciones tienen que estar puestas: la formula cuelga de ellas.
foreach (['field_tec_bom', 'field_tec_inventory'] as $relacion) {
  if (!isset($opciones['relationships'][$relacion])) {
    printf("  Falta la relacion %s en la pantalla. No sigo.\n\n", $relacion);
    return;
  }
}

$estaba = isset($campos[CUENTA]);
$tenia_pie = isset($opciones['footer'][PIE]);

// ---------------------------------------------------------------------------
// Los dos campos que alimentan la cuenta, escondidos.
// ---------------------------------------------------------------------------
$campos['field_tec_quantity'] = campo_escondido(
  'field_tec_quantity',
  'tec_inventory__field_tec_quantity',
  'field_tec_bom',
  4
);

$campos['field_tec_price'] = campo_escondido(
  'field_tec_price',
  'taxonomy_term__field_tec_price',
  'field_tec_inventory',
  6
);

// ---------------------------------------------------------------------------
// La columna.
// ---------------------------------------------------------------------------
$campos[CUENTA] = [
  'id' => CUENTA,
  'table' => 'views_simple_math_field',
  'field' => CUENTA,
  'relationship' => 'none',
  'plugin_id' => 'field_views_simple_math_field',
  'label' => 'Cost',
  'exclude' => FALSE,
  'fieldset_one' => [
    // El modulo pinta esto como casillas: lo que no entra en la formula va a
    // cero, igual que lo dejaria la interfaz.
    'data_field' => [
      'field_tec_inventory' => 0,
      'field_tec_quantity_input' => 0,
      'field_tec_unit_purchase' => 0,
      'field_tec_quantity' => 'field_tec_quantity',
      'field_tec_price' => 'field_tec_price',
      'field_tec_unit_use' => 0,
      'field_tec_material_type' => 0,
    ],
    'formula' => '@field_tec_quantity * @field_tec_price',
  ],
  // Una linea con cantidad cero no es un error que haya que anotar en el
  // registro cada vez que alguien abre una ficha.
  'mute_logs' => TRUE,
  'set_precision' => TRUE,
  'precision' => '2',
  'decimal' => '.',
  'separator' => ',',
  'format_plural' => FALSE,
  'format_plural_string' => '1' . PoItem::DELIMITER . '@count',
  // Escapado y no escrito: este fichero no tiene por que sobrevivir a un editor
  // que adivine la codificacion, y en este proyecto ya han caido varios.
  'prefix' => "\u{0E3F} ",
  'suffix' => '',
] + relleno_de_campo();

// ---------------------------------------------------------------------------
// Quien se ve y quien no.
// ---------------------------------------------------------------------------
foreach (VISIBLES as $cual) {
  if (isset($campos[$cual])) {
    $campos[$cual]['exclude'] = FALSE;
  }
}
foreach (ESCONDIDOS as $cual) {
  if (isset($campos[$cual])) {
    $campos[$cual]['exclude'] = TRUE;
  }
}

// ---------------------------------------------------------------------------
// El orden. Las columnas de una tabla de Views salen en el orden de los campos,
// y el modulo de calculo solo lee campos pintados antes que el, asi que la
// cuenta va detras de los dos escondidos que la alimentan.
// ---------------------------------------------------------------------------
$orden = array_merge(VISIBLES, ESCONDIDOS, [CUENTA]);
$ordenados = [];
foreach ($orden as $cual) {
  if (isset($campos[$cual])) {
    $ordenados[$cual] = $campos[$cual];
  }
}
// Lo que no estaba en la lista se queda detras, en el orden que tenia. Hoy es
// solo el tipo de material, que esta puesto para agrupar y no se ve.
foreach ($campos as $cual => $campo) {
  if (!isset($ordenados[$cual])) {
    $ordenados[$cual] = $campo;
  }
}

$opciones['fields'] = $ordenados;

// ---------------------------------------------------------------------------
// La columna en el estilo de la tabla, alineada a la derecha como todo lo que
// es dinero en este ERP. Se copia la de la cantidad para no inventar la forma.
// ---------------------------------------------------------------------------
$estilo = &$opciones['style']['options'];
$estilo['columns'][CUENTA] = CUENTA;
$ficha = $estilo['info']['field_tec_quantity_input'] ?? [
  'sortable' => 0,
  'default_sort_order' => 'asc',
  'align' => '',
  'separator' => '',
  'empty_column' => 0,
  'responsive' => '',
];
$ficha['align'] = 'views-align-right';
$estilo['info'][CUENTA] = $ficha;
unset($estilo);

// ---------------------------------------------------------------------------
// El total, al pie. No es la suma de la columna: la columna redondea cada linea
// al centimo, y un hilo que vale seis diezmilesimas de baht por centimetro sale
// a cero en todas sus lineas. Lo suma aparte MaterialCost, en crudo.
// ---------------------------------------------------------------------------
// Sin esto la pantalla seguiria heredando el pie de la pantalla maestra, que
// esta vacio, y lo que se escriba aqui no se dibujaria nunca.
$opciones['defaults']['footer'] = FALSE;

$opciones['footer'][PIE] = [
  'id' => PIE,
  'table' => 'views',
  // El nombre del plugin, no la palabra «area»: Views busca el manejador por
  // aqui, y si no lo encuentra no dibuja nada y no se queja.
  'field' => PIE,
  'relationship' => 'none',
  'group_type' => 'group',
  'admin_label' => '',
  'plugin_id' => PIE,
  // Una talla sin BoM no lleva pie: el propio plugin no dibuja nada, pero asi
  // tampoco se le pregunta.
  'empty' => FALSE,
];

unset($opciones);

$vista->set('display', $pantallas);
$vista->save();

printf("  Columna Cost: %s\n", $estaba ? 'ya estaba, reescrita igual' : 'puesta');
printf("  Formula: %s\n", '@field_tec_quantity * @field_tec_price');
printf("  Total al pie: %s\n", $tenia_pie ? 'ya estaba, reescrito igual' : 'puesto');
print "  Orden de columnas: " . implode(', ', array_keys($pantallas[PANTALLA]['display_options']['fields'])) . "\n";
print "\n  Los dos campos de la cuenta van escondidos: solo existen para multiplicar.\n";
print "  Queda exportar la configuracion.\n\n";
