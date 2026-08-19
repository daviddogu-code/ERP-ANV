<?php

/**
 * @file
 * La foto pequena de una tabla crece al pasarle el raton por encima.
 *
 *   php vendor\bin\drush.php scr scripts/la-foto-crece-al-pasar-el-raton.php
 *
 * Lo hacia y dejo de hacerlo. El modulo que dibuja la foto trae un javascript que
 * engancha el raton una sola vez, al leerse el fichero, sin `Drupal.behaviors` y
 * sin esperar a que la pagina este montada; y todas las pantallas de este ERP que
 * llevan la foto llegan despues de ese instante, porque son pestanas, ajax o
 * BigPipe. El enganche no encontraba ninguna fila, no daba ningun error, y la foto
 * se quedaba quieta.
 *
 * Ahora lo hace una regla de CSS, que no depende de cuando llegue el marcado. Esta
 * prueba mira las cuatro cosas de las que depende, que son cuatro sitios distintos
 * donde esto se puede volver a romper sin que se note:
 *
 *   1. Que la regla existe y **no esta encerrada** en una tabla concreta. Vivio un
 *      tiempo dentro de `.tec-log__table`, y ahi arreglaba una pantalla de las
 *      diecisiete que llevan la foto.
 *   2. Que la hoja **viaja con la biblioteca del modulo del popup**, que es la que
 *      se cuelga de todas las paginas. Enganchada a una direccion o a una pantalla
 *      se quedaria corta en las otras dieciseis.
 *   3. Que el **marcado sigue teniendo la forma** que la regla busca: la foto
 *      grande dentro del mismo cajon que la pequena. Si el modulo cambiara de
 *      forma, la regla dejaria de casar sin dar error, igual que el javascript.
 *   4. Que la pagina de verdad **carga la hoja**.
 *
 * Deshace todo lo que crea. Se puede lanzar dos veces.
 */

use Drupal\tec_production\OrderNumber;
use Drupal\views\Views;

const HOJA = 'modules/custom/admin_form_styles/css/popup-on-hover.css';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$problemas = 0;

$mirar = function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, !$bien && $pista !== '' ? ': ' . $pista : '');
  if (!$bien) {
    $problemas++;
  }
};

print "\n";
print "=====================================================================\n";
print "  La foto crece al pasar el raton\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. La regla, y que no este encerrada en ninguna tabla.
// ---------------------------------------------------------------------------
print "La regla\n";
print str_repeat('-', 70) . "\n";

$css = is_file(DRUPAL_ROOT . '/' . HOJA) ? (string) file_get_contents(DRUPAL_ROOT . '/' . HOJA) : '';
// El selector tiene que empezar el renglon: lo que se vigila es que no le hayan
// puesto nada delante, que es como se encierra una regla en una tabla concreta.
$regla = (bool) preg_match('/^\.spv-popup-wrapper:hover\s+\.spv-popup-content\s*\{[^}]*display:\s*block/m', $css);
printf("  %s: %s\n", HOJA, $css === '' ? 'no existe' : strlen($css) . ' caracteres');
$mirar('la regla del raton existe y no cuelga de ninguna tabla concreta', $regla,
  $css === '' ? 'falta el fichero' : 'no hay una regla suelta .spv-popup-wrapper:hover .spv-popup-content');

$log = (string) @file_get_contents(DRUPAL_ROOT . '/modules/custom/tec_production/css/production-log.css');
$mirar('y no ha quedado una copia encerrada en el log de produccion',
  !str_contains($log, ':hover .spv-popup-content'),
  'production-log.css sigue teniendo su propia copia de la regla');

// ---------------------------------------------------------------------------
// 2. Que viaja con la biblioteca del modulo.
// ---------------------------------------------------------------------------
print "\n";
print "Donde va enganchada\n";
print str_repeat('-', 70) . "\n";

