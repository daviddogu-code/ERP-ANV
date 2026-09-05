<?php

/**
 * @file
 * El pattern tiene codigo, no es un termino de taxonomia.
 *
 *   php vendor/bin/drush.php scr scripts/el-patron.php
 */

use Drupal\Core\File\FileExists;
use Drupal\tec_inventory\Entity\Pattern;
use Drupal\tec_inventory\MaterialCost;
use Drupal\tec_inventory\OrderBom;
use Drupal\tec_inventory\PatternRecipe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$etm = \Drupal::entityTypeManager();
$marca = 'PATRON TEST ' . date('His');
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

$contar = static function (string $tipo, array $conds = []) use ($etm): int {
  $q = $etm->getStorage($tipo)->getQuery()->accessCheck(FALSE);
  foreach ($conds as $campo => $valor) {
    $q->condition($campo, $valor);
  }
  return (int) $q->count()->execute();
};

$antes = [
  'productos' => $contar('tec_product'),
  'patrones_oscar' => $contar('taxonomy_term', ['vid' => 'tec_patterns']),
  'tallas' => $contar('taxonomy_term', ['vid' => 'tec_sizes']),
  'tipos' => $contar('taxonomy_term', ['vid' => 'tec_materials']),
];

$barrer = static function () use (&$basura, $etm): void {
  foreach (array_reverse($basura) as $entidad) {
    try {
      foreach ($etm->getStorage('flagging')->loadByProperties(['entity_id' => $entidad->id()]) as $bandera) {
        $bandera->delete();
      }
      $entidad->delete();
    }
    catch (\Throwable $e) {
    }
  }
  $basura = [];
};

echo "El patron\n";

