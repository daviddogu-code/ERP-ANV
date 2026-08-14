<?php

/**
 * Le pone a cada portada el campo con la pantalla que abre, y lo rellena.
 *
 * Por que hace falta: la vista de la portada enlazaba el icono y el titulo al
 * nodo. Para las siete portadas que llevan su pantalla dentro en forma de bloque
 * eso esta bien, porque el nodo ES la pantalla y tiene alias bonito. Pero cuatro
 * pantallas del ERP no son nodos, son rutas del modulo tec_production, y esos
 * cuatro nodos no tienen alias: al pasar el raton salia `/node/11`, y la
 * redireccion no se veia hasta despues de pinchar.
 *
 * Con este campo el enlace deja de salir del nodo y sale del campo, o sea que el
 * raton ensena el destino de verdad en las once, y las cuatro redirecciones
 * dejan de hacer falta.
 *
 * Las siete primeras rutas no se escriben a mano: se sacan del alias real de cada
 * nodo. Asi no hay erratas y el campo dice lo que dice Drupal. Las cuatro que no
 * tienen alias son las unicas escritas aqui, porque su ruta la define el codigo
 * del modulo y no la base de datos.
 *
 * Uso: php vendor/bin/drush.php scr scripts/destino-de-las-portadas.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkTitleVisibility;
use Drupal\link\LinkItemInterface;

const CAMPO = 'field_tec_target';

// Las cuatro pantallas que viven en el codigo, no en un nodo. Ver
// modules/custom/tec_production/tec_production.routing.yml.
const RUTAS_DE_CODIGO = [
  11 => '/o/queue',
  12 => '/production/log',
  13 => '/production/report',
  14 => '/stock',
];

$etm = \Drupal::entityTypeManager();

print "\n";

// -----------------------------------------------------------------------------
// 1. El campo.
// -----------------------------------------------------------------------------
print "  --- 1. el campo ---\n\n";

if (!FieldStorageConfig::loadByName('node', CAMPO)) {
  FieldStorageConfig::create([
    'field_name' => CAMPO,
    'entity_type' => 'node',
    'type' => 'link',
    'cardinality' => 1,
  ])->save();
  print "      almacen creado (enlace, uno por ficha)\n";
}
else {
  print "      el almacen ya estaba\n";
}

if (!FieldConfig::loadByName('node', 'tec_landing_page', CAMPO)) {
  FieldConfig::create([
    'field_name' => CAMPO,
    'entity_type' => 'node',
    'bundle' => 'tec_landing_page',
    'label' => 'Destination',
    'description' => 'The screen this icon opens on the start page. If the screen lives inside this page, use this page\'s own path, like /p. If it is a module screen, use its route, like /stock.',
    'required' => TRUE,
    'settings' => [
      // Solo rutas de dentro: una portada no manda a otra web.
      'link_type' => LinkItemInterface::LINK_INTERNAL,
      // Sin texto de enlace: el texto ya lo pone el titulo de la ficha.
      'title' => LinkTitleVisibility::Disabled->value,
    ],
  ])->save();
  print "      campo creado en Landing page, obligatorio\n";
}
else {
  print "      el campo ya estaba\n";
}

// Que se pueda escribir. En el modo de pintado no hace falta tocar nada: la
// portada se monta con Layout Builder, y lo que no se coloca en una seccion no
// se pinta.
$repositorio = \Drupal::service('entity_display.repository');
$formulario = $repositorio->getFormDisplay('node', 'tec_landing_page', 'default');
if (!$formulario->getComponent(CAMPO)) {
  $formulario->setComponent(CAMPO, ['type' => 'link_default', 'weight' => 4])->save();
  print "      puesto en el formulario de edicion\n";
}
else {
  print "      ya estaba en el formulario\n";
}

$pintado = $repositorio->getViewDisplay('node', 'tec_landing_page', 'default');
if ($pintado->getComponent(CAMPO)) {
  $pintado->removeComponent(CAMPO)->save();
  print "      escondido en el modo de pintado\n";
}

print "\n";

// -----------------------------------------------------------------------------
// 2. Rellenarlo.
// -----------------------------------------------------------------------------
print "  --- 2. las once portadas ---\n\n";

$almacen = $etm->getStorage('node');
$ids = $almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_landing_page')
  ->sort('nid')
  ->execute();

$sinDestino = [];

foreach ($almacen->loadMultiple($ids) as $nodo) {
  $nid = (int) $nodo->id();

  // El alias manda cuando existe: es lo que Drupal considera la direccion de
  // esta ficha, y asi el campo no se queda desfasado si alguien lo cambia.
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
  $ruta = $alias !== '/node/' . $nid ? $alias : (RUTAS_DE_CODIGO[$nid] ?? '');
  $origen = $alias !== '/node/' . $nid ? 'alias' : 'ruta de codigo';

  if ($ruta === '') {
    $sinDestino[] = $nid . ' ' . $nodo->label();
    printf("      %-4s %-22s SIN DESTINO\n", $nid, $nodo->label());
    continue;
  }

  $nodo->set(CAMPO, ['uri' => 'internal:' . $ruta]);
  $nodo->save();

  printf("      %-4s %-22s %-22s (%s)\n", $nid, $nodo->label(), $ruta, $origen);
}

print "\n";

if ($sinDestino) {
  print "  Hay portadas sin destino, y el campo es obligatorio:\n";
  foreach ($sinDestino as $s) {
    print '      ' . $s . "\n";
  }
  print "\n";
}
else {
  printf("  Las %d portadas tienen destino.\n\n", count($ids));
}

// -----------------------------------------------------------------------------
// 3. Fuera la muleta.
// -----------------------------------------------------------------------------
// Las cuatro redirecciones de node/11 a node/14 se pusieron el 14 de agosto para
// que los iconos llevaran a algun sitio. Ya no hacen falta: el enlace sale del
// campo y va derecho a la pantalla. Y quedarse con ellas seria peor que borrarlas,
// porque dentro de un ano nadie sabria por que /node/14 salta a otro sitio.
print "  --- 3. las cuatro redirecciones que ya no hacen falta ---\n\n";

if ($sinDestino) {
  print "      no se tocan: hay portadas sin destino\n\n";
}
else {
  $almacenRedirecciones = $etm->getStorage('redirect');
  $borradas = 0;

  foreach (array_keys(RUTAS_DE_CODIGO) as $nid) {
    $idsRedireccion = $almacenRedirecciones->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source.path', 'node/' . $nid)
      ->execute();

    if (!$idsRedireccion) {
      printf("      node/%-3s ya no tenia\n", $nid);
      continue;
    }

    $almacenRedirecciones->delete($almacenRedirecciones->loadMultiple($idsRedireccion));
    printf("      node/%-3s borrada\n", $nid);
    $borradas++;
  }

  printf("\n      %d borradas, quedan %d redirecciones en el sitio\n\n",
    $borradas,
    (int) $almacenRedirecciones->getQuery()->accessCheck(FALSE)->count()->execute());
}
