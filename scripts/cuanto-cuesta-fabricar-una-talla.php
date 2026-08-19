<?php

/**
 * @file
 * La tabla de BoM de una talla cobra cada material por lo que consume, y se
 * cierra con lo que suma.
 *
 *   php vendor\bin\drush.php scr scripts/cuanto-cuesta-fabricar-una-talla.php
 *
 * La columna Cost multiplica dos numeros que estan en sitios distintos: la
 * cantidad calculada vive en la linea de BoM y el coste de consumo vive en el
 * material, que es un termino de taxonomia. La cuenta la hace un modulo leyendo
 * campos de la propia vista, asi que se rompe de una manera silenciosa: si el
 * campo escondido que la alimenta se cae, la columna sigue saliendo y dice cero.
 *
 * Por eso esta prueba no comprueba que haya una columna, comprueba que dice el
 * numero que tiene que decir, y lo comprueba contra la cuenta hecha aparte.
 *
 * Tres cosas que se prueban aparte, porque cada una es una decision que alguien
 * podria deshacer sin darse cuenta:
 *
 *   1. En el BoM se escribe `1/12` de plancha, no `0.0833`. Si la columna cobrara
 *      lo escrito, una talla que gasta un doceavo de plancha costaria doce veces
 *      mas.
 *   2. El total del pie no es la suma de la columna. Un hilo que vale seis
 *      diezmilesimas de baht por centimetro sale a cero en cada linea, y diez
 *      lineas de cero siguen siendo cero: el pie tiene que sumar en crudo.
 *   3. Un material sin coste de consumo no se cuenta como cero callando: el pie
 *      lo dice, porque un total al que le falta un material lee como un producto
 *      mas barato de lo que es y nadie va a buscar un numero que parece bien.
 *
 * Deshace todo lo que crea. Se puede lanzar dos veces.
 */

use Drupal\views\Views;

const VISTA = 'tec_product_elements';
const PANTALLA = 'embed_7';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$almacenProducto = $gestor->getStorage('tec_product');
$almacenInventario = $gestor->getStorage('tec_inventory');
$almacenTerminos = $gestor->getStorage('taxonomy_term');

$problemas = 0;

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, !$bien && $pista !== '' ? ': ' . $pista : '');
  if (!$bien) {
    $problemas++;
  }
};

/**
 * Dibuja la tabla del BoM de una talla: cabeceras, filas y pie.
 */
$dibujar = static function (int $talla): array {
  $vista = Views::getView(VISTA);
  if (!$vista) {
    return [];
  }
  $vista->setDisplay(PANTALLA);
  $vista->setArguments([$talla]);
  $vista->preExecute();
  $vista->execute();
  $html = (string) \Drupal::service('renderer')->renderInIsolation($vista->render());
  $vista->destroy();

  $limpiar = static fn(string $celda): string => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($celda))));

  $cabeceras = [];
  if (preg_match('/<thead.*?<tr[^>]*>(.*?)<\/tr>/s', $html, $fila)) {
    preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $fila[1], $celdas);
    $cabeceras = array_map($limpiar, $celdas[1] ?? []);
  }

  $filas = [];
  if (preg_match('/<tbody(.*?)<\/tbody>/s', $html, $cuerpo)) {
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $cuerpo[1], $trs);
    foreach ($trs[1] ?? [] as $tr) {
      preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $tr, $celdas);
      $filas[] = array_map($limpiar, $celdas[1] ?? []);
    }
  }

  // El pie es una tabla suya, detras de la de las filas.
  $pie = '';
  if (preg_match('/<table[^>]*class="[^"]*tec-material-cost[^"]*".*?<\/table>/s', $html, $trozo)) {
    $pie = $limpiar($trozo[0]);
  }

  return ['cabeceras' => $cabeceras, 'filas' => $filas, 'pie' => $pie];
};

$basura = ['producto' => [], 'lineas' => [], 'terminos' => []];

/**
 * Monta una talla con las lineas de BoM que se le digan.
 *
 * Cada linea es [lo que se escribe, el material].
 */
$montarTalla = static function (string $nombre, array $lineas) use ($almacenProducto, $almacenInventario, &$basura) {
  $producto = $almacenProducto->create(['type' => 'tec_product', 'title' => 'PRUEBA coste ' . $nombre]);
  $producto->save();
  $basura['producto'][] = $producto->id();

  $color = $almacenProducto->create(['type' => 'tec_color_variation', 'title' => 'PRUEBA coste color ' . $nombre]);
  $color->save();
  $basura['producto'][] = $color->id();
  $producto->set('field_tec_color_variations', [$color->id()]);
  $producto->save();

  $talla = $almacenProducto->create([
    'type' => 'tec_size_variation',
    'title' => 'PRUEBA coste talla ' . $nombre,
    'field_tec_price' => 999.00,
  ]);
  $talla->save();
  $basura['producto'][] = $talla->id();

  $color = $almacenProducto->loadUnchanged($color->id());
  $color->set('field_tec_size_variations', [$talla->id()]);
  $color->save();

  $puestas = [];
  foreach ($lineas as [$escrito, $material]) {
    $linea = $almacenInventario->create([
      'type' => 'tec_bom_item',
      'title' => '',
      'field_tec_inventory' => ['target_id' => $material->id()],
      'field_tec_quantity_input' => $escrito,
      'field_tec_size_variation' => ['target_id' => $talla->id()],
    ]);
    $linea->save();
    $basura['lineas'][] = $linea->id();
    $puestas[] = $almacenInventario->loadUnchanged($linea->id());
  }

  return [$almacenProducto->loadUnchanged($talla->id()), $puestas];
};

