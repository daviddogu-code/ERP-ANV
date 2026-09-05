<?php

/**
 * @file
 * Ensena que appendComponent() se come el peso y el constructor no.
 *
 * Esto no arregla nada: existe para dejar probada la receta de copiar una
 * portada. El guion que rehizo la portada de marcas copiaba los pesos del pattern
 * y aun asi la pantalla salio desordenada, y hasta que no se puso el pattern y la
 * copia uno al lado del otro nadie vio por que. El motivo esta en el nucleo:
 *
 *     public function appendComponent(SectionComponent $component) {
 *       $component->setWeight($this->getNextHighestWeight($component->getRegion()));
 *       $this->setComponent($component);
 *
 * El peso que traiga la pieza da igual, porque la primera linea lo pisa con el
 * siguiente numero libre de la region.
 *
 * El guion de la portada ya esta corregido, pero ese no se puede probar sin
 * borrar el nodo 4, y el nodo 4 no se toca: la vista tec_brands escribe /node/4
 * en cuatro enlaces. Asi que la receta se prueba aqui, con el pattern de colores
 * de material y sin guardar nada.
 *
 * Uso: drush php:script scripts/respeta-el-peso-al-copiar-la-seccion
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$pattern = \Drupal::entityTypeManager()->getStorage('node')->load(9);
if (!$pattern) {
  echo "\n No esta el nodo 9, que es el pattern. Sin el no hay nada que copiar.\n\n";
  return;
}
$seccionPattern = $pattern->get('layout_builder__layout')->first()->section;

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " El pattern: nodo 9 (colores)\n";
echo str_repeat('=', 82) . "\n\n";

/**
 * Los pesos de una seccion, en el orden en que se van a pintar.
 */
$pesos = static function (Section $seccion): array {
  $lista = [];
  foreach ($seccion->getComponentsByRegion($seccion->getDefaultRegion()) as $componente) {
    $lista[$componente->get('configuration')['id']] = $componente->getWeight();
  }
  return $lista;
};

$pesosPattern = $pesos($seccionPattern);
foreach ($pesosPattern as $id => $peso) {
  printf("   peso %-3s %s\n", $peso, $id);
}

/**
 * Las piezas del pattern recien copiadas, con su peso puesto.
 *
 * Se devuelven en el orden en que las da getComponents(), que es el orden en que
 * estan guardadas y no el orden en que se pintan. Esa diferencia es justo la que
 * convierte el fallo del peso en un desorden visible.
 */
$copiarPiezas = static function (Section $seccionPattern): array {
  $piezas = [];
  foreach ($seccionPattern->getComponents() as $componente) {
    $pieza = new SectionComponent(
      \Drupal::service('uuid')->generate(),
      $componente->getRegion(),
      $componente->get('configuration')
    );
    $pieza->setWeight($componente->getWeight());
    $piezas[] = $pieza;
  }
  return $piezas;
};

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Como se hizo mal: setWeight() y luego appendComponent()\n";
echo str_repeat('=', 82) . "\n\n";

$mal = new Section($seccionPattern->getLayoutId(), $seccionPattern->getLayoutSettings());
foreach ($copiarPiezas($seccionPattern) as $pieza) {
  $mal->appendComponent($pieza);
}
$pesosMal = $pesos($mal);
foreach ($pesosMal as $id => $peso) {
  printf("   peso %-3s %s\n", $peso, $id);
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Como hay que hacerlo: pasarle las piezas al constructor\n";
echo str_repeat('=', 82) . "\n\n";

$bien = new Section($seccionPattern->getLayoutId(), $seccionPattern->getLayoutSettings(), $copiarPiezas($seccionPattern));
$pesosBien = $pesos($bien);
foreach ($pesosBien as $id => $peso) {
  printf("   peso %-3s %s\n", $peso, $id);
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " El veredicto\n";
echo str_repeat('=', 82) . "\n\n";

// Se comparan los pesos como lista ordenada, que es lo que decide el orden en
// pantalla, no como conjunto: dos secciones con los mismos numeros repartidos de
// otra forma pintan cosas distintas.
printf("   pattern:               %s\n", implode(' -> ', array_map(
  static fn (string $id, int $peso): string => $id . ' (' . $peso . ')',
  array_keys($pesosPattern),
  $pesosPattern
)));
printf("   con appendComponent: %s\n", implode(' -> ', array_map(
  static fn (string $id, int $peso): string => $id . ' (' . $peso . ')',
  array_keys($pesosMal),
  $pesosMal
)));
printf("   con el constructor:  %s\n\n", implode(' -> ', array_map(
  static fn (string $id, int $peso): string => $id . ' (' . $peso . ')',
  array_keys($pesosBien),
  $pesosBien
)));

printf(
  "   appendComponent respeta el pattern: %s\n",
  array_keys($pesosMal) === array_keys($pesosPattern) ? 'si' : 'NO, y por eso salio desordenada'
);
printf(
  "   el constructor respeta el pattern:  %s\n",
  array_keys($pesosBien) === array_keys($pesosPattern) && $pesosBien === $pesosPattern ? 'si' : 'NO, mirar esto antes de fiarse'
);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " No se ha guardado nada: las dos secciones eran de mentira.\n";
echo str_repeat('=', 82) . "\n\n";