try {
  $tallas = $etm->getStorage('taxonomy_term')->loadMultiple(
    $etm->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_sizes')
      ->sort('weight')
      ->sort('name')
      ->range(0, 2)
      ->execute()
  );
  $tipos = $etm->getStorage('taxonomy_term')->loadMultiple(
    $etm->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_materials')
      ->sort('weight')
      ->sort('name')
      ->range(0, 1)
      ->execute()
  );
  $talla_a = array_shift($tallas);
  $talla_b = array_shift($tallas);
  $tipo = array_shift($tipos);
  $mirar('hay al menos dos tallas y un tipo de material', $talla_a && $talla_b && $tipo);

  $tipos_producto = $etm->getStorage('taxonomy_term')->loadMultiple(
    $etm->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_product_types')
      ->sort('name')
      ->range(0, 1)
      ->execute()
  );
  $tipo_producto = array_shift($tipos_producto);
  $mirar('hay un product type en el catalogo', (bool) $tipo_producto);

  $cuero = $guardar($etm->getStorage('taxonomy_term')->create([
    'vid' => 'tec_inventory',
    'name' => $marca . ' PA-55 H 15 mm',
    'field_tec_stock_level' => 0,
    'field_tec_cost' => '10.00',
    'field_tec_units' => 1,
    'field_tec_split_into' => 1,
  ]));
  $cuero_gordo = $guardar($etm->getStorage('taxonomy_term')->create([
    'vid' => 'tec_inventory',
    'name' => $marca . ' PA-55 H 20 mm',
    'field_tec_stock_level' => 0,
    'field_tec_cost' => '12.00',
    'field_tec_units' => 1,
    'field_tec_split_into' => 1,
  ]));

  $existia = Pattern::loadByCode('055');
  $code = $existia ? ('T055' . date('His')) : '055';

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
          'target_id' => (int) $cuero->id(),
          'qty' => '1/15',
        ],
        (int) $talla_b->id() => [
          'kind' => 'material',
          'target_id' => (int) $cuero_gordo->id(),
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
          'qty' => '5.665',
        ],
        (int) $talla_b->id() => [
          'kind' => 'type',
          'target_id' => (int) $tipo->id(),
          'qty' => '5.91',
        ],
      ],
    ],
    [
      'label' => '',
      'cells' => [
        (int) $talla_b->id() => [
          'kind' => 'material',
          'target_id' => (int) $cuero->id(),
          'qty' => '1/8',
        ],
      ],
    ],
  ]);
  $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
  $foto = \Drupal::service('file.repository')->writeData(
    $png,
    'public://pattern-test-' . $code . '.png',
    FileExists::Replace
  );
  $foto->setPermanent();
  $foto->save();
  $basura[] = $foto;
  $patron->set('image', ['target_id' => $foto->id()]);
  $guardar($patron);

  $sin_foto = Pattern::create([
    'code' => $code . 'x',
    'name' => 'No photo',
    'product_type' => $tipo_producto ? (int) $tipo_producto->id() : NULL,
    'product_material' => 'leather',
  ]);
  $guardar($sin_foto);

  $mirar('se guarda por codigo, no por vocabulario', Pattern::loadByCode($code)?->id() === $patron->id());
  $mirar('el nombre corto es codigo mas nombre', (string) $patron->label() === $code . ' Gloves', (string) $patron->label());
  $mirar('lleva el product type del catalogo',
    $tipo_producto && (int) $patron->get('product_type')->target_id === (int) $tipo_producto->id()
  );
  $peso_a = (int) $patron->get('weight')->value;
  $peso_b = (int) $sin_foto->get('weight')->value;
  $mirar('el nuevo patron va al final', $peso_a >= 1 && $peso_b > $peso_a);

  $producto = $guardar($etm->getStorage('tec_product')->create([
    'type' => 'tec_product',
    'title' => $marca . ' producto',
    'field_product_name' => $marca . ' producto',
    PatternRecipe::PATTERN_FIELD => $patron->id(),
  ]));
  $producto = $etm->getStorage('tec_product')->loadUnchanged($producto->id());
  $titulo_producto = PatternRecipe::productTitle($producto);
  $mirar('el pattern cuenta el producto que cuelga de el',
    PatternRecipe::productCounts([(int) $patron->id(), (int) $sin_foto->id()]) === [
      (int) $patron->id() => 1,
      (int) $sin_foto->id() => 0,
    ]
  );
  $ids_del_pattern = array_map(
    static fn($item): int => (int) $item->id(),
    PatternRecipe::productsOf($patron)
  );
  $mirar('productsOf encuentra ese producto', $ids_del_pattern === [(int) $producto->id()]);

  $sin_foto->set('weight', $peso_a);
  $patron->set('weight', $peso_b);
  $sin_foto->save();
  $patron->save();

  $url = $patron->toUrl('canonical')->toString();
  $mirar('la URL es /pattern/' . $code, str_contains($url, '/pattern/' . $code) && !str_contains($url, '/taxonomy/term'), $url);

  $lineas = $patron->lines();
  $celda_a = $lineas[0]['cells'][(int) $talla_a->id()] ?? [];
  $celda_b = $lineas[0]['cells'][(int) $talla_b->id()] ?? [];
  $mirar('una linea puede llevar un SKU distinto en cada talla',
    ($celda_a['kind'] ?? '') === 'material'
    && (int) ($celda_a['target_id'] ?? 0) === (int) $cuero->id()
    && (int) ($celda_b['target_id'] ?? 0) === (int) $cuero_gordo->id()
    && ($celda_a['qty'] ?? '') === '1/15'
    && ($celda_b['qty'] ?? '') === '1/12'
  );
  $mirar('una celda amarilla apunta al tipo',
    ($lineas[1]['cells'][(int) $talla_a->id()]['kind'] ?? '') === 'type'
    && (int) ($lineas[1]['cells'][(int) $talla_a->id()]['target_id'] ?? 0) === (int) $tipo->id()
  );

  $viejo = Pattern::create(['code' => 'TOLD' . date('His'), 'name' => 'Legacy']);
  $viejo->set('sizes', [['target_id' => $talla_a->id()]]);
  $viejo->set('bom', json_encode([[
    'kind' => 'material',
    'target_id' => (int) $cuero->id(),
    'qty' => [(int) $talla_a->id() => '5.665'],
  ]]));
  $guardar($viejo);
  $migrada = $viejo->lines()[0]['cells'][(int) $talla_a->id()] ?? [];
  $mirar('una receta vieja (un SKU por fila) sigue leyendose',
    (int) ($migrada['target_id'] ?? 0) === (int) $cuero->id()
    && ($migrada['qty'] ?? '') === '5.665'
  );

  $clon = Pattern::create(['code' => $code, 'name' => 'Otro']);
  $choque = $clon->validate();
  $mirar('el codigo no se puede repetir', $choque->count() > 0, (string) $choque->count() . ' violaciones');

  $mirar('new, edit, delete, add y organize no sirven de codigo',
    Pattern::RESERVED === ['new', 'edit', 'delete', 'add', 'organize']
    && Pattern::loadByCode('new') === NULL
    && Pattern::loadByCode('organize') === NULL
  );

  $mirar('los pedidos siguen leyendo el BoM de la talla',
    OrderBom::SIZE_BOM_FIELD === 'field_tec_bom' && MaterialCost::BOM_FIELD === 'field_tec_bom'
  );

  $kernel = \Drupal::service('http_kernel');
  $cuenta = $etm->getStorage('user')->load(1);
  $anterior = \Drupal::currentUser()->getAccount();
  \Drupal::currentUser()->setAccount($cuenta);
  $pedir = static function (string $ruta) use ($kernel): array {
    $peticion = Request::create($ruta, 'GET');
    $peticion->setSession(\Drupal::service('session'));
    try {
      $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
      return [$respuesta->getStatusCode(), (string) $respuesta->getContent()];
    }
    catch (\Throwable $e) {
      return ['EXCEPCION: ' . $e->getMessage(), ''];
    }
  };

  [$codigo_lista, $html_lista] = $pedir('/pattern');
  $mirar('/pattern responde', $codigo_lista === 200, (string) $codigo_lista);
  $mirar('la lista dice pattern, no mould',
    str_contains($html_lista, '055 is the same cut')
    && !preg_match('/mould/i', $html_lista)
  );
  $mirar('la lista enseña el codigo', str_contains($html_lista, $code));
  $pos_image = strpos($html_lista, '>Image</th>');
  $pos_code = strpos($html_lista, '>Code</th>');
  $mirar('Image va a la izquierda de Code', $pos_image !== FALSE && $pos_code !== FALSE && $pos_image < $pos_code);
  $mirar('la lista enseña la miniatura 40x40 con popup',
    str_contains($html_lista, 'small_40x40')
    && str_contains($html_lista, 'spv-popup-wrapper')
    && str_contains($html_lista, 'pattern-test-' . $code)
  );
  $mirar('una fila sin foto reserva el mismo hueco 40x40',
    str_contains($html_lista, 'tec-pattern-list__thumb--empty')
  );
  $pos_name = strpos($html_lista, '>Name</th>');
  $pos_ptype = strpos($html_lista, '>Product type</th>');
  $pos_pmaterial = strpos($html_lista, '>Product material</th>');
  $mirar('Product type va entre Name y Product material',
    $pos_name !== FALSE && $pos_ptype !== FALSE && $pos_pmaterial !== FALSE
    && $pos_name < $pos_ptype && $pos_ptype < $pos_pmaterial
  );
  $pos_rows = strpos($html_lista, '>Rows</th>');
  $pos_products = strpos($html_lista, '>Products</th>');
  $pos_ops = strpos($html_lista, '>Operations</th>');
  $mirar('Products va entre Rows y Operations',
    $pos_rows !== FALSE && $pos_products !== FALSE && $pos_ops !== FALSE
    && $pos_rows < $pos_products && $pos_products < $pos_ops
  );
  $mirar('la lista cuenta el producto del pattern',
    str_contains($html_lista, '1 product')
    && str_contains($html_lista, '/pattern/' . $code . '#tec-pattern-products')
  );
  if ($tipo_producto) {
    $mirar('la lista enseña el product type', str_contains($html_lista, $tipo_producto->label()));
  }
  $mirar('sale el boton de crear', str_contains($html_lista, '+ Pattern'));
  $mirar('la lista ofrece Organize Patterns, no el arrastre',
    str_contains($html_lista, 'Organize Patterns')
    && !str_contains($html_lista, 'Save order')
    && !str_contains($html_lista, 'pattern-order-weight')
  );
  $pos_x = strpos($html_lista, '/pattern/' . $code . 'x');
  $pos_0 = strpos($html_lista, '/pattern/' . $code . '"');
  $mirar('la lista sigue el weight, no el codigo', $pos_x !== FALSE && $pos_0 !== FALSE && $pos_x < $pos_0);

  [$codigo_org, $html_org] = $pedir('/pattern/organize');
  $mirar('/pattern/organize responde', $codigo_org === 200, (string) $codigo_org);
  $mirar('en Organize se arrastra y se guarda',
    str_contains($html_org, 'draggable')
    && str_contains($html_org, 'Save order')
    && str_contains($html_org, 'pattern-order-weight')
    && !str_contains($html_org, '>Products</th>')
  );

  [$codigo_ficha, $html_ficha] = $pedir('/pattern/' . $code);
  $mirar('/pattern/' . $code . ' responde', $codigo_ficha === 200, (string) $codigo_ficha);
  $mirar('la ficha del pattern no dice mould', !preg_match('/mould/i', $html_ficha));
  $mirar('la ficha enseña el nombre del patron', str_contains($html_ficha, 'tec-pattern-heading__name') && str_contains($html_ficha, 'Gloves'));
  $mirar('la ficha lista el producto que salio del pattern',
    str_contains($html_ficha, 'tec-pattern-products')
    && str_contains($html_ficha, $titulo_producto)
    && str_contains($html_ficha, '/tec_product/' . $producto->id())
  );
  [$codigo_vacio, $html_vacio] = $pedir('/pattern/' . $code . 'x');
  $mirar('un pattern sin productos lo dice',
    $codigo_vacio === 200
    && str_contains($html_vacio, 'No products yet.')
  );
  $mirar('la ficha enseña el Product type', str_contains($html_ficha, 'tec-pattern-heading__type') && $tipo_producto && str_contains($html_ficha, $tipo_producto->label()));
  $mirar('la ficha enseña el Product material a la derecha', str_contains($html_ficha, 'tec-pattern-heading__material') && str_contains($html_ficha, 'Leather'));
  $mirar('la ficha enseña el SKU verde', str_contains($html_ficha, $cuero->label()));
  $mirar('la ficha enseña el otro SKU de la misma linea', str_contains($html_ficha, $cuero_gordo->label()));
  $mirar('la ficha enseña el tipo amarillo', str_contains($html_ficha, $tipo->label()));
  $mirar('la ficha enseña 1/15', str_contains($html_ficha, '1/15'));
  $mirar('la ficha es tarjetas por talla, no una tabla de columnas', str_contains($html_ficha, 'tec-size-card') && str_contains($html_ficha, 'Bill of Materials'));
  $mirar('la ficha usa la tabla pequeña del producto', str_contains($html_ficha, 'table-sm'));
  $mirar('una celda vacia se ve, a la misma altura', str_contains($html_ficha, 'tec-size-card__bom-row--empty'));
  $mirar('no hay boton + Product todavia', !str_contains($html_ficha, '+ Product'));

  [$codigo_nuevo, $html_nuevo] = $pedir('/pattern/new');
  $mirar('/pattern/new responde', $codigo_nuevo === 200, (string) $codigo_nuevo);
  $mirar('el alta pide Code, Name, Product type, Product material e Image',
    str_contains($html_nuevo, 'Code')
    && str_contains($html_nuevo, 'Name')
    && str_contains($html_nuevo, 'Product type')
    && str_contains($html_nuevo, 'Product material')
    && str_contains($html_nuevo, 'Image')
    && str_contains($html_nuevo, 'Material')
    && str_contains($html_nuevo, 'Type')
  );
  $mirar('el alta enlaza a Manage product types', str_contains($html_nuevo, 'Manage product types'));
  $mirar('Save no se queda girando si falta el codigo',
    str_contains($html_nuevo, 'id="tec-pattern-shell"')
    && str_contains($html_nuevo, 'novalidate')
    && str_contains($html_nuevo, 'save_pattern')
    && str_contains($html_nuevo, 'tec-pattern-save')
  );

  $estado_vacio = new \Drupal\Core\Form\FormState();
  $estado_vacio->setValues([
    'code' => '',
    'name' => '',
    'sizes' => [],
    'save_pattern' => 'Save',
  ]);
  $estado_vacio->setProgrammed();
  $estado_vacio->setSubmitted();
  \Drupal::formBuilder()->submitForm(\Drupal\tec_inventory\Form\PatternForm::class, $estado_vacio, NULL);
  $mirar('guardar sin codigo no crea el patron', $estado_vacio->hasAnyErrors() && Pattern::loadByCode('') === NULL);

  [$codigo_edit, $html_edit] = $pedir('/pattern/' . $code . '/edit');
  $mirar('/pattern/' . $code . '/edit responde', $codigo_edit === 200, (string) $codigo_edit);
  $mirar('Requires no trae 1/12 de ejemplo', !str_contains($html_edit, 'placeholder="1/12"'));
  $mirar('no hay columna Line', !str_contains($html_edit, '>Line</th>'));
  $mirar('el edit tiene Add row', str_contains($html_edit, 'Add row'));

  [$codigo_start, $html_start] = $pedir('/start');
  $mirar('/start enlaza a /pattern', $codigo_start === 200 && (str_contains($html_start, 'href="/pattern"') || str_contains($html_start, 'href="/pt"')));
  $mirar('/start muestra Patterns', str_contains($html_start, 'Patterns'));

  \Drupal::currentUser()->setAccount($anterior);

  $mirar('no se ha creado ningun termino en tec_patterns',
    $contar('taxonomy_term', ['vid' => 'tec_patterns']) === $antes['patrones_oscar']
  );
  $mirar('no se han creado tallas ni tipos',
    $contar('taxonomy_term', ['vid' => 'tec_sizes']) === $antes['tallas']
    && $contar('taxonomy_term', ['vid' => 'tec_materials']) === $antes['tipos']
  );
}
catch (\Throwable $e) {
  $mirar('la prueba no ha reventado', FALSE, $e->getMessage());
}

$barrer();

$mirar('se ha borrado lo que se ha creado',
  Pattern::loadByCode($code ?? '') === NULL
  && $contar('taxonomy_term', ['vid' => 'tec_inventory', 'name' => $marca . ' PA-55 H 15 mm']) === 0
  && $contar('taxonomy_term', ['vid' => 'tec_inventory', 'name' => $marca . ' PA-55 H 20 mm']) === 0
  && $contar('tec_product') === $antes['productos']
);

echo $problemas ? "\n$problemas fallos.\n" : "\nTodo bien.\n";
if ($problemas) {
  exit(1);
}
