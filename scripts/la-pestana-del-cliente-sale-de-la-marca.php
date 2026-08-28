<?php

/**
 * @file
 * La pestana Products del cliente sale de las marcas, y en dos ordenes.
 *
 *   php vendor\bin\drush.php scr scripts/la-pestana-del-cliente-sale-de-la-marca.php
 *
 * QUE ES ESTO.
 *
 * En la ficha de un cliente hay tres pestanas -Orders, Products, Details- y la de
 * Products es un bloque de la vista `tec_products` que recibe el numero del cliente.
 * Contestaba «los productos cuyo cliente es este», saltando por el campo de cliente
 * del producto, que se retiro. Ahora contesta «los productos de las marcas que
 * compra este cliente».
 *
 * Al lado vivia la pantalla de ordenar productos, `/tec_crm/N/reorder`, y **ya no
 * existe**: se retiro la noche del 19 de agosto de 2026, cuando el orden de los
 * productos dejo de ser del cliente y paso a ser de la marca. Lo que aquella
 * pantalla hacia se prueba ahora en `scripts/la-marca-manda-en-el-orden.php`.
 *
 * LAS CUATRO COSAS QUE ESTA PRUEBA VIGILA.
 *
 * Una, que cada cliente vea los productos de TODAS sus marcas. Un producto de una
 * marca que compran dos clientes sale en las dos fichas, que antes no podia pasar:
 * el producto llevaba escrito un cliente y solo uno.
 *
 * Dos, que no vea los de las marcas que no compra.
 *
 * Tres, que los bloques salgan en el orden de marcas de SU ficha, que lo pone el
 * modulo al pintar la vista porque en la consulta no se puede.
 *
 * Y cuatro, que dentro de cada bloque manden el orden de la marca. Son dos ordenes
 * de dos duenos distintos actuando a la vez sobre la misma lista, y esta prueba los
 * pone a contradecirse a proposito: la marca que el cliente puso primera es la
 * segunda del alfabeto, y el producto que la marca puso primero es el ultimo por
 * numero de ficha. Si los dos ordenes salen bien con el juego montado asi, no puede
 * ser casualidad.
 *
 * Se monta su propio juego y lo barre al empezar y al acabar. Todo lo suyo se llama
 * «PRUEBA pestana algo», con nombre largo y propio: un rastro corto como «PRUEBA» se
 * lleva por delante el juego de pruebas permanente de la casa.
 */

use Drupal\tec_production\CataloguePosition;
use Drupal\views\Views;

const RASTRO = 'PRUEBA pestana';
const VISTA = 'tec_products';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');
$contactos = $gestor->getStorage('tec_crm');

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
$barrer = function () use ($terminos, $productos, $contactos): int {
  $barridas = 0;
  foreach ([[$productos, 'title'], [$contactos, 'title'], [$terminos, 'name']] as [$almacen, $campo]) {
    $suyas = $almacen->getQuery()->accessCheck(FALSE)->condition($campo, RASTRO, 'STARTS_WITH')->execute();
    foreach ($almacen->loadMultiple($suyas) as $ficha) {
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

foreach (['block_4' => 'la pestana Products de la ficha'] as $pantalla => $comoSeLlama) {
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

print "\nDentro de cada marca manda el orden de la marca\n";
print str_repeat('-', 70) . "\n";

// Se arrastra la marca alfa, que es la que tiene dos productos, dejando delante el
// que se creo el ultimo. Asi el orden que tiene que salir no es el de los numeros de
// ficha ni el del alfabeto, que son los dos que saldrian solos.
CataloguePosition::write([
  (int) $delOtro->id() => 1,
  (int) $deAlfa->id() => 2,
]);
$productos->resetCache();

$ve = $loQueVeAlPintar('block_4', (string) $deSiempre->id());
$dondeSale = array_flip($ve);
$mirar('el producto que la marca puso primero sale primero',
  ($dondeSale[(string) $delOtro->id()] ?? -1) < ($dondeSale[(string) $deAlfa->id()] ?? -1),
  'salen ' . implode(', ', $ve) . ' y la marca puso delante el ' . $delOtro->id());

print "\nLos bloques salen en el orden de marcas del cliente\n";
print str_repeat('-', 70) . "\n";

// El cliente tiene apuntadas beta y luego alfa, al reves del alfabeto. Si los bloques
// salen en su orden, primero viene el producto de beta y despues los dos de alfa; si
// saliera el alfabeto o el numero de ficha, saldria alfa primero.
$mirar('primero el bloque de beta, que es la que puso primera',
  ($ve[0] ?? '') === (string) $deBeta->id(),
  'salen ' . implode(', ', $ve) . ' y el de beta es el ' . $deBeta->id());

$mirar('y las dos de alfa detras, en el orden que arrastro la marca',
  array_slice($ve, 1) === array_map('strval', [$delOtro->id(), $deAlfa->id()]),
  'detras salen ' . implode(', ', array_slice($ve, 1)));

// Subir del producto a la marca y de la marca a sus clientes es un camino con dos
// saltos, y un salto mal puesto multiplica las filas en silencio.
$mirar('y sigue sin repetirse ninguno al ordenar por la posicion',
  count($ve) === count(array_unique($ve)),
  count($ve) . ' filas para ' . count(array_unique($ve)) . ' productos');

$barrer();

print "\n" . str_repeat('=', 70) . "\n";
printf("  %s El juego de prueba se ha borrado.\n",
  $problemas === 0 ? 'Todo bien.' : $problemas . ' cosas mal.');
print "\n";
