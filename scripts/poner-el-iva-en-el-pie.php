<?php

/**
 * @file
 * Puts subtotal, VAT and real total at the foot of a purchase order.
 *
 * Until now the two purchase screens borrowed their total from attachment_1,
 * an attachment shared with the two sales screens, which sums the line totals
 * and calls the result Total. On a Thai supplier that is not what gets paid.
 *
 * So the two purchase displays stop using that attachment and grow a footer of
 * their own, drawn by the tec_purchase_vat_totals handler. Sales keeps the
 * attachment exactly as it is: the sales side of VAT is a different question
 * and has not been answered yet.
 *
 * Run: drush scr scripts/poner-el-iva-en-el-pie.php
 */

use Drupal\views\Entity\View;

const VISTA = 'tec_order_sales_order_line_items';
const PIE = 'tec_purchase_vat_totals';

// The order page and the printed order. The draft is not here: it totals in the
// browser while you type, so its VAT is javascript, not this.
const PANTALLAS_DE_COMPRA = ['block_2', 'page_3'];

$vista = View::load(VISTA);
if (!$vista) {
  print "No existe la vista " . VISTA . ".\n";
  return;
}

$displays = $vista->get('display');

// 1. The shared attachment stops attaching to the purchase screens.
$attachment = &$displays['attachment_1']['display_options']['displays'];
$quitados = [];
foreach (PANTALLAS_DE_COMPRA as $pantalla) {
  if (isset($attachment[$pantalla])) {
    unset($attachment[$pantalla]);
    $quitados[] = $pantalla;
  }
}
unset($attachment);
printf("  attachment_1 deja de poner su total en: %s\n", $quitados ? implode(', ', $quitados) : 'nada, ya estaba fuera');
printf("  y lo sigue poniendo en: %s\n", implode(', ', array_keys($displays['attachment_1']['display_options']['displays'])));

// 2. The purchase screens get their own foot.
foreach (PANTALLAS_DE_COMPRA as $pantalla) {
  if (!isset($displays[$pantalla])) {
    printf("  [MAL] no existe la pantalla %s\n", $pantalla);
    continue;
  }

  $opciones = &$displays[$pantalla]['display_options'];

  // A display with no footer of its own inherits the empty one from default,
  // so the override has to be declared or the footer is thrown away on save.
  $opciones['defaults']['footer'] = FALSE;
  $opciones['footer'][PIE] = [
    'id' => PIE,
    'table' => 'views',
    'field' => PIE,
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => PIE,
    'empty' => TRUE,
  ];
  unset($opciones);

  printf("  %s ya tiene su pie de subtotal, IVA y total\n", $pantalla);
}

$vista->set('display', $displays);
$vista->save();

print "\nGuardado. Repasando lo que ha quedado:\n";
$vista = View::load(VISTA);
$displays = $vista->get('display');
foreach (PANTALLAS_DE_COMPRA as $pantalla) {
  printf("  %-8s pie: %s   |   attachment_1 le atiende: %s\n",
    $pantalla,
    implode(', ', array_keys($displays[$pantalla]['display_options']['footer'] ?? [])) ?: '(vacio)',
    isset($displays['attachment_1']['display_options']['displays'][$pantalla]) ? 'si' : 'no'
  );
}
foreach (['block_1', 'page_4'] as $pantalla) {
  printf("  %-8s (ventas, no se toca)   attachment_1 le atiende: %s\n",
    $pantalla,
    isset($displays['attachment_1']['display_options']['displays'][$pantalla]) ? 'si' : 'NO, y deberia'
  );
}
