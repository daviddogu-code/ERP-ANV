<?php

/**
 * @file
 * El pedido de un clic sale de las marcas del cliente, no del cliente pegado a
 * cada producto.
 *
 *   php vendor\bin\drush.php scr scripts/el-boton-de-pedido-mira-las-marcas.php
 *
 * QUE ES ESTO.
 *
 * En la ficha de un cliente hay una bandera, «+ Order», que crea de golpe un
 * borrador de pedido con una linea por cada talla de cada producto de ese
 * cliente. Es la puerta por la que entran los pedidos de venta, y por dentro son
 * dos piezas: una ECA que la bandera dispara, y una vista que la ECA consulta por
 * su nombre -`tec_order_eca_create_draft_sales`, pantalla `default`- pasando
 * el numero del cliente.
 *
 * Esa vista contestaba «las tallas de los productos cuyo cliente es este»,
 * saltando por el campo de cliente del producto. Y ese campo se va, porque el
 * cliente ya no vive en el producto: vive en la marca, y quien compra la marca lo
 * dice el cliente en su ficha.
 *
 * Asi que la vista tiene que contestar lo mismo por otro camino: «las tallas de
 * los productos de las marcas que compra este cliente».
 *
 * POR QUE ESTA PRUEBA ES EN DOS MITADES.
 *
 * La primera mitad tiene que dar el mismo resultado antes y despues del cambio:
 * el cliente de siempre recibe sus mismas tallas. Es la red que sujeta el cambio.
 *
 * La segunda MITAD FALLA A PROPOSITO antes del cambio, y es la razon de hacerlo:
 * un segundo cliente que compra la misma marca no recibia nada, porque el
 * producto solo podia apuntar a un cliente. Cuando las dos mitades salgan en
 * verde, el circulo esta cerrado.
 *
 * Y la tercera comprueba un peligro que el camino nuevo trae y el viejo no tenia.
 * La vista llega ahora al cliente por su lista de marcas, asi que una marca
 * apuntada dos veces se une dos veces y el pedido sale con cada linea duplicada.
 * Medido: con la marca repetida devolvia cuatro filas en vez de dos. Se arregla
 * donde nace -la lista es un conjunto, y el guardado deja una fila por marca- y
 * no con un DISTINCT en la consulta, porque la consulta no es la que esta mal.
 *
 * Se monta su propio juego -una marca, dos clientes, un producto, un color y dos
 * tallas- y lo borra al final.
 */

use Drupal\views\Views;

const VISTA = 'tec_order_eca_create_draft_sales';
const PANTALLA = 'default';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');
$contactos = $gestor->getStorage('tec_crm');

$problemas = 0;
$creadas = [];

// Todo lo que monta esta prueba se llama «PRUEBA boton de pedido algo», y lo primero
// que hace es barrer lo que lleve ese nombre: si una vez se murio a medias, la de
// hoy empieza limpia en vez de acumular clientes de mentira.
//
// El nombre es largo a proposito y esa es la leccion. Antes el rastro era solo
// «PRUEBA», y con eso el barrido se llevo por delante el juego de pruebas
// permanente del ERP -el que fabrica scripts/fabricar-el-juego-de-pruebas.php-,
// que usa ese mismo prefijo y del que depende la prueba de humo para poder pedir
// las pantallas de pedidos de venta. O sea que un barrido de limpieza borro lo que
// otra prueba necesitaba. El prefijo largo hace imposible volver a confundirlos, y
// que siga empezando por PRUEBA es a proposito: asi la escoba de la casa recoge
// tambien lo de esta prueba si algun dia hace falta.
const RASTRO = 'PRUEBA boton de pedido';

$barrer = function () use ($gestor, $terminos, $productos, $contactos): int {
  $barridas = 0;

  /**
   * Lo que se llama como esta prueba, y nada mas.
   */
  $losMios = function ($almacen, string $campo) {
    return $almacen->loadMultiple($almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition($campo, RASTRO, 'STARTS_WITH')
      ->execute());
  };

  $mios = $losMios($contactos, 'title');

  // Los pedidos se buscan por su cliente y no por su nombre, porque al guardarlos
  // la ECA de numeracion les cambia el titulo y por nombre no apareceria ninguno.
  if ($mios !== []) {
    $ordenes = $gestor->getStorage('tec_order');
    $suyos = $ordenes->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_sales_order')
      ->condition('field_tec_customer', array_keys($mios), 'IN')
      ->execute();
    foreach ($ordenes->loadMultiple($suyos) as $orden) {
      foreach ($orden->get('field_tec_line_items')->referencedEntities() as $linea) {
        $linea->delete();
        $barridas++;
      }
      $orden->delete();
      $barridas++;
    }
  }

  foreach ([$losMios($productos, 'title'), $mios, $losMios($terminos, 'name')] as $tanda) {
    foreach ($tanda as $ficha) {
      $ficha->delete();
      $barridas++;
    }
  }

  return $barridas;
};

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