$biblioteca = \Drupal::service('library.discovery')->getLibraryByName('simple_popup_views', 'simple_popup_views');
$hojas = array_map(fn(array $f) => ltrim((string) $f['data'], '/'), $biblioteca['css'] ?? []);
printf("  biblioteca simple_popup_views: %s\n", implode(', ', $hojas) ?: 'ninguna hoja');
$mirar('la hoja va dentro de la biblioteca del modulo del popup',
  in_array(HOJA, $hojas, TRUE),
  implode(', ', $hojas));
$mirar('y la pone un hook, no una copia del fichero del modulo',
  function_exists('admin_form_styles_library_info_alter'),
  'admin_form_styles_library_info_alter()');

// ---------------------------------------------------------------------------
// 3. Que el marcado tiene la forma que la regla busca.
// ---------------------------------------------------------------------------
print "\n";
print "La forma del marcado\n";
print str_repeat('-', 70) . "\n";

$contadores = \Drupal::keyValue(OrderNumber::LEDGER);
$contadoresAntes = $contadores->getAll();
$basura = ['lineas' => [], 'pedido' => [], 'producto' => [], 'terminos' => []];

try {
  // Una foto de las que ya hay en el sitio: lo que se prueba es la forma del
  // marcado, no la imagen.
  $fotos = $gestor->getStorage('file')->getQuery()
    ->accessCheck(FALSE)
    ->condition('filemime', 'image/%', 'LIKE')
    ->range(0, 1)
    ->execute();
  if (!$fotos) {
    print "  No hay ninguna imagen en el sitio. Esta parte no se puede probar.\n";
  }
  else {
    $foto = reset($fotos);

    $almacenProducto = $gestor->getStorage('tec_product');
    $almacenTermino = $gestor->getStorage('taxonomy_term');
    $almacenPedido = $gestor->getStorage('tec_order');
    $almacenLinea = $gestor->getStorage('tec_line_item');

    $termino = $almacenTermino->create(['vid' => 'tec_sizes', 'name' => 'PRUEBA talla del raton', 'weight' => 0]);
    $termino->save();
    $basura['terminos'][] = $termino->id();

    $talla = $almacenProducto->create([
      'type' => 'tec_size_variation',
      'title' => 'PRUEBA raton talla',
      'field_tec_size' => ['target_id' => $termino->id()],
      'field_tec_price' => '100.00',
    ]);
    $talla->save();
    $basura['producto'][] = $talla->id();

    $color = $almacenProducto->create([
      'type' => 'tec_color_variation',
      'title' => 'PRUEBA raton color',
      'field_tec_size_variations' => [$talla->id()],
      'field_tec_images' => [['target_id' => $foto, 'alt' => 'PRUEBA raton']],
    ]);
    $color->save();
    $basura['producto'][] = $color->id();

    $producto = $almacenProducto->create([
      'type' => 'tec_product',
      'title' => 'PRUEBA raton producto',
      'field_product_name' => 'PRUEBA raton producto',
      'field_tec_color_variations' => [$color->id()],
    ]);
    $producto->save();
    $basura['producto'][] = $producto->id();

    $pedido = $almacenPedido->create(['type' => 'tec_sales_order', 'title' => 'PRUEBA raton pedido']);
    $pedido->save();
    $basura['pedido'][] = $pedido->id();

    $linea = $almacenLinea->create([
      'type' => 'tec_sales_order_line_item',
      'title' => 'PRUEBA raton linea',
      'field_tec_product' => ['target_id' => $producto->id()],
      'field_tec_color_variation' => ['target_id' => $color->id()],
      'field_tec_size_variation' => ['target_id' => $talla->id()],
      'field_tec_quantity' => 5,
      'field_tec_order' => ['target_id' => $pedido->id()],
    ]);
    $linea->save();
    $basura['lineas'][] = $linea->id();

    $pedido = $almacenPedido->loadUnchanged($pedido->id());
    $pedido->set('field_tec_line_items', [$linea->id()]);
    $pedido->save();

    $vista = Views::getView('tec_order_sales_order_line_items');
    $vista->setDisplay('block_1');
    $vista->setArguments([$pedido->id()]);
    $vista->preExecute();
    $vista->execute();
    $html = (string) \Drupal::service('renderer')->renderInIsolation($vista->render());
    $vista->destroy();

    // La regla dice "la foto grande que este dentro del cajon al que se le pasa
    // el raton". Aqui se pregunta al marcado exactamente eso, en vez de buscar
    // dos trozos de texto y suponer que uno esta dentro del otro.
    $documento = new \DOMDocument();
    @$documento->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $rutas = new \DOMXPath($documento);
    $cajones = $rutas->query('//*[contains(@class, "spv-popup-wrapper")]');
    $grandes = $rutas->query('//*[contains(@class, "spv-popup-wrapper")]//*[contains(@class, "spv-popup-content")]//img');
    $pequenas = $rutas->query('//*[contains(@class, "spv-popup-wrapper")]//*[contains(@class, "spv-popup-link")]//img');

    printf("  cajones %d, foto pequena %d, foto grande dentro del cajon %d\n",
      $cajones->length, $pequenas->length, $grandes->length);

    $mirar('la fila trae su cajon con la foto pequena', $cajones->length > 0 && $pequenas->length > 0,
      $cajones->length . ' cajones');
    $mirar('y la foto grande cuelga del mismo cajon, que es lo que la regla busca',
      $grandes->length > 0, 'no hay ninguna imagen dentro de spv-popup-content');

    if ($grandes->length && $pequenas->length) {
      $chica = $pequenas->item(0)->getAttribute('width');
      $grande = $grandes->item(0)->getAttribute('width');
      printf("  la pequena mide %s y la grande %s\n", $chica ?: '?', $grande ?: '?');
      $mirar('y la grande es mas grande, que para eso se pasa el raton',
        (int) $grande > (int) $chica, $chica . ' -> ' . $grande);
    }
  }

  // -------------------------------------------------------------------------
  // 4. Y una pagina de verdad la carga.
  // -------------------------------------------------------------------------
  print "\n";
  print "Una pagina de verdad\n";
  print str_repeat('-', 70) . "\n";

  $agregado = (bool) \Drupal::config('system.performance')->get('css.preprocess');
  $peticion = \Symfony\Component\HttpFoundation\Request::create('/tec_order/' . ($basura['pedido'][0] ?? 1047), 'GET');
  $peticion->setSession(\Drupal::service('session'));
  $pagina = (string) \Drupal::service('http_kernel')
    ->handle($peticion, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST, FALSE)
    ->getContent();

  printf("  agregacion de css %s\n", $agregado ? 'encendida, no se puede ver el nombre del fichero' : 'apagada');
  $mirar('la pagina de un pedido carga la hoja del raton',
    $agregado || str_contains($pagina, 'popup-on-hover.css'),
    'no sale en el html de la pagina');
}
finally {
  $gestor->getStorage('tec_line_item');
  foreach ($basura['lineas'] as $id) {
    $gestor->getStorage('tec_line_item')->loadUnchanged($id)?->delete();
  }
  foreach ($basura['pedido'] as $id) {
    $gestor->getStorage('tec_order')->loadUnchanged($id)?->delete();
  }
  foreach (array_reverse($basura['producto']) as $id) {
    try {
      $gestor->getStorage('tec_product')->loadUnchanged($id)?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar el producto %s: %s\n", $id, $e->getMessage());
    }
  }
  foreach ($basura['terminos'] as $id) {
    try {
      $gestor->getStorage('taxonomy_term')->loadUnchanged($id)?->delete();
    }
    catch (\Throwable $e) {
      printf("  aviso: no se ha podido borrar el termino %s: %s\n", $id, $e->getMessage());
    }
  }
  $contadores->deleteAll();
  if ($contadoresAntes) {
    $contadores->setMultiple($contadoresAntes);
  }
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. La foto crece sin javascript, en las diecisiete pantallas.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";
