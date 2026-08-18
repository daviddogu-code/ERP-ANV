<?php

/**
 * @file
 * La pestana Products del cliente y la de ordenar salen de las marcas.
 *
 *   php vendor\bin\drush.php scr scripts/la-pestana-del-cliente-sale-de-la-marca.php
 *
 * QUE ES ESTO.
 *
 * En la ficha de un cliente hay tres pestanas -Orders, Products, Details- y la de
 * Products es un bloque de la vista `tec_products` que recibe el numero del
 * cliente. Al lado vive la pantalla de ordenar productos, `/tec_crm/N/reorder`, que
 * es la que guarda a mano en que orden salen, y ese orden es el que sale impreso en
 * la proforma.
 *
 * Las dos contestaban «los productos cuyo cliente es este», saltando por el campo
 * de cliente del producto, que se retira. Pasan a contestar «los productos de las
 * marcas que compra este cliente».
 *
 * LAS TRES COSAS QUE ESTA PRUEBA VIGILA.
 *
 * Una, que cada cliente vea los productos de TODAS sus marcas. Un producto de una
 * marca que compran dos clientes sale en las dos fichas, que antes no podia pasar:
 * el producto llevaba escrito un cliente y solo uno.
 *
 * Dos, que no vea los de las marcas que no compra.
 *
 * Tres, y es la delicada: que el ORDEN ARRASTRADO no se pierda. El modulo
 * draggableviews guarda el orden en su propia tabla con una clave que incluye los
 * argumentos de la vista, o sea el numero del cliente. Mientras la pantalla siga
 * recibiendo al cliente, esas filas siguen valiendo; si se le cambiara el argumento
 * por la marca, el orden hecho a mano se quedaria huerfano sin que nada avisara. Hay
 * orden guardado de ocho clientes, asi que no es un peligro teorico.
 *
 * Se monta su propio juego y lo barre al empezar y al acabar. Todo lo suyo se llama
 * «PRUEBA pestana algo», con nombre largo y propio: un rastro corto como «PRUEBA» se
 * lleva por delante el juego de pruebas permanente de la casa.
 */

use Drupal\views\Views;

const RASTRO = 'PRUEBA pestana';
const VISTA = 'tec_products';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');
$contactos = $gestor->getStorage('tec_crm');
$bd = \Drupal::database();

$problemas = 0;

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
 * Lo que una pantalla devuelve para un cliente.
 */
$loQueVe = function (string $pantalla, string $cid): array {
  $vista = Views::getView(VISTA);
  if (!$vista) {
    return [];
  }
  $vista->setDisplay($pantalla);
  $vista->setArguments([$cid]);
  $vista->preExecute();
  $vista->execute();

  $salen = [];
  foreach ($vista->result as $fila) {
    $salen[] = (string) ($fila->_entity?->id() ?? $fila->id ?? '?');
  }

  return $salen;
};

/**
 * Lo que una pantalla ensena YA PINTADA, que no es lo mismo.
 *
 * El orden de los bloques por marca no lo pone la consulta -no se puede, y esta
 * explicado en el modulo- sino el modulo al terminar, cuando la vista se pinta. Una
 * prueba que solo ejecute la consulta no lo ve y da por bueno lo que no lo esta.
 */
$loQueVeAlPintar = function (string $pantalla, string $cid): array {
  $vista = Views::getView(VISTA);
  if (!$vista) {
    return [];
  }
  $vista->setDisplay($pantalla);
  $vista->setArguments([$cid]);
  $vista->render($pantalla);

  $salen = [];
  foreach ($vista->result as $fila) {
    $salen[] = (string) ($fila->_entity?->id() ?? $fila->id ?? '?');
  }

  return $salen;
};

/**
 * Barre lo de esta prueba y nada mas.
 */
$barrer = function () use ($terminos, $productos, $contactos, $bd): int {
  $barridas = 0;
  foreach ([[$productos, 'title'], [$contactos, 'title'], [$terminos, 'name']] as [$almacen, $campo]) {
    $suyas = $almacen->getQuery()->accessCheck(FALSE)->condition($campo, RASTRO, 'STARTS_WITH')->execute();
    foreach ($almacen->loadMultiple($suyas) as $ficha) {
      $bd->delete('draggableviews_structure')->condition('entity_id', $ficha->id())->execute();
      $ficha->delete();
      $barridas++;
    }
  }

  return $barridas;
};

