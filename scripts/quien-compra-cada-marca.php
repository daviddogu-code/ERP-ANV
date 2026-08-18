<?php

/**
 * @file
 * Quien compra cada marca, segun los tres sitios que lo dicen.
 *
 *   php vendor\bin\drush.php scr scripts/quien-compra-cada-marca.php
 *
 * La misma pregunta ha estado contestada en tres lugares distintos:
 *
 *   1. La marca lo decia de si misma, en su campo Customer. Una sola casilla, o
 *      sea que una marca solo podia tener un cliente. Retirado el 19 de agosto
 *      de 2026, cuando sus datos se mudaron al tercero.
 *   2. Cada producto lo dice por su cuenta, con su propio campo Customer, que
 *      hoy sigue siendo obligatorio. Nada obliga a que coincida con su marca.
 *   3. Y el cliente lo dice de sus marcas, que es el sitio donde se queda.
 *
 * Solo mira. No arregla nada, no escribe nada.
 */

const CAMPO_NUEVO = 'field_tec_brands';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');
$contactos = $gestor->getStorage('tec_crm');

$marcas = $terminos->loadByProperties(['vid' => 'tec_brands']);

/**
 * El nombre de un contacto, con su numero detras para poder buscarlo.
 */
$quien = function ($ficha): string {
  return $ficha ? $ficha->label() . ' (' . $ficha->id() . ')' : '(ninguno)';
};

print "\n";
print "=====================================================================\n";
print "  Quien compra cada marca\n";
print "=====================================================================\n";

$hayCampoNuevo = isset(\Drupal::service('entity_field.manager')
  ->getFieldDefinitions('tec_crm', 'tec_contact_organization')[CAMPO_NUEVO]);

printf("\n  marcas: %d, productos: %d, contactos: %d\n", count($marcas),
  $productos->getQuery()->accessCheck(FALSE)->condition('type', 'tec_product')->count()->execute(),
  $contactos->getQuery()->accessCheck(FALSE)->count()->execute());
printf("  el sitio nuevo (%s en el contacto): %s\n", CAMPO_NUEVO,
  $hayCampoNuevo ? 'existe' : 'todavia no existe');

foreach ($marcas as $marca) {
  printf("\n  %s (%s)\n", $marca->label(), $marca->id());
  print '  ' . str_repeat('-', 66) . "\n";

  // 1. Lo que decia la marca de si misma.
  if (!$marca->hasField('field_tec_customer')) {
    print "    dice la marca            (ya no tiene ese campo)\n";
  }
  else {
    printf("    dice la marca            %s\n", $quien($marca->get('field_tec_customer')->entity));
  }

  // 2. Lo que dicen sus productos.
  $ids = $productos->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_product')
    ->condition('field_tec_brand', $marca->id())
    ->execute();
  $porProducto = [];
  foreach ($productos->loadMultiple($ids) as $producto) {
    $cliente = $producto->get('field_tec_customer')->entity;
    $clave = $cliente ? $cliente->id() : '0';
    $porProducto[$clave] = ($porProducto[$clave] ?? 0) + 1;
  }
  if ($porProducto === []) {
    print "    dicen sus productos      (no tiene productos)\n";
  }
  foreach ($porProducto as $cid => $cuantos) {
    printf("    dicen sus productos      %-40s en %d producto%s\n",
      $quien($cid === '0' ? NULL : $contactos->load($cid)), $cuantos, $cuantos === 1 ? '' : 's');
  }

  // 3. Lo que dicen los clientes de sus marcas.
  if ($hayCampoNuevo) {
    $desdeCliente = $contactos->loadMultiple($contactos->getQuery()
      ->accessCheck(FALSE)
      ->condition(CAMPO_NUEVO, $marca->id())
      ->execute());
    if ($desdeCliente === []) {
      print "    dicen los clientes       (ninguno la tiene puesta)\n";
    }
    foreach ($desdeCliente as $cliente) {
      printf("    dicen los clientes       %s\n", $quien($cliente));
    }
  }
}

print "\n";
print "  Productos sin marca\n";
print '  ' . str_repeat('-', 66) . "\n";
$sinMarca = $productos->loadMultiple($productos->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_product')
  ->notExists('field_tec_brand')
  ->execute());
if ($sinMarca === []) {
  print "    ninguno\n";
}
foreach ($sinMarca as $producto) {
  printf("    %s (%s), cliente %s\n", $producto->label(), $producto->id(),
    $quien($producto->get('field_tec_customer')->entity));
}

print "\n";
print "  Productos cuyo cliente no es el de su marca\n";
print '  ' . str_repeat('-', 66) . "\n";
$discrepan = 0;
$puedenDiscrepar = $marcas !== [] && reset($marcas)->hasField('field_tec_customer');
if (!$puedenDiscrepar) {
  print "    ya no pueden: la marca no tiene cliente propio con el que discrepar\n";
}
$conMarca = $puedenDiscrepar
  ? $productos->loadMultiple($productos->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_product')
    ->exists('field_tec_brand')
    ->execute())
  : [];
foreach ($conMarca as $producto) {
  $marca = $producto->get('field_tec_brand')->entity;
  $suyo = $marca && $marca->hasField('field_tec_customer')
    ? $marca->get('field_tec_customer')->entity
    : NULL;
  $mio = $producto->get('field_tec_customer')->entity;
  if ($suyo && $mio && $suyo->id() !== $mio->id()) {
    $discrepan++;
    printf("    %s (%s): el producto dice %s, la marca %s dice %s\n",
      $producto->label(), $producto->id(), $quien($mio), $marca->label(), $quien($suyo));
  }
}
if ($puedenDiscrepar && $discrepan === 0) {
  print "    ninguno\n";
}

print "\n=====================================================================\n\n";
