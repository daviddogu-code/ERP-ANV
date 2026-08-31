<?php

/**
 * @file
 * El IVA de una venta: Tailandia 7%, fuera 0%, pie Subtotal / VAT / Total.
 *
 *   php vendor\bin\drush.php scr scripts\el-iva-de-venta.php
 *
 * ANV esta dada de alta. Toda venta con destino Tailandia lleva el tipo, da
 * igual como tribute el cliente. Exportacion es cero. No se copia la lista de
 * compras (Thai not registered): esa es del proveedor, y la misma ficha puede
 * ser cliente y proveedor.
 *
 * Las lineas siguen en neto. El pie dice Subtotal, VAT, Total. El porcentaje
 * se estampa en el pedido al crearlo, para que una proforma de marzo siga
 * diciendo lo de marzo en diciembre.
 *
 * Se monta su propio juego y lo barre al terminar.
 */

use Drupal\Core\Form\FormState;
use Drupal\tec_portal\Form\FactoryOrderForm;
use Drupal\tec_portal\Form\FactoryPlaceOrderForm;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_production\Vat;
use Drupal\views\Views;

const MARCA = 'ZZIVA VENTA';
const BAHT = "\u{0E3F}";
const CLIENTE = 1395;

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

tec_production_ensure_sales_vat_rate();

$problemas = 0;
$basura = [];

$mirar = static function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $pista === '' ? '' : ': ' . $pista);
  if (!$bien) {
    $problemas++;
  }
};

$guardar = static function ($entidad) use (&$basura) {
  $entidad->save();
  $basura[] = $entidad;
  return $entidad;
};

$leido = static function (string $html): string {
  $texto = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
  $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return preg_replace('/\s+/u', ' ', $texto);
};

$dinero = static fn(float $cantidad): string => BAHT . ' ' . number_format($cantidad, 2);

$barrer = static function () use (&$basura): void {
  foreach (array_reverse($basura) as $entidad) {
    try {
      $entidad->delete();
    }
    catch (\Throwable $e) {
    }
  }
  $basura = [];
  $gestor = \Drupal::entityTypeManager();
  foreach (['tec_line_item', 'tec_order', 'tec_crm'] as $tipo) {
    foreach ($gestor->getStorage($tipo)->loadMultiple() as $entidad) {
      if (str_starts_with((string) $entidad->label(), MARCA)) {
        $entidad->delete();
      }
    }
  }
};

$crm = $gestor->getStorage('tec_crm');
$pedidos = $gestor->getStorage('tec_order');
$lineas = $gestor->getStorage('tec_line_item');

$cliente_de = static function (string $pais, string $codigo, ?string $tratamiento = NULL) use ($crm, $guardar) {
  $valores = [
    'type' => 'tec_contact_organization',
    'title' => MARCA . ' ' . $codigo,
    'field_tec_customer_code' => $codigo,
    'field_tec_contact_type' => [['target_id' => CLIENTE]],
    'field_tec_address' => [['country_code' => $pais]],
  ];
  if ($tratamiento !== NULL) {
    $valores[Vat::TREATMENT_FIELD] = $tratamiento;
  }
  return $guardar($crm->create($valores));
};

$pedido_de = static function ($cliente, float $neto) use ($lineas, $pedidos, $guardar) {
  $linea = $guardar($lineas->create([
    'type' => 'tec_sales_order_line_item',
    'title' => MARCA . ' linea',
    'field_tec_quantity' => 10,
    'field_tec_price' => number_format($neto / 10, 2, '.', ''),
    'field_tec_custom_sales_price' => TRUE,
    'field_tec_line_item_total_number' => number_format($neto, 2, '.', ''),
    'uid' => 1,
  ]));
  $pedido = $guardar($pedidos->create([
    'type' => 'tec_sales_order',
    'title' => MARCA . ' ' . $cliente->label(),
    'field_tec_customer' => $cliente->id(),
    'field_tec_line_items' => [$linea->id()],
    'field_tec_order_status' => 'draft',
    'status' => 1,
    'uid' => 1,
  ]));
  $linea->set('field_tec_order', $pedido->id());
  $linea->save();
  return $pedidos->loadUnchanged($pedido->id()) ?: $pedido;
};