print "\n";
print "=====================================================================\n";
print "  Cuanto cuesta fabricar una talla\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// Dos materiales de los que ya hay, con coste de consumo puesto y distinto uno
// del otro: si los dos costaran lo mismo, una columna que confundiera las filas
// pasaria la prueba.
// ---------------------------------------------------------------------------
$candidatos = $almacenTerminos->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->exists('field_tec_price')
  ->sort('tid')
  ->execute();

$porCoste = [];
foreach ($almacenTerminos->loadMultiple($candidatos) as $termino) {
  $coste = (float) ($termino->get('field_tec_price')->value ?? 0);
  if ($coste <= 0) {
    continue;
  }
  $porCoste[(string) $coste] = $termino;
}

if (count($porCoste) < 2) {
  print "  No hay dos materiales con coste de consumo. No se puede probar.\n\n";
  return;
}

// El caro primero: es el que lleva la cantidad escrita como formula, para que
// cobrar lo escrito en lugar de lo calculado se note a la legua.
krsort($porCoste, SORT_NUMERIC);
$caro = reset($porCoste);
$barato = end($porCoste);

printf("  Materiales: %s (%s) y %s (%s)\n",
  $caro->label(), $caro->get('field_tec_price')->value,
  $barato->label(), $barato->get('field_tec_price')->value);

