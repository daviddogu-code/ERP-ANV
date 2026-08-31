<?php

/**
 * @file
 * Line items on the sales-order card is the portal table, not Oscar's view.
 *
 *   php vendor\bin\drush.php scr scripts\la-ficha-usa-la-tabla-del-portal.php
 */

use Drupal\tec_portal\Form\FactoryOrderForm;
use Drupal\tec_portal\OrderLineGrid;
use Drupal\tec_portal\PortalOrder;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

\Drupal::moduleHandler()->loadInclude('tec_portal', 'install');

$problemas = 0;
$mirar = static function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, !$bien && $pista !== '' ? ': ' . $pista : '');
  if (!$bien) {
    $problemas++;
  }
};

print "\n";
print "=====================================================================\n";
print "  La ficha usa la tabla del portal en Line items\n";
print "=====================================================================\n\n";

$schema = \Drupal::keyValue('system.schema');
$antes = (int) $schema->get('tec_portal');
echo "schema before: $antes\n";

if ($antes < 8009) {
  tec_portal_update_8009();
  $schema->set('tec_portal', 8009);
  echo "ran 8009\n";
}
else {
  _tec_portal_point_sales_card_lines_at_portal_grid();
  echo "8009 already applied; ensure only\n";
}

echo 'schema after: ' . $schema->get('tec_portal') . "\n\n";

$tabs = \Drupal::config('quicktabs.quicktabs_instance.tec_order_sales_order_tabs')->get('configuration_data') ?? [];
$bids = [];
foreach ($tabs as $tab) {
  $bids[(string) ($tab['title'] ?? '')] = (string) ($tab['content']['block_content']['options']['bid'] ?? '');
  if (($tab['title'] ?? '') === 'Material Summary') {
    $bids['Material Summary view'] = (string) ($tab['content']['view_content']['options']['vid'] ?? '');
  }
}

$mirar('Line items apunta al bloque PHP',
  ($bids['Line items'] ?? '') === 'tec_portal_sales_order_lines',
  $bids['Line items'] ?? 'ausente');
$mirar('Material calculation sigue siendo la vista de Oscar',
  ($bids['Material calculation'] ?? '') === 'views_block:tec_order_material_calculation_terms-block_1',
  $bids['Material calculation'] ?? 'ausente');
$mirar('Material Summary sigue siendo su vista',
  ($bids['Material Summary view'] ?? '') === 'tec_order_material_calculation_summary',
  $bids['Material Summary view'] ?? 'ausente');
$mirar('Production Documents sigue siendo su bloque',
  ($bids['Production Documents'] ?? '') === 'tec_order_production_documents',
  $bids['Production Documents'] ?? 'ausente');
$mirar('la vista de lineas sigue instalada (proforma)',
  (bool) \Drupal::entityTypeManager()->getStorage('view')->load('tec_order_sales_order_line_items'));

$definicion = \Drupal::service('plugin.manager.block')->getDefinition('tec_portal_sales_order_lines', FALSE);
$mirar('el plugin del bloque existe', !empty($definicion));

$pedido = NULL;
$ids = \Drupal::entityTypeManager()->getStorage('tec_order')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_sales_order')
  ->exists('field_tec_line_items')
  ->range(0, 20)
  ->sort('id', 'DESC')
  ->execute();
foreach ($ids as $id) {
  $candidato = \Drupal::entityTypeManager()->getStorage('tec_order')->load($id);
  if (!$candidato) {
    continue;
  }
  foreach ($candidato->get('field_tec_line_items')->referencedEntities() as $linea) {
    $qty = $linea->hasField('field_tec_quantity') && !$linea->get('field_tec_quantity')->isEmpty()
      ? (float) $linea->get('field_tec_quantity')->value
      : 0.0;
    if ($qty >= 1) {
      $pedido = $candidato;
      break 2;
    }
  }
}

