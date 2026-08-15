<?php

/**
 * @file
 * Prueba la lista de compra de punta a punta: la pantalla y el boton.
 *
 *   php vendor\bin\drush.php scr scripts/funciona-la-lista-de-compra.php
 *
 * /purchase ensena lo que hay que comprar agrupado por proveedor, y cada
 * proveedor lleva un boton que crea su pedido de compra con las lineas ya
 * rellenas. Comprobar que la pagina devuelve 200 no vale de nada: lo que puede
 * salir mal es el pedido que escribe, y eso no se ve mirando el codigo HTTP.
 *
 * Se prueba como lo haria un navegador -se pide la pagina, se devuelve con sus
 * campos y su ficha de seguridad- porque llamar al constructor de formularios a
 * mano deja el boton sin pulsar: el guardado depende de saber que boton se ha
 * apretado, y un formulario programado no tiene ninguno.
 *
 * Se mira, en este orden:
 *
 *   - que la pantalla trae filas de verdad, no una tabla vacia
 *   - que el pedido sale con su numero, su proveedor y su estado
 *   - que las lineas llevan material, cantidad, precio y total
 *   - que la linea apunta a su pedido, que es por donde el ERP las encuentra
 *   - que el material desaparece de la lista, porque ya viene de camino
 *
 * Borra el pedido y las lineas que ha creado, asi que se puede lanzar otra vez
 * y no le descuadra las cuentas a comprobacion.php.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Component\Utility\NestedArray;

const DIRECCION = '/purchase';

$gestor = \Drupal::entityTypeManager();
$nucleo = \Drupal::service('http_kernel');
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));
$sesion = \Drupal::service('session');

$problemas = 0;

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
 * Pide una pagina como usuario 1.
 */
$pedir = function (string $ruta, string $metodo = 'GET', array $datos = []) use ($nucleo, $sesion) {
  $peticion = Request::create($ruta, $metodo, $datos);
  $peticion->setSession($sesion);
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (\Drupal\Core\Form\EnforcedResponseException $e) {
    // Un formulario que guarda redirige, y dentro de una peticion interna la
    // redireccion sale por aqui. Es buena senal.
    return $e->getResponse();
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO: %s\n      %s\n      en %s:%d\n", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
    return new \Symfony\Component\HttpFoundation\Response('', 500);
  }
};

/**
 * Devuelve los campos de un formulario tal y como los mandaria un navegador.
 */
$campos = function (string $html): array {
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML($html);
  libxml_clear_errors();

  $datos = [];
  foreach ($documento->getElementsByTagName('input') as $campo) {
    $nombre = $campo->getAttribute('name');
    $tipo = strtolower($campo->getAttribute('type'));
    if ($nombre === '' || in_array($tipo, ['submit', 'button', 'image'], TRUE)) {
      continue;
    }
    if (in_array($tipo, ['checkbox', 'radio'], TRUE) && !$campo->hasAttribute('checked')) {
      continue;
    }
    $datos[$nombre] = $campo->getAttribute('value');
  }
  return $datos;
};

/**
 * Los nombres con corchetes hay que anidarlos o no llegan al nucleo.
 */
$anidar = function (array $datos): array {
  $anidados = [];
  foreach ($datos as $nombre => $valor) {
    if (!preg_match_all('/\[([^\]]*)\]/', $nombre, $partes)) {
      $anidados[$nombre] = $valor;
      continue;
    }
    $camino = array_merge([strstr($nombre, '[', TRUE)], $partes[1]);
    NestedArray::setValue($anidados, $camino, $valor);
  }
  return $anidados;
};

/**
 * Cuantos pedidos de compra hay.
 */
$cuantosPedidos = function () use ($gestor): int {
  return (int) $gestor->getStorage('tec_order')->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_purchase_order')
    ->count()
    ->execute();
};

echo "\n";
echo "La pantalla\n";
echo str_repeat('-', 78) . "\n";

$respuesta = $pedir(DIRECCION);
$mirar('la pantalla carga', $respuesta->getStatusCode() === 200, 'codigo ' . $respuesta->getStatusCode());
if ($respuesta->getStatusCode() !== 200) {
  echo "\n  Sin pantalla no hay nada que probar.\n\n";
  return;
}

$html = $respuesta->getContent();
$datos = $campos($html);

$cantidades = array_values(array_filter(array_keys($datos), fn($n) => preg_match('/^g\d+\[lines\]\[\d+\]\[quantity\]$/', $n)));
$mirar('trae filas con cantidad', $cantidades !== [], count($cantidades) . ' filas');
$mirar('trae la ficha de seguridad', isset($datos['form_token']));

// El boton lleva el identificador del proveedor en su nombre, que es como el
// guardado sabe para quien es el pedido.
preg_match_all('/name="(create-\d+)"/', $html, $botones);
$boton = $botones[1][0] ?? '';
$mirar('trae el boton de crear el pedido', $boton !== '', $boton);

if (!$cantidades || $boton === '') {
  echo "\n  Sin filas o sin boton no se puede seguir. Puede que no haya nada\n";
  echo "  que comprar: mira scripts/que-hay-que-comprar.php.\n\n";
  return;
}

