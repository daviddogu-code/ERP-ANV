<?php

/**
 * @file
 * Deja de esconder los botones de crear cuando la lista esta vacia.
 *
 * Un area de cabecera con `empty: false` no se pinta si la vista no devuelve
 * filas. Para un boton de exportar esta bien, porque no hay nada que exportar.
 * Para el boton de crear es al reves: la lista vacia es justo el momento en el que
 * ese boton es la unica manera de empezar.
 *
 * El 14 de agosto se arreglaron diez asi recorriendo las portadas. Quedaron cinco
 * que ese barrido no podia ver, porque estan en displays a los que no llega
 * ninguna portada. Se arreglan cuatro; el de patrones se deja a proposito, porque
 * esta pendiente de decidir si Patrones se retira del ERP y no tiene sentido
 * arreglarle un boton a algo que puede desaparecer.
 *
 * Uso: drush php:script scripts/que-no-se-esconda-el-boton-de-crear
 *      drush php:script scripts/que-no-se-esconda-el-boton-de-crear -- de-verdad
 */

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

// Vista, display y por que importa cada uno.
$porArreglar = [
  ['tec_colors', 'page_1', 'la pagina de colores. El bloque de la portada ya estaba bien, asi que este se salvo del barrido de portadas'],
  ['tec_inventory_transactions', 'block_1', 'el historial de stock de un material. Un material recien creado no tiene movimientos, asi que este boton se esconde siempre el primer dia'],
  ['tec_products', 'block_2', 'un listado de productos. Con el catalogo por importar, la lista vacia es el estado normal'],
  ['tec_products', 'block_4', 'otro listado de productos, mismo caso'],
];

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad ? " DE VERDAD: se escribe\n" : " ENSAYO: anade -- de-verdad para hacerlo\n";
echo str_repeat('=', 82) . "\n\n";

foreach ($porArreglar as [$vistaId, $displayId, $motivo]) {
  $configuracion = \Drupal::configFactory()->getEditable('views.view.' . $vistaId);
  $displays = $configuracion->get('display');

  $area = &$displays[$displayId]['display_options']['header']['area_text_custom'];
  if (!isset($area)) {
    printf("  %-30s %-9s no tiene esa cabecera, se salta\n", $vistaId, $displayId);
    continue;
  }

  printf("  %-30s %-9s %s\n", $vistaId, $displayId, !empty($area['empty']) ? 'ya estaba bien' : 'se destapa');
  echo '      ' . $motivo . "\n\n";

  $area['empty'] = TRUE;
  unset($area);

  if ($deVerdad) {
    $configuracion->set('display', $displays)->save();
  }
}

echo str_repeat('=', 82) . "\n";
echo " Se deja a proposito: tec_patterns / block_1, pendiente de decidir si se retira\n";
echo str_repeat('=', 82) . "\n\n";
