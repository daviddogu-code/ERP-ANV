<?php

/**
 * @file
 * Un pedido de compra vale lo que valia el dia que se hizo.
 *
 * Hasta hoy la linea de compra se multiplicaba por el coste que tuviera el
 * material en ese momento, no por el precio con el que nacio la linea. Como el
 * total se recalcula en cada guardado, subir el coste de un material reescribia
 * el importe de todos los pedidos viejos que lo llevaran, y bastaba con recibir
 * mercancia en abril para que el pedido de enero cambiara de precio solo.
 *
 * Curiosamente el propio dibujo ya daba por hecho que mandaba la linea: no
 * calcula nada si la linea no tiene precio. Solo que luego multiplicaba por otra
 * cosa. Y ventas, que es de donde se copio todo esto, siempre multiplico por el
 * precio de su linea. Compras era la unica que no.
 *
 * Se tocan cuatro cosas:
 *
 *   1. La cuenta multiplica por el precio de la linea.
 *   2. Quien crea el pedido desde una ficha de proveedor estampaba en la linea
 *      el coste por unidad de consumo del material en vez del de compra, que es
 *      de donde salen los 0,02 de las lineas viejas de KJ contra un material de
 *      80. Ese es el origen de todo el enredo.
 *   3. El borrador y la ficha del pedido enseñan el precio de la linea, no el
 *      coste de hoy del material.
 *   4. El Sub total de la ficha deja de ser una multiplicacion de la pantalla y
 *      pasa a ser el total guardado, que es lo que ya leen el impreso y el pie.
 *
 * Run: php vendor\bin\drush.php scr scripts/el-precio-de-la-linea-manda.php
 */

use Drupal\views\Entity\View;

const VISTA = 'tec_order_sales_order_line_items';

/**
 * Cambia un valor dentro del dibujo BPMN de una ECA.
 *
 * El dibujo no es adorno: si alguien abre el modelador y guarda, el ejecutable
 * se vuelve a generar desde el. Dejarlo con el token viejo seria dejar puesta
 * la trampa para dentro de seis meses.
 */
function tocar_el_dibujo(string $proceso, string $actividad, array $campos, ?string $nombre = NULL): void {
  $config = \Drupal::configFactory()->getEditable('eca.model.' . $proceso);
  $xml = new DOMDocument();
  $xml->preserveWhiteSpace = TRUE;
  if (!@$xml->loadXML((string) $config->get('modeldata'))) {
    printf("  [MAL] el dibujo de %s no es XML valido\n", $proceso);
    return;
  }

  $buscador = new DOMXPath($xml);
  $paso = $buscador->query(sprintf('//*[@id="%s"]', $actividad))->item(0);
  if (!$paso) {
    printf("  [MAL] el dibujo de %s no tiene el paso %s\n", $proceso, $actividad);
    return;
  }

  if ($nombre !== NULL) {
    $paso->setAttribute('name', $nombre);
  }

  foreach ($campos as $campo => $valor) {
    $texto = $buscador->query(sprintf('.//*[local-name()="field"][@name="%s"]/*[local-name()="string"]', $campo), $paso)->item(0);
    if (!$texto) {
      printf("  [MAL] el paso %s del dibujo no tiene el campo %s\n", $actividad, $campo);
      continue;
    }
    $texto->nodeValue = '';
    $texto->appendChild($xml->createTextNode($valor));
  }

  $config->set('modeldata', $xml->saveXML())->save();
}

// -----------------------------------------------------------------------------
// 1. La cuenta multiplica por el precio de la linea.
// -----------------------------------------------------------------------------
print "La cuenta del total de la linea\n" . str_repeat('-', 78) . "\n";

$cuenta = \Drupal::configFactory()->getEditable('eca.eca.process_fpvka81');
$acciones = $cuenta->get('actions');
$antes = $acciones['Activity_020nm9h']['configuration']['value'] ?? '(no esta)';
$acciones['Activity_020nm9h']['configuration']['value'] = '[entity:field_tec_price:value]';
$cuenta->set('actions', $acciones)->save();
tocar_el_dibujo('process_fpvka81', 'Activity_020nm9h', ['value' => '[entity:field_tec_price:value]']);
printf("  %s\n  %s\n", $antes, '-> [entity:field_tec_price:value]');
print "  (ventas ya lo hacia asi desde siempre, en process_lvy385w)\n";

// -----------------------------------------------------------------------------
// 2. Quien crea el pedido estampa el coste de compra, no el de consumo.
// -----------------------------------------------------------------------------
print "\nEl precio con el que nace la linea\n" . str_repeat('-', 78) . "\n";

$crear = \Drupal::configFactory()->getEditable('eca.eca.process_kryibry');
$acciones = $crear->get('actions');
$antes = $acciones['Activity_00es7rw']['configuration']['field_value'] ?? '(no esta)';
$acciones['Activity_00es7rw']['configuration']['field_value'] = '[inventoryItem:field_tec_cost]';
// Se llamaba "Set Sales price" en un flujo de compras, calcado de ventas sin
// cambiarle el nombre. Ese descuido es justo el que se esta arreglando.
$acciones['Activity_00es7rw']['label'] = 'Set Purchase cost & save';
$crear->set('actions', $acciones)->save();
tocar_el_dibujo('process_kryibry', 'Activity_00es7rw',
  ['field_value' => '[inventoryItem:field_tec_cost]'], 'Set Purchase cost & save');
printf("  %s   (el coste por unidad de consumo del material)\n", $antes);
print "  -> [inventoryItem:field_tec_cost]   (el de compra, que es lo que se paga)\n";

