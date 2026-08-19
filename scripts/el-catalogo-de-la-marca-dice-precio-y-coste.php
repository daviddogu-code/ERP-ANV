<?php

/**
 * @file
 * La ficha de una marca lista sus tallas, deja tocar el precio y dice el coste.
 *
 *   php vendor\bin\drush.php scr scripts/el-catalogo-de-la-marca-dice-precio-y-coste.php
 *
 * Monta una marca de prueba con un producto, un color y dos tallas, cada una con
 * su BoM y su precio, y comprueba cinco cosas que son cinco maneras distintas de
 * que esto se rompa sin dar error:
 *
 *   1. Que hay **una fila por talla** y no una por producto. Es el cambio entero:
 *      el precio de venta y el coste viven en la talla.
 *   2. Que el precio sale en una **casilla de escribir** y con el valor que tiene
 *      guardado, no como texto.
 *   3. Que al **enviar el formulario** el precio se guarda de verdad. Una casilla
 *      que se dibuja y no guarda es peor que no tenerla: parece que has cambiado
 *      el precio.
 *   4. Que el **coste** de cada fila es la suma de su BoM, y que no se confunden
 *      las filas.
 *   5. Que las tallas salen en el **orden del vocabulario** y no por su nombre. Se
 *      prueba a proposito con una talla que se llama AAA y pesa mas que una que se
 *      llama ZZZ: si el orden fuera alfabetico, saldrian al reves.
 *   6. Que lo que no cambia de una fila a la siguiente **se dice una vez**: el
 *      nombre del producto, su material, la foto y el color. Y que aun asi cada
 *      fila conserva lo suyo, que es talla, precio y coste.
 *
 * Deshace todo lo que crea. Se puede lanzar dos veces.
 */

use Drupal\Core\Form\EnforcedResponseException;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const VISTA = 'tec_products';
const PANTALLA = 'brand_catalogue';

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
 * Deja un texto de una tabla en una sola linea, sin etiquetas.
 */
$limpio = static fn(string $trozo): string => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($trozo))));

$basura = ['producto' => [], 'lineas' => [], 'terminos' => []];

print "\n";
print "=====================================================================\n";
print "  El catalogo de la marca dice precio y coste\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// Dos materiales de los que ya hay, con coste y distinto uno del otro.
// ---------------------------------------------------------------------------
$porCoste = [];
foreach ($almacenTerminos->loadMultiple($almacenTerminos->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->exists('field_tec_price')
  ->execute()) as $termino) {
  $coste = (float) ($termino->get('field_tec_price')->value ?? 0);
  if ($coste > 0) {
    $porCoste[(string) $coste] = $termino;
  }
}
if (count($porCoste) < 2) {
  print "  No hay dos materiales con coste de consumo. No se puede probar.\n\n";
  return;
}
krsort($porCoste, SORT_NUMERIC);
$caro = reset($porCoste);
$barato = end($porCoste);

