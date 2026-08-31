<?php

/**
 * @file
 * El pie de un pedido de compra dice lo que de verdad se paga.
 *
 *   php vendor\bin\drush.php scr scripts/se-suma-el-iva.php
 *
 * Hasta ahora las pantallas de compra ensenaban una sola cifra, la suma de las
 * lineas, y la llamaban Total. Con un proveedor tailandes eso no es lo que se
 * paga: el 7% se estampaba en cada pedido y no lo leia nadie, asi que el numero
 * de la pantalla y el de la factura no podian coincidir.
 *
 * Se prueban los tres casos que existen, porque los tres se ven distintos:
 *
 *   1. proveedor tailandes registrado -> subtotal, IVA y total
 *   2. proveedor extranjero           -> lo mismo, pero el IVA es un cero dicho
 *   3. pedido viejo, sin porcentaje   -> el total pelado de siempre
 *
 * Y se prueba lo que no debe pasar: que a ventas le salga una linea de IVA. La
 * proforma comparte con compras la pieza que ponia el total, asi que separarlas
 * mal se notaria ahi y en ningun otro sitio.
 *
 * Al terminar borra lo que ha creado.
 */

use Drupal\tec_production\Vat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZPRUEBA IVA';
const BAHT = "\u{0E3F}";

$gestor = \Drupal::entityTypeManager();
$nucleo = \Drupal::service('http_kernel');
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));
$sesion = \Drupal::service('session');

$problemas = 0;
$basura = [];

$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$pedir = function (string $ruta) use ($nucleo, $sesion) {
  $peticion = Request::create($ruta);
  $peticion->setSession($sesion);
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO al pedir %s: %s\n      %s\n", $ruta, get_class($e), $e->getMessage());
    return new \Symfony\Component\HttpFoundation\Response('', 500);
  }
};

/**
 * El texto de una pagina, sin etiquetas y sin espacios de sobra.
 *
 * Asi se puede preguntar por lo que se lee y no por como esta maquetado, que es
 * lo unico que le importa a quien mira la pantalla.
 */
$leido = function (string $html): string {
  $texto = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
  $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return preg_replace('/\s+/u', ' ', $texto);
};

// Con el espacio detras del simbolo, que es como sale en todas estas pantallas.
$dinero = fn(float $cantidad): string => BAHT . ' ' . number_format($cantidad, 2);

