<?php

/**
 * @file
 * Que automatismo escribe el nombre de una talla, y con que receta.
 *
 * El nombre que se ve en el formulario de producto -"PRUEBA producto - Black -
 * XXS"- no lo ha escrito nadie a mano: lo monta un proceso de ECA juntando el
 * producto, el color y la talla. Interesa saber cual, con que plantilla, y en que
 * momento se dispara, porque de eso depende si el nombre se queda viejo cuando
 * alguien cambia el nombre del producto.
 *
 * Uso: drush php:script scripts/quien-escribe-el-nombre-de-la-talla
 */

$factory = \Drupal::configFactory();

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Procesos que escriben el titulo de una talla o de un color\n";
echo str_repeat('=', 82) . "\n\n";

foreach ($factory->listAll('eca.eca.') as $nombre) {
  $proceso = $factory->get($nombre);

  // Solo los que tocan variaciones de producto.
  $eventos = [];
  foreach ($proceso->get('events') ?? [] as $evento) {
    $tipo = $evento['configuration']['type'] ?? '';
    if (str_contains($tipo, 'tec_size_variation') || str_contains($tipo, 'tec_color_variation')) {
      $eventos[] = $evento['plugin'] . '  (' . $tipo . ')';
    }
  }
  if (!$eventos) {
    continue;
  }

  // Y de esos, solo los que escriben en un titulo o etiqueta.
  $escrituras = [];
  foreach ($proceso->get('actions') ?? [] as $id => $accion) {
    $campo = $accion['configuration']['field_name'] ?? '';
    if (!in_array($campo, ['title', 'label', 'name'], TRUE)) {
      continue;
    }
    $escrituras[] = [
      'id' => $id,
      'plugin' => $accion['plugin'],
      'etiqueta' => $accion['label'] ?? '',
      'campo' => $campo,
      'valor' => $accion['configuration']['field_value'] ?? ($accion['configuration']['value'] ?? ''),
      'metodo' => $accion['configuration']['method'] ?? '',
      'guarda' => $accion['configuration']['save_entity'] ?? '',
    ];
  }
  if (!$escrituras) {
    continue;
  }

  printf("  %s  %s\n", $proceso->get('id'), $proceso->get('status') ? '' : '(APAGADO)');
  printf("      %s\n", $proceso->get('label'));
  foreach ($eventos as $evento) {
    printf("      se dispara con: %s\n", $evento);
  }
  foreach ($escrituras as $escritura) {
    printf("      escribe en '%s':\n", $escritura['campo']);
    printf("          plantilla: %s\n", $escritura['valor']);
    printf("          accion:    %s (%s)\n", $escritura['etiqueta'], $escritura['plugin']);
    if ($escritura['guarda'] !== '') {
      printf("          guarda:    %s\n", $escritura['guarda'] ? 'si' : 'no');
    }
  }
  echo "\n";
}

echo str_repeat('=', 82) . "\n";
echo " Y que dice hoy la ficha\n";
echo str_repeat('=', 82) . "\n\n";

$gestor = \Drupal::entityTypeManager();
$almacen = $gestor->getStorage('tec_product');

$ids = $almacen->getQuery()->accessCheck(FALSE)->condition('type', 'tec_size_variation')->execute();
foreach ($almacen->loadMultiple($ids) as $talla) {
  printf("  talla %s: \"%s\"\n", $talla->id(), $talla->label());
  printf("      producto:  \"%s\"\n", $talla->get('field_tec_product')->entity?->label() ?? '(ninguno)');
  printf("      color:     \"%s\"\n", $talla->get('field_tec_color_variation')->entity?->label() ?? '(ninguno)');
  printf("      talla:     \"%s\"\n", $talla->get('field_tec_size')->entity?->label() ?? '(ninguna)');
  printf("      creada:    %s\n", date('d/m/Y H:i', (int) $talla->get('created')->value));
  printf("      cambiada:  %s\n", date('d/m/Y H:i', (int) $talla->get('changed')->value));
}

$ids = $almacen->getQuery()->accessCheck(FALSE)->condition('type', 'tec_product')->execute();
foreach ($almacen->loadMultiple($ids) as $producto) {
  printf("\n  producto %s: \"%s\"\n", $producto->id(), $producto->label());
  printf("      creado:    %s\n", date('d/m/Y H:i', (int) $producto->get('created')->value));
  printf("      cambiado:  %s\n", date('d/m/Y H:i', (int) $producto->get('changed')->value));
}

echo "\n";