print "\n";
print "El porcentaje segun el pais, no segun como tributa de proveedor\n";
print str_repeat('-', 70) . "\n";

try {

$mirar('el campo esta en el pedido de venta',
  (bool) \Drupal\field\Entity\FieldConfig::loadByName('tec_order', 'tec_sales_order', Vat::RATE_FIELD));

$tipo = Vat::standardRate();
$mirar('el tipo vigente es un numero', $tipo > 0, (string) $tipo);

$th = $cliente_de('TH', 'ZZTH', Vat::LOCAL_EXEMPT);
$it = $cliente_de('IT', 'ZZIT', Vat::STANDARD);
$sg = $cliente_de('SG', 'ZZSG');

$mirar('Tailandia es el tipo aunque el cliente figure como no registrado',
  Vat::forCustomer($th) === $tipo,
  (string) Vat::forCustomer($th));
$mirar('Italia es cero aunque figure como proveedor que cobra IVA',
  Vat::forCustomer($it) === 0.0);
$mirar('Singapur es cero', Vat::forCustomer($sg) === 0.0);
$mirar('sin ficha es cero', Vat::forCustomer(NULL) === 0.0);

print "\nAl crear el pedido se estampa\n";
print str_repeat('-', 70) . "\n";

$neto = 1000.00;
$pedidoTh = $pedido_de($th, $neto);
$pedidoIt = $pedido_de($it, $neto);
$pedidoSg = $pedido_de($sg, $neto);

$cuentaTh = Vat::breakdown($pedidoTh);
$iva = Vat::on($neto, $tipo);
$bruto = round($neto + $iva, 2);

$mirar('a Tailandia se le estampo el tipo', ($cuentaTh['rate'] ?? NULL) === $tipo,
  var_export($cuentaTh['rate'] ?? NULL, TRUE));
$mirar('el IVA es el tipo del neto', abs(($cuentaTh['vat'] ?? -1) - $iva) < 0.005, $dinero($cuentaTh['vat'] ?? 0));
$mirar('el total es neto mas IVA', abs(($cuentaTh['gross'] ?? -1) - $bruto) < 0.005, $dinero($cuentaTh['gross'] ?? 0));

$cuentaIt = Vat::breakdown($pedidoIt);
$mirar('a Italia se le estampo un cero', ($cuentaIt['rate'] ?? NULL) === 0.0);
$mirar('y su total es el neto', abs(($cuentaIt['gross'] ?? -1) - $neto) < 0.005);

$cuentaSg = Vat::breakdown($pedidoSg);
$mirar('a Singapur se le estampo un cero', ($cuentaSg['rate'] ?? NULL) === 0.0);

$pedidoTh->set(Vat::RATE_FIELD, 9);
$pedidoTh->save();
$th->set('field_tec_address', [['country_code' => 'IT']]);
$th->save();
$pedidoTh = $pedidos->loadUnchanged($pedidoTh->id());
$mirar('un pedido ya estampado no se reescribe si el cliente cambia de pais',
  (float) $pedidoTh->get(Vat::RATE_FIELD)->value === 9.0);

$pedidoTh->set(Vat::RATE_FIELD, $tipo);
$pedidoTh->save();
$th->set('field_tec_address', [['country_code' => 'TH']]);
$th->save();

print "\nSin pais no se coloca ni se confirma\n";
print str_repeat('-', 70) . "\n";

$sinPais = $guardar($crm->create([
  'type' => 'tec_contact_organization',
  'title' => MARCA . ' sin pais',
  'field_tec_customer_code' => 'ZZNP',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
]));
$antes = $pedidos->getQuery()->accessCheck(FALSE)->condition('field_tec_customer', $sinPais->id())->execute();
$formulario = FactoryPlaceOrderForm::create(\Drupal::getContainer());
$estado = new FormState();
$estado->set('company_id', (int) $sinPais->id());
$estado->set('allowed_sizes', [1]);
$estado->setValue('lines', [1 => ['qty' => 2]]);
$construido = [];
$formulario->submitForm($construido, $estado);
\Drupal::messenger()->deleteAll();
$despues = $pedidos->getQuery()->accessCheck(FALSE)->condition('field_tec_customer', $sinPais->id())->execute();
$mirar('colocar sin pais no crea pedido', $despues === $antes);

$abierto = $pedido_de($sinPais, $neto);
$mirar('sin pais el Open no lleva porcentaje', $abierto->get(Vat::RATE_FIELD)->isEmpty());

print "\nEl pie del pedido cerrado\n";
print str_repeat('-', 70) . "\n";

$pedidoTh->set('field_tec_order_status', PortalOrder::PENDING_DEPOSIT);
$pedidoTh->save();
$pedidoIt->set('field_tec_order_status', PortalOrder::PENDING_DEPOSIT);
$pedidoIt->save();

$formTh = \Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedidoTh);
$htmlTh = (string) \Drupal::service('renderer')->renderRoot($formTh);
$textoTh = $leido($htmlTh);
$mirar('Tailandia dice el subtotal', str_contains($textoTh, 'Subtotal') && str_contains($textoTh, $dinero($neto)));
$mirar('Tailandia dice el IVA con el tipo', str_contains($textoTh, 'VAT ' . Vat::formatRate($tipo) . '%') && str_contains($textoTh, $dinero($iva)));
$mirar('Tailandia dice el total bruto', str_contains($textoTh, 'Total') && str_contains($textoTh, $dinero($bruto)));
$tipoEnForm = $formTh['#attached']['drupalSettings']['tecPortalVat']['rate'] ?? NULL;
$mirar('el formulario lleva el tipo al navegador',
  $tipoEnForm !== NULL && abs((float) $tipoEnForm - $tipo) < 0.005,
  var_export($tipoEnForm, TRUE));

