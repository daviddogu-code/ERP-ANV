<?php

/**
 * @file
 * Adios al campo de cliente del producto.
 *
 *   php vendor\bin\drush.php scr scripts/adios-al-cliente-del-producto.php
 *
 * El ultimo paso, y el unico que no se deshace. Antes de esto hay que haber pasado
 * por los otros tres, que dejan el campo sin nadie que lo lea:
 *
 *   las-pantallas-del-cliente-suben-por-la-marca.php
 *   la-pestana-agrupa-por-marca.php
 *   el-producto-suelta-al-cliente.php
 *
 * ANTES DE BORRAR SE PREGUNTA QUE SE VA A LLEVAR POR DELANTE. En Drupal la
 * configuracion esta encadenada: una vista que ensena un campo depende de ese campo, y
 * al borrarlo Drupal decide solo si puede arreglar a quien dependia o si tiene que
 * borrarlo tambien. Arreglar una vista es quitarle el campo; y si no puede arreglarla,
 * la borra ENTERA y sin preguntar. Una vista de 12 pantallas por un campo. Asi que
 * aqui se pregunta primero -eso es lo que hace `getConfigEntitiesToChangeOnDependency
 * Removal`- y si alguien fuese a morir, no se borra nada y se dice quien.
 *
 * Los datos escritos no se van con el campo: Drupal los aparta y los borra luego, en
 * el cron, a ratos. Aqui se le pide que lo haga ya, para no dejar la mudanza a medias
 * esperando a un cron que ademas estuvo apagado.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

const CAMPO = 'field_tec_customer';
const CLASE = 'tec_product';

print "\n";
print "=====================================================================\n";
print "  Adios al cliente del producto\n";
print "=====================================================================\n\n";

/**
 * Barre los datos apartados y cuenta como quedo la cosa.
 *
 * Drupal no borra los datos de un campo al borrarlo: los aparta, los apunta en una
 * lista y los va barriendo en el cron a ratos. Aqui se le pide que lo haga ya, para no
 * dejar la mudanza a medias esperando a un cron que ademas estuvo apagado. Y se llama
 * tambien cuando el campo ya no estaba, porque lo que suele quedarse atras no es el
 * campo sino esa lista.
 */
$barrerLoApartado = function (): void {
  $pendientes = fn(): int => count(\Drupal::state()->get('field.field.deleted', []))
    + count(\Drupal::state()->get('field.storage.deleted', []));

  $antes = $pendientes();
  if ($antes > 0) {
    field_purge_batch(500);
  }
  printf("  datos apartados pendientes de barrer: %d, y ahora %d\n", $antes, $pendientes());
};

$definicion = FieldConfig::loadByName(CLASE, CLASE, CAMPO);
$almacenamiento = FieldStorageConfig::loadByName(CLASE, CAMPO);

if (!$definicion && !$almacenamiento) {
  print "  El campo ya no esta.\n";
  $barrerLoApartado();
  print "\n";
  return;
}

// Que no quede nadie leyendolo, que es lo que hacen los tres guiones de antes.
$quedan = [];
foreach (\Drupal::configFactory()->listAll('views.view.') as $nombre) {
  if (str_contains((string) json_encode(\Drupal::config($nombre)->get('display')), CLASE . '__' . CAMPO)) {
    $quedan[] = str_replace('views.view.', '', $nombre);
  }
}
foreach (\Drupal::configFactory()->listAll('eca.eca.') as $nombre) {
  foreach (\Drupal::config($nombre)->get('actions') ?? [] as $paso) {
    $ajustes = $paso['configuration'] ?? [];
    // Solo los pasos que actuan sobre un producto. El cliente del PEDIDO se llama
    // igual y se queda.
    if (($ajustes['field_name'] ?? '') === CAMPO && str_contains((string) ($ajustes['object'] ?? ''), 'roduct')) {
      $quedan[] = \Drupal::config($nombre)->get('label') . ' / ' . ($paso['label'] ?? '?');
    }
  }
}
if ($quedan !== []) {
  print "  Todavia lo lee alguien. No borro nada:\n";
  foreach (array_unique($quedan) as $quien) {
    print '    ' . $quien . "\n";
  }
  print "\n";
  return;
}

// El vuelo previo: a quien le va a doler.
$gestorConfig = \Drupal::service('config.manager');
$loQuePasaria = $gestorConfig->getConfigEntitiesToChangeOnDependencyRemoval('config', [
  'field.storage.' . CLASE . '.' . CAMPO,
]);

$mortales = [];
foreach ($loQuePasaria['delete'] ?? [] as $victima) {
  // El propio campo y su definicion se van, claro: son el objeto del ejercicio.
  $id = $victima->getConfigDependencyName();
  if (str_contains($id, 'field.field.' . CLASE) || str_contains($id, 'field.storage.' . CLASE)) {
    continue;
  }
  $mortales[] = $id;
}

printf("  se arreglarian solos: %d\n", count($loQuePasaria['update'] ?? []));
foreach ($loQuePasaria['update'] ?? [] as $tocado) {
  printf("    %s\n", $tocado->getConfigDependencyName());
}
printf("  se borrarian: %d\n", count($loQuePasaria['delete'] ?? []));
foreach ($loQuePasaria['delete'] ?? [] as $victima) {
  printf("    %s\n", $victima->getConfigDependencyName());
}

if ($mortales !== []) {
  print "\n  ALTO. Borrar el campo se llevaria por delante:\n";
  foreach ($mortales as $mortal) {
    print '    ' . $mortal . "\n";
  }
  print "  No se ha borrado nada.\n\n";
  return;
}

$escritas = (int) \Drupal::entityTypeManager()->getStorage(CLASE)->getQuery()
  ->accessCheck(FALSE)
  ->exists(CAMPO)
  ->count()
  ->execute();

print "\n  Nadie muere. Borrando.\n";

$definicion?->delete();

// Y ahora hay que volver a preguntar por el almacenamiento en lugar de tirar del que
// se cargo arriba. Al borrar la ultima definicion de un campo, Drupal se lleva por su
// cuenta el almacenamiento y hasta la tabla; el objeto que quedaba en memoria apunta a
// algo que ya no existe, y borrarlo otra vez revienta preguntandole a una tabla que ya
// no esta si tiene datos.
$almacenamiento = FieldStorageConfig::loadByName(CLASE, CAMPO);
$almacenamiento?->delete();

$sigue = isset(\Drupal::service('entity_field.manager')->getFieldDefinitions(CLASE, CLASE)[CAMPO]);

print "\n";
printf("  el campo: %s\n", $sigue ? 'SIGUE AHI' : 'ya no existe');
printf("  fichas que lo tenian escrito: %d, ahora sin nada que apuntar\n", $escritas);
$barrerLoApartado();

print "\n=====================================================================\n";
print "  Queda exportar la configuracion y pasar el guardian.\n";
print "=====================================================================\n\n";
