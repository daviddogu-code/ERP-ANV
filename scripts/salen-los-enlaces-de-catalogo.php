<?php

/**
 * @file
 * Comprueba que cada formulario lleva el enlace a su catalogo.
 *
 * Los catalogos del ERP -tipos de producto, colores, tallas, unidades y tipos de
 * material- se gestionan en taxonomia y no se pueden crear al vuelo desde el
 * desplegable, asi que cada formulario lleva debajo del campo un enlace al
 * listado. Si ese enlace falta, quien esta rellenando una ficha no tiene forma de
 * anadir lo que le falta sin saberse la direccion de memoria.
 *
 * El 15 de agosto se descubrio que estaban perdidos en todas las pantallas de
 * editar. Se enganchaban comparando el identificador del formulario, y la ruta de
 * editar usa la operacion 'edit', que Drupal le pega al identificador: el modulo
 * comparaba con `..._form` y en editar el nombre real es `..._edit_form`. Solo
 * salian al crear. El de material se salvo porque los terminos de taxonomia usan
 * la misma operacion para las dos cosas.
 *
 * Y habia un segundo motivo, mas escondido: el color y la talla se editan
 * embebidos dentro del producto con inline_entity_form, y un formulario embebido
 * no pasa por el gancho normal con su propio identificador. Por eso el enlace de
 * tallas no podia salir donde de verdad hace falta, ni con el identificador bien.
 *
 * Uso: drush php:script scripts/salen-los-enlaces-de-catalogo
 */

use Drupal\Core\Form\FormState;
use Drupal\inline_entity_form\Element\InlineEntityForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$gestor = \Drupal::entityTypeManager();
$kernel = \Drupal::service('http_kernel');
$anterior = \Drupal::currentUser()->getAccount();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$fallos = 0;

/**
 * Los enlaces de catalogo que lleva un trozo de html, por su clase propia.
 *
 * Buscar el texto a secas no vale: la barra de administracion lleva un menu con
 * todos los vocabularios, asi que cualquier pantalla parece tenerlos todos. La
 * clase la pone solo nuestro ayudante.
 */
$enlacesEn = static function (string $html): array {
  preg_match_all(
    '~admin-form-styles-catalog-link[^>]*>\s*<a[^>]*href="([^"]+)"[^>]*>([^<]+)</a>~',
    $html,
    $coincidencias,
    PREG_SET_ORDER
  );
  $salida = [];
  foreach ($coincidencias as $una) {
    $salida[trim($una[2])] = $una[1];
  }
  return $salida;
};

/**
 * Un material de verdad, para poder pedir su pantalla de editar.
 */
$unMaterial = static function () {
  $ids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)->condition('vid', 'tec_inventory')->range(0, 1)->execute();
  return $ids ? reset($ids) : NULL;
};

$pantallas = [
  [
    '/admin/content/tec_product/add/tec_product',
    'anadir un producto',
    ['Manage product types'],
  ],
  [
    '/tec_product/887/edit',
    'editar un producto (aqui no salia ninguno)',
    // Solo el de tipos: al cargar la pagina la tabla de variaciones se dibuja
    // sola, sin ningun formulario de color abierto, asi que el campo de color no
    // existe todavia. Sus enlaces se comprueban mas abajo, por su camino.
    ['Manage product types'],
  ],
  [
    '/tec_product/888/edit',
    'editar un color suelto',
    ['Manage colors'],
  ],
  [
    '/tec_product/889/edit',
    'editar una talla suelta',
    ['Manage sizes'],
  ],
  [
    '/admin/structure/taxonomy/manage/tec_inventory/add',
    'anadir un material',
    ['Manage units', 'Manage Material Types'],
  ],
  [
    '/taxonomy/term/' . $unMaterial() . '/edit',
    'editar un material',
    ['Manage units', 'Manage Material Types'],
  ],
];

