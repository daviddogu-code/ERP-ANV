<?php

/**
 * @file
 * Como esta la ficha de alta de material: unidades, decimales y invenciones.
 *
 *   php vendor\bin\drush.php scr scripts/como-esta-la-ficha-del-material.php
 *
 * Los tres fallos que el dueno encontro el 15 de agosto en esa pantalla son de
 * la misma familia: el formulario no dice en que unidad esta cada cifra, deja
 * inventar terminos al vuelo y guarda menos decimales de los que le escriben.
 * Ninguno da error. Los tres se ven mirando la configuracion, y por eso esto
 * es un script y no una nota en un documento.
 *
 * Mira tres cosas:
 *
 *   - Que los campos que llevan cantidad digan de que unidad hablan. Escribir
 *     el punto de pedido en metros cuando el campo cuenta unidades de
 *     inventario multiplico por dos y medio una sugerencia de compra, y no lo
 *     canto nadie porque la etiqueta decia solo "Reorder Point".
 *   - Que ningun campo de referencia pueda crear terminos al vuelo, salvo los
 *     que son de etiquetado libre a proposito. Asi entro el color "Blue " con
 *     un espacio detras, que es un color nuevo para la base y el mismo color
 *     para cualquiera que lo lea.
 *   - Que los dos factores guarden decimales suficientes. Un factor es un
 *     divisor: redondear 0,005 a 0,01 no descuadra el dato, parte por la mitad
 *     todas las cantidades que se calculen con el.
 *
 * No escribe nada.
 */

const CAMPOS_CON_UNIDAD = [
  'field_tec_stock_level' => 'UoS',
  'field_tec_safety_stock' => 'UoS',
  'field_tec_reorder_point' => 'UoS',
  'field_tec_moq_quantity' => 'UoP',
];

// Cuantos decimales necesita cada factor. Cinco los decidio el dueno el 15 de
// agosto: con dos no caben las conversiones finas, como los centimetros por
// metro cuadrado de un tejido.
const DECIMALES = [
  'field_tec_units' => 5,
  'field_tec_split_into' => 5,
];

// El etiquetado libre es libre a proposito: ahi si se inventan terminos.
const PUEDE_INVENTAR = ['field_tec_tags'];

$definiciones = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('taxonomy_term', 'tec_inventory');
$almacenes = \Drupal::service('entity_field.manager')
  ->getFieldStorageDefinitions('taxonomy_term');

$problemas = 0;

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %-42s %s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle);
  if (!$bien) {
    $problemas++;
  }
};

echo "\n";
echo "La etiqueta dice en que unidad esta la cifra\n";
echo str_repeat('-', 78) . "\n";

foreach (CAMPOS_CON_UNIDAD as $campo => $unidad) {
  if (!isset($definiciones[$campo])) {
    $mirar($campo, FALSE, 'no existe');
    continue;
  }
  $etiqueta = (string) $definiciones[$campo]->getLabel();
  $mirar($campo, str_contains($etiqueta, $unidad), '"' . $etiqueta . '", espera ' . $unidad);
}

echo "\n";
echo "Nadie inventa terminos al escribir\n";
echo str_repeat('-', 78) . "\n";

foreach ($definiciones as $campo => $definicion) {
  if ($definicion->getType() !== 'entity_reference') {
    continue;
  }
  $ajustes = $definicion->getSetting('handler_settings') ?? [];
  $inventa = !empty($ajustes['auto_create']);
  $permitido = in_array($campo, PUEDE_INVENTAR, TRUE);

  if (!$inventa && !$permitido) {
    continue;
  }
  $mirar(
    $campo,
    $permitido,
    $permitido ? 'etiquetado libre, se le permite' : 'crea terminos al vuelo'
  );
}

echo "\n";
echo "Los factores guardan los decimales que se les escriben\n";
echo str_repeat('-', 78) . "\n";

foreach (DECIMALES as $campo => $quiere) {
  if (!isset($almacenes[$campo])) {
    $mirar($campo, FALSE, 'no existe');
    continue;
  }
  $tiene = (int) $almacenes[$campo]->getSetting('scale');
  $cifras = (int) $almacenes[$campo]->getSetting('precision');
  $mirar(
    $campo,
    $tiene >= $quiere,
    $tiene . ' decimales de ' . $cifras . ' cifras, quiere ' . $quiere
  );
}

// De poco sirve guardar cinco decimales si la pantalla ensena dos: el dato
// esta bien y el que lo lee cree que esta mal. Y con los factores es peor que
// eso, porque el modulo de calculo de las vistas no lee la base, lee lo que la
// columna dibuja: la cifra recortada es la que entra en la formula. Un factor
// dibujado con el formateador de enteros llega a la formula como 0 en cuanto
// vale menos que uno, y las formulas dividen por el.
echo "\n";
echo "Y las pantallas los ensenan enteros\n";
echo str_repeat('-', 78) . "\n";

foreach (\Drupal::entityTypeManager()->getStorage('view')->loadMultiple() as $vista) {
  foreach ($vista->get('display') as $nombre => $display) {
    foreach ($display['display_options']['fields'] ?? [] as $campo) {
      $cual = $campo['field'] ?? '';
      if (!isset(DECIMALES[$cual]) || ($campo['plugin_id'] ?? '') !== 'field') {
        continue;
      }
      // Sin decimales apuntados no es que este bien: es que el formateador no
      // tiene donde apuntarlos, que es el caso peligroso.
      $dibuja = ($campo['type'] ?? '') === 'number_decimal'
        ? (int) ($campo['settings']['scale'] ?? 0)
        : 0;
      $mirar(
        $vista->id() . ' / ' . $nombre,
        $dibuja >= DECIMALES[$cual],
        $cual . ' con ' . ($dibuja ?: 'formateador ' . ($campo['type'] ?? '?')) . ($dibuja ? ' decimales' : '')
      );
    }
  }
}

echo "\n";
echo $problemas === 0
  ? "La ficha del material esta como tiene que estar.\n\n"
  : "HAY $problemas cosas mal en la ficha del material.\n\n";