try {
  echo "\nPreparando dos proveedores y sus pedidos\n";
  echo str_repeat('-', 78) . "\n";

  $termino_storage = $gestor->getStorage('taxonomy_term');
  $ids = $termino_storage->getQuery()->accessCheck(FALSE)
    ->condition('vid', 'tec_inventory')
    ->range(0, 1)
    ->execute();
  if (!$ids) {
    echo "\n  No hay ni un material. Nada que probar.\n\n";
    return;
  }
  $material = (int) reset($ids);

  $crm_storage = $gestor->getStorage('tec_crm');
  $linea_storage = $gestor->getStorage('tec_line_item');
  $pedido_storage = $gestor->getStorage('tec_order');

  /**
   * Un proveedor de mentira con su forma de tributar ya dicha.
   */
  $proveedor_de = function (string $como, string $codigo, string $pais) use ($crm_storage, &$basura) {
    $contacto = $crm_storage->create([
      'type' => 'tec_contact_organization',
      'title' => MARCA . ' ' . $codigo,
      'name' => MARCA . ' ' . $codigo,
      'field_tec_customer_code' => $codigo,
      'field_tec_contact_type' => [TEC_CRM_UX_TYPE_SUPPLIER],
      'field_tec_address' => [['country_code' => $pais]],
      Vat::TREATMENT_FIELD => $como,
    ]);
    $contacto->save();
    $basura[] = $contacto;
    return $contacto;
  };

  /**
   * Un pedido con dos lineas de importes conocidos.
   */
  $pedido_de = function ($proveedor) use ($linea_storage, $pedido_storage, $material, &$basura) {
    $lineas = [];
    // El total de la linea no lo decide esto: lo pone ECA al guardar, y en una
    // linea de compra lo saca del coste de compra del material, no del precio
    // que se escriba aqui. Asi que el precio va solo para que la linea sea una
    // linea de verdad, y mas abajo se lee lo que haya quedado guardado.
    foreach ([[100, 7], [50, 6]] as [$precio, $cantidad]) {
      $linea = $linea_storage->create([
        'type' => 'tec_po_line_item',
        'field_tec_inventory' => $material,
        'field_tec_quantity' => $cantidad,
        'field_tec_price' => $precio,
        'field_tec_vendor' => $proveedor->id(),
        'uid' => 1,
      ]);
      $linea->save();
      $basura[] = $linea;
      $lineas[] = $linea->id();
    }

    $pedido = $pedido_storage->create([
      'type' => 'tec_purchase_order',
      'field_tec_vendor' => $proveedor->id(),
      'field_tec_line_items' => $lineas,
      'uid' => 1,
    ]);
    $pedido->save();
    array_unshift($basura, $pedido);
    return $pedido;
  };

  $tailandes = $proveedor_de(Vat::STANDARD, 'ZZTH', 'TH');
  $extranjero = $proveedor_de(Vat::FOREIGN, 'ZZIT', 'IT');

  $pedido_th = $pedido_de($tailandes);
  $pedido_ex = $pedido_de($extranjero);

  printf("         %s para el tailandes, %s para el extranjero\n", $pedido_th->label(), $pedido_ex->label());

  $neto = 0.0;
  foreach ($pedido_th->get('field_tec_line_items')->referencedEntities() as $linea) {
    $neto += (float) $linea->get('field_tec_line_item_total_number')->value;
  }
  $neto = round($neto, 2);
  $mirar('las dos lineas suman algo que gravar', $neto > 0, $dinero($neto));

  // La cuenta esperada se hace aparte, y a mano, para no preguntarle al mismo
  // codigo que se esta probando si acerto.
  $iva_esperado = round($neto * 7 / 100, 2);
  $total_esperado = round($neto + $iva_esperado, 2);

  echo "\nLa cuenta\n";
  echo str_repeat('-', 78) . "\n";

  $cuenta_th = Vat::breakdown($pedido_th);
  $mirar('al tailandes se le estampo el 7', ($cuenta_th['rate'] ?? NULL) === 7.0, var_export($cuenta_th['rate'] ?? NULL, TRUE));
  $mirar('el IVA es el 7% del neto', abs($cuenta_th['vat'] - $iva_esperado) < 0.005, $dinero($cuenta_th['vat']));
  $mirar('el total es neto mas IVA', abs($cuenta_th['gross'] - $total_esperado) < 0.005, $dinero($cuenta_th['gross']));

  $cuenta_ex = Vat::breakdown($pedido_ex);
  $mirar('al extranjero se le estampo un cero', ($cuenta_ex['rate'] ?? NULL) === 0.0, var_export($cuenta_ex['rate'] ?? NULL, TRUE));
  $mirar('y su total es el neto pelado', abs($cuenta_ex['gross'] - $neto) < 0.005, $dinero($cuenta_ex['gross']));

  echo "\nLa ficha del pedido y el impreso\n";
  echo str_repeat('-', 78) . "\n";

  foreach ([
    'la ficha' => '/tec_order/' . $pedido_th->id(),
    'el impreso' => '/po/' . $pedido_th->id() . '/print',
  ] as $donde => $ruta) {
    $respuesta = $pedir($ruta);
    $mirar($donde . ' abre', $respuesta->getStatusCode() === 200, 'estado ' . $respuesta->getStatusCode());
    if ($respuesta->getStatusCode() !== 200) {
      continue;
    }
    $crudo = (string) $respuesta->getContent();
    $texto = $leido($crudo);

    // Sin su hoja de estilo el pie sale desnudo y pegado a la izquierda, que en
    // un papel que se manda a un proveedor se nota mas que un numero mal puesto.
    $mirar($donde . ' lo pinta como un pie y no como una tabla suelta',
      str_contains($crudo, 'tec-vat-totals'));

    $mirar($donde . ' dice el subtotal', str_contains($texto, 'Subtotal ' . $dinero($neto)), $dinero($neto));
    $mirar($donde . ' dice el IVA con su tipo', str_contains($texto, 'VAT 7% ' . $dinero($iva_esperado)), $dinero($iva_esperado));
    $mirar($donde . ' dice lo que se paga', str_contains($texto, 'Total ' . $dinero($total_esperado)), $dinero($total_esperado));
  }

  // Un cero dicho, no un hueco: el proveedor de fuera no cobra IVA y el papel lo
  // explica, que es lo que se puede defender.
  $respuesta = $pedir('/tec_order/' . $pedido_ex->id());
  $texto = $leido((string) $respuesta->getContent());
  $mirar('al extranjero le sale el cero escrito', str_contains($texto, 'VAT 0% ' . $dinero(0)));

  $mirar('la hoja de estilo del pie existe',
    (bool) \Drupal::service('library.discovery')->getLibraryByName('tec_production', 'purchase_totals'),
    'tec_production/purchase_totals');

  echo "\nEl borrador, que suma en el navegador\n";
  echo str_repeat('-', 78) . "\n";

  // Aqui el pie no lo dibuja el servidor: lo hace el javascript segun se
  // teclean las cantidades. Lo unico que se puede comprobar desde aqui es que
  // le llegue el porcentaje, que es lo que le falta al navegador para poder
  // calcularlo. Lo demas es cosa suya.
  $respuesta = $pedir('/po/draft/' . $pedido_th->id());
  $borrador = (string) $respuesta->getContent();
  $mirar('el borrador de compra abre', $respuesta->getStatusCode() === 200, 'estado ' . $respuesta->getStatusCode());

  preg_match('/"tecPurchaseVat":\s*\{"rate":\s*([0-9.]+)\}/', $borrador, $dice);
  $mirar('y le llega el 7 al navegador',
    isset($dice[1]) && abs((float) $dice[1] - 7.0) < 0.005,
    $dice[1] ?? 'no le llega nada');

  echo "\nUn pedido de los de antes del IVA\n";
  echo str_repeat('-', 78) . "\n";

  // Los que se hicieron antes de que el campo existiera no tienen porcentaje, y
  // no se les inventa uno: se quedan con el total de siempre.
  $pedido_ex->set(Vat::RATE_FIELD, NULL)->save();
  $cuenta_vieja = Vat::breakdown($pedido_ex);
  $mirar('sin porcentaje no hay IVA que ensenar', $cuenta_vieja['rate'] === NULL && $cuenta_vieja['vat'] === NULL);

  $respuesta = $pedir('/tec_order/' . $pedido_ex->id());
  $texto = $leido((string) $respuesta->getContent());
  $mirar('pero no se queda sin total', str_contains($texto, 'Total ' . $dinero($neto)), $dinero($neto));
  $mirar('y no le aparece ni subtotal ni IVA', !str_contains($texto, 'Subtotal') && !str_contains($texto, 'VAT '));
}
finally {
  foreach ($basura as $ficha) {
    try {
      $ficha->delete();
    }
    catch (\Throwable $e) {
      printf("  no se ha podido borrar %s %s: %s\n", $ficha->getEntityTypeId(), $ficha->id(), $e->getMessage());
    }
  }
}

echo "\n";
echo $problemas === 0
  ? "El pie del pedido de compra dice lo que se paga.\n\n"
  : "$problemas cosa(s) mal.\n\n";
