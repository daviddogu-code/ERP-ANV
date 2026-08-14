<?php

/**
 * Termina de purgar los campos borrados, y ensena las filas en vez de la cola.
 *
 * La primera vez esto parecio un fallo: doce vueltas de purga y el contador de
 * pendientes se quedaba en dos. No era un fallo, era el contador equivocado. Los
 * pendientes son definiciones de campo, y una definicion no se va hasta que se
 * ha vaciado su tabla entera; mientras, las filas si bajaban de doscientas en
 * doscientas sin que se viera.
 *
 * Aqui se cuentan las filas, que es lo que se mueve.
 *
 * Uso: php vendor/bin/drush.php scr scripts/terminar-la-purga.php
 */

use Drupal\Core\Field\FieldPurger;

const POR_VUELTA = 500;

$db = \Drupal::database();
$repositorio = \Drupal::service('entity_field.deleted_fields_repository');
$purgador = \Drupal::service(FieldPurger::class);

$filasQuedan = function () use ($db) {
  $total = 0;
  foreach ($db->query("SHOW TABLES LIKE 'field\_deleted\_%'")->fetchCol() as $tabla) {
    $total += (int) $db->select($tabla)->countQuery()->execute()->fetchField();
  }
  return $total;
};

$cola = fn() => count($repositorio->getFieldStorageDefinitions()) + count($repositorio->getFieldDefinitions());

printf("\n  al empezar: %d filas en tablas de campos borrados, %d definiciones en la cola\n\n",
  $filasQuedan(), $cola());

for ($vuelta = 1; $vuelta <= 40; $vuelta++) {
  $antes = $filasQuedan();

  // Con la cola vacia se para, aunque queden filas. Lo que queda entonces son
  // tablas que ya no pertenecen a ninguna definicion, y la purga no las va a
  // tocar nunca: seguir dando vueltas solo gasta tiempo.
  if (!$cola()) {
    printf("      cola vacia en la vuelta %d%s\n",
      $vuelta,
      $antes ? ", y quedan $antes filas que la purga ya no puede tocar" : '');
    break;
  }

  $purgador->purgeBatch(POR_VUELTA);
  $despues = $filasQuedan();

  printf("      vuelta %-3d %6d filas -> %6d   (cola: %d)\n", $vuelta, $antes, $despues, $cola());

  // Si una vuelta no mueve ni una fila ni vacia la cola, esto si seria un
  // atasco de verdad y no tiene sentido seguir dando vueltas.
  if ($despues === $antes && $cola()) {
    print "\n      ATASCO: una vuelta entera sin mover nada\n";
    break;
  }
}

printf("\n  al terminar: %d filas, %d en la cola\n\n", $filasQuedan(), $cola());

$tablas = $db->query("SHOW TABLES LIKE 'field\_deleted\_%'")->fetchCol();
printf("  tablas de campos borrados que quedan: %s\n\n",
  $tablas ? implode(', ', $tablas) : 'ninguna');

foreach ($repositorio->getFieldStorageDefinitions() as $definicion) {
  printf("      sigue en la cola: almacen %s.%s\n",
    $definicion->getTargetEntityTypeId(), $definicion->getName());
}
foreach ($repositorio->getFieldDefinitions() as $definicion) {
  printf("      sigue en la cola: campo %s.%s en %s\n",
    $definicion->getTargetEntityTypeId(), $definicion->getName(), $definicion->getTargetBundle());
}

print "\n";
