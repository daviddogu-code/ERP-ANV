<?php

/**
 * @file
 * El pie del pedido entra en la tabla, para que cada cifra caiga bajo su columna.
 *
 *   php vendor\bin\drush.php scr scripts/el-pie-del-pedido-se-mete-en-la-tabla.php
 *
 * Esta manana el pie del pedido guardado aprendio a contar piezas, y la cifra
 * salio donde podia: al borde derecho, encima del total en baht y lejos de la
 * columna Qty. El dueno lo miro y dijo lo obvio, que **el total de cantidades
 * tiene que ir debajo de las cantidades**.
 *
 * No se podia desde donde estaba. Ese pie era `attachment_1`, otra pantalla de
 * la misma vista enganchada detras de la tabla, o sea **una segunda tabla**. Dos
 * tablas no se ponen de acuerdo en el ancho de sus columnas: el navegador mide
 * cada una por su contenido, asi que una cifra de la de abajo cae bajo la
 * columna de la de arriba solo por casualidad, y deja de caer en cuanto un
 * nombre de producto es mas largo. Con CSS no se arregla; se arregla metiendo la
 * fila **en la misma tabla**, que es lo unico que comparte anchos consigo mismo.
 *
 * Asi que la fila la escribe ahora el servidor, en
 * `modules/custom/tec_production/src/OrderFoot.php`, y este guion solo hace la
 * otra mitad: retirar la pantalla que sobra. Si se dejara, el pedido tendria dos
 * pies, uno dentro de la tabla y otro debajo, diciendo lo mismo dos veces.
 *
 * ## Lo que se pierde y lo que no
 *
 * `attachment_1` servia a dos pantallas, el bloque del pedido (`block_1`) y la
 * impresion de la Pro Forma (`page_4`), y las dos son tablas de verdad, asi que
 * las dos reciben la fila nueva. **El papel sigue diciendo cuantas piezas son**,
 * que es como lo pidio el dueno esta manana.
 *
 * Compras ya no la usaba: sus tres lineas -- Subtotal, IVA, Total -- se las pone
 * `VatTotals` desde el 16 de agosto. Ese pie tambien es una tablita aparte y
 * tiene el mismo defecto, pero alli no hay ninguna cifra que deba caer bajo una
 * columna: son tres importes, y el importe va al final en las dos.
 *
 * Y con la pantalla se va el ultimo campo agregado de la vista, con lo que se va
 * tambien la trampa que traia: al agregar, Views cambia quien dibuja el campo, y
 * el manejador nuevo lee `separator` como separador de millares. Por eso el
 * total salia `฿44, 090.00`. Ahora el pie lo escribe PHP con `number_format()`,
 * igual que el de compras y que el del borrador, y las tres cifras del ERP se
 * escriben por fin de la misma manera.
 *
 * Se puede lanzar dos veces: la segunda no encuentra nada que quitar.
 */

const VISTA = 'tec_order_sales_order_line_items';
const PIE = 'attachment_1';

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  print "\n  No existe la vista " . VISTA . ".\n\n";
  return;
}

print "\n";
print "=====================================================================\n";
print "  El pie del pedido se mete en la tabla\n";
print "=====================================================================\n\n";

$pantallas = $vista->get('display');

if (isset($pantallas[PIE])) {
  $atendia = array_keys($pantallas[PIE]['display_options']['displays'] ?? []);
  unset($pantallas[PIE]);
  $vista->set('display', $pantallas);
  $vista->save();
  printf("  Retirada la pantalla %s, que ponia el pie debajo de: %s\n", PIE, implode(', ', $atendia) ?: 'nadie');
}
else {
  printf("  La pantalla %s ya no estaba.\n", PIE);
}

// Que no quede ninguna otra pantalla agregando, porque la unica que lo hacia
// era esta. Si aparece otra, lo dice: es la que volveria a traer el separador
// de millares raro.
$agregan = [];
foreach ($pantallas as $nombre => $pantalla) {
  foreach ($pantalla['display_options']['fields'] ?? [] as $clave => $campo) {
    if (($campo['group_type'] ?? 'group') !== 'group') {
      $agregan[] = $nombre . '/' . $clave;
    }
  }
}
printf("  Campos agregados que quedan en la vista: %s\n", $agregan ? implode(', ', $agregan) : 'ninguno');

// ---------------------------------------------------------------------------
// Como queda, en un pedido de verdad.
// ---------------------------------------------------------------------------
$ids = \Drupal::entityQuery('tec_order')
  ->accessCheck(FALSE)
  ->condition('type', 'tec_sales_order')
  ->sort('id', 'DESC')
  ->range(0, 1)
  ->execute();
$pedido = (int) reset($ids);

print "\n";
print "La ultima fila de la tabla del pedido " . $pedido . "\n";
print str_repeat('-', 70) . "\n";

\Drupal::currentUser()->setAccount(\Drupal\user\Entity\User::load(1));
$dibujo = views_embed_view(VISTA, 'block_1', $pedido) ?? ['#markup' => ''];
$html = (string) \Drupal::service('renderer')->renderPlain($dibujo);

$dom = new \DOMDocument();
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xpath = new \DOMXPath($dom);

$fila = $xpath->query('//tr[contains(@class, "tec-foot-row")]')->item(0);
if (!$fila) {
  print "  No hay fila de pie. Algo falta.\n\n";
  return;
}

foreach ($xpath->query('.//td', $fila) as $celda) {
  $texto = trim(preg_replace('/\s+/', ' ', $celda->textContent));
  printf("  %-22s %s\n",
    'colspan ' . ($celda->getAttribute('colspan') ?: '1'),
    $texto === '' ? '·' : $texto);
}

print "\n";
