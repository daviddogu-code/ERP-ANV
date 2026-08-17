<?php

/**
 * @file
 * Ata otra vez los hilos sueltos entre productos, colores y tallas.
 *
 *   php vendor\bin\drush.php scr scripts/nadie-pierde-el-hilo-con-su-producto.php
 *   php vendor\bin\drush.php scr scripts/nadie-pierde-el-hilo-con-su-producto.php -- --aplicar
 *
 * Sin --aplicar solo cuenta lo que haria.
 *
 * Dos hilos distintos, los dos rotos por la misma razon: las ECA que los atan
 * escuchan solo el insert, asi que lo que nace en mal orden se queda torcido y
 * nadie vuelve a por ello.
 *
 *   1. Los enlaces hacia arriba. Cada talla apunta a su color y a su producto, y
 *      cada color a su producto. Nada de eso hace falta para dibujar la pagina
 *      del producto, que se lee de arriba abajo, asi que una ficha con esos
 *      campos vacios parece sana hasta que algo sube por el arbol. El lapiz de
 *      editar fabrica /tec_product/ sin numero detras y se aterriza en un 404;
 *      duplicar una talla lee el color de la talla, no lo encuentra y el proceso
 *      se corta sin escribir una linea. Esto ya no puede volver a pasar, porque
 *      los dos enlaces se rellenan en presave, asi que aqui basta con volver a
 *      guardar la ficha.
 *
 *   2. La lista del padre. Un color que su producto no tiene apuntado, o una
 *      talla que su color no tiene apuntada, no sale en pantalla aunque exista.
 *      Eso se arregla del lado del padre.
 *
 * Se puede lanzar dos veces.
 */

$aplicar = in_array('--aplicar', $extra ?? [], TRUE) || in_array('--aplicar', $_SERVER['argv'] ?? [], TRUE);

$almacen = \Drupal::entityTypeManager()->getStorage('tec_product');

/**
 * Todas las fichas de un tipo.
 */
function fichas_de(string $paquete): array {
  $almacen = \Drupal::entityTypeManager()->getStorage('tec_product');
  $ids = $almacen->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', $paquete)
    ->sort('id')
    ->execute();
  return $ids ? $almacen->loadMultiple($ids) : [];
}

/**
 * Los numeros que lista un campo de referencia multiple.
 */
function lista_de($ficha, string $campo): array {
  if (!$ficha || !$ficha->hasField($campo) || $ficha->get($campo)->isEmpty()) {
    return [];
  }
  return array_map(
    static fn(array $valor): int => (int) $valor['target_id'],
    $ficha->get($campo)->getValue()
  );
}

print "\n";
print $aplicar ? "Aplicando.\n\n" : "Ensayo. Nada se guarda. Anade -- --aplicar para hacerlo de verdad.\n\n";

// 1. Los enlaces hacia arriba.
print "Enlaces hacia arriba que faltan\n";
print str_repeat('-', 78) . "\n";

$atados = 0;
$imposibles = 0;
foreach (['tec_color_variation', 'tec_size_variation'] as $paquete) {
  foreach (fichas_de($paquete) as $ficha) {
    // Las mismas dos preguntas que se hace el presave, en el mismo orden.
    $faltan = [];
    $sinremedio = [];

    if (
      $paquete === 'tec_size_variation'
      && $ficha->hasField('field_tec_color_variation')
      && $ficha->get('field_tec_color_variation')->isEmpty()
    ) {
      $color = $ficha->id() ? _tec_inventory_who_lists((int) $ficha->id(), 'field_tec_size_variations') : NULL;
      $color === NULL ? $sinremedio[] = 'color' : $faltan[] = 'color ' . $color;
    }

    if ($ficha->hasField('field_tec_product') && $ficha->get('field_tec_product')->isEmpty()) {
      $producto = _tec_inventory_product_above($ficha);
      $producto === NULL ? $sinremedio[] = 'producto' : $faltan[] = 'producto ' . $producto;
    }

    if ($faltan === [] && $sinremedio === []) {
      continue;
    }

    $nombre = mb_strimwidth((string) $ficha->label(), 0, 24, '...');
    if ($faltan !== []) {
      printf("  [ OK ] %-22s %-8s %-24s %s\n", $paquete, $ficha->id(), $nombre, implode(', ', $faltan));
      $atados++;
      if ($aplicar) {
        // Guardar basta: el presave rellena los dos enlaces.
        $ficha->save();
      }
    }
    if ($sinremedio !== []) {
      printf("  [ NO ] %-22s %-8s %-24s nadie sabe su %s\n", $paquete, $ficha->id(), $nombre, implode(' ni su ', $sinremedio));
      $imposibles++;
    }
  }
}
if ($atados === 0 && $imposibles === 0) {
  print "  ninguno\n";
}
print "\n";

// 2. Las listas del padre.
print "Hijos que su padre no tiene apuntados\n";
print str_repeat('-', 78) . "\n";

$colgados = 0;
$parejas = [
  ['hijo' => 'tec_color_variation', 'sube' => 'field_tec_product', 'lista' => 'field_tec_color_variations'],
  ['hijo' => 'tec_size_variation', 'sube' => 'field_tec_color_variation', 'lista' => 'field_tec_size_variations'],
];
foreach ($parejas as $pareja) {
  foreach (fichas_de($pareja['hijo']) as $ficha) {
    if (!$ficha->hasField($pareja['sube']) || $ficha->get($pareja['sube'])->isEmpty()) {
      continue;
    }
    $padreId = (int) $ficha->get($pareja['sube'])->first()->target_id;
    $padre = \Drupal::entityTypeManager()->getStorage('tec_product')->load($padreId);
    if (!$padre || !$padre->hasField($pareja['lista'])) {
      continue;
    }
    $lista = lista_de($padre, $pareja['lista']);
    if (in_array((int) $ficha->id(), $lista, TRUE)) {
      continue;
    }
    printf("  [ OK ] %-22s %-8s %-24s se anade a %s\n", $pareja['hijo'], $ficha->id(), mb_strimwidth((string) $ficha->label(), 0, 24, '...'), $padreId);
    $colgados++;
    if ($aplicar) {
      $lista[] = (int) $ficha->id();
      $padre->set($pareja['lista'], $lista);
      $padre->save();
    }
  }
}
if ($colgados === 0) {
  print "  ninguno\n";
}
print "\n";

print str_repeat('=', 78) . "\n";
printf("  fichas reatadas: %d", $atados);
print $imposibles ? sprintf(", sin remedio: %d\n", $imposibles) : "\n";
printf("  hijos devueltos a la lista de su padre: %d\n", $colgados);
print $aplicar ? "\n  Hecho.\n\n" : "\n  Ensayo. Nada se ha guardado.\n\n";
