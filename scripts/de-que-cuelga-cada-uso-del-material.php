<?php

/**
 * @file
 * De que cuelga cada cosa que apunta a un material, para poder decir donde esta.
 *
 *   php vendor\bin\drush.php scr scripts/de-que-cuelga-cada-uso-del-material.php
 *
 * El aviso que impide borrar un material lista lo que le apunta, pero listarlo
 * por su nombre no sirve de nada: los escandallos y las lineas de pedido se
 * llaman igual que el material, asi que salen tres lineas diciendo 'khun' y el
 * que lo lee sigue sin saber en que producto ni en que pedido esta metido.
 *
 * Este guion mira, para cada cosa que apunta a un material, que campos de
 * referencia tiene hacia arriba y que hay dentro. De ahi sale como nombrar cada
 * uso de forma que se entienda.
 *
 * No escribe nada.
 */

$gestor = \Drupal::entityTypeManager();
$terminos = $gestor->getStorage('taxonomy_term');

$ids = $terminos->getQuery()->accessCheck(FALSE)->condition('vid', 'tec_inventory')->sort('tid')->execute();

foreach ($terminos->loadMultiple($ids) as $material) {
  $usos = [];
  foreach ($gestor->getStorage('field_storage_config')->loadMultiple() as $almacen) {
    if (!in_array($almacen->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
      continue;
    }
    if ($almacen->getSetting('target_type') !== 'taxonomy_term') {
      continue;
    }
    $tipo = $almacen->getTargetEntityTypeId();
    $campo = $almacen->getName();
    try {
      $almacenEntidad = $gestor->getStorage($tipo);
      $encontrados = $almacenEntidad->getQuery()
        ->accessCheck(FALSE)
        ->condition($campo, $material->id())
        ->execute();
    }
    catch (\Throwable $e) {
      continue;
    }
    foreach ($almacenEntidad->loadMultiple($encontrados) as $entidad) {
      $usos[] = $entidad;
    }
  }

  if (!$usos) {
    continue;
  }

  echo "\n";
  echo str_repeat('=', 88) . "\n";
  printf("Material %s: %s  (%d usos)\n", $material->id(), $material->label(), count($usos));
  echo str_repeat('=', 88) . "\n";

  foreach ($usos as $entidad) {
    printf(
      "\n  %s / %s  id %s\n      se llama: '%s'\n",
      $entidad->getEntityTypeId(),
      $entidad->bundle(),
      $entidad->id(),
      $entidad->label()
    );

    // Todas las referencias hacia otras entidades del ERP, que es por donde se
    // sube hasta el producto o el pedido.
    foreach ($entidad->getFields() as $nombre => $lista) {
      $definicion = $lista->getFieldDefinition();
      if ($definicion->getType() !== 'entity_reference') {
        continue;
      }
      $destino = $definicion->getSetting('target_type');
      if (in_array($destino, ['taxonomy_term', 'user'], TRUE)) {
        continue;
      }
      if ($lista->isEmpty()) {
        continue;
      }
      $apuntado = $lista->entity;
      printf(
        "      %-30s -> %-16s %s\n",
        $nombre,
        $destino,
        $apuntado ? "'" . $apuntado->label() . "'" : '(roto)'
      );
    }
  }
}

echo "\n";
