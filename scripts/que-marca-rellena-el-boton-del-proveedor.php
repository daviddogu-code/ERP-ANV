<?php

/**
 * @file
 * Cual de las marcas rellena de verdad el boton 'Add new material' del proveedor.
 *
 *   php vendor\bin\drush.php scr scripts/que-marca-rellena-el-boton-del-proveedor.php
 *
 * La cabecera de la pantalla block_2 de la vista tec_inventory es un area de
 * texto a medida con un boton que abre el formulario de material nuevo con el
 * proveedor ya puesto en la direccion. Hoy esta escrita con {{ id }}, que es
 * una marca de columna de fila, y una cabecera de vista no ve las columnas de
 * las filas: sale un hueco vacio.
 *
 * Las marcas que si existen en un area son las del argumento, y son dos, que no
 * valen lo mismo:
 *
 *   {{ arguments.id }}      el titulo del argumento, que un validador puede
 *                           haber cambiado por la etiqueta de la ficha
 *   {{ raw_arguments.id }}  el valor del argumento tal cual
 *
 * Este guion las prueba todas contra la cabecera de verdad, con un proveedor de
 * verdad, y ensena que sale de cada una. Asi se elige con pruebas y no de
 * memoria.
 *
 * No cambia nada: cambia el texto de la cabecera solo en memoria, sobre el
 * manejador ya construido, y no guarda la vista en ningun momento.
 */

$bd = \Drupal::database();
\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

// Un proveedor que tenga materiales, para que la cabecera tenga contexto.
$proveedor = (string) $bd->select('taxonomy_term__field_tec_vendor', 'v')
  ->fields('v', ['field_tec_vendor_target_id'])
  ->range(0, 1)
  ->execute()
  ->fetchField();

if ($proveedor === '') {
  echo "\n  Ningun material tiene proveedor: sin argumento no hay nada que probar.\n\n";
  return;
}

$titulo('Se prueba con el proveedor ' . $proveedor);
$ficha = \Drupal::entityTypeManager()->getStorage('tec_crm')->load($proveedor);
printf("  se llama: %s\n", $ficha ? $ficha->label() : '(ya no existe)');

// Se construye la pantalla una sola vez. Al construirla, la vista deja
// preparadas las marcas del argumento en build_info['substitutions'], que es
// justo lo que lee el area para rellenar el texto.
$ejecutable = \Drupal::entityTypeManager()->getStorage('view')->load('tec_inventory')->getExecutable();
$ejecutable->setDisplay('block_2');
$ejecutable->setArguments([$proveedor]);
$ejecutable->preExecute();
$ejecutable->build('block_2');

$titulo('Las marcas que la vista deja preparadas para las areas');
$preparadas = $ejecutable->build_info['substitutions'] ?? [];
if (!$preparadas) {
  echo "  ninguna\n";
}
foreach ($preparadas as $marca => $valor) {
  printf("  %-26s %s\n", $marca, var_export($valor, TRUE));
}

$area = $ejecutable->header['area_text_custom'] ?? NULL;
if (!$area) {
  echo "\n  La cabecera no tiene el area de texto a medida. Nada que probar.\n\n";
  return;
}

$original = $area->options['content'];

$variantes = [
  'la de ahora: {{ id }}' => '<a href="/admin/structure/taxonomy/manage/tec_inventory/add/?target_id={{ id }}&destination=/tec_crm/{{ id }}">Add new material</a>',
  '{{ arguments.id }}' => '<a href="/admin/structure/taxonomy/manage/tec_inventory/add/?target_id={{ arguments.id }}&destination=/tec_crm/{{ arguments.id }}">Add new material</a>',
  '{{ raw_arguments.id }}' => '<a href="/admin/structure/taxonomy/manage/tec_inventory/add/?target_id={{ raw_arguments.id }}&destination=/tec_crm/{{ raw_arguments.id }}">Add new material</a>',
];

$titulo('Que sale con cada marca');
$dibujante = \Drupal::service('renderer');
foreach ($variantes as $comoSeLlama => $contenido) {
  $area->options['content'] = $contenido;
  $salida = $area->render(FALSE);
  $html = (string) $dibujante->renderInIsolation($salida);
  $enlace = preg_match('~href="([^"]*)"~', $html, $coincide)
    ? html_entity_decode($coincide[1])
    : '(no sale enlace)';
  printf("  %-24s %s\n", $comoSeLlama, $enlace);
  $vale = (bool) preg_match('~target_id=' . preg_quote($proveedor, '~') . '(&|$)~', $enlace);
  printf("  %-24s lleva el proveedor %s: %s\n\n", '', $proveedor, $vale ? 'SI' : 'NO');
}

// Se deja como estaba, aunque no se guarde, para que nadie se confunda si
// alguien anade mas comprobaciones detras.
$area->options['content'] = $original;

$titulo('Y a donde vuelve el destination');
// Las dos formas de nombrar la ficha del proveedor. Interesa saber cual es la
// que el sitio da por buena, porque destination se sigue tal cual.
foreach (['/tec_crm/' . $proveedor, '/supplier/' . $proveedor] as $ruta) {
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath($ruta);
  $interna = \Drupal::service('path_alias.manager')->getPathByAlias($ruta);
  printf("  %-20s alias: %-24s camino interno: %s\n", $ruta, $alias, $interna);
}

echo "\n";