$htmlIt = (string) \Drupal::service('renderer')->renderRoot(
  \Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedidoIt)
);
$textoIt = $leido($htmlIt);
$mirar('Italia dice VAT 0%', str_contains($textoIt, 'VAT 0%'));
$mirar('Italia paga el neto', str_contains($textoIt, $dinero($neto)));

$pedidoTh->set(Vat::RATE_FIELD, NULL);
$pedidoTh->save();
$htmlViejo = (string) \Drupal::service('renderer')->renderRoot(
  \Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedidos->loadUnchanged($pedidoTh->id()))
);
$textoViejo = $leido($htmlViejo);
$mirar('sin porcentaje no hay linea de IVA', !str_contains($textoViejo, 'VAT ') && !str_contains($textoViejo, 'Subtotal'));
$mirar('y conserva el total', str_contains($textoViejo, 'Total'));

print "\nLa proforma\n";
print str_repeat('-', 70) . "\n";

$pedidoIt = $pedidos->loadUnchanged($pedidoIt->id());
$vista = Views::getView('tec_order_sales_order_line_items');
if ($vista) {
  $vista->setDisplay('page_4');
  $vista->setArguments([(int) $pedidoIt->id()]);
  $vista->execute();
  $pintado = $vista->render();
  $htmlPf = (string) \Drupal::service('renderer')->renderRoot($pintado);
  $textoPf = $leido($htmlPf);
  $mirar('la proforma de exportacion dice VAT 0%', str_contains($textoPf, 'VAT 0%'));
}
else {
  $mirar('existe la vista de lineas', FALSE);
}

}
finally {
  $barrer();
}

echo "\n";
echo $problemas === 0
  ? "La venta a Tailandia lleva IVA, la de fuera no, y el pie lo dice.\n\n"
  : "$problemas cosa(s) mal.\n\n";
