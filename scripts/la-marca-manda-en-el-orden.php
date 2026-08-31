<?php

/**
 * @file
 * La marca manda en el orden de sus productos, y la proforma nace ordenada.
 *
 *   php vendor\bin\drush.php scr scripts/la-marca-manda-en-el-orden.php
 *
 * QUE SE PRUEBA.
 *
 * Una proforma sale ordenada en cuatro niveles, y cada nivel lo decide una cosa
 * distinta:
 *
 *   marca     el orden de la lista de marcas de la ficha del cliente
 *   producto  `field_tec_catalogue_position`, arrastrado en la pantalla de la marca
 *   color     el orden de la lista de colores de la ficha del producto
 *   talla     el peso del termino en el vocabulario tec_sizes
 *
 * Hasta el 19 de agosto de 2026 el orden de los productos era del **cliente**:
 * se arrastraba en `/tec_crm/N/reorder` y se guardaba en la tabla de
 * `draggableviews`, una fila por cliente y producto. Ocho clientes eran ocho
 * ordenes del mismo catalogo, y ninguno le servia a la proforma: la vista que
 * crea las lineas corre para un cliente y no puede leer el orden de otro. Ahora
 * el numero vive en el producto, la marca lo arrastra una vez, y lo leen todas
 * las pantallas.
 *
 * POR QUE LA PRUEBA MONTA NOMBRES TAN RAROS.
 *
 * Un orden que resulta ser el alfabetico no prueba nada, porque el alfabetico es
 * justo lo que hacia el ERP antes de esto. Asi que en cada nivel el juego esta
 * montado para que el orden bueno y el alfabetico **no coincidan**:
 *
 *   marca     la ficha pone la marca B primera y la A segunda
 *   producto  se arrastra a casco, guante 2, guante 10 -- el alfabetico pone
 *             guante 10 antes de guante 2, y eso es tambien lo que separa un
 *             `sort` normal de `strnatcasecmp()`
 *   color     el producto lleva Gold antes de Black
 *   talla     6 oz pesa 7 y 10 oz pesa 9, o sea que 6 oz va primero; el
 *             alfabetico las pone al reves
 *
 * Y LA PARTE QUE NO SE VE: EL ORDEN SE CONGELA.
 *
 * La proforma se crea recorriendo las filas de la vista, una linea por fila, y
 * desde ese momento el orden ya no depende de la marca: cada pantalla que lista
 * las lineas de un pedido ordena por el numero de la linea, que es el orden en
 * que se crearon. Volver a arrastrar la marca el mes que viene cambia la
 * proforma siguiente, no las ya emitidas -- la misma regla que siguen los
 * precios. Eso se prueba aqui arrastrando la marca **despues** de emitir el
 * pedido y mirando que el pedido no se mueve.
 *
 * Monta su propio juego -- dos marcas, un cliente, cuatro productos, sus colores
 * y sus tallas -- y lo borra al final. Pulsar la bandera no gasta ningun numero
 * de pedido: los numeros los da el primer Save de una persona.
 */

use Drupal\Core\Form\FormState;
use Drupal\tec_production\CatalogueOrder;
use Drupal\tec_production\CataloguePosition;
use Drupal\tec_production\Form\BrandProductOrderForm;
use Drupal\views\Views;

const RASTRO = 'PRUEBA orden de marca';

// La vista que ECA consulta para crear las lineas, y la que imprime la proforma.
const VISTA_BORRADOR = 'tec_order_eca_create_draft_sales';
const VISTA_LINEAS = 'tec_order_sales_order_line_items';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');
$contactos = $gestor->getStorage('tec_crm');
$pedidos = $gestor->getStorage('tec_order');

$problemas = 0;
$creadas = [];

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

/**
 * Un titulo de seccion.
 */
$seccion = function (string $que): void {
  print "\n" . $que . "\n" . str_repeat('-', 70) . "\n";
};

/**
 * Barre lo que dejo una carrera anterior que se murio a medias.
 *
 * El rastro es largo a proposito: hubo un dia en que barrer por «PRUEBA» se
 * llevo por delante el juego de pruebas permanente del ERP, del que depende la
 * prueba de humo.
 */
