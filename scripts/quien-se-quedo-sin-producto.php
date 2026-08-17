<?php

/**
 * @file
 * Que colores y que tallas han perdido el hilo que sube hasta su producto.
 *
 *   php vendor\bin\drush.php scr scripts/quien-se-quedo-sin-producto.php
 *
 * Solo lee y cuenta. No toca nada.
 *
 * Un producto tiene colores y cada color tiene tallas, pero ademas cada hijo
 * guarda un atajo directo al abuelo en field_tec_product. Ese atajo es el que
 * usan las vistas para construir enlaces, asi que cuando falta la pantalla
 * sigue saliendo pero el lapiz de editar fabrica una direccion coja del estilo
 * /tec_product/ sin numero detras, y al guardar se aterriza en un 404.
 *
 * El atajo lo rellenan dos ECA que escuchan solo el insert y solo actuan si el
 * enlace al padre ya esta puesto en ese instante. Nada lo repara despues, asi
 * que lo que nace torcido se queda torcido.
 *
 * Mira tres cosas por cada ficha:
 *
 *   1. Si tiene el atajo al producto.
 *   2. Si no lo tiene, si se puede deducir (por el color, o por quien la lista).
 *   3. Si el padre la tiene apuntada en su propia lista de hijos.
 */

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
 * El numero al que apunta un campo de referencia, o cero.
 */