try {
  // -------------------------------------------------------------------------
  // 1. Las columnas y el numero de cada linea.
  // -------------------------------------------------------------------------
  [$talla, $lineas] = $montarTalla('columnas', [['1/12', $caro], ['2.5', $barato]]);
  printf("  Talla de prueba %d, con %d lineas de BoM\n", $talla->id(), count($lineas));

  $esperados = [];
  $enCrudo = 0.0;
  foreach ($lineas as $linea) {
    $material = $linea->get('field_tec_inventory')->entity;
    $cantidad = (float) $linea->get('field_tec_quantity')->value;
    $coste = (float) $material->get('field_tec_price')->value;
    $esperados[$material->label()] = number_format($cantidad * $coste, 2, '.', ',');
    $enCrudo += $cantidad * $coste;
  }

  $tabla = $dibujar((int) $talla->id());
  $cabeceras = $tabla['cabeceras'] ?? [];
  $filas = $tabla['filas'] ?? [];

  print "\n";
  print "Las columnas\n";
  print str_repeat('-', 70) . "\n";
  print "  cabeceras: " . implode(' | ', $cabeceras) . "\n";

  $mirar('la tabla tiene una columna que se llama Cost',
    in_array('Cost', $cabeceras, TRUE),
    implode(' | ', $cabeceras));

  $donde = array_search('Cost', $cabeceras, TRUE);
  $uou = array_search('UoU', $cabeceras, TRUE);
  $mirar('y va detras de UoU, la ultima de la fila',
    $donde !== FALSE && $uou !== FALSE && $donde === $uou + 1 && $donde === count($cabeceras) - 1,
    sprintf('UoU en %s, Cost en %s, de %d columnas', $uou === FALSE ? '-' : $uou, $donde === FALSE ? '-' : $donde, count($cabeceras)));

  // Cuatro: Material, Requires, UoU y Cost. La unidad de compra no dice nada de
  // lo que una talla consume, y la cantidad calculada y el coste de consumo
  // estan puestos para la formula, no para mirarlos.
  $mirar('no salen ni la unidad de compra ni los campos de la cuenta',
    count($cabeceras) === 4 && !in_array('UoP', $cabeceras, TRUE),
    count($cabeceras) . ' columnas: ' . implode(' | ', $cabeceras));

  print "\n";
  print "El numero de cada linea\n";
  print str_repeat('-', 70) . "\n";

  $mirar('salen las dos filas', count($filas) === 2, count($filas) . ' filas');

  $todas = '';
  foreach ($filas as $fila) {
    $material = $fila[0] ?? '';
    $dicho = $fila[$donde] ?? '';
    $todas .= ' ' . $dicho;
    $esperado = $esperados[$material] ?? NULL;

    if ($esperado === NULL) {
      $mirar('la fila dice de que material es', FALSE, 'fila: ' . implode(' | ', $fila));
      continue;
    }

    printf("  fila %-32s dice %s\n", $material, $dicho !== '' ? $dicho : 'nada');
    $mirar('cobra ' . $material . ' por lo que consume',
      str_contains($dicho, $esperado),
      'esperaba ' . $esperado . ', dice "' . $dicho . '"');
    $mirar('y lo dice en bahts', str_contains($dicho, "\u{0E3F}"), $dicho);
  }

  // Lo escrito era 1/12: si la columna cobrara el texto, saldria doce veces mas.
  $entero = number_format((float) $caro->get('field_tec_price')->value, 2, '.', ',');
  $mirar('una cantidad escrita como formula no se cobra como si fuera entera',
    !str_contains($todas, $entero),
    'aparece ' . $entero . ' en "' . trim($todas) . '"');

  print "\n";
  print "El total del pie\n";
  print str_repeat('-', 70) . "\n";
  printf("  pie: %s\n", $tabla['pie'] !== '' ? $tabla['pie'] : 'no sale');

  $mirar('la tabla se cierra con el coste de sus materiales',
    str_contains($tabla['pie'], 'Material cost'),
    $tabla['pie']);
  $mirar('y el total es la suma en crudo, redondeada una sola vez',
    str_contains($tabla['pie'], number_format(round($enCrudo, 2), 2, '.', ',')),
    'esperaba ' . number_format(round($enCrudo, 2), 2, '.', ',') . ', pie: ' . $tabla['pie']);

  // -------------------------------------------------------------------------
  // 2. El pie no es la suma de lo que se ve.
  // -------------------------------------------------------------------------
  print "\n";
  print "Diez lineas que a la vista valen cero\n";
  print str_repeat('-', 70) . "\n";

  $muchas = array_fill(0, 10, ['7', $barato]);
  [$tallaB, $lineasB] = $montarTalla('centimos', $muchas);
  $tablaB = $dibujar((int) $tallaB->id());

  $sumaCruda = 0.0;
  foreach ($lineasB as $linea) {
    $sumaCruda += (float) $linea->get('field_tec_quantity')->value * (float) $barato->get('field_tec_price')->value;
  }
  $esperadoB = number_format(round($sumaCruda, 2), 2, '.', ',');

  $columna = [];
  foreach ($tablaB['filas'] as $fila) {
    $columna[] = $fila[$donde] ?? '';
  }
  printf("  la columna dice: %s\n", implode(', ', array_unique($columna)));
  printf("  el pie dice: %s\n", $tablaB['pie'] !== '' ? $tablaB['pie'] : 'no sale');

  $todoCeros = TRUE;
  foreach ($columna as $celda) {
    if (!str_contains($celda, '0.00')) {
      $todoCeros = FALSE;
    }
  }
  $mirar('cada linea suelta redondea a cero', $todoCeros, implode(', ', array_unique($columna)));
  $mirar('pero el pie no dice cero, dice lo que suman',
    $esperadoB !== '0.00' && str_contains($tablaB['pie'], $esperadoB),
    'esperaba ' . $esperadoB . ', pie: ' . $tablaB['pie']);

  // -------------------------------------------------------------------------
  // 3. Un material sin coste no se cuenta como cero callando.
  // -------------------------------------------------------------------------
  print "\n";
  print "Un material sin coste todavia\n";
  print str_repeat('-', 70) . "\n";

  $sinCoste = $almacenTerminos->create([
    'vid' => 'tec_inventory',
    'name' => 'PRUEBA material sin coste',
  ]);
  $sinCoste->save();
  $basura['terminos'][] = $sinCoste->id();

  $vacio = (string) ($almacenTerminos->loadUnchanged($sinCoste->id())->get('field_tec_price')->value ?? '');
  $mirar('el material de prueba nace sin coste de consumo', $vacio === '', 'dice ' . $vacio);

  [$tallaC, $lineasC] = $montarTalla('sin coste', [['2', $caro], ['3', $sinCoste]]);
  $tablaC = $dibujar((int) $tallaC->id());
  printf("  pie: %s\n", $tablaC['pie'] !== '' ? $tablaC['pie'] : 'no sale');

  $soloElCaro = number_format(round(2 * (float) $caro->get('field_tec_price')->value, 2), 2, '.', ',');
  $mirar('el total es el de los materiales que si tienen precio',
    str_contains($tablaC['pie'], $soloElCaro),
    'esperaba ' . $soloElCaro . ', pie: ' . $tablaC['pie']);
  $mirar('y el pie avisa de que falta uno, y dice cual',
    str_contains($tablaC['pie'], 'no cost yet') && str_contains($tablaC['pie'], 'PRUEBA material sin coste'),
    $tablaC['pie']);
}
finally {
  // -------------------------------------------------------------------------
  // Recoger. Solo lo que ha creado esta prueba, por su numero.
  // -------------------------------------------------------------------------
  foreach ($basura['lineas'] as $id) {
    $almacenInventario->loadUnchanged($id)?->delete();
  }
  foreach (array_reverse($basura['producto']) as $id) {
    try {
      $almacenProducto->loadUnchanged($id)?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar el producto %s: %s\n", $id, $e->getMessage());
    }
  }
  foreach ($basura['terminos'] as $id) {
    try {
      $almacenTerminos->loadUnchanged($id)?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar el termino %s: %s\n", $id, $e->getMessage());
    }
  }
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. La talla dice lo que cuesta cada material y lo que suman.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";
