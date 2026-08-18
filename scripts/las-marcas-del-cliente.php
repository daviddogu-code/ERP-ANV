<?php

/**
 * @file
 * El cliente dice que marcas le hacemos, y ese pasa a ser el unico sitio.
 *
 *   php vendor\bin\drush.php scr scripts/las-marcas-del-cliente.php
 *
 * POR QUE.
 *
 * Hasta hoy el ERP contestaba «quien compra esta marca» en dos sitios, y los dos
 * decian lo mismo mal: la marca tenia UN cliente, y el producto tenia UN cliente.
 * Una casilla cada uno, o sea que dos empresas no podian comprar la misma marca
 * y hacia falta repetir la marca para poder repartirla. Y nada obligaba a que el
 * cliente del producto coincidiera con el de su marca, asi que se podian
 * contradecir sin que nada avisara.
 *
 * La realidad es la contraria: una empresa compra varias marcas, y la misma
 * marca la pueden comprar dos empresas. Eso es una lista, no una casilla, y va
 * en la ficha del cliente, que es donde se sabe. Ademas hace falta poder
 * apuntarla antes de que exista ningun producto -un cliente nuevo trae sus
 * marcas el primer dia y los productos vienen despues-, y por eso no puede
 * deducirse de los productos.
 *
 * QUE HACE.
 *
 * 1. Crea el campo `field_tec_brands` en las dos clases de contacto, sin limite
 *    de marcas y arrastrable, porque el orden lo pone la casa y va a servir para
 *    ordenar las lineas de las proformas.
 * 2. Se lleva a ese campo lo que hoy dicen los dos sitios viejos.
 * 3. Comprueba que no se ha perdido ni un par, y SOLO si sale bien retira el
 *    campo Customer de la marca. Si algo no cuadra, se para y no borra nada.
 *
 * El cliente del PRODUCTO no se toca aqui: ese es el corte gordo y tiene ocho
 * sitios detras.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir y respeta el orden que
 * alguien haya puesto a mano.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;

const CAMPO = 'field_tec_brands';
const VIEJO = 'field_tec_customer';
const CLASES = ['tec_contact_organization', 'tec_contact_person'];

$gestor = \Drupal::entityTypeManager();
$pantallas = \Drupal::service('entity_display.repository');
$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');
$contactos = $gestor->getStorage('tec_crm');

$puestas = 0;

print "\n";
print "=====================================================================\n";
print "  Las marcas del cliente\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. El campo.
// ---------------------------------------------------------------------------
print "El campo nuevo\n";
print str_repeat('-', 70) . "\n";

if (!FieldStorageConfig::loadByName('tec_crm', CAMPO)) {
  FieldStorageConfig::create([
    'field_name' => CAMPO,
    'entity_type' => 'tec_crm',
    'type' => 'entity_reference',
    // Sin limite. Es lo que separa este campo del que sustituye.
    'cardinality' => FieldStorageConfigInterface::CARDINALITY_UNLIMITED,
    'settings' => ['target_type' => 'taxonomy_term'],
  ])->save();
  printf("  %-58s creado\n", 'el almacen del campo');
  $puestas++;
}
else {
  printf("  %-58s ya estaba\n", 'el almacen del campo');
}

foreach (CLASES as $clase) {
  if (!FieldConfig::loadByName('tec_crm', $clase, CAMPO)) {
    FieldConfig::create([
      'field_name' => CAMPO,
      'entity_type' => 'tec_crm',
      'bundle' => $clase,
      'label' => 'Brands',
      'description' => 'Brands we make for this customer, in the order they should be listed. The same brand can be bought by more than one customer. Add them here even before the first product exists.',
      'required' => FALSE,
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['tec_brands' => 'tec_brands'],
          'sort' => ['field' => 'name', 'direction' => 'asc'],
          'auto_create' => FALSE,
          'auto_create_bundle' => '',
        ],
      ],
    ])->save();
    printf("  %-58s creado\n", 'el campo en ' . $clase);
    $puestas++;
  }
  else {
    printf("  %-58s ya estaba\n", 'el campo en ' . $clase);
  }

  // El widget de siempre con varios valores trae la tabla de arrastrar, que es
  // lo que hace falta para que el orden sea de la casa y no alfabetico.
  $formulario = $pantallas->getFormDisplay('tec_crm', $clase, 'default');
  $actual = $formulario->getComponent(CAMPO);
  if (($actual['type'] ?? '') !== 'entity_reference_autocomplete') {
    $formulario->setComponent(CAMPO, [
      'type' => 'entity_reference_autocomplete',
      // Detras de los datos generales y delante del bloque de proveedor.
      'weight' => 4,
      'region' => 'content',
      'settings' => [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
        'size' => 60,
        'placeholder' => '',
      ],
      'third_party_settings' => [],
    ])->save();
    printf("  %-58s puesto\n", 'y en su formulario, arrastrable');
    $puestas++;
  }
  else {
    printf("  %-58s ya estaba\n", 'y en su formulario, arrastrable');
  }
}

// ---------------------------------------------------------------------------
// 2. Lo que dicen los dos sitios viejos.
// ---------------------------------------------------------------------------
print "\nLo que hay que mudar\n";
print str_repeat('-', 70) . "\n";

$marcas = $terminos->loadByProperties(['vid' => 'tec_brands']);

// cliente => [marca => de donde se supo]
$pares = [];

foreach ($marcas as $marca) {
  if ($marca->hasField(VIEJO) && ($cliente = $marca->get(VIEJO)->entity)) {
    $pares[$cliente->id()][$marca->id()] = 'la marca';
  }
}

foreach ($productos->loadMultiple($productos->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_product')
  ->exists('field_tec_brand')
  ->exists(VIEJO)
  ->execute()) as $producto) {
  $marca = $producto->get('field_tec_brand')->entity;
  $cliente = $producto->get(VIEJO)->entity;
  if ($marca && $cliente && !isset($pares[$cliente->id()][$marca->id()])) {
    $pares[$cliente->id()][$marca->id()] = 'un producto';
  }
}

if ($pares === []) {
  print "  no hay ni un par que mudar\n";
}
foreach ($pares as $cid => $suyas) {
  $cliente = $contactos->load($cid);
  printf("  %s (%s)\n", $cliente ? $cliente->label() : '(borrado)', $cid);
  foreach ($suyas as $tid => $deDonde) {
    printf("      %-40s lo decia %s\n", ($terminos->load($tid)?->label() ?? '?') . ' (' . $tid . ')', $deDonde);
  }
}

// ---------------------------------------------------------------------------
// 3. Escribirlo.
// ---------------------------------------------------------------------------
print "\nEscribiendolo en la ficha del cliente\n";
print str_repeat('-', 70) . "\n";

foreach ($pares as $cid => $suyas) {
  $cliente = $contactos->load($cid);
  if (!$cliente || !$cliente->hasField(CAMPO)) {
    printf("  %-58s SIN CAMPO\n", 'cliente ' . $cid);
    continue;
  }

  // Lo que ya tenga se queda delante y en su orden: si alguien lo ha arrastrado,
  // manda esa mano y no este guion.
  $ya = array_map(fn(array $v): string => (string) $v['target_id'], $cliente->get(CAMPO)->getValue());
  $anadir = array_diff(array_map('strval', array_keys($suyas)), $ya);

  if ($anadir === []) {
    printf("  %-58s ya las tenia\n", $cliente->label());
    continue;
  }

  // Las nuevas entran por nombre, que es el unico orden defendible cuando nadie
  // ha dicho todavia cual quiere.
  usort($anadir, fn(string $a, string $b): int => strcasecmp(
    (string) $terminos->load($a)?->label(),
    (string) $terminos->load($b)?->label()
  ));

  $cliente->set(CAMPO, array_map(
    fn(string $tid): array => ['target_id' => $tid],
    array_merge($ya, $anadir)
  ));
  $cliente->save();
  printf("  %-58s %d puesta%s\n", $cliente->label(), count($anadir), count($anadir) === 1 ? '' : 's');
  $puestas++;
}

// ---------------------------------------------------------------------------
// 4. Comprobar antes de borrar.
// ---------------------------------------------------------------------------
print "\nComprobando que no se ha perdido nada\n";
print str_repeat('-', 70) . "\n";

$contactos->resetCache();
$perdidos = [];
foreach ($pares as $cid => $suyas) {
  $cliente = $contactos->loadUnchanged($cid);
  $tiene = $cliente ? array_map(fn(array $v): string => (string) $v['target_id'], $cliente->get(CAMPO)->getValue()) : [];
  foreach (array_keys($suyas) as $tid) {
    if (!in_array((string) $tid, $tiene, TRUE)) {
      $perdidos[] = 'cliente ' . $cid . ' sin la marca ' . $tid;
    }
  }
}

$total = array_sum(array_map('count', $pares));
if ($perdidos !== []) {
  printf("  MAL: %d pares no han llegado: %s\n", count($perdidos), implode(', ', $perdidos));
  print "\n  No borro el campo viejo. Hay que entender esto primero.\n\n";
  return;
}
printf("  %-58s %d de %d\n", 'todos los pares estan en su sitio nuevo', $total, $total);

// ---------------------------------------------------------------------------
// 5. Retirar el campo viejo de la marca.
// ---------------------------------------------------------------------------
print "\nRetirando el Customer de la marca\n";
print str_repeat('-', 70) . "\n";

$viejo = FieldConfig::loadByName('taxonomy_term', 'tec_brands', VIEJO);
if (!$viejo) {
  printf("  %-58s ya no estaba\n", 'el campo en el vocabulario de marcas');
}
else {
  // Primero fuera de las dos pantallas, que es lo que se ve, y despues el campo.
  foreach (['getFormDisplay', 'getViewDisplay'] as $cual) {
    $pantalla = $pantallas->{$cual}('taxonomy_term', 'tec_brands', 'default');
    if ($pantalla->getComponent(VIEJO)) {
      $pantalla->removeComponent(VIEJO)->save();
      printf("  %-58s fuera\n", $cual === 'getFormDisplay' ? 'del formulario de la marca' : 'de la ficha de la marca');
    }
  }
  $viejo->delete();
  // El almacen se queda: lo comparte el vocabulario de patrones, que no se toca.
  \Drupal::moduleHandler()->loadInclude('field', 'inc', 'field.purge');
  field_purge_batch(1000);
  printf("  %-58s borrado\n", 'el campo, y sus datos barridos');
  $puestas++;
}

print "\n=====================================================================\n";
printf("  %d cosas puestas. Queda exportar la configuracion.\n", $puestas);
print "=====================================================================\n\n";
