<?php

/**
 * @file
 * Abre el hueco donde cada producto guarda su sitio dentro de su marca.
 *
 *   php vendor\bin\drush.php scr scripts/la-marca-ordena-sus-productos.php
 *
 * Hasta hoy el orden de los productos era del cliente, no de la marca: se
 * arrastraba en /tec_crm/N/reorder y se guardaba en la tabla de
 * draggableviews, una fila por cliente y por producto. Ocho clientes, ocho
 * ordenes distintos del mismo catalogo, y ninguno servia para lo que hacia
 * falta: la proforma. La vista que crea el borrador va por cliente, no puede
 * mirar el orden de otro, y las lineas salian como las devolvia la base de
 * datos.
 *
 * El sitio del producto es de la marca. Una marca son unas cuantas docenas de
 * productos que el dueño de la marca quiere ver en su orden -las gorras
 * primero, luego las camisetas, y dentro de cada una lo que se vende mas- y
 * ese orden es el mismo para los ocho clientes que compran esa marca. Asi que
 * el numero se guarda en el producto, que es lo unico que todas las pantallas
 * pueden leer: el catalogo de la marca, la pestaña del cliente, y sobre todo
 * la vista que crea las lineas de la proforma.
 *
 * Cuatro niveles de orden, y con este quedan los cuatro puestos:
 *
 *   marca    -> el delta de field_tec_brands en la ficha del cliente
 *   producto -> este campo, arrastrando en la pantalla de la marca
 *   color    -> el delta de field_tec_color_variations en el producto
 *   talla    -> el peso del termino en el vocabulario tec_sizes
 *
 * La siembra es alfabetica y nada mas: es un punto de partida para poder
 * arrastrar, no un orden que signifique algo. Y solo toca los productos que no
 * tienen sitio todavia, asi que se puede volver a lanzar sin borrar lo que
 * alguien haya arrastrado.
 *
 * Se puede lanzar dos veces: lo que ya existe se deja como esta.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

const CAMPO = 'field_tec_catalogue_position';

$hechos = 0;
$saltados = 0;

print "\n1. El campo\n";
print "-----------\n";

if (FieldStorageConfig::loadByName('tec_product', CAMPO)) {
  print "[ya  ] almacen tec_product." . CAMPO . "\n";
  $saltados++;
}
else {
  FieldStorageConfig::create([
    'field_name' => CAMPO,
    'entity_type' => 'tec_product',
    'type' => 'integer',
    'cardinality' => 1,
    'settings' => ['unsigned' => FALSE, 'size' => 'normal'],
  ])->save();
  print "[hecho] almacen tec_product." . CAMPO . " (integer)\n";
  $hechos++;
}

if (FieldConfig::loadByName('tec_product', 'tec_product', CAMPO)) {
  print "[ya  ] campo tec_product." . CAMPO . "\n";
  $saltados++;
}
else {
  FieldConfig::create([
    'field_name' => CAMPO,
    'entity_type' => 'tec_product',
    'bundle' => 'tec_product',
    'label' => 'Position in the brand',
    'description' => "Where this product sits in its brand's list. Set by dragging on the brand's Organize products screen, and read by every screen that lists products, the brand catalogue and the pro forma included. Not typed by hand, and not the same for two brands: the number starts again at 1 inside each one.",
    'required' => FALSE,
    'settings' => ['min' => 0, 'max' => NULL, 'prefix' => '', 'suffix' => ''],
    'default_value' => [['value' => 0]],
  ])->save();
  print "[hecho] campo tec_product." . CAMPO . " 'Position in the brand'\n";
  $hechos++;
}

print "\n2. Que no salga en la ficha ni en el formulario\n";
print "----------------------------------------------\n";

// El numero no se escribe a mano: se arrastra. Si sale en el formulario del
// producto hay dos sitios donde decir lo mismo, y el que gane sera el ultimo
// que alguien guardo sin mirar.
$repositorio = \Drupal::service('entity_display.repository');
foreach (['form' => 'getFormDisplay', 'view' => 'getViewDisplay'] as $cual => $metodo) {
  $display = $repositorio->$metodo('tec_product', 'tec_product');
  if ($display->getComponent(CAMPO) === NULL) {
    print "[ya  ] escondido en el display de $cual\n";
    $saltados++;
    continue;
  }
  $display->removeComponent(CAMPO)->save();
  print "[hecho] escondido en el display de $cual\n";
  $hechos++;
}

print "\n3. La siembra, por orden alfabetico dentro de cada marca\n";
print "-------------------------------------------------------\n";

$almacen_productos = \Drupal::entityTypeManager()->getStorage('tec_product');
$marcas = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'tec_brands']);

/**
 * Nombre por el que se ordena un producto: el campo, y si esta vacio, el titulo.
 */
$nombre_de = static function ($producto): string {
  $nombre = '';
  if ($producto->hasField('field_product_name')) {
    $nombre = (string) $producto->get('field_product_name')->value;
  }
  return trim($nombre) !== '' ? trim($nombre) : (string) $producto->label();
};

$sembrados = 0;
$ya_tenian = 0;

foreach ($marcas as $marca) {
  $ids = $almacen_productos->getQuery()
    ->condition('type', 'tec_product')
    ->condition('field_tec_brand', $marca->id())
    ->accessCheck(FALSE)
    ->execute();
  if (!$ids) {
    continue;
  }

  $productos = $almacen_productos->loadMultiple($ids);

  // El sitio mas alto que ya esta ocupado en esta marca. Lo que se siembre va
  // detras, para no meterse por delante de nada que alguien haya arrastrado.
  $ocupados = [];
  $sin_sitio = [];
  foreach ($productos as $producto) {
    $sitio = (int) $producto->get(CAMPO)->value;
    if ($sitio > 0) {
      $ocupados[] = $sitio;
      continue;
    }
    $sin_sitio[] = $producto;
  }
  $ya_tenian += count($ocupados);

  if (!$sin_sitio) {
    print "[ya  ] " . $marca->label() . ": " . count($productos) . " productos, todos con sitio\n";
    continue;
  }

  usort($sin_sitio, static fn($a, $b) => strnatcasecmp($nombre_de($a), $nombre_de($b)));

  $siguiente = $ocupados ? max($ocupados) + 1 : 1;
  foreach ($sin_sitio as $producto) {
    $producto->set(CAMPO, $siguiente++);
    $producto->save();
    $sembrados++;
  }

  print "[hecho] " . $marca->label() . ": " . count($sin_sitio) . " productos sembrados"
    . ($ocupados ? " (a partir del " . (max($ocupados) + 1) . ")" : "")
    . ", " . count($productos) . " en total\n";
}
if ($sembrados) {
  $hechos++;
}

// Los productos sin marca no salen en ninguna pantalla de marca, asi que no
// hay donde arrastrarlos. Se cuentan para que se sepa que estan ahi.
$huerfanos = $almacen_productos->getQuery()
  ->condition('type', 'tec_product')
  ->notExists('field_tec_brand')
  ->accessCheck(FALSE)
  ->count()
  ->execute();

print "\nSembrados: $sembrados. Ya tenian sitio: $ya_tenian.\n";
if ($huerfanos) {
  print "Sin marca, y por eso sin sitio: $huerfanos productos. Estos salen en /orphans.\n";
}
print "\nHechos: $hechos. Ya estaban: $saltados.\n";
print "\nAhora hay que exportar la configuracion para que esto quede en el disco.\n";