function apunta_a($ficha, string $campo): int {
  if (!$ficha || !$ficha->hasField($campo) || $ficha->get($campo)->isEmpty()) {
    return 0;
  }
  return (int) $ficha->get($campo)->first()->target_id;
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

$productos = fichas_de('tec_product');
$colores = fichas_de('tec_color_variation');
$tallas = fichas_de('tec_size_variation');

// Quien lista a quien, para poder deducir al reves.
$duenoDelColor = [];
foreach ($productos as $producto) {
  foreach (lista_de($producto, 'field_tec_color_variations') as $colorId) {
    $duenoDelColor[$colorId] = (int) $producto->id();
  }
}
$duenoDeLaTalla = [];
foreach ($colores as $color) {
  foreach (lista_de($color, 'field_tec_size_variations') as $tallaId) {
    $duenoDeLaTalla[$tallaId] = (int) $color->id();
  }
}

print "\n";
printf("  %d productos, %d colores, %d tallas\n\n", count($productos), count($colores), count($tallas));

// Colores.
print "Colores sin atajo al producto\n";
print str_repeat('-', 78) . "\n";
printf("  %-8s %-28s %-12s %s\n", 'ficha', 'nombre', 'deducible', 'lo lista su producto');

$coloresRotos = 0;
$coloresSalvables = 0;
foreach ($colores as $color) {
  $id = (int) $color->id();
  $suProducto = apunta_a($color, 'field_tec_product');
  $loListan = $duenoDelColor[$id] ?? 0;

  if ($suProducto !== 0) {
    // Tiene atajo. Solo avisamos si apunta a otro sitio que quien lo lista.
    if ($loListan !== 0 && $loListan !== $suProducto) {
      printf("  %-8s %-28s %-12s %s\n", $id, mb_strimwidth((string) $color->label(), 0, 28, '...'), 'discrepa', 'lo lista ' . $loListan . ', el atajo dice ' . $suProducto);
      $coloresRotos++;
    }
    continue;
  }

  $coloresRotos++;
  $coloresSalvables += $loListan !== 0 ? 1 : 0;
  printf(
    "  %-8s %-28s %-12s %s\n",
    $id,
    mb_strimwidth((string) $color->label(), 0, 28, '...'),
    $loListan !== 0 ? 'si' : 'NO',
    $loListan !== 0 ? 'producto ' . $loListan : 'nadie'
  );
}
if ($coloresRotos === 0) {
  print "  ninguno\n";
}
print "\n";

// Tallas.
print "Tallas sin atajo al producto\n";
print str_repeat('-', 78) . "\n";
printf("  %-8s %-24s %-10s %-12s %s\n", 'ficha', 'nombre', 'su color', 'deducible', 'de donde');

$tallasRotas = 0;
$tallasSalvables = 0;
foreach ($tallas as $talla) {
  $id = (int) $talla->id();
  $suProducto = apunta_a($talla, 'field_tec_product');
  $suColor = apunta_a($talla, 'field_tec_color_variation');
  if ($suColor === 0) {
    $suColor = $duenoDeLaTalla[$id] ?? 0;
  }

  // Por donde se podria deducir el producto.
  $porElColor = 0;
  if ($suColor !== 0 && isset($colores[$suColor])) {
    $porElColor = apunta_a($colores[$suColor], 'field_tec_product') ?: ($duenoDelColor[$suColor] ?? 0);
  }

  if ($suProducto !== 0) {
    if ($porElColor !== 0 && $porElColor !== $suProducto) {
      printf("  %-8s %-24s %-10s %-12s %s\n", $id, mb_strimwidth((string) $talla->label(), 0, 24, '...'), $suColor ?: '-', 'discrepa', 'el color dice ' . $porElColor . ', el atajo dice ' . $suProducto);
      $tallasRotas++;
    }
    continue;
  }

  $tallasRotas++;
  $tallasSalvables += $porElColor !== 0 ? 1 : 0;
  printf(
    "  %-8s %-24s %-10s %-12s %s\n",
    $id,
    mb_strimwidth((string) $talla->label(), 0, 24, '...'),
    $suColor ?: '-',
    $porElColor !== 0 ? 'si' : 'NO',
    $porElColor !== 0 ? 'producto ' . $porElColor : ($suColor === 0 ? 'no tiene color' : 'su color tampoco lo sabe')
  );
}
if ($tallasRotas === 0) {
  print "  ninguna\n";
}
print "\n";

// Los hilos de vuelta, que son los que hacen que una ficha salga en pantalla.
print "Hijos que su padre no tiene apuntados\n";
print str_repeat('-', 78) . "\n";

$sueltos = 0;
$colgando = 0;
$parejas = [
  ['nombre' => 'color', 'hijas' => $colores, 'sube' => 'field_tec_product', 'lista' => 'field_tec_color_variations', 'padres' => $productos],
  ['nombre' => 'talla', 'hijas' => $tallas, 'sube' => 'field_tec_color_variation', 'lista' => 'field_tec_size_variations', 'padres' => $colores],
];
foreach ($parejas as $pareja) {
  foreach ($pareja['hijas'] as $hija) {
    $id = (int) $hija->id();
    $padreId = apunta_a($hija, $pareja['sube']);
    if ($padreId === 0) {
      continue;
    }
    $nombre = mb_strimwidth((string) $hija->label(), 0, 28, '...');
    if (!isset($pareja['padres'][$padreId])) {
      // Apunta a una ficha que no existe, o que no es del tipo que deberia.
      $existe = \Drupal::entityTypeManager()->getStorage('tec_product')->load($padreId);
      printf(
        "  %-6s %-6s %-28s apunta a %s, que %s\n",
        $pareja['nombre'],
        $id,
        $nombre,
        $padreId,
        $existe ? 'es un ' . $existe->bundle() : 'no existe'
      );
      $colgando++;
      continue;
    }
    if (!in_array($id, lista_de($pareja['padres'][$padreId], $pareja['lista']), TRUE)) {
      printf("  %-6s %-6s %-28s existe pero %s no lo tiene en su lista\n", $pareja['nombre'], $id, $nombre, $padreId);
      $sueltos++;
    }
  }
}
if ($sueltos === 0 && $colgando === 0) {
  print "  ninguno\n";
}
print "\n";

print str_repeat('=', 78) . "\n";
printf("  colores mal: %d (se pueden deducir %d)\n", $coloresRotos, $coloresSalvables);
printf("  tallas mal:  %d (se pueden deducir %d)\n", $tallasRotas, $tallasSalvables);
printf("  hijos sueltos de la lista de su padre: %d\n", $sueltos);
printf("  hijos que apuntan a un padre que no esta: %d\n", $colgando);
print "\n";