$barrer = function () use ($gestor, $terminos, $productos, $contactos, $pedidos): int {
  $barridas = 0;

  $losMios = function ($almacen, string $campo) {
    return $almacen->loadMultiple($almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition($campo, RASTRO, 'STARTS_WITH')
      ->execute());
  };

  // Los pedidos se buscan por su cliente y no por su nombre: al guardarlos la
  // numeracion les cambia el titulo y por nombre no apareceria ninguno.
  $mios = $losMios($contactos, 'title');
  if ($mios !== []) {
    $suyos = $pedidos->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_sales_order')
      ->condition('field_tec_customer', array_keys($mios), 'IN')
      ->execute();
    foreach ($pedidos->loadMultiple($suyos) as $pedido) {
      foreach ($pedido->get('field_tec_line_items')->referencedEntities() as $linea) {
        $linea->delete();
        $barridas++;
      }
      $pedido->delete();
      $barridas++;
    }
  }

  foreach ([$losMios($productos, 'title'), $mios, $losMios($terminos, 'name')] as $tanda) {
    foreach ($tanda as $ficha) {
      $ficha->delete();
      $barridas++;
    }
  }

  return $barridas;
};

/**
 * Una fila escrita para que se lea de un golpe: producto, color y talla.
 *
 * Lo que se compara son estas cadenas y no listas de numeros, porque cuando algo
 * falla lo que hace falta ver es «guante 2 Gold 10 oz antes que guante 2 Gold
 * 6 oz», no «4711 antes que 4708».
 */
$fila = fn(string $producto, string $color, string $talla): string
  => sprintf('%-16s %-8s %-6s', $producto, $color, $talla);

/**
 * Como se lee una fila de estas vistas: producto, color y talla.
 */
$describir = function ($talla) use ($fila): string {
  if (!$talla) {
    return $fila('?', '?', '?');
  }
  $color = $talla->hasField('field_tec_color_variation') ? $talla->get('field_tec_color_variation')->entity : NULL;
  $producto = $talla->hasField('field_tec_product') && !$talla->get('field_tec_product')->isEmpty()
    ? $talla->get('field_tec_product')->entity
    : ($color && $color->hasField('field_tec_product') ? $color->get('field_tec_product')->entity : NULL);

  $nombre = function ($ficha, string $campo): string {
    if (!$ficha || !$ficha->hasField($campo) || $ficha->get($campo)->isEmpty()) {
      return '?';
    }
    $termino = $ficha->get($campo)->entity;
    return $termino ? (string) $termino->label() : '?';
  };

  // El nombre se recorta y se pasa a minusculas porque el ERP guarda los
  // productos con las iniciales en mayuscula -- «PRUEBA Orden De Marca Guante 2»
  // -- y de lo que habla esta prueba es del orden, no de como se escriben.
  $corto = $producto
    ? mb_strtolower(trim(str_ireplace(RASTRO, '', (string) ($producto->get('field_product_name')->value ?? $producto->label()))))
    : '?';

  return $fila($corto, $nombre($color, 'field_tec_colors'), $nombre($talla, 'field_tec_size'));
};

/**
 * Que filas devuelve una pantalla, en su orden y ya legibles.
 */
$filasDe = function (string $vista, string $pantalla, array $args) use ($describir): array {
  $ejecutar = Views::getView($vista);
  if (!$ejecutar) {
    return [];
  }
  $ejecutar->setDisplay($pantalla);
  $ejecutar->setArguments($args);
  $ejecutar->preExecute();
  $ejecutar->execute();

  $filas = [];
  foreach ($ejecutar->result as $fila) {
    $filas[] = $describir($fila->_entity ?? NULL);
  }

  return $filas;
};

/**
 * Un termino de un vocabulario por su nombre exacto.
 */
$termino = function (string $vocabulario, string $nombre) use ($terminos) {
  $ids = $terminos->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->condition('name', $nombre)
    ->range(0, 1)
    ->execute();

  return $ids ? $terminos->load(reset($ids)) : NULL;
};