/**
 * Que tallas devuelve la vista para un cliente, que es lo que la ECA preguntara.
 */
$tallasDe = function (string $cid): array {
  $vista = Views::getView(VISTA);
  if (!$vista) {
    return [];
  }
  $vista->setDisplay(PANTALLA);
  $vista->setArguments([$cid]);
  $vista->preExecute();
  $vista->execute();

  $salen = [];
  foreach ($vista->result as $fila) {
    $salen[] = (string) ($fila->_entity?->id() ?? $fila->id ?? '?');
  }
  sort($salen);

  return $salen;
};

/**
 * Dos terminos cualesquiera de un vocabulario.
 */
$dosDe = function (string $vocabulario) use ($terminos): array {
  return array_values($terminos->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->range(0, 2)
    ->execute());
};

$colores = $dosDe('tec_colors');
$tallas = $dosDe('tec_sizes');
if (count($colores) < 1 || count($tallas) < 2) {
  print "\n  Hacen falta un color y dos tallas en los catalogos. No sigo.\n\n";
  return;
}

print "\n";
print "Montando el juego\n";
print str_repeat('-', 70) . "\n";

$barridas = $barrer();
if ($barridas > 0) {
  printf("  antes barro %d fichas que dejo una prueba anterior\n", $barridas);
}

$marca = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' marca']);
$marca->save();
$creadas[] = $marca;

$deSiempre = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' cliente de siempre',
  'field_tec_customer_code' => 'ZZEL1',
  'field_tec_brands' => [['target_id' => $marca->id()]],
]);
$deSiempre->save();
$creadas[] = $deSiempre;

$elOtro = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' cliente que comparte marca',
  'field_tec_customer_code' => 'ZZEL2',
  'field_tec_brands' => [['target_id' => $marca->id()]],
]);
$elOtro->save();
$creadas[] = $elOtro;

// El producto solo lleva su marca. Llevaba tambien un cliente propio, que es por
// donde iba esta consulta antes del cambio; se retiro el 19 de agosto de 2026.
$producto = $productos->create([
  'type' => 'tec_product',
  'field_product_name' => RASTRO . ' producto',
  'field_tec_brand' => [['target_id' => $marca->id()]],
]);
$producto->save();
$creadas[] = $producto;

$color = $productos->create([
  'type' => 'tec_color_variation',
  'field_tec_colors' => [['target_id' => $colores[0]]],
  'field_tec_product' => [['target_id' => $producto->id()]],
]);
$color->save();
$creadas[] = $color;

$susTallas = [];
foreach ([0, 1] as $cual) {
  $talla = $productos->create([
    'type' => 'tec_size_variation',
    'field_tec_size' => [['target_id' => $tallas[$cual]]],
    'field_tec_color_variation' => [['target_id' => $color->id()]],
  ]);
  $talla->save();
  $creadas[] = $talla;
  $susTallas[] = (string) $talla->id();
}
sort($susTallas);

// Los atajos de arriba abajo los pone el gancho de guardado, pero la vista sube
// por las listas de abajo, asi que las listas tienen que estar puestas.
$color->set('field_tec_size_variations', array_map(
  fn(string $id): array => ['target_id' => $id],
  $susTallas
));
$color->save();
$producto->set('field_tec_color_variations', [['target_id' => $color->id()]]);
$producto->save();

printf("  la marca %s, el producto %s, el color %s, las tallas %s\n",
  $marca->id(), $producto->id(), $color->id(), implode(' y ', $susTallas));
printf("  el cliente de siempre %s, el que comparte marca %s\n",
  $deSiempre->id(), $elOtro->id());

print "\n";
print "Primera mitad: el cliente de siempre recibe lo suyo\n";
print str_repeat('-', 70) . "\n";

$salen = $tallasDe((string) $deSiempre->id());
$mirar('la vista le devuelve sus dos tallas', $salen === $susTallas,
  'devuelve ' . (count($salen) ? implode(', ', $salen) : 'nada') . ' y se esperaban ' . implode(', ', $susTallas));

print "\n";
print "Segunda mitad: el que comparte marca tambien\n";
print str_repeat('-', 70) . "\n";

$salen = $tallasDe((string) $elOtro->id());
$mirar('y al segundo cliente, las mismas', $salen === $susTallas,
  'devuelve ' . (count($salen) ? implode(', ', $salen) : 'nada') . ' y se esperaban ' . implode(', ', $susTallas));

print "\n";
print "Tercera: la misma marca apuntada dos veces no duplica\n";
print str_repeat('-', 70) . "\n";