print "\n";
print "Montando el juego\n";
print str_repeat('-', 70) . "\n";

$barridas = $barrer();
if ($barridas > 0) {
  printf("  antes barro %d fichas que dejo una prueba anterior\n", $barridas);
}

$alfa = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' marca alfa']);
$alfa->save();
$beta = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' marca beta']);
$beta->save();

// El de siempre compra las dos marcas, y las tiene apuntadas al reves del alfabeto
// a proposito: asi se ve si el orden que sale es el suyo o el que le da la gana a
// la base de datos.
$deSiempre = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' cliente de siempre',
  'field_tec_brands' => [['target_id' => $beta->id()], ['target_id' => $alfa->id()]],
]);
$deSiempre->save();

$elOtro = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' cliente que comparte alfa',
  'field_tec_brands' => [['target_id' => $alfa->id()]],
]);
$elOtro->save();

/**
 * Un producto, que pertenece a una marca y a nadie mas.
 */
$hacerProducto = function (string $nombre, $marca) use ($productos) {
  $ficha = $productos->create([
    'type' => 'tec_product',
    'field_product_name' => RASTRO . ' ' . $nombre,
    'field_tec_brand' => [['target_id' => $marca->id()]],
  ]);
  $ficha->save();

  return $ficha;
};

$deAlfa = $hacerProducto('producto de alfa', $alfa);
$deBeta = $hacerProducto('producto de beta', $beta);
$delOtro = $hacerProducto('otro producto de alfa', $alfa);

$todosDeSiempre = [(string) $deAlfa->id(), (string) $deBeta->id(), (string) $delOtro->id()];
$deLaMarcaAlfa = [(string) $deAlfa->id(), (string) $delOtro->id()];
sort($todosDeSiempre);
sort($deLaMarcaAlfa);

printf("  marcas: alfa %s, beta %s\n", $alfa->id(), $beta->id());
printf("  productos: de alfa %s, de beta %s, otro de alfa %s\n",
  $deAlfa->id(), $deBeta->id(), $delOtro->id());
printf("  clientes: el de siempre %s (compra beta y alfa), el otro %s (solo alfa)\n",
  $deSiempre->id(), $elOtro->id());

foreach (['block_4' => 'la pestana Products de la ficha', 'page_2' => 'la pantalla de ordenar'] as $pantalla => $comoSeLlama) {
  printf("\n%s (%s)\n", $comoSeLlama, $pantalla);
  print str_repeat('-', 70) . "\n";

  $ve = $loQueVe($pantalla, (string) $deSiempre->id());
  $ordenados = $ve;
  sort($ordenados);
  $mirar('al cliente de siempre le ensena los de sus dos marcas',
    $ordenados === $todosDeSiempre,
    'ensena ' . (count($ve) ? implode(', ', $ve) : 'nada') . ' y se esperaban ' . implode(', ', $todosDeSiempre));

  $mirar('y sin repetir ninguno', count($ve) === count(array_unique($ve)),
    count($ve) . ' filas para ' . count(array_unique($ve)) . ' productos');

  $ve = $loQueVe($pantalla, (string) $elOtro->id());
  sort($ve);
  $mirar('y al otro cliente solo los de la marca que compra',
    $ve === $deLaMarcaAlfa,
    'ensena ' . (count($ve) ? implode(', ', $ve) : 'nada') . ' y se esperaban ' . implode(', ', $deLaMarcaAlfa));
}

print "\nEl orden arrastrado sigue valiendo\n";
print str_repeat('-', 70) . "\n";