// Los colores y las tallas son del catalogo de verdad, porque el orden de las
// tallas es el peso de su termino y eso no se puede inventar aqui.
$gold = $termino('tec_colors', 'Gold');
$black = $termino('tec_colors', 'Black');
$seis = $termino('tec_sizes', '6 oz');
$diez = $termino('tec_sizes', '10 oz');
$m = $termino('tec_sizes', 'M');
if (!$gold || !$black || !$seis || !$diez || !$m) {
  print "\n  Faltan colores o tallas del catalogo (Gold, Black, 6 oz, 10 oz, M). No sigo.\n\n";
  return;
}
if ($seis->getWeight() >= $diez->getWeight()) {
  print "\n  6 oz ya no pesa menos que 10 oz: el vocabulario de tallas se ha desordenado. No sigo.\n\n";
  return;
}

print "\n" . str_repeat('=', 70) . "\n";
print "  La marca manda en el orden\n";
print str_repeat('=', 70) . "\n";

$seccion('Montando el juego');

$barridas = $barrer();
if ($barridas > 0) {
  printf("  antes barro %d fichas que dejo una carrera anterior\n", $barridas);
}

try {
  $marcaA = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' marca A']);
  $marcaA->save();
  $creadas[] = $marcaA;

  $marcaB = $terminos->create(['vid' => 'tec_brands', 'name' => RASTRO . ' marca B']);
  $marcaB->save();
  $creadas[] = $marcaB;

  // La ficha pone la B primera. Es el primer nivel del orden y el que menos se
  // ve: si esto se ignorara, el pedido saldria por numero de marca y nadie
  // notaria nada raro hasta comparar con la proforma hecha a mano.
  $cliente = $contactos->create([
    'type' => 'tec_contact_organization',
    'title' => RASTRO . ' cliente',
    'field_tec_customer_code' => 'ZZOM1',
    'field_tec_brands' => [
      ['target_id' => $marcaB->id()],
      ['target_id' => $marcaA->id()],
    ],
  ]);
  $cliente->save();
  $creadas[] = $cliente;

  /**
   * Un producto con sus colores y sus tallas, ya encadenado de arriba abajo.
   *
   * @param array $colores
   *   Termino de color => lista de terminos de talla, en el orden en que se
   *   quiere que queden guardados.
   */
  $fabricar = function (string $nombre, $marca, array $colores) use ($productos, &$creadas) {
    $producto = $productos->create([
      'type' => 'tec_product',
      'field_product_name' => RASTRO . ' ' . $nombre,
      'field_tec_brand' => [['target_id' => $marca->id()]],
    ]);
    $producto->save();
    $creadas[] = $producto;

    $suyos = [];
    foreach ($colores as [$color, $tallas]) {
      $variacion = $productos->create([
        'type' => 'tec_color_variation',
        'field_tec_colors' => [['target_id' => $color->id()]],
        'field_tec_product' => [['target_id' => $producto->id()]],
      ]);
      $variacion->save();
      $creadas[] = $variacion;

      $susTallas = [];
      foreach ($tallas as $talla) {
        $fila = $productos->create([
          'type' => 'tec_size_variation',
          'field_tec_size' => [['target_id' => $talla->id()]],
          'field_tec_color_variation' => [['target_id' => $variacion->id()]],
          'field_tec_product' => [['target_id' => $producto->id()]],
        ]);
        $fila->save();
        $creadas[] = $fila;
        $susTallas[] = ['target_id' => $fila->id()];
      }

      // El gancho de guardado pone los atajos de arriba abajo, pero las vistas
      // suben por las listas de abajo, asi que las listas tienen que estar.
      $variacion->set('field_tec_size_variations', $susTallas);
      $variacion->save();
      $suyos[] = ['target_id' => $variacion->id()];
    }

    $producto->set('field_tec_color_variations', $suyos);
    $producto->save();

    return $productos->loadUnchanged($producto->id());
  };

  // Se crean en este orden -- guante 10, guante 2, casco -- y el sitio que el
  // ERP les da al nacer tiene que ser ese mismo, 1, 2 y 3.
  $guante10 = $fabricar('guante 10', $marcaA, [[$black, [$m]]]);
  $guante2 = $fabricar('guante 2', $marcaA, [[$gold, [$diez, $seis]], [$black, [$diez, $seis]]]);
  $casco = $fabricar('casco', $marcaA, [[$black, [$m]]]);
  $productoB = $fabricar('producto de la B', $marcaB, [[$black, [$m]]]);

  printf("  marcas %s (A) y %s (B), cliente %s\n", $marcaA->id(), $marcaB->id(), $cliente->id());
  printf("  productos: guante 10 %s, guante 2 %s, casco %s, el de la B %s\n",
    $guante10->id(), $guante2->id(), $casco->id(), $productoB->id());

  // ---------------------------------------------------------------------------
  $seccion('1. El numero vive en el producto, y un producto nuevo va al final');

  $mirar('el producto tiene el campo del sitio',
    $guante10->hasField(CataloguePosition::FIELD));

  foreach ([
    'core.entity_form_display.tec_product.tec_product.default' => 'el formulario',
    'core.entity_view_display.tec_product.tec_product.default' => 'la ficha',
  ] as $nombre => $quien) {
    $display = \Drupal::config($nombre);
    $mirar('y ' . $quien . ' no lo ensena',
      array_key_exists(CataloguePosition::FIELD, $display->get('hidden') ?? [])
      && !array_key_exists(CataloguePosition::FIELD, $display->get('content') ?? []),
      'esta en ' . $nombre);
  }

  $sitios = [
    'guante 10' => CataloguePosition::of($guante10),
    'guante 2' => CataloguePosition::of($guante2),
    'casco' => CataloguePosition::of($casco),
  ];
  $mirar('los tres nacen con sitio, en el orden en que llegaron',
    array_values($sitios) === [1, 2, 3],
    implode(', ', array_map(fn($que, $sitio) => "$que=$sitio", array_keys($sitios), $sitios)));

  $mirar('y el siguiente que llegue sera el 4',
    CataloguePosition::nextFor((int) $marcaA->id()) === 4,
    'dice ' . CataloguePosition::nextFor((int) $marcaA->id()));

  $mirar('el producto de la otra marca empieza por el 1, que es su lista',
    CataloguePosition::of($productoB) === 1,
    'esta en el ' . CataloguePosition::of($productoB));

  // ---------------------------------------------------------------------------
  $seccion('2. La pantalla de arrastrar los productos de la marca');

  $cuenta = \Drupal::currentUser()->getAccount();
  $mirar('la pantalla es de la marca y de nadie mas',
    BrandProductOrderForm::access($cuenta, $marcaA)->isAllowed()
    && BrandProductOrderForm::access($cuenta, $black)->isForbidden(),
    'deja entrar donde no debe o no deja donde si');

  $mirar('y cuelga de la ficha de la marca',
    \Drupal::service('router.route_provider')
      ->getRoutesByPattern('/taxonomy/term/{taxonomy_term}/organize')->count() === 1);

  $formulario = \Drupal::formBuilder()->getForm(BrandProductOrderForm::class, $marcaA);
  $enPantalla = array_values(array_filter(array_keys($formulario['table']),
    fn($clave) => is_numeric($clave)));
  $mirar('la pantalla los saca en su sitio, no por nombre',
    $enPantalla === [(int) $guante10->id(), (int) $guante2->id(), (int) $casco->id()],
    'saca ' . implode(', ', $enPantalla));

  $numeros = array_map(
    fn($pid) => strip_tags((string) $formulario['table'][$pid]['no']['#markup']),
    $enPantalla);
  $mirar('y numera las filas de 1 en adelante',
    $numeros === ['1', '2', '3'],
    'numera ' . implode(', ', $numeros));

  /**
   * Arrastra: pulsa Save con los pesos que se le den, como haria una persona.
   */
  $arrastrar = function (array $pesos) use ($marcaA): void {
    $estado = new FormState();
    $estado->setValues(['table' => array_map(fn(int $peso) => ['weight' => $peso], $pesos)]);
    \Drupal::formBuilder()->submitForm(BrandProductOrderForm::class, $estado, $marcaA);
  };

  // Casco arriba, guante 2 se queda donde esta, guante 10 al final.
  $arrastrar([
    (int) $casco->id() => -1,
    (int) $guante2->id() => 2,
    (int) $guante10->id() => 3,
  ]);

  $releer = fn($ficha) => $productos->loadUnchanged($ficha->id());
  $casco = $releer($casco);
  $guante2 = $releer($guante2);
  $guante10 = $releer($guante10);

  $mirar('arrastrar guarda el orden nuevo',
    [CataloguePosition::of($casco), CataloguePosition::of($guante2), CataloguePosition::of($guante10)] === [1, 2, 3],
    sprintf('casco=%d, guante 2=%d, guante 10=%d',
      CataloguePosition::of($casco), CataloguePosition::of($guante2), CataloguePosition::of($guante10)));

  $mirar('y no toca al producto de la otra marca',
    CataloguePosition::of($releer($productoB)) === 1);

  // Guardar sin mover nada no escribe nada: lo dice la propia pantalla al
  // contar cuantos productos se movieron.
  $mirar('guardar el mismo orden no mueve ningun producto',
    CataloguePosition::write([
      (int) $casco->id() => 1,
      (int) $guante2->id() => 2,
      (int) $guante10->id() => 3,
    ]) === 0);

  // ---------------------------------------------------------------------------
  $seccion('3. El catalogo de la marca sigue ese orden, y por dentro color y talla');

  // El catalogo de la marca lleva tres de los cuatro niveles: el primero es del
  // cliente y aqui no hay cliente. Casco arriba, guante 2 con sus dos colores en
  // el orden de su ficha y sus dos tallas por peso, y guante 10 al final.
  $laMarcaA = [
    $fila('casco', 'Black', 'M'),
    $fila('guante 2', 'Gold', '6 oz'),
    $fila('guante 2', 'Gold', '10 oz'),
    $fila('guante 2', 'Black', '6 oz'),
    $fila('guante 2', 'Black', '10 oz'),
    $fila('guante 10', 'Black', 'M'),
  ];
  $laMarcaB = [$fila('producto de la b', 'Black', 'M')];

  $catalogo = $filasDe('tec_products', 'brand_catalogue', [(string) $marcaA->id()]);
  foreach ($catalogo as $numero => $linea) {
    printf("     %d  %s\n", $numero + 1, $linea);
  }
  $mirar('el catalogo sale en orden de producto, color y talla',
    $catalogo === $laMarcaA,
    count($catalogo) . ' filas y se esperaban ' . count($laMarcaA));

  // ---------------------------------------------------------------------------
  $seccion('4. La vista que crea la proforma sale ordenada, y la marca manda');

  $conLaBPrimero = array_merge($laMarcaB, $laMarcaA);

  $borrador = $filasDe(VISTA_BORRADOR, 'default', [(string) $cliente->id()]);
  foreach ($borrador as $numero => $linea) {
    printf("     %d  %s\n", $numero + 1, $linea);
  }

  $mirar('la marca B va primera porque la ficha la puso primera',
    $borrador === $conLaBPrimero,
    count($borrador) . ' filas; la primera dice «' . trim($borrador[0] ?? '') . '»');

  // Y al reves: lo que ordena no es el numero de la marca ni su nombre, es el
  // sitio que ocupa en la ficha del cliente.
  $cliente->set('field_tec_brands', [
    ['target_id' => $marcaA->id()],
    ['target_id' => $marcaB->id()],
  ]);
  $cliente->save();

  $alReves = $filasDe(VISTA_BORRADOR, 'default', [(string) $cliente->id()]);
  $mirar('y si la ficha cambia el orden de las marcas, la vista cambia con ella',
    $alReves === array_merge($laMarcaA, $laMarcaB),
    'la ultima dice «' . trim(end($alReves) ?: '') . '»');

  // Se deja como estaba para que el pedido de abajo salga con la B primera.
  $cliente->set('field_tec_brands', [
    ['target_id' => $marcaB->id()],
    ['target_id' => $marcaA->id()],
  ]);
  $cliente->save();

  // ---------------------------------------------------------------------------
  $seccion('5. De punta a punta: el boton + Order hace las lineas en ese orden');

  $bandera = $gestor->getStorage('flag')->load('tec_order_draft_sales');
  try {
    \Drupal::service('flag')->flag($bandera, $cliente);
  }
  catch (\Throwable $chispa) {
    // La ECA acaba mandando al navegador al pedido recien hecho, y en la
    // consola no hay navegador. El pedido ya esta guardado cuando eso salta.
  }

  $suyos = array_values($pedidos->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_sales_order')
    ->condition('field_tec_customer', $cliente->id())
    ->execute());
  $mirar('el boton hace un pedido', count($suyos) === 1, count($suyos) . ' pedidos');

  if (count($suyos) === 1) {
    $pedido = $pedidos->load(reset($suyos));
    $creadas[] = $pedido;

    $lineas = $pedido->get('field_tec_line_items')->referencedEntities();
    foreach ($lineas as $linea) {
      $creadas[] = $linea;
    }

    $enElPedido = [];
    $ids = [];
    foreach ($lineas as $linea) {
      $ids[] = (int) $linea->id();
      $enElPedido[] = $describir($linea->hasField('field_tec_size_variation')
        ? $linea->get('field_tec_size_variation')->entity
        : NULL);
    }
    foreach ($enElPedido as $numero => $texto) {
      printf("     %d  %s\n", $numero + 1, $texto);
    }

    $ordenados = $ids;
    sort($ordenados);
    $mirar('las lineas se crearon en el orden de la vista',
      $enElPedido === $conLaBPrimero,
      count($enElPedido) . ' lineas; la primera dice «' . trim($enElPedido[0] ?? '') . '»');
    $mirar('y sus numeros van hacia arriba, que es lo que las pantallas ordenan',
      $ids === $ordenados,
      implode(', ', $ids));

    // -------------------------------------------------------------------------
    $seccion('6. Una vez emitido, el pedido no se mueve');

    // Las pantallas esconden las lineas que valen cero, y un borrador nace a
    // cero. Se les pone cantidad, que es lo que hace una persona antes de
    // imprimir la proforma.
    foreach ($lineas as $numero => $linea) {
      $linea->set('field_tec_quantity', 10 + $numero);
      $linea->save();
    }

    $imprimir = function () use ($pedido, $describir): array {
      $vista = Views::getView(VISTA_LINEAS);
      $vista->setDisplay('page_4');
      $vista->setArguments([$pedido->id()]);
      $vista->preExecute();
      $vista->execute();

      $filas = [];
      foreach ($vista->result as $fila) {
        $linea = $fila->_entity ?? NULL;
        $filas[] = $describir($linea && $linea->hasField('field_tec_size_variation')
          ? $linea->get('field_tec_size_variation')->entity
          : NULL);
      }

      return $filas;
    };

    $antes = $imprimir();
    $mirar('la proforma imprime sus lineas en el orden en que se crearon',
      $antes === $conLaBPrimero,
      count($antes) . ' filas; la primera dice «' . trim($antes[0] ?? '') . '»');

    // Y ahora se arrastra la marca del reves, que es lo que rompia el principio.
    CataloguePosition::write([
      (int) $guante10->id() => 1,
      (int) $guante2->id() => 2,
      (int) $casco->id() => 3,
    ]);

    $despues = $imprimir();
    $mirar('volver a arrastrar la marca no mueve el pedido ya emitido',
      $despues === $antes,
      'la primera dice ahora «' . trim($despues[0] ?? '') . '»');

    // Un navegador pide cada pantalla en una peticion nueva y lee los productos
    // de la base de datos. Aqui todo pasa en el mismo proceso, y las tallas que
    // se cargaron antes del arrastre siguen en memoria apuntando a los productos
    // de antes, asi que la vista contestaria con el orden viejo aunque el nuevo
    // ya este guardado. Se vacia esa memoria, que es lo que hace una peticion.
    $productos->resetCache();

    $siguiente = $filasDe(VISTA_BORRADOR, 'default', [(string) $cliente->id()]);
    $mirar('pero la proforma siguiente ya sale con el orden nuevo',
      $siguiente === array_merge($laMarcaB, [
        $fila('guante 10', 'Black', 'M'),
        $fila('guante 2', 'Gold', '6 oz'),
        $fila('guante 2', 'Gold', '10 oz'),
        $fila('guante 2', 'Black', '6 oz'),
        $fila('guante 2', 'Black', '10 oz'),
        $fila('casco', 'Black', 'M'),
      ]),
      'la segunda dice «' . trim($siguiente[1] ?? '') . '»');

    // El sumador de una pantalla que agrega tiene que devolver UNA fila. Si
    // alguien le pone un orden por el numero de la linea, Views mete ese numero
    // en el GROUP BY y el bloque devuelve una fila por linea, cada una con su
    // propio total. Paso de verdad el 19 de agosto.
    $suma = Views::getView(VISTA_LINEAS);
    $suma->setDisplay('block_5');
    $suma->setArguments([$pedido->id()]);
    $suma->preExecute();
    $suma->execute();
    $mirar('y el bloque de totales sigue devolviendo una sola fila',
      count($suma->result) === 1,
      count($suma->result) . ' filas, o sea que agrupa por linea');
  }

  // ---------------------------------------------------------------------------
  $seccion('7. La pantalla vieja y sus pesos ya no estan');

  $vistaProductos = \Drupal::config('views.view.tec_products');
  $mirar('no queda la pantalla de ordenar productos del cliente',
    $vistaProductos->get('display.page_2') === NULL);

  $mirar('ni su direccion /tec_crm/N/reorder',
    \Drupal::service('router.route_provider')
      ->getRoutesByPattern('/tec_crm/{arg_0}/reorder')->count() === 0);

  $mirar('la tabla de pesos de draggableviews ya no existe',
    !\Drupal::database()->schema()->tableExists('draggableviews_structure'));

  $citan = [];
  foreach (\Drupal::configFactory()->listAll('views.view.') as $nombre) {
    if (str_contains(json_encode(\Drupal::config($nombre)->getRawData()), 'draggableviews')) {
      $citan[] = substr($nombre, strlen('views.view.'));
    }
  }
  $mirar('y ninguna vista la cita ya',
    $citan === [],
    implode(', ', $citan));

  // ---------------------------------------------------------------------------
  $seccion('8. Quien lista lineas ordena por su numero, y quien suma no ordena');

  $lineasConfig = \Drupal::config('views.view.' . VISTA_LINEAS);
  foreach (['page_1', 'page_2', 'page_3', 'page_4', 'block_1', 'block_2', 'block_4'] as $pantalla) {
    $sorts = $lineasConfig->get('display.' . $pantalla . '.display_options.sorts');
    $mirar($pantalla . ' ordena por el numero de la linea',
      is_array($sorts) && array_keys($sorts) === ['id'] && ($sorts['id']['order'] ?? '') === 'ASC',
      $sorts === NULL ? 'hereda el orden' : implode(', ', array_keys((array) $sorts)));
  }
  foreach (['default', 'block_5'] as $pantalla) {
    $sorts = $lineasConfig->get('display.' . $pantalla . '.display_options.sorts');
    $mirar($pantalla . ' no ordena, porque suma',
      $sorts === [] || $sorts === NULL,
      implode(', ', array_keys((array) $sorts)));
  }

  $borradorConfig = \Drupal::config('views.view.' . VISTA_BORRADOR);
  $mirar('y la vista que crea las lineas no ordena en SQL, lo hace PHP',
    ($borradorConfig->get('display.default.display_options.sorts') ?: []) === []
    && function_exists('tec_production_views_post_execute')
    && in_array('default', CatalogueOrder::SCREENS[VISTA_BORRADOR] ?? [], TRUE));
}
finally {
  $seccion('Recogiendo');

  // La bandera se baja sola al acabar la ECA, pero una que se murio a medias la
  // dejaria puesta y la carrera siguiente se portaria de otra manera.
  $bandera = $gestor->getStorage('flag')->load('tec_order_draft_sales');
  if ($bandera && isset($cliente)) {
    foreach (\Drupal::service('flag')->getEntityFlaggings($bandera, $cliente) as $puesta) {
      $puesta->delete();
    }
  }

  // De dentro hacia fuera: borrar un producto se lleva a sus hijos por su
  // cuenta, asi que se comprueba que siguen ahi antes de borrar cada uno.
  $borradas = 0;
  foreach (array_reverse($creadas) as $ficha) {
    $ficha = $gestor->getStorage($ficha->getEntityTypeId())->loadUnchanged($ficha->id());
    if ($ficha) {
      $ficha->delete();
      $borradas++;
    }
  }
  printf("  borradas %d fichas del juego\n", $borradas);
}

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. La marca manda en el orden y el pedido emitido no se mueve.\n\n";
}
else {
  printf("  %d cosas mal.\n\n", $problemas);
}
