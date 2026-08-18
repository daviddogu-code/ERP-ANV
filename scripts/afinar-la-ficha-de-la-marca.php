<?php

/**
 * @file
 * Dos ajustes de configuracion alrededor de la marca.
 *
 *   php vendor\bin\drush.php scr scripts/afinar-la-ficha-de-la-marca.php
 *
 * 1. APAGAR EL CANAL RSS DE LAS FICHAS DE TERMINO.
 *
 * En la ficha de cualquier termino sale un icono naranja que lleva a
 * `/taxonomy/term/NN/feed`. Lo pone la vista de Drupal `taxonomy_term`, que tiene
 * una pantalla de tipo canal enganchada a la pagina, y ese canal lista los NODOS
 * etiquetados con el termino. Aqui no hay nodos etiquetados con nada: el
 * contenido del ERP son entidades propias, asi que el canal esta
 * permanentemente vacio.
 *
 * Se apaga la pantalla en vez de borrarla. Apagada, Drupal ni la engancha a la
 * pagina -o sea, se va el icono- ni le da ruta, asi que la direccion contesta un
 * 404. Y si algun dia hiciera falta, se vuelve a encender con una casilla.
 *
 * Afecta a las fichas de TODOS los vocabularios, no solo a marcas, porque esa
 * vista es la que dibuja la ficha de cualquier termino. Es lo que se quiere: el
 * canal esta igual de vacio en colores, tallas y materiales.
 *
 * 2. QUE LA MARCA VENGA PUESTA AL CREAR UN PRODUCTO DESDE SU CATALOGO.
 *
 * El boton «+ Product» del catalogo de una marca ya sabe de que marca sale, asi
 * que no tiene sentido volver a preguntarlo. El modulo `epp` rellena un campo con
 * lo que venga en la direccion, y el campo de cliente del producto ya lo usa con
 * la clave `target_id`. Aqui se le da al campo de marca su propia clave,
 * `brand_id`, para que las dos no se pisen.
 *
 * `epp` solo escribe si el token se resuelve del todo, o sea que entrar al
 * formulario sin `?brand_id=` no cambia nada, y valida antes de guardar, asi que
 * un numero que no sea de una marca se descarta en vez de colarse.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir.
 */

use Drupal\field\Entity\FieldConfig;

const CANAL = 'feed_1';
const CLAVE = 'brand_id';

$gestor = \Drupal::entityTypeManager();

print "\n";
print "=====================================================================\n";
print "  Afinar la ficha de la marca\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. El canal RSS de las fichas de termino.
// ---------------------------------------------------------------------------
print "El canal RSS\n";
print str_repeat('-', 70) . "\n";

$vista = $gestor->getStorage('view')->load('taxonomy_term');
if (!$vista) {
  print "  No esta la vista taxonomy_term. Nada que apagar.\n";
}
else {
  $pantallas = $vista->get('display');
  if (!isset($pantallas[CANAL])) {
    print "  La vista no tiene la pantalla " . CANAL . ". Nada que apagar.\n";
  }
  elseif (($pantallas[CANAL]['display_options']['enabled'] ?? TRUE) === FALSE) {
    printf("  %-58s ya estaba apagado\n", 'el canal de las fichas de termino');
  }
  else {
    $pantallas[CANAL]['display_options']['enabled'] = FALSE;
    $vista->set('display', $pantallas);
    $vista->save();
    printf("  %-58s apagado\n", 'el canal de las fichas de termino');
  }
}

// ---------------------------------------------------------------------------
// 2. La marca puesta desde la direccion.
// ---------------------------------------------------------------------------
print "\nLa marca al crear un producto\n";
print str_repeat('-', 70) . "\n";

$campo = FieldConfig::loadByName('tec_product', 'tec_product', 'field_tec_brand');
if (!$campo) {
  print "  No esta el campo de marca del producto. No sigo.\n\n";
  return;
}

$queria = '[current-page:query:' . CLAVE . ']';
if ($campo->getThirdPartySetting('epp', 'value') === $queria) {
  printf("  %-58s ya estaba\n", 'se rellena con ?' . CLAVE . '=');
}
else {
  $campo->setThirdPartySetting('epp', 'value', $queria);
  // Solo al crear. En una ficha que ya existe, la direccion no manda.
  $campo->setThirdPartySetting('epp', 'on_update', 0);
  $campo->save();
  printf("  %-58s puesto\n", 'se rellena con ?' . CLAVE . '=');
}

// Guardando por la via de la entidad, Drupal apunta solo que este campo ahora
// depende del modulo que lo rellena.
$dependencias = $campo->getDependencies()['module'] ?? [];
printf("  %-58s %s\n", 'y el campo dice de quien depende',
  in_array('epp', $dependencias, TRUE) ? 'epp apuntado' : 'FALTA epp');

print "\n=====================================================================\n";
print "  Hecho. Queda exportar la configuracion.\n";
print "=====================================================================\n\n";
