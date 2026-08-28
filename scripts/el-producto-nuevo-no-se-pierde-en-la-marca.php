<?php

/**
 * @file
 * El boton + Product de la marca deja de tirar al que lo pulsa a un sitio ciego.
 *
 *   php vendor\bin\drush.php scr scripts/el-producto-nuevo-no-se-pierde-en-la-marca.php
 *
 * El catalogo de una marca es una lista de tallas: cada fila es una talla, y
 * desde ella se sube al color y al producto. Por eso un producto sin color no
 * tiene ninguna fila, y un color sin talla tampoco. Hasta hoy el boton
 * + Product de esa pantalla llevaba pegado un destination a la propia marca, que
 * pisa el redirect que trae de serie el formulario de ECK, y ese redirect es el
 * unico bueno: la ficha del producto, que es donde estan los botones
 * + Variation y + Size. Con el destination puesto, al guardar caias en una lista
 * de tallas donde el producto que acababas de crear no podia estar, sin nada mas
 * que el enlace del mensaje de estado para seguir. El 19 de agosto de 2026 el
 * dueno se lo encontro y no sabia si el producto se habia guardado.
 *
 * Asi que el destination se va, y la cadena queda entera:
 *
 *   + Product -> Save -> ficha del producto -> + Variation -> + Size -> la
 *   marca, con el producto ya dentro
 *
 * De paso la cabecera gana el boton Organize, que hasta hoy solo se alcanzaba
 * por la pestana de arriba -- y esa pestana la ensena un bloque que en este tema
 * esta limitado al rol administrator, asi que para los demas no existia.
 *
 * El aviso de lo que la lista no puede ensenar no esta aqui: lo escribe
 * BrandGaps, que lee los productos de la marca y los nombra debajo de la tabla.
 *
 * Se puede lanzar dos veces: la segunda no encuentra nada que cambiar.
 */

const VISTA = 'tec_products';
const PANTALLA = 'brand_catalogue';

/**
 * El destination que sobra, con el token de la marca dentro.
 *
 * Se busca tal cual esta escrito y tambien con el & escapado, porque la interfaz
 * de Views guarda una cosa o la otra segun por donde se haya pasado el texto.
 */
function fueraElDestino(string $texto): string {
  return (string) preg_replace(
    '/(&|&amp;)destination=\/taxonomy\/term\/\{\{\s*raw_arguments\.tid\s*\}\}/',
    '',
    $texto
  );
}

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  print "\n  No existe la vista " . VISTA . ".\n\n";
  return;
}

print "\n";
print "=====================================================================\n";
print "  El producto nuevo no se pierde en la marca\n";
print "=====================================================================\n\n";

$pantallas = $vista->get('display');
if (!isset($pantallas[PANTALLA])) {
  print "  No existe la pantalla " . PANTALLA . ".\n\n";
  return;
}

$opciones = &$pantallas[PANTALLA]['display_options'];
$cambios = 0;

print "1. El boton de anadir deja de decidir donde acabas\n";
print "--------------------------------------------------\n";

foreach (['header', 'empty'] as $zona) {
  foreach ($opciones[$zona] ?? [] as $clave => $area) {
    $texto = (string) ($area['content'] ?? '');
    if (!str_contains($texto, 'add/tec_product')) {
      continue;
    }
    $limpio = fueraElDestino($texto);
    if ($limpio === $texto) {
      printf("  %-7s %s ya no llevaba destination\n", $zona, $clave);
      continue;
    }
    $opciones[$zona][$clave]['content'] = $limpio;
    printf("  %-7s %s: fuera el destination a la marca\n", $zona, $clave);
    $cambios++;
  }
}

print "\n2. La cabecera gana el boton de ordenar\n";
print "---------------------------------------\n";

// El mismo par de botones que la pestana Products del cliente, en el mismo orden
// y con las mismas clases, porque son la misma pareja de acciones.
$organize = ' <span class="btn-default"><strong>'
  . '<a href="/taxonomy/term/{{ raw_arguments.tid }}/organize'
  . '?destination=/taxonomy/term/{{ raw_arguments.tid }}">Organize</a>'
  . '</strong></span>';

$puesto = FALSE;
foreach ($opciones['header'] ?? [] as $clave => $area) {
  $texto = (string) ($area['content'] ?? '');
  if (!str_contains($texto, 'add/tec_product')) {
    continue;
  }
  if (str_contains($texto, '/organize')) {
    printf("  header %s ya tenia el boton\n", $clave);
    $puesto = TRUE;
    continue;
  }
  // Dentro del div que alinea a la derecha, detras del + Product.
  $opciones['header'][$clave]['content'] = str_contains($texto, '</div>')
    ? preg_replace('/<\/div>\s*$/', $organize . '</div>', $texto, 1)
    : $texto . $organize;
  printf("  header %s: + Product | Organize\n", $clave);
  $puesto = TRUE;
  $cambios++;
}
if (!$puesto) {
  print "  no hay ninguna cabecera con el boton de anadir\n";
}

print "\n3. El texto de cuando no hay nada que ensenar\n";
print "---------------------------------------------\n";

// Decia "No products in this brand yet", y no siempre era verdad: una marca con
// dos productos sin talla ensenaba eso mismo. Ahora dice lo que pasa de verdad,
// y quien esta a medias lo nombra el aviso de BrandGaps justo debajo.
$viejo = 'No products in this brand yet';
$nuevo = 'Nothing to show here yet';
$explica = 'A product joins this list once it has a colour and that colour has a size.';

foreach ($opciones['empty'] ?? [] as $clave => $area) {
  $texto = (string) ($area['content'] ?? '');
  if (!str_contains($texto, $viejo)) {
    printf("  empty %s ya estaba redactado\n", $clave);
    continue;
  }
  $opciones['empty'][$clave]['content'] = str_replace(
    '<h4>' . $viejo . '</h4>',
    '<h4>' . $nuevo . '</h4><div>' . $explica . '</div>',
    $texto
  );
  printf("  empty %s: \"%s\" -> \"%s\"\n", $clave, $viejo, $nuevo);
  $cambios++;
}

unset($opciones);

if ($cambios) {
  $vista->set('display', $pantallas);
  $vista->save();
  printf("\n  Guardada la vista con %d cambios.\n", $cambios);
}
else {
  print "\n  Nada que cambiar en la vista.\n";
}

print "\n4. Como quedan los textos\n";
print "-------------------------\n";

$pantallas = $vista->get('display');
foreach (['header', 'empty'] as $zona) {
  foreach ($pantallas[PANTALLA]['display_options'][$zona] ?? [] as $clave => $area) {
    printf("  [%s %s]\n  %s\n\n", $zona, $clave, (string) ($area['content'] ?? ''));
  }
}

print "Ahora hay que exportar la configuracion para que esto quede en el disco.\n";
print "\n";