$elOtro->set('field_tec_brands', [
  ['target_id' => $marca->id()],
  ['target_id' => $marca->id()],
]);
$elOtro->save();

$elOtro = $contactos->loadUnchanged($elOtro->id());
$mirar('la repetida no llega ni a guardarse',
  $elOtro->get('field_tec_brands')->count() === 1,
  $elOtro->get('field_tec_brands')->count() . ' filas en la lista');

$salen = $tallasDe((string) $elOtro->id());
$mirar('y sigue recibiendo dos tallas y no cuatro', $salen === $susTallas,
  'devuelve ' . count($salen) . ' filas');

print "\n";
print "Y un cliente que no compra la marca no recibe nada\n";
print str_repeat('-', 70) . "\n";

$ajeno = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' cliente ajeno',
  'field_tec_customer_code' => 'ZZEL3',
]);
$ajeno->save();
$creadas[] = $ajeno;

$salen = $tallasDe((string) $ajeno->id());
$mirar('la vista no le devuelve nada', $salen === [], count($salen) . ' filas');

print "\n";
print "Y de punta a punta: la bandera hace el borrador\n";
print str_repeat('-', 70) . "\n";

// La vista contestando bien no basta para dormir tranquilo: lo que el ERP tiene
// que seguir haciendo es el pedido. Asi que se levanta la bandera de verdad, la
// misma que el usuario pulsa en la ficha, y se mira que sale por el otro lado. Se
// hace con los dos clientes: el de siempre prueba que el camino de antes sigue
// entero, y el otro que el nuevo llega hasta el final.
$bandera = $gestor->getStorage('flag')->load('tec_order_draft_sales');
$pedidos = $gestor->getStorage('tec_order');

/**
 * Levanta la bandera de un cliente y devuelve el pedido que sale de ahi.
 */
$pedirDeUnClic = function ($cliente) use ($bandera, $pedidos, &$creadas): ?array {
  try {
    \Drupal::service('flag')->flag($bandera, $cliente);
  }
  catch (\Throwable $chispa) {
    // La ECA termina mandando al navegador al pedido recien hecho, y en la
    // consola no hay navegador. El pedido ya esta guardado cuando eso salta.
  }

  // Ojo, que son dos campos con el mismo nombre y distinto dueño: este es el
  // `field_tec_customer` del PEDIDO, que se queda. El que se retira es el del
  // producto.
  $suyos = array_values($pedidos->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_sales_order')
    ->condition('field_tec_customer', $cliente->id())
    ->execute());
  if (count($suyos) !== 1) {
    return NULL;
  }

  $pedido = $pedidos->load(reset($suyos));
  $creadas[] = $pedido;

  $lleva = [];
  foreach ($pedido->get('field_tec_line_items')->referencedEntities() as $linea) {
    $creadas[] = $linea;
    $lleva[] = (string) $linea->get('field_tec_size_variation')->target_id;
  }
  sort($lleva);

  return [$pedido, $lleva];
};

foreach (['el de siempre' => $deSiempre, 'el que comparte marca' => $elOtro] as $quien => $cliente) {
  $salio = $pedirDeUnClic($cliente);
  $mirar("a $quien le sale un pedido", $salio !== NULL);
  if ($salio === NULL) {
    continue;
  }
  [$pedido, $lleva] = $salio;

  $mirar("y lleva una linea por talla, las que son", $lleva === $susTallas,
    'lleva ' . (count($lleva) ? implode(', ', $lleva) : 'ninguna')
    . ' y se esperaban ' . implode(', ', $susTallas));

  $sigueLevantada = \Drupal::service('flag')->getFlagging($bandera, $cliente);
  $mirar('y la bandera se baja sola al acabar', $sigueLevantada === NULL);

  // Sin puntuar, solo anotado: la ECA pide el pedido «sin publicar» y sale
  // publicado. Pasa igual por el camino viejo y por el nuevo, asi que no lo trae
  // este cambio y no se toca aqui. Ademas el borrador no es un estado guardado en
  // el pedido: `/o/draft/N` es solo una pantalla que lista sus lineas.
  printf("  (nota)  el pedido de %s sale %s\n", $quien,
    $pedido->isPublished() ? 'publicado, y la ECA lo pedia sin publicar' : 'sin publicar');
}

// Se borra de dentro hacia fuera. Borrar el producto arrastra a sus hijos por su
// cuenta, asi que se comprueba antes de borrar cada uno.
foreach (array_reverse($creadas) as $ficha) {
  $gestor->getStorage($ficha->getEntityTypeId())->loadUnchanged($ficha->id())?->delete();
}

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. El juego de prueba se ha borrado.\n\n";
}
else {
  printf("  %d cosas mal. El juego de prueba se ha borrado.\n\n", $problemas);
}