foreach ($pantallas as [$ruta, $comentario, $esperados]) {
  echo "\n";
  echo str_repeat('=', 82) . "\n";
  printf(" %s\n %s\n", $comentario, $ruta);
  echo str_repeat('=', 82) . "\n\n";

  $peticion = Request::create($ruta, 'GET');
  $peticion->setSession(\Drupal::service('session'));
  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    $codigo = $respuesta->getStatusCode();
    $html = (string) $respuesta->getContent();
  }
  catch (\Throwable $e) {
    printf("  EXCEPCION: %s\n", $e->getMessage());
    $fallos++;
    continue;
  }

  if ($codigo !== 200) {
    printf("  la pantalla devuelve %s\n", $codigo);
    $fallos++;
    continue;
  }

  $encontrados = $enlacesEn($html);
  foreach ($esperados as $texto) {
    $bien = isset($encontrados[$texto]);
    printf("  %-24s %s\n", $texto, $bien ? 'sale  ' . $encontrados[$texto] : 'FALTA');
    if (!$bien) {
      $fallos++;
    }
  }
  // Un enlace de mas tampoco esta bien: seria el mismo puesto dos veces.
  foreach (array_diff(array_keys($encontrados), $esperados) as $sobra) {
    printf("  %-24s SOBRA\n", $sobra);
    $fallos++;
  }
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Los formularios embebidos, que es donde se trabaja de verdad\n";
echo str_repeat('=', 82) . "\n\n";

// Estos no se pueden pedir con una direccion: se abren al pulsar "Add new size" o
// "Edit" en la tabla, o sea por AJAX. Asi que se monta el elemento de
// inline_entity_form y se deja que el modulo lo construya, que es el mismo camino
// que sigue al pulsar el boton, y es ahi dentro donde tiene que haber saltado el
// gancho de los formularios embebidos.
$embebidos = [
  ['tec_size_variation', 889, 'field_tec_size', 'editar una talla desde el producto'],
  ['tec_size_variation', NULL, 'field_tec_size', 'anadir una talla nueva, que es cuando se echa en falta una'],
  ['tec_color_variation', 888, 'field_tec_colors', 'editar un color desde el producto'],
];

foreach ($embebidos as [$grupo, $id, $campo, $comentario]) {
  printf("  %s\n", $comentario);
  try {
    $entityForm = [
      '#type' => 'inline_entity_form',
      '#entity_type' => 'tec_product',
      '#bundle' => $grupo,
      '#form_mode' => 'default',
      '#save_entity' => FALSE,
      '#parents' => ['prueba'],
      '#array_parents' => ['prueba'],
      '#tree' => TRUE,
    ];
    // Sin ficha de partida, IEF crea una del grupo pedido y la operacion pasa a
    // ser de anadir, que es el caso de "Add new size".
    if ($id !== NULL) {
      $entityForm['#default_value'] = $gestor->getStorage('tec_product')->load($id);
      $entityForm['#op'] = 'edit';
    }

    $completo = [];
    $construido = InlineEntityForm::processEntityForm($entityForm, new FormState(), $completo);

    $sufijo = (string) ($construido[$campo]['widget']['#suffix'] ?? '');
    $cuantas = substr_count($sufijo, 'admin-form-styles-catalog-link');

    printf("      trae el campo %-22s %s\n", $campo, isset($construido[$campo]) ? 'si' : 'NO');
    printf("      y debajo el enlace                   %s\n", $cuantas === 1 ? 'si' : ($cuantas === 0 ? 'NO' : "SALE $cuantas VECES"));
    if ($cuantas === 1 && preg_match('~href="([^"]+)"~', $sufijo, $donde)) {
      printf("      apunta a                             %s\n", $donde[1]);
    }
    if ($cuantas !== 1) {
      $fallos++;
    }
  }
  catch (\Throwable $e) {
    printf("      No se ha podido montar: %s\n", $e->getMessage());
    $fallos++;
  }
  echo "\n";
}

\Drupal::currentUser()->setAccount($anterior);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $fallos === 0 ? " Todo bien.\n" : " HAY $fallos cosas mal.\n";
echo str_repeat('=', 82) . "\n\n";