// Se escriben a mano las filas del orden, las mismas que escribiria un arrastre en
// la pantalla de ordenar. La clave lleva el numero del cliente, y de eso va la
// comprobacion: mientras la pantalla siga recibiendo al cliente, lo arrastrado vale.
//
// Se le da peso a los TRES y en un orden que no es ni el del alfabeto ni el de los
// numeros. Hay que darselo a todos: los que no tienen peso guardado salen ANTES que
// los que lo tienen, porque la pantalla esta puesta en «los nuevos arriba», asi que
// con uno solo arrastrado no se ve nada.
// Se arrastra a proposito al REVES de lo que tiene que salir en la pestana: primero
// el de alfa. Asi las dos cosas que se comprueban no pueden coincidir por casualidad.
// Si al final la pestana saca el de beta delante, es porque manda el orden de marcas
// del cliente; y si dentro de alfa sigue saliendo antes el que se arrastro antes, es
// porque el arrastre no se ha perdido. La pantalla de ordenar, que no agrupa, tiene
// que seguir sacandolos tal cual se arrastraron.
$aMano = [
  (string) $deAlfa->id() => -50,
  (string) $deBeta->id() => 0,
  (string) $delOtro->id() => 50,
];
foreach ($aMano as $pid => $peso) {
  $bd->insert('draggableviews_structure')
    ->fields([
      'view_name' => VISTA,
      'view_display' => 'page_2',
      'args' => json_encode([(string) $deSiempre->id()]),
      'entity_id' => $pid,
      'weight' => $peso,
      'parent' => 0,
    ])
    ->execute();
}

// Con cuidado al comparar: los numeros de ficha salen de la vista como texto y las
// claves de un array de PHP se vuelven enteras solas, asi que sin igualar el tipo
// esto falla ensenando dos listas identicas.
$arrastrados = array_map('strval', array_keys($aMano));

$ve = $loQueVe('page_2', (string) $deSiempre->id());
$mirar('la pantalla de ordenar los saca tal cual se arrastraron',
  $ve === $arrastrados,
  'salen ' . implode(', ', $ve) . ' y se arrastraron ' . implode(', ', $arrastrados));

$ve = $loQueVeAlPintar('block_4', (string) $deSiempre->id());
$dondeSale = array_flip($ve);
$mirar('y en la pestana el arrastre manda dentro de cada marca',
  ($dondeSale[(string) $deAlfa->id()] ?? -1) < ($dondeSale[(string) $delOtro->id()] ?? -1),
  'salen ' . implode(', ', $ve) . ' y de alfa se arrastro antes el ' . $deAlfa->id());

print "\nLos bloques salen en el orden de marcas del cliente\n";
print str_repeat('-', 70) . "\n";

// El cliente tiene apuntadas beta y luego alfa, al reves del alfabeto. Si los bloques
// salen en su orden, primero viene el producto de beta y despues los dos de alfa; si
// saliera el alfabeto o el numero de ficha, saldria alfa primero.
$ve = $loQueVeAlPintar('block_4', (string) $deSiempre->id());
$mirar('primero el bloque de beta, que es la que puso primera',
  ($ve[0] ?? '') === (string) $deBeta->id(),
  'salen ' . implode(', ', $ve) . ' y el de beta es el ' . $deBeta->id());

$mirar('y las dos de alfa detras, aunque una se arrastro a la primera posicion',
  array_slice($ve, 1) === array_map('strval', [$deAlfa->id(), $delOtro->id()]),
  'detras salen ' . implode(', ', array_slice($ve, 1)));

// Subir del producto a la marca y de la marca a sus clientes es un camino con dos
// saltos, y un salto mal puesto multiplica las filas en silencio.
$mirar('y sigue sin repetirse ninguno al ordenar por la posicion',
  count($ve) === count(array_unique($ve)),
  count($ve) . ' filas para ' . count(array_unique($ve)) . ' productos');

/**
 * Cuantas filas de orden hay guardadas para la pantalla de ordenar.
 */
$filasDeOrden = fn(): int => (int) $bd->select('draggableviews_structure', 'd')
  ->condition('view_name', VISTA)
  ->condition('view_display', 'page_2')
  ->countQuery()
  ->execute()
  ->fetchField();

$conLasMias = $filasDeOrden();
$mirar('y las filas de orden de los demas clientes siguen ahi', $conLasMias > count($aMano),
  $conLasMias . ' filas guardadas para esa pantalla');

$barrer();

$mirar('y al recoger no se lleva las de los demas',
  $filasDeOrden() === $conLasMias - count($aMano),
  'quedan ' . $filasDeOrden() . ' de las ' . ($conLasMias - count($aMano)) . ' que habia antes');

print "\n" . str_repeat('=', 70) . "\n";
printf("  %s El juego de prueba se ha borrado.\n",
  $problemas === 0 ? 'Todo bien.' : $problemas . ' cosas mal.');
print "\n";
