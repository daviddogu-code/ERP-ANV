<?php

/**
 * @file
 * Un pedido de compra vale lo que valia el dia que se hizo.
 *
 *   php vendor\bin\drush.php scr scripts/el-precio-no-se-mueve.php
 *
 * Se prueba el caso que planteo el dueño, con sus mismas cifras:
 *
 *   1 de enero    pedido 001, cantidad 100 a 10 -> 1.000
 *   1 de febrero  el proveedor sube el material de 10 a 15
 *   1 de marzo    pedido 002, cantidad 100 a 15 -> 1.500
 *   5 de marzo    se entra en el 001 y tiene que seguir diciendo 10 y 1.000
 *   1 de abril    llega la mercancia del 001 y sigue diciendo 10 y 1.000
 *
 * Hasta hoy el 001 pasaba a valer 1.500 en cuanto alguien lo guardara, porque el
 * importe se recalculaba con el coste que tuviera el material en ese momento. Y
 * recibir mercancia guarda la linea, asi que bastaba con que llegara el camion.
 *
 * Se mira ademas que el material de la prueba tenga un coste por unidad de
 * consumo bien distinto del de compra. Confundir esos dos es lo que llevaba
 * escribiendo 0,02 en lineas de un material de 80.
 *
 * Al terminar borra lo que ha creado.
 */

use Drupal\tec_production\Vat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const MARCA = 'ZZPRUEBA PRECIO';
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

$leido = function (string $html): string {
  $texto = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
  $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return preg_replace('/\s+/u', ' ', $texto);
};

$dinero = fn(float $cantidad): string => BAHT . ' ' . number_format($cantidad, 2);

/**
 * Lo que hay guardado ahora mismo en la linea, sin nada en memoria de por medio.
 */
$releer = function ($linea) use ($gestor): array {
  $fresca = $gestor->getStorage('tec_line_item')->loadUnchanged($linea->id());
  return [
    'precio' => $fresca->get('field_tec_price')->isEmpty() ? NULL : (float) $fresca->get('field_tec_price')->value,
    'total' => $fresca->get('field_tec_line_item_total_number')->isEmpty()
      ? NULL : (float) $fresca->get('field_tec_line_item_total_number')->value,
  ];
};

