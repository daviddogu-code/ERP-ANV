<?php

/**
 * @file
 * Deja las filas de /supplier-orders en una sola linea de texto.
 *
 *   php vendor\bin\drush.php scr scripts/poner-las-filas-en-una-linea.php
 *
 * El CSS (supplier-orders.css) es lo que impide el salto. Esto solo aplasta
 * los saltos que hay entre los iconos de la columna de acciones, para que el
 * HTML tampoco los separe.
 */

use Drupal\views\Entity\View;

$problemas = 0;
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$una_linea = function (string $html): string {
  $html = preg_replace('/\s+/', ' ', $html) ?? $html;
  return trim($html);
};

$campos_de_iconos = ['nothing', 'nothing_1', 'views_conditional_field'];

echo "\n";
echo "Iconos de las listas de compra, en una linea\n";
echo str_repeat('-', 78) . "\n";

foreach ([
  ['tec_supplier_orders', 'default', '/supplier-orders'],
  ['tec_supplier_orders', 'block_3', 'bloque en la ficha del proveedor'],
  ['tec_orders_orders', 'block_3', 'pestana de pedidos del proveedor'],
] as [$nombre, $display, $donde]) {
  $vista = View::load($nombre);
  if (!$vista) {
    $mirar("$nombre existe", FALSE);
    continue;
  }
  $mostrar = $vista->get('display');
  $campos = $mostrar[$display]['display_options']['fields'] ?? [];
  if (!$campos) {
    $mirar("$donde tiene campos", FALSE);
    continue;
  }

  $cambio = FALSE;
  foreach ($campos_de_iconos as $id) {
    if (!isset($campos[$id])) {
      continue;
    }
    foreach (['text', 'or'] as $clave) {
      if ($clave === 'text') {
        $valor = $campos[$id]['alter']['text'] ?? NULL;
      }
      else {
        $valor = $campos[$id]['or'] ?? NULL;
      }
      if (!is_string($valor) || !str_contains($valor, 'tec-control')) {
        continue;
      }
      $limpio = $una_linea($valor);
      if ($limpio === $valor) {
        continue;
      }
      $cambio = TRUE;
      if ($clave === 'text') {
        $campos[$id]['alter']['text'] = $limpio;
      }
      else {
        $campos[$id]['or'] = $limpio;
      }
    }
  }

  if ($cambio) {
    $mostrar[$display]['display_options']['fields'] = $campos;
    $vista->set('display', $mostrar);
    $vista->save();
  }

  $guardados = $vista->get('display')[$display]['display_options']['fields'];
  $textos = [];
  foreach ($campos_de_iconos as $id) {
    if (isset($guardados[$id]['alter']['text'])) {
      $textos[] = $guardados[$id]['alter']['text'];
    }
    if (isset($guardados[$id]['or'])) {
      $textos[] = $guardados[$id]['or'];
    }
  }
  $con_iconos = array_values(array_filter($textos, fn($t) => is_string($t) && str_contains($t, 'tec-control')));
  $todos_una = $con_iconos && !array_filter($con_iconos, fn($t) => str_contains($t, "\n"));
  $mirar("$donde deja los iconos en una linea", $todos_una);
}

echo "\nCSS\n";
echo str_repeat('-', 78) . "\n";

$librerias = \Drupal::service('library.discovery')->getLibrariesByExtension('tec_production');
$mirar('la libreria supplier_orders existe', isset($librerias['supplier_orders']));
$mirar('y apunta al css de una linea',
  str_contains(json_encode($librerias['supplier_orders'] ?? []), 'supplier-orders.css'));

$nucleo = \Drupal::service('http_kernel');
$sesion = \Drupal::service('session');
\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));
$peticion = \Symfony\Component\HttpFoundation\Request::create('/supplier-orders');
$peticion->setSession($sesion);
$html = (string) $nucleo->handle($peticion, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST, FALSE)->getContent();
$mirar('y /supplier-orders lo carga', str_contains($html, 'supplier-orders.css'));
$mirar('y la tabla sigue ahi', str_contains($html, 'view-tec-supplier-orders'));
$css_archivo = \Drupal::service('extension.list.module')->getPath('tec_production') . '/css/supplier-orders.css';
$css_texto = @file_get_contents(DRUPAL_ROOT . '/' . $css_archivo) ?: '';
$mirar('y el marco blanco no tiene tope de 1580',
  str_contains($css_texto, 'path-supplier-orders') && str_contains($css_texto, 'max-width: none'));

echo "\n";
echo $problemas === 0
  ? "Las filas de Supplier Orders caben en una linea.\n\n"
  : "$problemas cosa(s) mal.\n\n";