// -----------------------------------------------------------------------------
// 3 y 4. Las dos pantallas leen la linea y no el material.
// -----------------------------------------------------------------------------
print "\nLas pantallas\n" . str_repeat('-', 78) . "\n";

$vista = View::load(VISTA);
$displays = $vista->get('display');

// De donde se copian las dos columnas: el impreso, que lleva las buenas desde
// el principio y es el unico sitio donde el precio nunca se movio.
$modeloPrecio = $displays['page_3']['display_options']['fields']['field_tec_price'];
$modeloTotal = $displays['page_3']['display_options']['fields']['field_tec_line_item_total_number'];

// Los millares con coma, que en el impreso el precio salia sin ellos.
$displays['page_3']['display_options']['fields']['field_tec_price']['settings']['thousand_separator'] = ',';

/**
 * Que columna se va y por cual se cambia, pantalla por pantalla.
 *
 * La segunda del borrador no se cambia por nada: es un resto muerto, una
 * multiplicacion oculta cuya formula entera era "@field_tec_cost" y a la que no
 * mira nadie. Se va con el coste, que es de lo unico que hablaba.
 */
$cambios = [
  'page_1' => [
    'field_tec_cost' => ['field_tec_price', 'Purchase Cost'],
    'field_views_simple_math_field_1' => NULL,
  ],
  'block_2' => [
    'field_tec_cost' => ['field_tec_price', 'Purchase Cost'],
    'field_views_simple_math_field' => ['field_tec_line_item_total_number', 'Sub total'],
  ],
];

$plantillas = [
  'field_tec_price' => $modeloPrecio,
  'field_tec_line_item_total_number' => $modeloTotal,
];

foreach ([
  'page_1' => 'el borrador  po/draft/%',
  'block_2' => 'la ficha     tec_order/%',
] as $pantalla => $como) {
  $opciones = &$displays[$pantalla]['display_options'];
  $campos = $opciones['fields'];
  $sevan = array_keys($cambios[$pantalla]);

  // Ninguna columna que se queda puede estar leyendo a una que se va, o quedaria
  // en blanco sin que nadie se entere hasta que lo vea un proveedor.
  foreach ($campos as $clave => $config) {
    if (in_array($clave, $sevan, TRUE)) {
      continue;
    }
    $escrito = json_encode($config);
    foreach ($sevan as $quese_va) {
      if (str_contains($escrito, '@' . $quese_va) || str_contains($escrito, '{{ ' . $quese_va . ' }}')) {
        printf("  [MAL] en %s la columna %s lee %s, que se esta quitando. No se toca nada.\n",
          $pantalla, $clave, $quese_va);
        return;
      }
    }
  }

  $tenia = array_keys($campos);
  $nuevos = [];
  foreach ($campos as $clave => $config) {
    // La que llega puede existir ya en otro sitio de la lista, oculta. Se
    // recoloca en el hueco de la que se va.
    if (in_array($clave, array_column(array_filter($cambios[$pantalla]), 0), TRUE)) {
      continue;
    }
    if (!array_key_exists($clave, $cambios[$pantalla])) {
      $nuevos[$clave] = $config;
      continue;
    }
    if ($cambios[$pantalla][$clave] === NULL) {
      continue;
    }
    [$nueva, $rotulo] = $cambios[$pantalla][$clave];
    $llega = $plantillas[$nueva];
    $llega['label'] = $rotulo;
    $llega['exclude'] = FALSE;
    $nuevos[$nueva] = $llega;
  }
  $opciones['fields'] = $nuevos;
  $campos = $nuevos;

  // El alineado se guarda aparte de la columna, en una lista de la tabla, asi
  // que hay que llevarlo a mano al nombre nuevo. Y las dos son dinero, que va
  // a la derecha en todas las pantallas.
  $estilo = &$opciones['style']['options']['info'];
  foreach ($cambios[$pantalla] as $vieja => $cambio) {
    if ($cambio === NULL) {
      unset($estilo[$vieja]);
      continue;
    }
    $nueva = $cambio[0];
    $ajuste = $estilo[$nueva] ?? $estilo[$vieja] ?? [
      'sortable' => FALSE,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => FALSE,
      'responsive' => '',
    ];
    $ajuste['align'] = 'views-align-right';
    $estilo[$nueva] = $ajuste;
    unset($estilo[$vieja]);
  }
  unset($estilo);
  unset($opciones);

  printf("  %s\n", $como);
  foreach ($cambios[$pantalla] as $vieja => $cambio) {
    printf("    %-32s -> %s\n", $vieja, $cambio === NULL ? '(fuera, no la leia nadie)' : $cambio[0]);
  }
}

$vista->set('display', $displays);
$vista->save();

// -----------------------------------------------------------------------------
// Como ha quedado.
// -----------------------------------------------------------------------------
print "\nLas tres pantallas de compra\n" . str_repeat('=', 78) . "\n";
$vista = View::load(VISTA);
foreach (['page_1' => 'borrador  po/draft/%', 'block_2' => 'ficha     tec_order/%', 'page_3' => 'impreso   po/%/print'] as $pantalla => $como) {
  $rotulos = [];
  foreach ($vista->getDisplay($pantalla)['display_options']['fields'] as $campo) {
    if (empty($campo['exclude'])) {
      $rotulos[] = ($campo['label'] ?? '') === '' ? '(sin nombre)' : $campo['label'];
    }
  }
  printf("  %s\n    %s\n", $como, implode(' | ', $rotulos));
}