try {
  echo "\n1 de enero: un material a 10 y el pedido 001\n";
  echo str_repeat('-', 78) . "\n";

  // Los dos factores hacen que el coste por unidad de consumo sea 0,10, cien
  // veces menor que el de compra. Si alguna vez se vuelve a confundir uno con
  // otro, aqui se ve en la primera cifra.
  $material = $gestor->getStorage('taxonomy_term')->create([
    'vid' => 'tec_inventory',
    'name' => MARCA . ' material',
    'field_tec_cost' => 10,
    'field_tec_units' => 1,
    'field_tec_split_into' => 100,
  ]);
  $material->save();
  $basura[] = $material;

  $consumo = (float) $material->get('field_tec_price')->value;
  $mirar('el material se compra a 10', abs((float) $material->get('field_tec_cost')->value - 10) < 0.005);
  $mirar('y su coste por unidad de consumo es otra cosa', abs($consumo - 0.10) < 0.0005, number_format($consumo, 4));

  $proveedor = $gestor->getStorage('tec_crm')->create([
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' proveedor',
    'name' => MARCA . ' proveedor',
    'field_tec_customer_code' => 'ZZPR',
    'field_tec_contact_type' => [TEC_CRM_UX_TYPE_SUPPLIER],
    'field_tec_address' => [['country_code' => 'TH']],
    Vat::TREATMENT_FIELD => Vat::STANDARD,
  ]);
  $proveedor->save();
  $basura[] = $proveedor;

  /**
   * Un pedido de una linea, con el precio del dia como hace la lista de compra.
   */
  $pedido_de = function (float $cantidad) use ($gestor, $material, $proveedor, &$basura) {
    $linea = $gestor->getStorage('tec_line_item')->create([
      'type' => 'tec_po_line_item',
      'field_tec_inventory' => $material->id(),
      'field_tec_quantity' => $cantidad,
      'field_tec_price' => (float) $material->get('field_tec_cost')->value,
      'field_tec_vendor' => $proveedor->id(),
      'uid' => 1,
    ]);
    $linea->save();
    $basura[] = $linea;

    $pedido = $gestor->getStorage('tec_order')->create([
      'type' => 'tec_purchase_order',
      'field_tec_vendor' => $proveedor->id(),
      'field_tec_line_items' => [$linea->id()],
      'uid' => 1,
    ]);
    $pedido->save();
    array_unshift($basura, $pedido);

    return [$pedido, $linea];
  };

  [$pedido1, $linea1] = $pedido_de(100);
  $uno = $releer($linea1);
  $mirar('el 001 nace con precio 10', $uno['precio'] !== NULL && abs($uno['precio'] - 10) < 0.005,
    $uno['precio'] === NULL ? '(vacio)' : number_format($uno['precio'], 2));
  $mirar('y con importe 1.000', $uno['total'] !== NULL && abs($uno['total'] - 1000) < 0.005,
    $uno['total'] === NULL ? '(vacio)' : number_format($uno['total'], 2));

  echo "\n1 de febrero: el proveedor sube el material de 10 a 15\n";
  echo str_repeat('-', 78) . "\n";

  $material->set('field_tec_cost', 15)->save();
  $mirar('el material ya cuesta 15', abs((float) $material->get('field_tec_cost')->value - 15) < 0.005);

  $uno = $releer($linea1);
  $mirar('y el 001 no se ha enterado', $uno['precio'] !== NULL && abs($uno['precio'] - 10) < 0.005
    && $uno['total'] !== NULL && abs($uno['total'] - 1000) < 0.005,
    sprintf('precio %s, importe %s', number_format((float) $uno['precio'], 2), number_format((float) $uno['total'], 2)));

  echo "\n1 de marzo: el pedido 002, ya al precio nuevo\n";
  echo str_repeat('-', 78) . "\n";

  [$pedido2, $linea2] = $pedido_de(100);
  $dos = $releer($linea2);
  $mirar('el 002 nace con precio 15', $dos['precio'] !== NULL && abs($dos['precio'] - 15) < 0.005,
    $dos['precio'] === NULL ? '(vacio)' : number_format($dos['precio'], 2));
  $mirar('y con importe 1.500', $dos['total'] !== NULL && abs($dos['total'] - 1500) < 0.005,
    $dos['total'] === NULL ? '(vacio)' : number_format($dos['total'], 2));

  echo "\n5 de marzo: se entra en el 001 y en el 002\n";
  echo str_repeat('-', 78) . "\n";

  // Lo que hay que leer en pantalla, que es de lo que se hablo. El pie lleva el
  // 7% de un proveedor tailandes, asi que el total de abajo no es el subtotal.
  foreach ([
    ['el 001', $pedido1, 10.0, 1000.0],
    ['el 002', $pedido2, 15.0, 1500.0],
  ] as [$cual, $pedido, $precio, $importe]) {
    $respuesta = $pedir('/tec_order/' . $pedido->id());
    if ($respuesta->getStatusCode() !== 200) {
      $mirar($cual . ' abre', FALSE, 'estado ' . $respuesta->getStatusCode());
      continue;
    }
    $texto = $leido((string) $respuesta->getContent());

    $mirar($cual . ' enseña su Purchase Cost', str_contains($texto, $dinero($precio)), $dinero($precio));
    $mirar($cual . ' enseña su Sub total', str_contains($texto, $dinero($importe)), $dinero($importe));
    $mirar($cual . ' cuadra el pie con la linea',
      str_contains($texto, 'Subtotal ' . $dinero($importe)), $dinero($importe));

    $suma = Vat::breakdown($pedido);
    $mirar($cual . ' suma lo mismo por dentro', abs($suma['net'] - $importe) < 0.005, $dinero($suma['net']));
  }

  // Y el impreso, que es el papel que ve el proveedor.
  $respuesta = $pedir('/po/' . $pedido1->id() . '/print');
  $texto = $leido((string) $respuesta->getContent());
  $mirar('el impreso del 001 tambien dice 10', str_contains($texto, $dinero(10)));
  $mirar('y no se ha inventado el precio de hoy', !str_contains($texto, $dinero(15)));

  echo "\n1 de abril: llega la mercancia del 001\n";
  echo str_repeat('-', 78) . "\n";

  // Recibir guarda la linea, que es justo cuando se recalculaba el importe. Este
  // era el camino por el que un pedido cerrado cambiaba de precio sin que nadie
  // lo tocara.
  $linea1 = $gestor->getStorage('tec_line_item')->loadUnchanged($linea1->id());
  $linea1->set('field_tec_quantity_received', 100)->save();

  $uno = $releer($linea1);
  $mirar('recibirla no le ha movido el precio', $uno['precio'] !== NULL && abs($uno['precio'] - 10) < 0.005,
    number_format((float) $uno['precio'], 2));
  $mirar('ni el importe', $uno['total'] !== NULL && abs($uno['total'] - 1000) < 0.005,
    number_format((float) $uno['total'], 2));

  echo "\nY el borrador, que suma en el navegador\n";
  echo str_repeat('-', 78) . "\n";

  // El borrador multiplica en javascript lo que lee de la columna del precio. Si
  // esa columna volviera a enseñar el coste del material, la pantalla y lo
  // guardado dirian cosas distintas mientras se teclea.
  $respuesta = $pedir('/po/draft/' . $pedido1->id());
  $borrador = (string) $respuesta->getContent();
  $mirar('el borrador del 001 abre', $respuesta->getStatusCode() === 200, 'estado ' . $respuesta->getStatusCode());
  $mirar('y su columna de precio es la de la linea',
    str_contains($borrador, 'views-field-field-tec-price'));
  $mirar('con el 10 de enero, no con el 15 de hoy',
    str_contains($leido($borrador), $dinero(10)) && !str_contains($leido($borrador), $dinero(15)));
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
  ? "Un pedido emitido vale lo que valia. Subir un coste no reescribe el pasado.\n\n"
  : "$problemas cosa(s) mal.\n\n";
