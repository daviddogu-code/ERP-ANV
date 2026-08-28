<?php

/**
 * @file
 * Applies the portal lock config that YAML is waiting to import.
 *
 *   php vendor/bin/drush.php scr scripts/aplicar-el-candado-del-portal.php
 *
 * Safe to run more than once. Does not delete anything. Writes the Customer
 * role, takes the admin theme off Authenticated, gives staff the start-page
 * permission, and points /start at that permission.
 */

use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;

$customer = Role::load('tec_customer');
if (!$customer) {
  print "No esta el rol tec_customer.\n";
  exit(1);
}

foreach ($customer->getPermissions() as $p) {
  $customer->revokePermission($p);
}
foreach ([
  'view any tec_crm entities of bundle tec_contact_organization',
  'view any tec_crm entities of bundle tec_contact_person',
  'view any tec_line_item entities of bundle tec_sales_order_line_item',
  'view any tec_order entities of bundle tec_sales_order',
  'view any tec_product entities of bundle tec_color_variation',
  'view any tec_product entities of bundle tec_product',
  'view any tec_product entities of bundle tec_size_variation',
] as $p) {
  $customer->grantPermission($p);
}
$customer->save();
print 'Customer: ' . count($customer->getPermissions()) . " permisos\n";

$auth = Role::load('authenticated');
if ($auth->hasPermission('view the administration theme')) {
  $auth->revokePermission('view the administration theme');
  $auth->save();
  print "Authenticated: quitado el tema de administracion\n";
}
else {
  print "Authenticated: ya no tenia el tema de administracion\n";
}

foreach (['tec_supervisor', 'tec_manager', 'tec_executive'] as $rid) {
  $role = Role::load($rid);
  if (!$role->hasPermission('access tec start page')) {
    $role->grantPermission('access tec start page');
    $role->save();
    print "$rid: portada concedida\n";
  }
  else {
    print "$rid: ya tenia la portada\n";
  }
}

$view = View::load('tec_icons_launchpad');
$displays = $view->get('display');
$displays['default']['display_options']['access'] = [
  'type' => 'perm',
  'options' => ['perm' => 'access tec start page'],
];
$view->set('display', $displays);
$view->save();
print 'Launchpad: ' . $displays['default']['display_options']['access']['options']['perm'] . "\n";

$view_display = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load('user.user.default');
if ($view_display && !$view_display->getComponent('field_tec_contact_person')) {
  $view_display->setComponent('field_tec_contact_person', [
    'type' => 'entity_reference_label',
    'label' => 'above',
    'weight' => -8,
    'settings' => ['link' => TRUE],
  ]);
  $view_display->save();
  print "Ficha de usuario: Contact person visible para la fabrica\n";
}

print "Listo.\n";
