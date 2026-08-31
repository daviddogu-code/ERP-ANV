<?php

/**
 * @file
 * Printed proforma is the PHP page at /o/pf/{id}/print, not Oscar's view.
 *
 *   php vendor\bin\drush.php scr scripts\el-print-de-la-proforma.php
 */

use Drupal\tec_portal\PortalOrder;
use Drupal\tec_portal\Proforma;
use Drupal\tec_production\Company;
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
print "  El print de la proforma\n";
print "=====================================================================\n\n";

$schema = \Drupal::keyValue('system.schema');
$antes = (int) $schema->get('tec_portal');
echo "schema before: $antes\n";
tec_portal_update_8011();
tec_portal_update_8012();
tec_portal_update_8013();
if ($antes < 8013) {
  $schema->set('tec_portal', 8013);
  echo "ran through 8013\n";
}
else {
  echo "8013 already applied; ensure only\n";
}
echo 'schema after: ' . $schema->get('tec_portal') . "\n";
\Drupal::service('theme.registry')->reset();
echo "theme registry reset\n\n";

$vista = \Drupal::entityTypeManager()->getStorage('view')->load('tec_order_sales_order_line_items');
$enabled = $vista ? ($vista->get('display')['page_4']['display_options']['enabled'] ?? TRUE) : 'missing';
$mirar('page_4 de Oscar esta apagada', $enabled === FALSE, (string) $enabled);

$ruta = NULL;
try {
  $ruta = \Drupal::service('router.route_provider')->getRouteByName('tec_portal.proforma');
}
catch (\Throwable) {
}
$mirar('existe la ruta PHP /o/pf/{id}/print', $ruta && $ruta->getPath() === '/o/pf/{tec_order}/print');

$po = \Drupal::entityTypeManager()->getStorage('view')->load('tec_order_sales_order_line_items');
$poPath = $po ? (string) ($po->get('display')['page_3']['display_options']['path'] ?? $po->get('display')['page_1']['display_options']['path'] ?? '') : '';
// Purchase print is page with path po/%/print — find it.
$compra = '';
if ($po) {
  foreach ($po->get('display') as $display) {
    $path = (string) ($display['display_options']['path'] ?? '');
    if ($path === 'po/%/print' || $path === 'po/%/print/') {
      $compra = $path;
      break;
    }
  }
}
$mirar('el print de compra no se ha tocado', $compra === 'po/%/print', $compra);

$jsPages = (string) \Drupal::config('asset_injector.js.tec_print_browser')->get('conditions.request_path.pages');
$cssPages = (string) \Drupal::config('asset_injector.css.tec_print')->get('conditions.request_path.pages');
$mirar('el print() inmediato ya no corre en la proforma', !str_contains($jsPages, '/o/pf/'), $jsPages);
$mirar('el CSS de Oscar ya no pisa la proforma', !str_contains($cssPages, '/o/pf/'), $cssPages);
$mirar('el print() de compra sigue', str_contains($jsPages, '/po/'), $jsPages);

$cssFile = DRUPAL_ROOT . '/modules/custom/tec_portal/css/proforma.css';
$jsFile = DRUPAL_ROOT . '/modules/custom/tec_portal/js/proforma.js';
$mirar('el CSS declara A4', str_contains((string) file_get_contents($cssFile), 'size: A4'));
$mirar('el JS espera a las fotos', str_contains((string) file_get_contents($jsFile), 'window.print') && str_contains((string) file_get_contents($jsFile), 'tec-portal__img'));

$spinner = (string) \Drupal::config('asset_injector.js.tec_link_spinner')->get('code');
$mirar('el spinner no se queda en un print de otra pestana',
  str_contains($spinner, "closest('a')") && str_contains($spinner, "target === '_blank'"));

\Drupal::currentUser()->setAccount(User::load(1));

$pedido = NULL;
$abierto = NULL;
$ids = \Drupal::entityTypeManager()->getStorage('tec_order')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_sales_order')
  ->sort('id', 'DESC')
  ->range(0, 40)
  ->execute();
