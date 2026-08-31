<?php

/**
 * @file
 * Download on File invoice saves the scan; it does not open a tab.
 *
 *   php vendor\bin\drush.php scr scripts/el-download-de-la-factura.php
 *
 * TEC_PO=1115 to pin a production order. Otherwise the first purchase order
 * that already has a scan.
 */

use Drupal\tec_production\Controller\InvoiceDownloadController;
use Drupal\tec_production\Form\InvoiceUploadForm;
use Drupal\tec_production\Purchasing;
use Drupal\user\Entity\User;

$problemas = 0;
$mirar = static function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, !$bien && $pista !== '' ? ': ' . $pista : '');
  if (!$bien) {
    $problemas++;
  }
};

print "\n";
print "=====================================================================\n";
print "  El download de la factura\n";
print "=====================================================================\n\n";

$ruta = NULL;
try {
  $ruta = \Drupal::service('router.route_provider')->getRouteByName('tec_production.po_invoice_file');
}
catch (\Throwable) {
}
$mirar('existe /tec_order/{id}/invoice/file', $ruta && $ruta->getPath() === '/tec_order/{tec_order}/invoice/file');

$js = (string) file_get_contents(\Drupal::moduleHandler()->getModule('tec_production')->getPath() . '/js/invoice.js');
$mirar('el JS fuerza la descarga, no window.open', str_contains($js, 'setAttribute(\'download\'') && !str_contains($js, 'window.open'));

$storage = \Drupal::entityTypeManager()->getStorage('tec_order');
$pin = (int) (getenv('TEC_PO') ?: 0);
$order = $pin ? $storage->load($pin) : NULL;
if (!$order) {
  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_purchase_order')
    ->exists('field_tec_supplier_invoice')
    ->range(0, 1)
    ->execute();
  $order = $ids ? $storage->load(reset($ids)) : NULL;
}

if (!$order) {
  echo "\n  (no hay pedido de compra con factura en esta base)\n";
}
else {
  printf("pedido %d \"%s\"\n", $order->id(), $order->label());
  $file = Purchasing::invoice($order);
  $mirar('tiene factura en el campo', (bool) $file);
  if (!$file) {
    throw new \RuntimeException('no file');
  }

  $mirar('el fichero esta en disco', is_file($file->getFileUri()), $file->getFileUri());

  $bajar = Purchasing::invoiceDownloadUrl($order);
  $mirar('invoiceDownloadUrl apunta a esa ruta', $bajar && str_contains($bajar->toString(), '/invoice/file'));

  $form = \Drupal::formBuilder()->getForm(InvoiceUploadForm::class, $order);
  $html = (string) \Drupal::service('renderer')->renderRoot($form);
  $mirar('el formulario dice Download', str_contains($html, 'Download'));
  $mirar('el enlace va a /invoice/file', str_contains($html, '/invoice/file') && str_contains($html, 'tec-invoice__download'));
  $mirar('sin target=_blank en Download', !preg_match('/tec-invoice__download[^>]*target="_blank"/', $html));
  $mirar('el nombre del archivo abre el scan', str_contains($html, 'tec-invoice__open')
    && str_contains($html, 'target="_blank"')
    && (str_contains($html, '/system/files') || str_contains($html, htmlspecialchars($file->getFilename()))));

  $respuesta = \Drupal::classResolver(InvoiceDownloadController::class)->download($order);
  $disposicion = (string) $respuesta->headers->get('Content-Disposition');
  $mirar('la respuesta es un adjunto', str_contains($disposicion, 'attachment'));
  $mirar('y lleva el nombre del scan', str_contains($disposicion, $file->getFilename()));

  $lukpla = user_load_by_name('lukpla');
  if ($lukpla) {
    $mirar('lukpla entra al dialogo', InvoiceUploadForm::access($lukpla, $order)->isAllowed());
  }
  $mirar('uid1 entra al dialogo', InvoiceUploadForm::access(User::load(1), $order)->isAllowed());
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Download guarda el archivo. No abre una pestana.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";

if ($problemas > 0) {
  throw new \RuntimeException("$problemas checks failed.");
}
