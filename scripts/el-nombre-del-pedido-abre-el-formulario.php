<?php

/**
 * @file
 * Sales order names on /o and the customer Orders tab open /o/order/{id}.
 *
 *   php vendor\bin\drush.php scr scripts\el-nombre-del-pedido-abre-el-formulario.php
 */

use Drupal\user\Entity\User;
use Drupal\views\Views;

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
print "  El nombre del pedido abre /o/order\n";
print "=====================================================================\n\n";

$schema = \Drupal::keyValue('system.schema');
$antes = (int) $schema->get('tec_portal');
echo "schema before: $antes\n";

if ($antes < 8010) {
  tec_portal_update_8010();
  $schema->set('tec_portal', 8010);
  echo "ran 8010\n";
}
else {
  tec_portal_update_8010();
  echo "8010 already applied; ensure only\n";
}

echo 'schema after: ' . $schema->get('tec_portal') . "\n\n";

$vista = \Drupal::entityTypeManager()->getStorage('view')->load('tec_orders_orders');
$displays = $vista ? $vista->get('display') : [];
$texto = static function (string $display) use ($displays): string {
  return (string) ($displays[$display]['display_options']['fields']['title']['alter']['text'] ?? '');
};
$entidad = static function (string $display) use ($displays): bool {
  return !empty($displays[$display]['display_options']['fields']['title']['settings']['link_to_entity']);
};

$mirar('/o (block_1): el nombre va a /o/order',
  str_contains($texto('block_1'), 'href="/o/order/{{ id }}"') && !$entidad('block_1'),
  $texto('block_1'));
$mirar('Orders del cliente (block_2): igual',
  str_contains($texto('block_2'), 'href="/o/order/{{ id }}"') && !$entidad('block_2'),
  $texto('block_2'));
$mirar('pedidos de compra (block_3): siguen a la ficha',
  $entidad('block_3') && !str_contains($texto('block_3'), '/o/order/{{ id }}'),
  'link_to_entity=' . ($entidad('block_3') ? 'yes' : 'no') . ' text=' . $texto('block_3'));
$mirar('la cola sigue apuntando a /tec_order/{id}',
  str_contains(file_get_contents(DRUPAL_ROOT . '/modules/custom/tec_production/src/Form/ProductionQueueForm.php'), 'internal:/tec_order/'));

\Drupal::currentUser()->setAccount(User::load(1));

$view = Views::getView('tec_orders_orders');
$html = (string) \Drupal::service('renderer')->renderRoot($view->preview('block_1'));
$mirar('el HTML de /o enlaza nombres a /o/order/', str_contains($html, 'href="/o/order/'));
$mirar('y no deja el nombre en /tec_order/', !preg_match('#href="/tec_order/\d+"#', $html));

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. El nombre en /o abre /o/order, como /my.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";

if ($problemas > 0) {
  throw new \RuntimeException("$problemas checks failed.");
}