foreach ($ids as $id) {
  $candidato = \Drupal::entityTypeManager()->getStorage('tec_order')->load($id);
  if (!$candidato) {
    continue;
  }
  if (!$pedido && PortalOrder::hasProforma($candidato)) {
    $pedido = $candidato;
  }
  if (!$abierto && PortalOrder::isOpen($candidato)) {
    $abierto = $candidato;
  }
  if ($pedido && $abierto) {
    break;
  }
}

if ($abierto) {
  $mirar('Open no tiene acceso al print',
    !\Drupal\tec_portal\Controller\ProformaController::access(User::load(1), $abierto)->isAllowed());
}

if (!$pedido) {
  echo "\n  (no hay pedido confirmado para pintar HTML)\n";
}
else {
  printf("\n  Pedido %d \"%s\"\n", $pedido->id(), $pedido->label());
  $build = Proforma::page($pedido);
  $html = (string) \Drupal::service('renderer')->renderRoot($build);
  $mirar('dice PROFORMA, no el letrero gordo',
    str_contains($html, 'PROFORMA') && !str_contains($html, 'PRO FORMA') && !str_contains($html, 'tec-proforma__doc'));
  $nombrePdf = Proforma::fileTitle($pedido);
  $mirar('el PDF se llama PROFORMA_ y el pedido',
    str_starts_with($nombrePdf, 'PROFORMA_') && !str_contains($nombrePdf, ' ') && str_contains($nombrePdf, str_replace(' ', '_', (string) $pedido->label())),
    $nombrePdf);
  $mirar('lleva el numero de pedido', str_contains($html, (string) $pedido->label()));
  $mirar('lleva el membrete de Company settings', str_contains($html, Company::legalName()));
  $web = Company::website();
  if ($web !== '') {
    $mirar('no imprime la web de la empresa', !str_contains($html, $web));
  }
  $mirar('la tabla usa cabeceras cortas',
    str_contains($html, '>Product<') && str_contains($html, '>Material<') && str_contains($html, '>Total<')
    && !str_contains($html, 'Product name') && !str_contains($html, 'Product material') && !str_contains($html, 'Item total'));
  $mirar('y sigue siendo la tabla del portal', str_contains($html, 'tec-portal__col-image') && str_contains($html, 'tec-portal__table'));
  $mirar('sin titulo Image: la columna ya es la foto', !str_contains($html, '>Image<'));
  $mirar('mayusculas de cabecera van en CSS',
    str_contains((string) file_get_contents($cssFile), 'text-transform: uppercase')
    && !str_contains($html, '>PRODUCT<'));
  $mirar('no es la vista de Oscar', !str_contains($html, 'view-tec-order-sales-order-line-items'));
  $buyer = Proforma::buyer($pedido);
  if ($buyer['name'] !== '') {
    $mirar('lleva el cliente', str_contains($html, $buyer['name']));
    $posFecha = strpos($html, '>Date<');
    $posCliente = strpos($html, $buyer['name']);
    $mirar('el cliente va debajo de la fecha', $posFecha !== FALSE && $posCliente !== FALSE && $posFecha < $posCliente);
  }
  $orden = static function (string $chunk, array $etiquetas): bool {
    $ultimo = -1;
    foreach ($etiquetas as $etiqueta) {
      $pos = strpos($chunk, '>' . $etiqueta . '<');
      if ($pos === FALSE) {
        continue;
      }
      if ($pos < $ultimo) {
        return FALSE;
      }
      $ultimo = $pos;
    }
    return $ultimo >= 0;
  };
  preg_match('#class="tec-proforma__buyer-table"[^>]*>(.*?)</table>#s', $html, $tablaCliente);
  preg_match('#class="tec-proforma__seller-table"[^>]*>(.*?)</table>#s', $html, $tablaMembrete);
  $cliente = $tablaCliente[1] ?? '';
  $membrete = $tablaMembrete[1] ?? '';
  $mirar('cliente: Company, VAT ID, Country, Address',
    $orden($cliente, ['Company', 'VAT ID', 'Country', 'Address'])
    && !str_contains($cliente, '>Phone<'));
  $mirar('membrete: Company, Tax ID, Country, Address, Email, Phone',
    $orden($membrete, ['Company', 'Tax ID', 'Country', 'Address', 'Email', 'Phone'])
    && str_contains($membrete, '>Country<'));
  $posSeller = strpos($html, 'tec-proforma__seller');
  $posMeta = strpos($html, 'tec-proforma__meta');
  $mirar('sin logo en el papel', !str_contains($html, 'tec-proforma__logo'));
  $mirar('PROFORMA y cliente a la izquierda, membrete a la derecha',
    $posMeta !== FALSE && $posSeller !== FALSE && $posMeta < $posSeller);
  $css = (string) file_get_contents($cssFile);
  $mirar('linea vertical en el medio, como Date/Company',
    str_contains($css, 'grid-template-columns: 1fr 1fr')
    && str_contains($css, '.tec-proforma__meta')
    && str_contains($css, 'border-right: 1px solid #c8c8c8')
    && str_contains($css, 'border-top: 1px solid #c8c8c8'));
  $mirar('cliente alineado al borde izquierdo del A4',
    !str_contains($css, 'align-items: flex-end')
    && !str_contains($css, 'width: 82mm'));
  $mirar('hueco de la raya = margen lateral 8mm',
    str_contains($css, 'padding: 0 8mm 0 0')
    && str_contains($css, 'padding: 0 0 0 8mm')
    && str_contains($css, 'margin: 8mm 8mm 10mm'));
  $mirar('sin BILL TO', !str_contains($html, 'Bill to') && !str_contains($html, 'BILL TO'));
  $mirar('los datos del cliente van a la derecha como la fecha',
    str_contains((string) file_get_contents($cssFile), '.tec-proforma__buyer-table td')
    && str_contains((string) file_get_contents($cssFile), 'text-align: right'));
  $mirar('el pie de tabla usa la misma altura',
    str_contains((string) file_get_contents($cssFile), 'tfoot tr')
    && str_contains((string) file_get_contents($cssFile), 'tfoot td'));
  $banco = Company::bankName();
  if ($banco !== '') {
    $mirar('lleva el banco si esta relleno', str_contains($html, $banco));
  }
  else {
    $mirar('sin banco no inventa una seccion', !str_contains($html, 'Bank details'));
  }

  $request = Request::create('/o/pf/' . $pedido->id() . '/print');
  $request->attributes->set('_route', 'tec_portal.proforma');
  $request->attributes->set('tec_order', $pedido);
  $request->setSession(new Session(new MockArraySessionStorage()));
  \Drupal::requestStack()->push($request);
  $delControlador = (string) \Drupal::service('renderer')->renderRoot(
    \Drupal\tec_portal\Controller\ProformaController::create(\Drupal::getContainer())->view($pedido)
  );
  $mirar('el controlador pinta la misma tabla', str_contains($delControlador, 'tec-portal__table'));
  $mirar('las miniaturas no van lazy',
    !preg_match('/<img[^>]*class="tec-portal__img"[^>]*loading="lazy"/', $html)
    && !preg_match('/<img[^>]*loading="lazy"[^>]*class="tec-portal__img"/', $html));
  $mirar('la libreria de print esta enganchada', in_array('tec_portal/proforma', $build['#attached']['library'] ?? [], TRUE));
  preg_match('#<tbody>(.*?)</tbody>#s', $html, $cuerpo);
  $filas = substr_count($cuerpo[1] ?? '', '<tr');
  $fotos = preg_match_all('/class="tec-portal__img"/', $cuerpo[1] ?? '');
  $huecos = substr_count($cuerpo[1] ?? '', 'tec-portal__thumb--empty');
  $mirar('cada linea reserva el hueco de la foto', $filas > 0 && ($fotos + $huecos) === $filas,
    sprintf('filas=%d fotos=%d huecos=%d', $filas, $fotos, $huecos));
  \Drupal::requestStack()->pop();
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. /o/pf/{id}/print es el papel PHP.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";

if ($problemas > 0) {
  throw new \RuntimeException("$problemas checks failed.");
}
