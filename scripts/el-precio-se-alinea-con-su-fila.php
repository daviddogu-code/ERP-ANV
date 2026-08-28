<?php

/**
 * @file
 * En el catalogo de una marca, la casilla del precio va en la linea de su fila.
 *
 *   php vendor\bin\drush.php scr scripts/el-precio-se-alinea-con-su-fila.php
 *
 * Todas las columnas de esa tabla son texto menos una, que es una casilla para
 * escribir. Drupal envuelve cada casilla en un `.form-item` que trae margen
 * arriba y abajo -- el sitio de una etiqueta que aqui esta escondida -- y esos
 * dos margenes eran todo el problema: el de arriba bajaba la casilla por debajo
 * de la linea de su fila, y el de abajo hacia la fila mas alta que nada de lo
 * que hay dentro.
 *
 * Se arregla con dos reglas, y hacen falta las dos: quitar los margenes deja la
 * celda exactamente tan alta como la casilla, y centrar las celdas pone el
 * medio de la casilla a la altura del medio de las palabras. Solo centrar
 * dejaria la casilla medio margen alta.
 *
 * Esta prueba mira las tres cosas de las que depende, que son tres sitios
 * distintos donde esto se puede volver a romper sin que se note:
 *
 *   1. Que las dos reglas existen y **no estan encerradas** dentro de otra.
 *   2. Que la hoja **viaja con la pantalla**, que es lo que la pone en la ficha
 *      de la marca este donde este esa ficha manana.
 *   3. Que el **marcado sigue teniendo la forma** que las reglas buscan: la
 *      casilla dentro de un `.form-item`, dentro de la celda del precio, dentro
 *      de un contenedor que dice que pantalla es. Si Views cambiara cualquiera
 *      de esas tres clases, las reglas dejarian de casar sin dar error.
 *
 * No crea nada ni borra nada.
 */

use Drupal\views\Views;

const HOJA = 'modules/custom/admin_form_styles/css/brand-catalogue.css';
const VISTA = 'tec_products';
const PANTALLA = 'brand_catalogue';

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
print "  El precio se alinea con su fila\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. Las dos reglas.
// ---------------------------------------------------------------------------
print "Las reglas\n";
print str_repeat('-', 70) . "\n";

$css = is_file(DRUPAL_ROOT . '/' . HOJA) ? (string) file_get_contents(DRUPAL_ROOT . '/' . HOJA) : '';
printf("  %s: %s\n", HOJA, $css === '' ? 'no existe' : strlen($css) . ' caracteres');

// El selector tiene que empezar el renglon: lo que se vigila es que no le hayan
// puesto nada delante, que es como se encierra una regla en un sitio mas
// pequeno del que le toca.
$sinMargen = (bool) preg_match(
  '/^\.view-id-tec_products\.view-display-id-brand_catalogue td\.views-field-form-field-field-tec-price \.form-item\s*\{[^}]*margin:\s*0/m',
  $css);
$centrado = (bool) preg_match(
  '/^\.view-id-tec_products\.view-display-id-brand_catalogue td\s*\{[^}]*vertical-align:\s*middle/m',
  $css);

$mirar('la casilla del precio no arrastra los margenes de su etiqueta', $sinMargen,
  'falta la regla que pone margin: 0 en el .form-item');
$mirar('y las celdas de la fila van centradas', $centrado,
  'falta la regla que pone vertical-align: middle en los td');

// ---------------------------------------------------------------------------
// 2. Que la hoja viaja con la pantalla.
// ---------------------------------------------------------------------------
print "\n";
print "Donde va enganchada\n";
print str_repeat('-', 70) . "\n";

$biblioteca = \Drupal::service('library.discovery')->getLibraryByName('admin_form_styles', 'brand-catalogue');
$hojas = array_map(fn(array $f) => ltrim((string) $f['data'], '/'), $biblioteca['css'] ?? []);
printf("  biblioteca admin_form_styles/brand-catalogue: %s\n", implode(', ', $hojas) ?: 'ninguna hoja');

$mirar('la hoja esta en la biblioteca de la pantalla', in_array(HOJA, $hojas, TRUE),
  implode(', ', $hojas));
$mirar('y la cuelga la pantalla, no una direccion', function_exists('admin_form_styles_views_pre_render'),
  'admin_form_styles_views_pre_render()');

// ---------------------------------------------------------------------------
// 3. Que el marcado tiene la forma que las reglas buscan.
// ---------------------------------------------------------------------------
print "\n";
print "La forma del marcado\n";
print str_repeat('-', 70) . "\n";

// Una marca que tenga tallas: da igual cual, lo que se prueba es la forma.
$marcas = $gestor->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_brands')
  ->sort('tid')
  ->execute();

$conFilas = NULL;
$html = '';
foreach ($marcas as $tid) {
  $vista = Views::getView(VISTA);
  $vista->setDisplay(PANTALLA);
  $vista->setArguments([$tid]);
  $vista->preExecute();
  $vista->execute();
  if (count($vista->result)) {
    $conFilas = $tid;
    $html = (string) \Drupal::service('renderer')->renderInIsolation($vista->render());
    $vista->destroy();
    break;
  }
  $vista->destroy();
}

if (!$conFilas) {
  print "  Ninguna marca tiene tallas todavia. Esta parte no se puede probar.\n";
}
else {
  $marca = $gestor->getStorage('taxonomy_term')->load($conFilas);
  printf("  marca %s (%d)\n", $marca->label(), $conFilas);

  $documento = new \DOMDocument();
  @$documento->loadHTML('<?xml encoding="utf-8" ?>' . $html);
  $rutas = new \DOMXPath($documento);

  // Se le pregunta al marcado exactamente lo que dicen las reglas, en vez de
  // buscar tres trozos de texto y suponer que uno esta dentro del otro.
  $contenedor = '//*[contains(@class, "view-id-tec_products") and contains(@class, "view-display-id-brand_catalogue")]';
  $celda = $contenedor . '//td[contains(@class, "views-field-form-field-field-tec-price")]';

  $contenedores = $rutas->query($contenedor);
  $celdas = $rutas->query($celda);
  $casillas = $rutas->query($celda . '//*[contains(@class, "form-item")]//input');
  $otras = $rutas->query($contenedor . '//tbody//td');

  printf("  contenedores %d, celdas de precio %d, casillas dentro de un .form-item %d, celdas en total %d\n",
    $contenedores->length, $celdas->length, $casillas->length, $otras->length);

  $mirar('la pantalla se sigue anunciando con las dos clases que las reglas piden',
    $contenedores->length > 0, 'no hay ningun contenedor view-id-tec_products.view-display-id-brand_catalogue');
  $mirar('la columna del precio se sigue llamando igual', $celdas->length > 0,
    'no hay ninguna celda views-field-form-field-field-tec-price');
  $mirar('y la casilla sigue viniendo envuelta en un .form-item, que es lo que se corrige',
    $casillas->length > 0, 'la casilla ya no cuelga de un .form-item');
  $mirar('las demas celdas de la fila son las que se centran con ella', $otras->length > $celdas->length,
    'la fila no tiene mas celdas que la del precio');
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. El precio va en la linea de su fila.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";