if (!$pedido) {
  echo "\n  (no hay pedido de venta con lineas para pintar HTML)\n";
}
else {
  printf("\n  Pedido %d \"%s\"\n", $pedido->id(), $pedido->label());
  \Drupal::currentUser()->setAccount(User::load(1));

  $html = (string) \Drupal::service('renderer')->renderRoot(OrderLineGrid::create()->build($pedido));
  $mirar('la tabla PHP tiene la columna Image', str_contains($html, '>Image<'));
  $mirar('y Product, no Sales price',
    str_contains($html, '>Product<') && !str_contains($html, 'Product name') && !str_contains($html, 'Sales price'));
  $mirar('y no es la vista de Oscar', !str_contains($html, 'view-tec-order-sales-order-line-items'));
  $mirar('y lleva las clases de /o/order',
    str_contains($html, 'tec-portal__table') && str_contains($html, 'tec-portal--place'));

  $request = Request::create('/tec_order/' . $pedido->id());
  $request->attributes->set('_route', 'entity.tec_order.canonical');
  $request->attributes->set('tec_order', $pedido);
  $request->setSession(new Session(new MockArraySessionStorage()));
  \Drupal::requestStack()->push($request);

  $bloque = \Drupal::service('plugin.manager.block')->createInstance('tec_portal_sales_order_lines', []);
  $delBloque = (string) \Drupal::service('renderer')->renderRoot($bloque->build());
  $mirar('el bloque resuelve el pedido de la ruta', str_contains($delBloque, 'tec-portal__table'));

  $ficha = '';
  try {
    $build = \Drupal::entityTypeManager()->getViewBuilder('tec_order')->view($pedido, 'full');
    $ficha = (string) \Drupal::service('renderer')->renderRoot($build);
  }
  catch (\Throwable $e) {
    echo '  aviso: no pude pintar la ficha entera en CLI: ' . $e->getMessage() . "\n";
  }
  if ($ficha !== '') {
    $mirar('la ficha pinta la tabla del portal',
      str_contains($ficha, 'tec-portal__table') && str_contains($ficha, 'tec-portal--card-lines'),
      str_contains($ficha, 'view-tec-order-sales-order-line-items') ? 'sigue saliendo la vista de Oscar' : 'sin tabla PHP');
    $mirar('y no pinta el bloque de vistas de Line items',
      !str_contains($ficha, 'view-tec-order-sales-order-line-items'));
    $mirar('Material calculation sigue en la ficha',
      str_contains($ficha, 'view-tec-order-material-calculation-terms')
      || str_contains($ficha, 'tec-order-material-calculation-terms'),
      'no aparece la vista de materiales');
    $mirar('Production Documents sigue en la ficha',
      str_contains($ficha, 'tec-order-production-documents')
      || str_contains($ficha, 'production-documents'),
      'no aparece el bloque de documentos');
  }

  if (!PortalOrder::isOpen($pedido)) {
    $delForm = (string) \Drupal::service('renderer')->renderRoot(
      \Drupal::formBuilder()->getForm(FactoryOrderForm::class, $pedido)
    );
    preg_match_all('#<tbody>.*?</tbody>#s', $html, $cuerpoFicha);
    preg_match_all('#<tbody>.*?</tbody>#s', $delForm, $cuerpoForm);
    $texto = static function (string $markup): string {
      return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($markup), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    };
    $mirar('/o/order y la ficha pintan las mismas filas',
      ($cuerpoFicha[0][0] ?? '') !== ''
      && $texto($cuerpoFicha[0][0] ?? '') === $texto($cuerpoForm[0][0] ?? ''),
      'ficha ' . strlen($cuerpoFicha[0][0] ?? '') . ' /o/order ' . strlen($cuerpoForm[0][0] ?? ''));
  }

  \Drupal::requestStack()->pop();
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. Line items de la ficha es la tabla de /o/order.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";

if ($problemas > 0) {
  throw new \RuntimeException("$problemas checks failed.");
}
