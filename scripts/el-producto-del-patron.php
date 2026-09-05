<?php

/**
 * @file
 * Del pattern al pedido: alta, ficha, extras, explosion, material calculation.
 *
 *   php vendor/bin/drush.php scr scripts/el-producto-del-patron.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\tec_inventory\Entity\Pattern;
use Drupal\tec_inventory\MaterialCost;
use Drupal\tec_inventory\OrderBom;
use Drupal\tec_inventory\PatternRecipe;
use Drupal\views\Views;

$etm = \Drupal::entityTypeManager();
$marca = 'PATTERN ' . date('His');
$code = '';
$problemas = 0;
$basura = [];

$mirar = static function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$guardar = static function ($entidad) use (&$basura) {
  $entidad->save();
  $basura[] = $entidad;
  return $entidad;
};

$barrer = static function () use (&$basura, $etm, $marca): void {
  foreach (array_reverse($basura) as $entidad) {
    try {
      foreach ($etm->getStorage('flagging')->loadByProperties(['entity_id' => $entidad->id()]) as $bandera) {
        $bandera->delete();
      }
      if ($entidad->getEntityTypeId() === 'tec_line_item' && $entidad->hasField('field_tec_line_item_bom')) {
        foreach ($entidad->get('field_tec_line_item_bom')->referencedEntities() as $foto) {
          $foto->delete();
        }
      }
      $entidad->delete();
    }
    catch (\Throwable $e) {
    }
  }
};

echo "El producto del patron\n";

try {
  $mirar('el campo del pattern existe', (bool) FieldConfig::loadByName('tec_product', 'tec_product', PatternRecipe::PATTERN_FIELD));
  $mirar('los agujeros viven en el color', (bool) FieldConfig::loadByName('tec_product', 'tec_color_variation', PatternRecipe::HOLES_FIELD));
  $mirar('los extras viven en la talla', (bool) FieldConfig::loadByName('tec_product', 'tec_size_variation', PatternRecipe::EXTRAS_FIELD));
  $mirar('Oscar field_tec_pattern sigue ahi', (bool) FieldConfig::loadByName('tec_product', 'tec_product', 'field_tec_pattern'));

  $tallas = $etm->getStorage('taxonomy_term')->loadMultiple(
    $etm->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_sizes')
      ->sort('weight')
      ->sort('name')
      ->range(0, 2)
      ->execute()
  );
  $talla_a = array_shift($tallas);
  $talla_b = array_shift($tallas);
  $tipos = $etm->getStorage('taxonomy_term')->loadMultiple(
    $etm->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_materials')
      ->sort('name')
      ->range(0, 1)
      ->execute()
  );
  $tipo = array_shift($tipos);
  $tipos_producto = $etm->getStorage('taxonomy_term')->loadMultiple(
    $etm->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_product_types')
      ->sort('name')
      ->range(0, 1)
      ->execute()
  );
  $tipo_producto = array_shift($tipos_producto);
  $colores = $etm->getStorage('taxonomy_term')->loadMultiple(
    $etm->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_colors')
      ->sort('name')
      ->range(0, 1)
      ->execute()
  );
  $color_term = array_shift($colores);
  $mirar('hay tallas, tipo, product type y color', $talla_a && $talla_b && $tipo && $tipo_producto && $color_term);

  $material = static function (string $nombre, string $compra, $tipo_mat = NULL) use ($guardar, $etm) {
    $valores = [
      'vid' => 'tec_inventory',
      'name' => $nombre,
      'field_tec_stock_level' => 0,
      'field_tec_cost' => $compra,
      'field_tec_units' => 1,
      'field_tec_split_into' => 1,
    ];
    if ($tipo_mat) {
      $valores['field_tec_material_type'] = $tipo_mat->id();
    }
    return $guardar($etm->getStorage('taxonomy_term')->create($valores));
  };
  $foam = $material($marca . ' foam 15 mm', '10.00');
  $leather_black = $material($marca . ' Leather Black', '20.00', $tipo);
  $sticker = $material($marca . ' sticker', '1.00');
  $mirar('el desplegable del tipo ofrece Leather Black',
    isset(PatternRecipe::skusOfType((int) $tipo->id())[(int) $leather_black->id()])
  );

  $existia = Pattern::loadByCode('055');
  $code = $existia ? ('T2' . date('His')) : '055';
  $patron = Pattern::create([
    'code' => $code,
    'name' => 'Gloves',
    'product_type' => $tipo_producto ? (int) $tipo_producto->id() : NULL,
    'product_material' => 'leather',
  ]);
  $patron->set('sizes', [
    ['target_id' => $talla_a->id()],
    ['target_id' => $talla_b->id()],
  ]);
  $patron->setLines([
    [
      'label' => 'Foam',
      'cells' => [
        (int) $talla_a->id() => [
          'kind' => 'material',
          'target_id' => (int) $foam->id(),
          'qty' => '1/15',
        ],
        (int) $talla_b->id() => [
          'kind' => 'material',
          'target_id' => (int) $foam->id(),
          'qty' => '1/12',
        ],
      ],
    ],
    [
      'label' => 'Leather',
      'cells' => [
        (int) $talla_a->id() => [
          'kind' => 'type',
          'target_id' => (int) $tipo->id(),
          'qty' => '5',
        ],
        (int) $talla_b->id() => [
          'kind' => 'type',
          'target_id' => (int) $tipo->id(),
          'qty' => '6',
        ],
      ],
    ],
  ]);
  $guardar($patron);

  $prod = $guardar($etm->getStorage('tec_product')->create([
    'type' => 'tec_product',
    'title' => $marca . ' producto',
    'field_product_name' => $marca . ' producto',
    'field_tec_product_type' => $tipo_producto->id(),
    'field_tec_product_material' => 'leather',
    PatternRecipe::PATTERN_FIELD => $patron->id(),
  ]));
  $col = $guardar($etm->getStorage('tec_product')->create([
    'type' => 'tec_color_variation',
    'title' => $marca . ' black',
    'field_tec_colors' => $color_term->id(),
    'field_tec_product' => $prod->id(),
  ]));
  PatternRecipe::setHoles($col, [(int) $tipo->id() => (int) $leather_black->id()]);
  $col->save();
  $prod->set('field_tec_color_variations', [$col->id()]);
  $prod->save();

  $col = $etm->getStorage('tec_product')->loadUnchanged($col->id());
  $size = NULL;
  $otra = NULL;
  foreach ($col->get('field_tec_size_variations')->referencedEntities() as $candidate) {
    if (PatternRecipe::sizeTermId($candidate) === (int) $talla_a->id()) {
      $size = $candidate;
    }
    if (PatternRecipe::sizeTermId($candidate) === (int) $talla_b->id()) {
      $otra = $candidate;
    }
  }
  $mirar('el color nace con las tallas del pattern', $size && $otra);

  [$codigo_pattern, $html_pattern] = (static function () use ($code, $prod) {
    $peticion = \Symfony\Component\HttpFoundation\Request::create('/pattern/' . $code);
    $peticion->setSession(\Drupal::service('session'));
    $cuenta = \Drupal::currentUser()->getAccount();
    \Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));
    try {
      $respuesta = \Drupal::service('http_kernel')->handle(
        $peticion,
        \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST,
        FALSE
      );
      $html = (string) $respuesta->getContent();
      $codigo = $respuesta->getStatusCode();
    }
    catch (\Throwable $e) {
      $html = $e->getMessage();
      $codigo = 0;
    }
    \Drupal::currentUser()->setAccount($cuenta);
    return [$codigo, $html];
  })();
  $prod = $etm->getStorage('tec_product')->loadUnchanged($prod->id());
  $mirar('la ficha del pattern enseña este producto',
    $codigo_pattern === 200
    && str_contains($html_pattern, PatternRecipe::productTitle($prod))
    && str_contains($html_pattern, '/tec_product/' . $prod->id())
  );
  if (!$size) {
    $size = $guardar($etm->getStorage('tec_product')->create([
      'type' => 'tec_size_variation',
      'title' => $marca . ' size',
      'field_tec_size' => $talla_a->id(),
      'field_tec_color_variation' => $col->id(),
      'field_tec_product' => $prod->id(),
      'field_tec_price' => '100.00',
    ]));
    $col->set('field_tec_size_variations', [$size->id()]);
    $col->save();
  }
  PatternRecipe::setExtras($size, [['target_id' => (int) $sticker->id(), 'qty' => '2']]);
  $size->set('field_tec_price', '100.00');
  $size->save();
  if ($otra) {
    $otra = $etm->getStorage('tec_product')->loadUnchanged($otra->id());
  }
  $col = $etm->getStorage('tec_product')->loadUnchanged($col->id());
  $mirar('el extra de una talla no sale en la otra',
    (bool) PatternRecipe::extrasOfSize($size)
    && $otra
    && !PatternRecipe::extrasOfSize($otra)
  );
  $mirar('el extra ya no vive en el color', PatternRecipe::extrasOf($col) === []);

  $lineas_extra = PatternRecipe::extraLinesOf($col);
  $term_a = (int) $talla_a->id();
  $term_b = (int) $talla_b->id();
  $mirar('la ficha ve una linea de extra alineada entre tallas', count($lineas_extra) === 1);
  $mirar('y la talla vacia queda hueca en esa linea',
    (int) ($lineas_extra[0]['cells'][$term_a]['target_id'] ?? 0) === (int) $sticker->id()
    && empty($lineas_extra[0]['cells'][$term_b]['target_id'] ?? 0)
  );
  PatternRecipe::applyExtraLines($col, [
    [
      'cells' => [
        $term_a => ['target_id' => (int) $sticker->id(), 'qty' => '2'],
        $term_b => ['target_id' => 0, 'qty' => ''],
      ],
    ],
  ]);
  $col = $etm->getStorage('tec_product')->loadUnchanged($col->id());
  $size = $etm->getStorage('tec_product')->loadUnchanged($size->id());
  if ($otra) {
    $otra = $etm->getStorage('tec_product')->loadUnchanged($otra->id());
  }
  $mirar('guardar la linea no copia el extra a la otra talla',
    (bool) PatternRecipe::extrasOfSize($size)
    && $otra
    && !PatternRecipe::extrasOfSize($otra)
  );
  $mirar('y el color guarda la linea, no un extra suelto',
    PatternRecipe::hasExtraLines($col)
    && PatternRecipe::extrasOf($col) === []
  );

  $peticion = \Symfony\Component\HttpFoundation\Request::create('/tec_product/' . $prod->id());
  $peticion->setSession(\Drupal::service('session'));
  $cuenta = \Drupal::currentUser()->getAccount();
  \Drupal::currentUser()->setAccount($etm->getStorage('user')->load(1));
  try {
    $respuesta = \Drupal::service('http_kernel')->handle(
      $peticion,
      \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST,
      FALSE
    );
    $html_ficha = (string) $respuesta->getContent();
  }
  catch (\Drupal\Core\Form\EnforcedResponseException $e) {
    $html_ficha = (string) $e->getResponse()->getContent();
  }
  catch (\Throwable $e) {
    $html_ficha = $e->getMessage();
  }
  \Drupal::currentUser()->setAccount($cuenta);
  $mirar('la ficha del producto dice Pattern, no Mould',
    str_contains($html_ficha, 'Pattern ' . $code)
    && str_contains($html_ficha, 'Greens come from the pattern.')
    && !preg_match('/mould/i', $html_ficha)
  );
  $mirar('la ficha del producto ofrece Add extra',
    str_contains($html_ficha, 'Add extra')
    && str_contains($html_ficha, 'tec-ficha-extra-add')
  );
  $mirar('junto a Add extra hay sitio para Saved y Could not save',
    str_contains($html_ficha, 'tec-ficha-extra-status')
    && str_contains($html_ficha, 'aria-live')
  );
  $mirar('las filas verdes y amarillas siguen en el Bill of Materials',
    str_contains($html_ficha, 'tec-recipe--green')
    && str_contains($html_ficha, 'tec-recipe--yellow')
  );
  $mirar('la fila extra enseña el nombre completo',
    str_contains($html_ficha, 'tec-ficha-extra-sku-name')
    && str_contains($html_ficha, (string) $sticker->label())
  );
  $mirar('la fila extra enseña el coste',
    str_contains($html_ficha, 'tec-ficha-extra-cost')
    && (str_contains($html_ficha, 'data-tec-line-cost="2"')
      || str_contains($html_ficha, 'data-tec-line-cost="2.00"'))
  );
  $mirar('un producto con pattern no ofrece + Size',
    !str_contains($html_ficha, '>+ Size</a>')
    && !str_contains($html_ficha, '> + Size</a>')
  );
  $mirar('un producto con pattern no duplica talla ni color',
    !str_contains($html_ficha, 'title="Duplicate size"')
    && !str_contains($html_ficha, 'title="Duplicate variation"')
  );
  $mirar('sigue + Variation y Duplicate product',
    str_contains($html_ficha, '+ Variation')
    && str_contains($html_ficha, 'title="Duplicate product"')
  );
  $admin = $etm->getStorage('user')->load(1);
  $bandera_talla = \Drupal::service('flag')->getFlagById('tec_product_duplicate_size');
  $bandera_color = \Drupal::service('flag')->getFlagById('tec_product_duplicate_color');
  $bandera_prod = \Drupal::service('flag')->getFlagById('tec_product_duplicate');
  $mirar('Duplicate size queda prohibido con pattern',
    $bandera_talla && !$bandera_talla->actionAccess('flag', $admin, $size)->isAllowed()
  );
  $mirar('Duplicate variation queda prohibido con pattern',
    $bandera_color && !$bandera_color->actionAccess('flag', $admin, $col)->isAllowed()
  );
  $mirar('Duplicate product sigue permitido',
    $bandera_prod && $bandera_prod->actionAccess('flag', $admin, $prod)->isAllowed()
  );
  $mirar('+ Size no se ve en el color con pattern',
    $col->hasField('field_tec_create_size')
    && !$col->get('field_tec_create_size')->access('view', $admin)
  );

  $size = $etm->getStorage('tec_product')->loadUnchanged($size->id());
  $mirar('el producto tiene pattern', PatternRecipe::hasPattern($size));
  $mirar('con los agujeros llenos esta encajado', PatternRecipe::wired($size));
  $mirar('no inventa una talla que el pattern no tiene',
    in_array((int) $talla_a->id(), $patron->sizeIds(), TRUE)
    && !in_array(999999, $patron->sizeIds(), TRUE)
  );

  $coste = MaterialCost::ofSize($size);
  $esperado = (1 / 15) * 10 + 5 * 20 + 2 * 1;
  $mirar('el pie suma foam + Leather Black + sticker',
    $coste && abs($coste['total'] - $esperado) < 0.05,
    $coste ? (string) $coste['total'] : 'sin coste'
  );

  $lineas = PatternRecipe::linesOfSize($size);
  $skus = [];
  foreach ($lineas as $row) {
    if ($row['material']) {
      $skus[] = (int) $row['material']->id();
    }
  }
  $mirar('la receta lleva SKUs, no el tipo Leather',
    in_array((int) $foam->id(), $skus, TRUE)
    && in_array((int) $leather_black->id(), $skus, TRUE)
    && !in_array((int) $tipo->id(), $skus, TRUE)
  );

  $patron->setLines([
    [
      'label' => 'Foam',
      'cells' => [
        (int) $talla_a->id() => [
          'kind' => 'material',
          'target_id' => (int) $foam->id(),
          'qty' => '1/10',
        ],
        (int) $talla_b->id() => [
          'kind' => 'material',
          'target_id' => (int) $foam->id(),
          'qty' => '1/12',
        ],
      ],
    ],
    [
      'label' => 'Leather',
      'cells' => [
        (int) $talla_a->id() => [
          'kind' => 'type',
          'target_id' => (int) $tipo->id(),
          'qty' => '5',
        ],
        (int) $talla_b->id() => [
          'kind' => 'type',
          'target_id' => (int) $tipo->id(),
          'qty' => '6',
        ],
      ],
    ],
  ]);
  $patron->save();
  \Drupal::entityTypeManager()->getStorage('tec_pattern')->resetCache([$patron->id()]);
  \Drupal::entityTypeManager()->getStorage('tec_product')->resetCache([$prod->id(), $size->id(), $col->id()]);
  $reloaded = Pattern::loadByCode($code);
  $foam_qty = $reloaded ? ($reloaded->lines()[0]['cells'][(int) $talla_a->id()]['qty'] ?? '') : '';
  $mirar('el pattern guardo 1/10 de foam', $foam_qty === '1/10', $foam_qty);
  $coste2 = MaterialCost::ofSize($etm->getStorage('tec_product')->loadUnchanged($size->id()));
  $esperado2 = (1 / 10) * 10 + 5 * 20 + 2 * 1;
  $mirar('cambiar el foam en el pattern mueve el pie del producto',
    $coste2 && abs($coste2['total'] - $esperado2) < 0.05,
    $coste2 ? (string) $coste2['total'] : 'sin coste'
  );

  $fotosDe = static function ($linea): array {
    $out = [];
    if (!$linea || !$linea->hasField('field_tec_line_item_bom')) {
      return $out;
    }
    foreach ($linea->get('field_tec_line_item_bom')->referencedEntities() as $foto) {
      $tid = (int) $foto->get('field_tec_inventory')->target_id;
      $out[$tid] = [
        'per' => $foto->hasField(OrderBom::PER_FIELD) && !$foto->get(OrderBom::PER_FIELD)->isEmpty()
          ? (float) $foto->get(OrderBom::PER_FIELD)->value
          : 0.0,
        'qty' => $foto->hasField(OrderBom::QTY_FIELD) && !$foto->get(OrderBom::QTY_FIELD)->isEmpty()
          ? (float) $foto->get(OrderBom::QTY_FIELD)->value
          : 0.0,
        'input' => $foto->hasField(OrderBom::INPUT_FIELD) && !$foto->get(OrderBom::INPUT_FIELD)->isEmpty()
          ? (string) $foto->get(OrderBom::INPUT_FIELD)->value
          : '',
        'cost' => $foto->hasField(OrderBom::COST_FIELD) && !$foto->get(OrderBom::COST_FIELD)->isEmpty()
          ? (float) $foto->get(OrderBom::COST_FIELD)->value
          : 0.0,
      ];
    }
    return $out;
  };

  $explotados = PatternRecipe::explodeLines($size);
  $ids_explotados = array_map(static fn($row) => (int) $row['material']->id(), $explotados);
  $mirar('la receta que explota lleva foam, leather y sticker',
    in_array((int) $foam->id(), $ids_explotados, TRUE)
    && in_array((int) $leather_black->id(), $ids_explotados, TRUE)
    && in_array((int) $sticker->id(), $ids_explotados, TRUE)
    && count($explotados) === 3
  );

  $linea = $etm->getStorage('tec_line_item')->create([
    'type' => 'tec_sales_order_line_item',
    'title' => $marca . ' linea',
    'field_tec_product' => $prod->id(),
    'field_tec_color_variation' => $col->id(),
    'field_tec_price' => '12.50',
    'field_tec_custom_sales_price' => TRUE,
    'field_tec_quantity' => 10,
    'field_tec_size_variation' => ['target_id' => $size->id()],
  ]);
  $linea->save();
  $basura[] = $linea;
  $linea = $etm->getStorage('tec_line_item')->loadUnchanged($linea->id());
  $fotos = $linea->get('field_tec_line_item_bom')->referencedEntities();
  $ids_foto = [];
  foreach ($fotos as $foto) {
    $ids_foto[] = (int) $foto->get('field_tec_inventory')->target_id;
  }
  $mirar('el pedido explota Leather Black, no el tipo',
    in_array((int) $leather_black->id(), $ids_foto, TRUE)
    && !in_array((int) $tipo->id(), $ids_foto, TRUE)
    && count($fotos) === 3,
    implode(',', $ids_foto)
  );
  $mapa = $fotosDe($linea);
  $mirar('la foto guarda 1/10 de foam por pieza',
    isset($mapa[(int) $foam->id()])
    && abs($mapa[(int) $foam->id()]['per'] - 0.1) < 0.0001
    && $mapa[(int) $foam->id()]['input'] === '1/10'
  );
  $mirar('y diez piezas piden 50 de Leather Black y 20 de sticker',
    isset($mapa[(int) $leather_black->id()], $mapa[(int) $sticker->id()])
    && abs($mapa[(int) $leather_black->id()]['qty'] - 50) < 0.05
    && abs($mapa[(int) $sticker->id()]['qty'] - 20) < 0.05
    && abs($mapa[(int) $sticker->id()]['per'] - 2) < 0.0001
  );

  $coste_otra = $otra ? MaterialCost::ofSize($otra) : NULL;
  $esperado_otra = (1 / 12) * 10 + 6 * 20;
  $mirar('la talla sin extra no suma el sticker',
    $otra
    && $coste_otra
    && abs($coste_otra['total'] - $esperado_otra) < 0.05
    && !in_array((int) $sticker->id(), array_map(
      static fn($row) => (int) $row['material']->id(),
      PatternRecipe::explodeLines($otra)
    ), TRUE),
    $coste_otra ? (string) $coste_otra['total'] : 'sin talla'
  );

  $linea_b = NULL;
  if ($otra) {
    $linea_b = $etm->getStorage('tec_line_item')->create([
      'type' => 'tec_sales_order_line_item',
      'title' => $marca . ' linea b',
      'field_tec_product' => $prod->id(),
      'field_tec_color_variation' => $col->id(),
      'field_tec_price' => '12.50',
      'field_tec_custom_sales_price' => TRUE,
      'field_tec_quantity' => 5,
      'field_tec_size_variation' => ['target_id' => $otra->id()],
    ]);
    $linea_b->save();
    $basura[] = $linea_b;
    $linea_b = $etm->getStorage('tec_line_item')->loadUnchanged($linea_b->id());
    $mapa_b = $fotosDe($linea_b);
    $mirar('la linea de la otra talla no lleva sticker',
      count($mapa_b) === 2
      && isset($mapa_b[(int) $foam->id()], $mapa_b[(int) $leather_black->id()])
      && !isset($mapa_b[(int) $sticker->id()])
    );
  }

  $linea->set('field_tec_quantity', 20);
  $linea->save();
  $linea = $etm->getStorage('tec_line_item')->loadUnchanged($linea->id());
  $mapa = $fotosDe($linea);
  $mirar('al cambiar la cantidad se reescala el sticker, no se releen extras',
    isset($mapa[(int) $sticker->id()])
    && abs($mapa[(int) $sticker->id()]['per'] - 2) < 0.0001
    && abs($mapa[(int) $sticker->id()]['qty'] - 40) < 0.05
  );

  $pedido = $guardar($etm->getStorage('tec_order')->create([
    'type' => 'tec_sales_order',
    'title' => $marca . ' pedido',
    'field_tec_line_items' => array_values(array_filter([
      $linea->id(),
      $linea_b ? $linea_b->id() : NULL,
    ])),
    'field_tec_order_status' => 'draft',
    'status' => 1,
  ]));
  $linea->set('field_tec_order', $pedido->id());
  $linea->save();
  if ($linea_b) {
    $linea_b->set('field_tec_order', $pedido->id());
    $linea_b->save();
  }

  PatternRecipe::applyExtraLines($col, [
    [
      'cells' => [
        $term_a => ['target_id' => (int) $sticker->id(), 'qty' => '9'],
        $term_b => ['target_id' => 0, 'qty' => ''],
      ],
    ],
  ]);
  $linea = $etm->getStorage('tec_line_item')->loadUnchanged($linea->id());
  $mapa = $fotosDe($linea);
  $mirar('cambiar el extra del producto no mueve la foto del pedido',
    isset($mapa[(int) $sticker->id()])
    && abs($mapa[(int) $sticker->id()]['per'] - 2) < 0.0001
  );

  $linea_nueva = $etm->getStorage('tec_line_item')->create([
    'type' => 'tec_sales_order_line_item',
    'title' => $marca . ' linea nueva',
    'field_tec_product' => $prod->id(),
    'field_tec_color_variation' => $col->id(),
    'field_tec_price' => '12.50',
    'field_tec_custom_sales_price' => TRUE,
    'field_tec_quantity' => 1,
    'field_tec_size_variation' => ['target_id' => $size->id()],
  ]);
  $linea_nueva->save();
  $basura[] = $linea_nueva;
  $mapa_nueva = $fotosDe($etm->getStorage('tec_line_item')->loadUnchanged($linea_nueva->id()));
  $mirar('una linea nueva si nace con el extra de hoy',
    isset($mapa_nueva[(int) $sticker->id()])
    && abs($mapa_nueva[(int) $sticker->id()]['per'] - 9) < 0.0001
  );

  $html_calculo = '';
  $filas_calculo = 0;
  $cuenta_vista = \Drupal::currentUser()->getAccount();
  \Drupal::currentUser()->setAccount($etm->getStorage('user')->load(1));
  try {
    foreach (['tec_order_material_calculation_summary', 'tec_order_material_calculation_terms'] as $vista_id) {
      $vista = Views::getView($vista_id);
      if (!$vista) {
        continue;
      }
      $vista->setDisplay('default');
      $cuantos = count($vista->display_handler->getHandlers('argument'));
      if ($cuantos > 0) {
        $vista->setArguments(array_fill(0, $cuantos, (string) $pedido->id()));
      }
      $vista->preExecute();
      $vista->execute();
      $filas_calculo += count($vista->result);
      $salida = $vista->render();
      $html_calculo .= (string) \Drupal::service('renderer')->renderInIsolation($salida);
      $vista->destroy();
    }
  }
  catch (\Throwable $e) {
    $html_calculo = $e->getMessage();
  }
  \Drupal::currentUser()->setAccount($cuenta_vista);
  $mirar('material calculation ve foam, leather y sticker',
    $filas_calculo > 0
    && str_contains($html_calculo, (string) $foam->label())
    && str_contains($html_calculo, (string) $leather_black->label())
    && str_contains($html_calculo, (string) $sticker->label()),
    $filas_calculo . ' filas'
  );

  $color_term_2 = $guardar($etm->getStorage('taxonomy_term')->create([
    'vid' => 'tec_colors',
    'name' => $marca . ' white',
  ]));
  $col2 = $guardar($etm->getStorage('tec_product')->create([
    'type' => 'tec_color_variation',
    'title' => $marca . ' white',
    'field_tec_colors' => $color_term_2->id(),
    'field_tec_product' => $prod->id(),
  ]));
  PatternRecipe::setHoles($col2, [(int) $tipo->id() => (int) $leather_black->id()]);
  $col2->save();
  $prod->set('field_tec_color_variations', [$col->id(), $col2->id()]);
  $prod->save();
  $col2 = $etm->getStorage('tec_product')->loadUnchanged($col2->id());
  $size2 = NULL;
  foreach ($col2->get('field_tec_size_variations')->referencedEntities() as $candidate) {
    if (PatternRecipe::sizeTermId($candidate) === (int) $talla_a->id()) {
      $size2 = $candidate;
      break;
    }
  }
  $mirar('el segundo color nace con las tallas del pattern', (bool) $size2);
  if ($size2) {
    $linea_c = $etm->getStorage('tec_line_item')->create([
      'type' => 'tec_sales_order_line_item',
      'title' => $marca . ' linea white',
      'field_tec_product' => $prod->id(),
      'field_tec_color_variation' => $col2->id(),
      'field_tec_price' => '12.50',
      'field_tec_custom_sales_price' => TRUE,
      'field_tec_quantity' => 3,
      'field_tec_size_variation' => ['target_id' => $size2->id()],
    ]);
    $linea_c->save();
    $basura[] = $linea_c;
    $mapa_c = $fotosDe($etm->getStorage('tec_line_item')->loadUnchanged($linea_c->id()));
    $mirar('el extra de un color no sale en el pedido del otro',
      isset($mapa_c[(int) $foam->id()], $mapa_c[(int) $leather_black->id()])
      && !isset($mapa_c[(int) $sticker->id()])
    );
  }

  $bom_viejo = $guardar($etm->getStorage('tec_inventory')->create([
    'type' => 'tec_bom_item',
    'title' => $marca . ' bom legado',
    'field_tec_inventory' => ['target_id' => $foam->id()],
    'field_tec_quantity_input' => '3',
    'field_tec_quantity' => '3.0000',
  ]));
  $legado = $guardar($etm->getStorage('tec_product')->create([
    'type' => 'tec_size_variation',
    'title' => $marca . ' legado',
    'field_tec_bom' => [['target_id' => $bom_viejo->id()]],
  ]));
  $coste_legado = MaterialCost::ofSize($legado);
  $mirar('sin pattern sigue el BoM de la talla',
    $coste_legado && abs($coste_legado['total'] - 30.0) < 0.05,
    $coste_legado ? (string) $coste_legado['total'] : 'sin coste'
  );

  $oscar = (int) $etm->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'tec_patterns')
    ->count()
    ->execute();
  $mirar('no se ha tocado el vocabulario de Oscar', $oscar >= 0);

  $form_prod = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('tec_product.tec_product.default');
  $mirar('el formulario muestra el pattern, no el Pattern vacio',
    $form_prod
    && $form_prod->getComponent(PatternRecipe::PATTERN_FIELD)
    && !$form_prod->getComponent('field_tec_pattern')
  );
}
catch (\Throwable $e) {
  $mirar('sin excepcion', FALSE, $e->getMessage());
}

$barrer();
$sigue = Pattern::loadByCode($code);
if ($sigue) {
  try {
    $sigue->delete();
  }
  catch (\Throwable $e) {
  }
}

echo "\n";
if ($problemas) {
  printf("FALLA %d\n", $problemas);
  exit(1);
}
echo "OK\n";