try {
  // -------------------------------------------------------------------------
  // La marca de prueba, con un producto, un color y dos tallas.
  // -------------------------------------------------------------------------
  $marca = $almacenTerminos->create(['vid' => 'tec_brands', 'name' => 'PRUEBA marca del catalogo']);
  $marca->save();
  $basura['terminos'][] = $marca->id();

  // Dos tallas cuyo nombre va al contrario que su peso, para que el orden no
  // pueda salir bien por casualidad.
  $tallaGrande = $almacenTerminos->create(['vid' => 'tec_sizes', 'name' => 'PRUEBA AAA', 'weight' => 10]);
  $tallaGrande->save();
  $basura['terminos'][] = $tallaGrande->id();
  $tallaPequena = $almacenTerminos->create(['vid' => 'tec_sizes', 'name' => 'PRUEBA ZZZ', 'weight' => 0]);
  $tallaPequena->save();
  $basura['terminos'][] = $tallaPequena->id();

  $producto = $almacenProducto->create([
    'type' => 'tec_product',
    'title' => 'PRUEBA catalogo producto',
    'field_product_name' => 'PRUEBA catalogo producto',
    'field_tec_brand' => ['target_id' => $marca->id()],
  ]);
  // El material del producto es una lista corta; se coge el primer valor que
  // admita, que es lo que vera cualquiera que abra el formulario.
  $posibles = array_keys($producto->get('field_tec_product_material')->getFieldDefinition()->getSetting('allowed_values') ?? []);
  $material = $posibles[0] ?? NULL;
  if ($material !== NULL) {
    $producto->set('field_tec_product_material', $material);
  }
  $producto->save();
  $basura['producto'][] = $producto->id();

  $color = $almacenProducto->create([
    'type' => 'tec_color_variation',
    'title' => 'PRUEBA catalogo color',
  ]);
  $color->save();
  $basura['producto'][] = $color->id();

  $producto->set('field_tec_color_variations', [$color->id()]);
  $producto->save();

  // Dos tallas: la de nombre AAA con dos lineas de BoM y precio 1200, y la ZZZ
  // con una linea y precio 950.
  $tallas = [];
  foreach ([
    ['PRUEBA catalogo AAA', $tallaGrande, '1200.00', [['2', $caro], ['3', $barato]]],
    ['PRUEBA catalogo ZZZ', $tallaPequena, '950.00', [['1', $caro]]],
  ] as [$titulo, $termino, $precio, $lineas]) {
    $talla = $almacenProducto->create([
      'type' => 'tec_size_variation',
      'title' => $titulo,
      'field_tec_size' => ['target_id' => $termino->id()],
      'field_tec_price' => $precio,
    ]);
    $talla->save();
    $basura['producto'][] = $talla->id();

    foreach ($lineas as [$escrito, $cual]) {
      $linea = $almacenInventario->create([
        'type' => 'tec_bom_item',
        'title' => '',
        'field_tec_inventory' => ['target_id' => $cual->id()],
        'field_tec_quantity_input' => $escrito,
        'field_tec_size_variation' => ['target_id' => $talla->id()],
      ]);
      $linea->save();
      $basura['lineas'][] = $linea->id();
    }
    $tallas[$titulo] = $almacenProducto->loadUnchanged($talla->id());
  }

  $color = $almacenProducto->loadUnchanged($color->id());
  $color->set('field_tec_size_variations', array_map(fn($t) => $t->id(), array_values($tallas)));
  $color->save();

  printf("  Marca de prueba %d, producto %d, color %d, tallas %s\n",
    $marca->id(), $producto->id(), $color->id(),
    implode(' y ', array_map(fn($t) => $t->id(), array_values($tallas))));

  // El coste que tiene que decir cada fila, calculado aparte.
  $costes = [];
  foreach ($tallas as $titulo => $talla) {
    $suma = 0.0;
    foreach ($almacenProducto->loadUnchanged($talla->id())->get('field_tec_bom')->referencedEntities() as $linea) {
      $suma += (float) $linea->get('field_tec_quantity')->value
        * (float) $linea->get('field_tec_inventory')->entity->get('field_tec_price')->value;
    }
    $costes[$titulo] = number_format(round($suma, 2), 2, '.', ',');
  }

  // -------------------------------------------------------------------------
  // La tabla.
  // -------------------------------------------------------------------------
  $pantalla = Views::getView(VISTA);
  $pantalla->setDisplay(PANTALLA);
  $pantalla->setArguments([$marca->id()]);
  $pantalla->preExecute();
  $pantalla->execute();
  $html = (string) \Drupal::service('renderer')->renderInIsolation($pantalla->render());
  $pantalla->destroy();

  $cabeceras = [];
  if (preg_match('/<thead.*?<tr[^>]*>(.*?)<\/tr>/s', $html, $trozo)) {
    preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $trozo[1], $ths);
    $cabeceras = array_map($limpio, $ths[1] ?? []);
  }

  $filas = [];
  if (preg_match('/<tbody(.*?)<\/tbody>/s', $html, $cuerpo)) {
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $cuerpo[1], $trs);
    foreach ($trs[1] ?? [] as $tr) {
      preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $tr, $tds);
      $filas[] = $tds[1] ?? [];
    }
  }

  print "\n";
  print "Las columnas\n";
  print str_repeat('-', 70) . "\n";
  printf("  cabeceras: %s\n", implode(' | ', $cabeceras));

  foreach (['Product name', 'Material', 'Variation', 'Size', 'Sales price', 'Material cost'] as $columna) {
    $mirar('sale la columna ' . $columna, in_array($columna, $cabeceras, TRUE), implode(' | ', $cabeceras));
  }
  // Siete: la foto no lleva titulo.
  $mirar('y son siete, con la foto delante sin titulo',
    count($cabeceras) === 7 && ($cabeceras[0] ?? 'x') === '',
    count($cabeceras) . ' columnas');

  print "\n";
  print "Una fila por talla\n";
  print str_repeat('-', 70) . "\n";
  $mirar('salen las dos tallas, no una fila por producto',
    count($filas) === 2, count($filas) . ' filas');

  // -------------------------------------------------------------------------
  // El orden: por peso del termino, no por nombre.
  // -------------------------------------------------------------------------
  $columnaTalla = array_search('Size', $cabeceras, TRUE);
  $orden = [];
  foreach ($filas as $fila) {
    $orden[] = $limpio($fila[$columnaTalla] ?? '');
  }
  printf("  orden de tallas: %s\n", implode(' antes de ', $orden));
  $mirar('la talla de menos peso sale primero, aunque se llame ZZZ',
    ($orden[0] ?? '') === 'PRUEBA ZZZ' && ($orden[1] ?? '') === 'PRUEBA AAA',
    implode(', ', $orden));

  // -------------------------------------------------------------------------
  // El precio: una casilla, con lo que hay guardado dentro.
  // -------------------------------------------------------------------------
  print "\n";
  print "El precio se puede tocar\n";
  print str_repeat('-', 70) . "\n";

  $columnaPrecio = array_search('Sales price', $cabeceras, TRUE);
  $casillas = [];
  foreach ($filas as $indice => $fila) {
    $celda = $fila[$columnaPrecio] ?? '';
    if (preg_match('/name="(form_field_field_tec_price[^"]*)"/', $celda, $nombre)) {
      preg_match('/value="([^"]*)"/', $celda, $valor);
      $casillas[$indice] = ['nombre' => $nombre[1], 'valor' => $valor[1] ?? ''];
    }
  }
  printf("  casillas: %s\n", implode(', ', array_map(fn($c) => $c['nombre'] . ' = ' . $c['valor'], $casillas)) ?: 'ninguna');

  $mirar('cada fila lleva su casilla de precio', count($casillas) === 2, count($casillas) . ' casillas');
  $valores = array_map(fn($c) => (float) $c['valor'], $casillas);
  $mirar('y traen el precio que tiene la talla guardado',
    in_array(1200.0, $valores, TRUE) && in_array(950.0, $valores, TRUE),
    implode(', ', $valores));
  // El boton lo pone Views, y tiene que estar: sin ajax es la unica manera de
  // que lo escrito llegue a la talla.
  $mirar('y hay un boton de guardar', str_contains($html, 'type="submit"'), 'no sale');

  // -------------------------------------------------------------------------
  // El coste de cada fila.
  // -------------------------------------------------------------------------
  print "\n";
  print "El coste de cada talla\n";
  print str_repeat('-', 70) . "\n";

  $columnaCoste = array_search('Material cost', $cabeceras, TRUE);
  $columnaNombre = array_search('Product name', $cabeceras, TRUE);
  foreach ($filas as $indice => $fila) {
    $talla = $limpio($fila[$columnaTalla] ?? '');
    $dicho = $limpio($fila[$columnaCoste] ?? '');
    $esperado = $talla === 'PRUEBA AAA' ? $costes['PRUEBA catalogo AAA'] : $costes['PRUEBA catalogo ZZZ'];
    printf("  %-12s dice %-16s y tiene que decir %s\n", $talla, $dicho !== '' ? $dicho : 'nada', $esperado);
    $mirar('el coste de ' . $talla . ' es el de su BoM', str_contains($dicho, $esperado), $dicho);
    $mirar('y lo dice en bahts', str_contains($dicho, "\u{0E3F}"), $dicho);
  }
  $mirar('las dos filas no dicen el mismo coste',
    $costes['PRUEBA catalogo AAA'] !== $costes['PRUEBA catalogo ZZZ']
    && $limpio($filas[0][$columnaCoste] ?? '') !== $limpio($filas[1][$columnaCoste] ?? ''),
    'los dos costes coinciden, la prueba no valdria');

  // -------------------------------------------------------------------------
  // Lo que no cambia se dice una vez.
  // -------------------------------------------------------------------------
  print "\n";
  print "Y no se repite en cada fila\n";
  print str_repeat('-', 70) . "\n";

  $columnaMaterial = array_search('Material', $cabeceras, TRUE);
  $columnaColor = array_search('Variation', $cabeceras, TRUE);

  $nombrePrimera = $limpio($filas[0][$columnaNombre] ?? '');
  $nombreSegunda = $limpio($filas[1][$columnaNombre] ?? '');
  printf("  nombre: fila 1 \"%s\", fila 2 \"%s\"\n", $nombrePrimera, $nombreSegunda);

  $mirar('la primera fila del bloque dice de que producto es', $nombrePrimera !== '', 'sale vacia');
  $mirar('y la siguiente ya no lo repite', $nombreSegunda === '', 'dice ' . $nombreSegunda);
  $mirar('ni repite el material', $limpio($filas[1][$columnaMaterial] ?? '') === '',
    $limpio($filas[1][$columnaMaterial] ?? ''));
  $mirar('ni el color', $limpio($filas[1][$columnaColor] ?? '') === '',
    $limpio($filas[1][$columnaColor] ?? ''));
  // Lo que cambia en cada fila sigue estando: si esto se cayera, la tabla se
  // habria quedado en blanco de cintura para abajo.
  $mirar('pero la segunda fila conserva su talla, su precio y su coste',
    $limpio($filas[1][$columnaTalla] ?? '') !== ''
    && str_contains($filas[1][$columnaPrecio] ?? '', 'form_field_field_tec_price')
    && $limpio($filas[1][$columnaCoste] ?? '') !== '',
    'talla "' . $limpio($filas[1][$columnaTalla] ?? '') . '", coste "'
    . $limpio($filas[1][$columnaCoste] ?? '') . '"');

  // -------------------------------------------------------------------------
  // Enviar el formulario de verdad, desde la pagina de la marca.
  // -------------------------------------------------------------------------
  print "\n";
  print "Y guarda lo que se escribe\n";
  print str_repeat('-', 70) . "\n";

  $kernel = \Drupal::service('http_kernel');
  $direccion = '/taxonomy/term/' . $marca->id();
  $peticion = Request::create($direccion, 'GET');
  $peticion->setSession(\Drupal::service('session'));
  $pagina = (string) $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE)->getContent();

  // Sin mirar mayusculas: el ERP capitaliza el nombre de un producto al
  // guardarlo, asi que lo que se escribio en minusculas sale en la pagina con
  // cada palabra en mayuscula.
  $saleElProducto = stripos($pagina, 'PRUEBA catalogo producto') !== FALSE;
  $mirar('la ficha de la marca dibuja el catalogo',
    $saleElProducto,
    'no sale el producto; PRUEBA aparece ' . substr_count($pagina, 'PRUEBA') . ' veces, la tabla '
    . (str_contains($pagina, 'form_field_field_tec_price') ? 'si' : 'no') . ' esta');

  // El aspecto de la tabla lo pone una hoja de estilo que se engancha a la
  // pantalla, no a la direccion. Con la agregacion encendida el nombre del
  // fichero no se puede ver, y entonces esto no se puede comprobar asi.
  $agregado = (bool) \Drupal::config('system.performance')->get('css.preprocess');
  $mirar('y trae la hoja de estilo que la viste',
    $agregado || str_contains($pagina, 'brand-catalogue.css'),
    'no la carga');

  if (!$saleElProducto
    && preg_match('/<tbody(.*?)<\/tbody>/s', $pagina, $cuerpoPagina)
    && preg_match('/<tr[^>]*>(.*?)<\/tr>/s', $cuerpoPagina[1], $primera)) {
    preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $primera[1], $celdas);
    foreach ($celdas[1] ?? [] as $indice => $celda) {
      printf("    celda %d de la pagina: %s\n", $indice + 1, mb_substr($limpio($celda), 0, 90));
    }
  }

  $escondidos = [];
  foreach (['form_id', 'form_build_id', 'form_token'] as $cual) {
    if (preg_match('/name="' . $cual . '"[^>]*value="([^"]*)"/', $pagina, $trozo)
      || preg_match('/value="([^"]*)"[^>]*name="' . $cual . '"/', $pagina, $trozo)) {
      $escondidos[$cual] = $trozo[1];
    }
  }
  printf("  formulario: %s\n", $escondidos['form_id'] ?? 'no encontrado');

  // La casilla de la talla ZZZ, que es la primera fila.
  $cual = NULL;
  if (preg_match_all('/name="(form_field_field_tec_price\[[^"]*)"[^>]*value="([^"]*)"/', $pagina, $todas, PREG_SET_ORDER)) {
    foreach ($todas as $encontrada) {
      if ((float) $encontrada[2] === 950.0) {
        $cual = $encontrada[1];
      }
    }
  }
  $mirar('la casilla de la talla de 950 esta en la pagina de la marca', $cual !== NULL, 'no se encuentra');

  if ($cual !== NULL && isset($escondidos['form_id'])) {
    $cuerpo = [
      'form_id' => $escondidos['form_id'],
      'form_build_id' => $escondidos['form_build_id'] ?? '',
      'form_token' => $escondidos['form_token'] ?? '',
      'op' => 'Save',
    ];

    // El nombre de la casilla llega como
    // form_field_field_tec_price[1429][field_tec_price][0][value], y un navegador
    // manda eso anidado. Una clave con los corchetes dentro no es lo mismo: el
    // formulario no ve ningun valor y guarda cero filas, que es exactamente lo
    // que paso la primera vez que se escribio esta prueba.
    preg_match_all('/\[([^\]]*)\]/', $cual, $trozos);
    $camino = array_merge([strstr($cual, '[', TRUE)], $trozos[1]);
    $rama = &$cuerpo;
    foreach ($camino as $paso) {
      if (!isset($rama[$paso]) || !is_array($rama[$paso])) {
        $rama[$paso] = [];
      }
      $rama = &$rama[$paso];
    }
    $rama = '1010.50';
    unset($rama);

    $envio = Request::create($direccion, 'POST', $cuerpo);
    $envio->setSession(\Drupal::service('session'));

    // Al guardar, el formulario redirige, y en una peticion de dentro esa
    // redireccion no viaja como respuesta sino como excepcion. Cazarla es la
    // manera de leerla: sin esto la prueba se cae justo cuando funciona.
    try {
      $codigo = $kernel->handle($envio, HttpKernelInterface::SUB_REQUEST, FALSE)->getStatusCode();
    }
    catch (EnforcedResponseException $redireccion) {
      $codigo = $redireccion->getResponse()->getStatusCode();
    }

    $tallaZZZ = NULL;
    foreach ($tallas as $titulo => $talla) {
      if ($titulo === 'PRUEBA catalogo ZZZ') {
        $tallaZZZ = $almacenProducto->loadUnchanged($talla->id());
      }
    }
    $ahora = (float) $tallaZZZ->get('field_tec_price')->value;
    printf("  respuesta %d, precio de la talla ZZZ: %s\n", $codigo, $ahora);
    $mirar('el precio escrito en la tabla se guarda en la talla',
      abs($ahora - 1010.50) < 0.001,
      'esperaba 1010.50, dice ' . $ahora);
    $mirar('y la pagina contesta con una redireccion, no con un error',
      $codigo < 400, 'ha contestado ' . $codigo);
  }
}
finally {
  // -------------------------------------------------------------------------
  // Recoger.
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
  print "  Todo bien. La marca lista sus tallas, con precio y coste.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";