// Lo que la pantalla propone, para comprobar luego que es lo que se ha escrito.
$propuesto = [];
foreach ($cantidades as $nombre) {
  preg_match('/^g(\d+)\[lines\]\[(\d+)\]\[quantity\]$/', $nombre, $trozos);
  $propuesto[(int) $trozos[2]] = (int) $datos[$nombre];
}
foreach ($propuesto as $tid => $cantidad) {
  $material = $gestor->getStorage('taxonomy_term')->load($tid);
  printf("         propone %d de %s\n", $cantidad, $material ? $material->label() : ('material ' . $tid));
}

echo "\n";
echo "El boton\n";
echo str_repeat('-', 78) . "\n";

$antes = $cuantosPedidos();
$datos[$boton] = 'Create purchase order';
$datos['op'] = 'Create purchase order';

$respuesta = $pedir(DIRECCION, 'POST', $anidar($datos));
$mirar(
  'el formulario redirige al guardar',
  $respuesta->isRedirection(),
  'codigo ' . $respuesta->getStatusCode() . ', a ' . ($respuesta->headers->get('Location') ?? '(ningun sitio)')
);

$ahora = $cuantosPedidos();
$mirar('se ha creado un pedido y solo uno', $ahora === $antes + 1, $antes . ' antes, ' . $ahora . ' ahora');

if ($ahora !== $antes + 1) {
  echo "\n  No se ha creado el pedido, no hay nada que revisar.\n\n";
  return;
}

$ids = $gestor->getStorage('tec_order')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_purchase_order')
  ->sort('id', 'DESC')
  ->range(0, 1)
  ->execute();
$pedido = $gestor->getStorage('tec_order')->load(reset($ids));

echo "\n";
echo "El pedido que ha salido\n";
echo str_repeat('-', 78) . "\n";
printf("         %s (ficha %s)\n", $pedido->label(), $pedido->id());

$mirar(
  'lleva numero con el formato del ERP',
  (bool) preg_match('/^\d{2}-\d+$/', (string) $pedido->label()),
  (string) $pedido->label()
);
$proveedor = $pedido->get('field_tec_vendor')->entity;
$mirar('lleva proveedor', $proveedor !== NULL, $proveedor ? $proveedor->label() : '');
$mirar(
  'lleva estado',
  $pedido->get('field_tec_po_status')->value === 'open',
  (string) $pedido->get('field_tec_po_status')->value
);
$mirar('lleva dueno', !$pedido->get('uid')->isEmpty(), (string) $pedido->get('uid')->target_id);

$lineas = $pedido->get('field_tec_line_items')->referencedEntities();
$mirar('lleva las lineas', count($lineas) === count($propuesto), count($lineas) . ' de ' . count($propuesto));

foreach ($lineas as $linea) {
  $material = $linea->get('field_tec_inventory')->entity;
  $tid = $material ? (int) $material->id() : 0;
  $cantidad = (int) $linea->get('field_tec_quantity')->value;
  $precio = $linea->get('field_tec_price')->value;
  $total = $linea->get('field_tec_line_item_total_number')->value;
  $coste = $material ? (float) $material->get('field_tec_cost')->value : 0.0;

  printf("\n         linea %s: %s\n", $linea->id(), $linea->label());
  printf("         cantidad %s, precio %s, total %s\n", $cantidad, $precio ?? '(vacio)', $total ?? '(vacio)');

  $mirar('  la linea apunta al material', $material !== NULL);
  $mirar(
    '  la cantidad es la de la pantalla',
    isset($propuesto[$tid]) && $cantidad === $propuesto[$tid],
    $cantidad . ' contra ' . ($propuesto[$tid] ?? '?')
  );
  $mirar('  la linea lleva precio', $precio !== NULL && $precio !== '');
  // Lo calcula la ECA process_fpvka81 al guardar, y solo si hay precio.
  $mirar(
    '  el total es cantidad por coste',
    $total !== NULL && abs((float) $total - $cantidad * $coste) < 0.005,
    ($total ?? '(vacio)') . ' contra ' . number_format($cantidad * $coste, 2, '.', '')
  );
  $mirar(
    '  la linea se titula con el material',
    $material !== NULL && (string) $linea->label() === (string) $material->label(),
    (string) $linea->label()
  );
  $mirar(
    '  la linea apunta a su pedido',
    (int) ($linea->get('field_tec_order')->target_id ?? 0) === (int) $pedido->id(),
    (string) ($linea->get('field_tec_order')->target_id ?? '(vacio)')
  );
}

echo "\n";
echo "Lo pedido ya no se vuelve a pedir\n";
echo str_repeat('-', 78) . "\n";

$respuesta = $pedir(DIRECCION);
$datos = $campos($respuesta->getContent());
$quedan = array_values(array_filter(array_keys($datos), fn($n) => preg_match('/^g\d+\[lines\]\[\d+\]\[quantity\]$/', $n)));
$mirar(
  'los materiales pedidos salen de la lista',
  count($quedan) === count($cantidades) - count($propuesto),
  count($quedan) . ' filas quedan'
);

echo "\n";
echo "Recogiendo\n";
echo str_repeat('-', 78) . "\n";

foreach ($lineas as $linea) {
  $linea->delete();
}
$pedido->delete();
printf("         borrado el pedido %s y sus %d lineas\n", $pedido->label(), count($lineas));
$mirar('la base queda como estaba', $cuantosPedidos() === $antes, $cuantosPedidos() . ' pedidos');

echo "\n";
echo $problemas === 0
  ? "La lista de compra funciona: propone, escribe el pedido y no repite.\n\n"
  : "HAY $problemas cosas mal. No dar por bueno el boton.\n\n";
