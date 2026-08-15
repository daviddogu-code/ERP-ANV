<?php

/**
 * @file
 * El molde de una portada que funciona, para rehacer la de marcas igual.
 *
 * Colores es el caso gemelo de marcas: un vocabulario de taxonomia con una
 * vista en cuadricula metida en un nodo portada. Si copiamos su estructura pieza
 * a pieza, la de marcas queda indistinguible de las que ya funcionan en vez de
 * ser un remiendo con otra forma.
 *
 * Uso: drush php:script scripts/como-esta-hecha-la-portada-de-colores
 */

$almacen = \Drupal::entityTypeManager()->getStorage('node');
$nodo = $almacen->load(9);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Nodo 9 (Colors): todos sus campos\n";
echo str_repeat('=', 82) . "\n\n";

foreach ($nodo->getFields() as $nombre => $campo) {
  $definicion = $campo->getFieldDefinition();
  $valor = $campo->isEmpty() ? '(vacio)' : json_encode($campo->getValue(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  // El diseno se imprime aparte, aqui solo ensuciaria.
  if ($nombre === 'layout_builder__layout') {
    $valor = '(se imprime abajo)';
  }
  printf(
    "  %-28s %-12s %s\n",
    $nombre,
    $definicion->getType(),
    mb_strimwidth($valor, 0, 300, '...')
  );
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Nodo 9: el diseno, seccion por seccion\n";
echo str_repeat('=', 82) . "\n\n";

foreach ($nodo->get('layout_builder__layout') as $indice => $trozo) {
  $seccion = $trozo->section;
  printf("  Seccion %d\n", $indice);
  printf("      plantilla: %s\n", $seccion->getLayoutId());
  printf("      ajustes:   %s\n", json_encode($seccion->getLayoutSettings(), JSON_UNESCAPED_SLASHES));
  echo "\n";
  foreach ($seccion->getComponents() as $uuid => $componente) {
    printf("      componente %s\n", $uuid);
    printf("          plugin: %s\n", $componente->getPluginId());
    printf("          region: %s\n", $componente->getRegion());
    printf("          peso:   %s\n", $componente->getWeight());
    printf("          config: %s\n", json_encode($componente->get('configuration'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "\n";
  }
}

echo str_repeat('=', 82) . "\n";
echo " Las direcciones cortas de cada portada\n";
echo str_repeat('=', 82) . "\n\n";

$alias = \Drupal::entityTypeManager()->getStorage('path_alias');
$ids = $alias->getQuery()->accessCheck(FALSE)->condition('path', '/node/%', 'LIKE')->execute();
foreach ($alias->loadMultiple($ids) as $uno) {
  printf("  %-14s -> %s\n", $uno->getAlias(), $uno->getPath());
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Hay algun rastro de la direccion de marcas suelto por ahi\n";
echo str_repeat('=', 82) . "\n\n";

$sueltos = $alias->getQuery()->accessCheck(FALSE)->condition('path', '/node/4')->execute();
echo $sueltos
  ? "  si, quedan alias apuntando a /node/4: " . implode(', ', $sueltos) . "\n"
  : "  no, ningun alias apunta a /node/4\n";

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Quien pinta los iconos de la pantalla de inicio\n";
echo str_repeat('=', 82) . "\n\n";

// Buscamos la vista que lista portadas: es la que da los iconos del inicio y
// hay que saber por que criterio ordena y filtra antes de meter un nodo nuevo.
foreach (\Drupal::entityTypeManager()->getStorage('view')->loadMultiple() as $vista) {
  $base = $vista->get('base_table');
  if ($base !== 'node_field_data') {
    continue;
  }
  $muestra = json_encode($vista->get('display'), JSON_UNESCAPED_SLASHES);
  if (!str_contains($muestra, 'tec_landing_page')) {
    continue;
  }
  $porDefecto = $vista->get('display')['default']['display_options'] ?? [];
  printf("  vista: %s (%s)\n", $vista->id(), $vista->label());
  printf("      activa:  %s\n", $vista->status() ? 'si' : 'NO');
  printf("      filtros: %s\n", implode(', ', array_keys($porDefecto['filters'] ?? [])));
  printf("      orden:   %s\n", implode(', ', array_keys($porDefecto['sorts'] ?? [])));
  printf("      campos:  %s\n", implode(', ', array_keys($porDefecto['fields'] ?? [])));
  foreach ($vista->get('display') as $id => $display) {
    printf(
      "      display %-10s %-8s %s\n",
      $id,
      $display['display_plugin'],
      $display['display_options']['path'] ?? ''
    );
  }
  echo "\n";
}
