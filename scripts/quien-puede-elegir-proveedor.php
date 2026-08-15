<?php

/**
 * @file
 * Prueba con que usuario se puede rellenar el campo de proveedor de un material.
 *
 * Uso: php vendor/bin/drush.php scr scripts/quien-puede-elegir-proveedor.php
 *
 * El campo field_tec_vendor resuelve por la vista tec_crm_references, y el
 * nucleo, antes de mirar los datos, comprueba el acceso a esa vista contra el
 * usuario que este activo (ViewsSelection::initializeView). Si el acceso falla,
 * la lista sale vacia y la restriccion del campo rechaza cualquier proveedor.
 *
 * Eso importa para el importador: una importacion lanzada por cron o por drush
 * no corre como el usuario 1, corre como el anonimo. Si el anonimo no tiene
 * acceso a esa vista, la importacion no puede rellenar un campo obligatorio y
 * todas las filas fallan la validacion.
 *
 * Aqui se prueba el mismo material con cada usuario para saber si eso pasa de
 * verdad y con quien. No toca nada.
 */

use Drupal\user\Entity\User;

$gestor = \Drupal::entityTypeManager();
$definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'tec_inventory');
$campo = $definiciones['field_tec_vendor'];
$ajustes = $campo->getSettings();
$nombreVista = $ajustes['handler_settings']['view']['view_name'];
$nombrePantalla = $ajustes['handler_settings']['view']['display_name'];

// La organizacion que esta marcada como proveedor.
$proveedores = $gestor->getStorage('tec_crm')->loadByProperties(['type' => 'tec_contact_organization']);
$proveedor = NULL;
foreach ($proveedores as $ficha) {
  foreach ($ficha->get('field_tec_contact_type') as $elemento) {
    if ($elemento->entity && $elemento->entity->label() === 'Supplier') {
      $proveedor = $ficha;
      break 2;
    }
  }
}

print "\n";
printf("  proveedor de prueba: %s (id %s)\n", $proveedor ? $proveedor->label() : 'NINGUNO', $proveedor ? $proveedor->id() : '-');
printf("  vista del campo:     %s / %s\n\n", $nombreVista, $nombrePantalla);

if (!$proveedor) {
  print "  sin proveedor no se puede probar\n\n";
  return;
}

// Los ajustes de acceso de esa pantalla, que son la causa.
$vista = $gestor->getStorage('view')->load($nombreVista);
$pantallas = $vista->get('display');
$acceso = $pantallas[$nombrePantalla]['display_options']['access']
  ?? $pantallas['default']['display_options']['access']
  ?? [];
printf("  acceso de la pantalla: tipo %s, opciones %s\n\n",
  $acceso['type'] ?? '(sin definir)',
  json_encode($acceso['options'] ?? []));

// ---------------------------------------------------------------------------
printf("  %-6s %-14s %-30s %-12s %s\n", 'uid', 'nombre', 'roles', 've la vista', 'acepta el proveedor');
print '  ' . str_repeat('-', 96) . "\n";

$original = \Drupal::currentUser()->getAccount();

foreach (array_keys($gestor->getStorage('user')->loadMultiple()) as $uid) {
  $cuenta = User::load($uid);
  \Drupal::currentUser()->setAccount($cuenta);

  // Hay que tirar la cache del manejador de seleccion, que se guarda por campo
  // y no sabe que ha cambiado el usuario.
  \Drupal::service('plugin.manager.entity_reference_selection')->clearCachedDefinitions();

  $vistaEjecutable = \Drupal\views\Views::getView($nombreVista);
  $veLaVista = $vistaEjecutable && $vistaEjecutable->access($nombrePantalla);

  $material = $gestor->getStorage('taxonomy_term')->create([
    'vid' => 'tec_inventory',
    'name' => 'PRUEBA VENDOR ' . $uid . ' ' . date('His'),
    'field_tec_vendor' => $proveedor->id(),
  ]);

  $errores = [];
  foreach ($material->get('field_tec_vendor')->validate() as $violacion) {
    $errores[] = strip_tags((string) $violacion->getMessage());
  }

  printf("  %-6s %-14s %-30s %-12s %s\n",
    $uid,
    $uid ? $cuenta->getAccountName() : '(anonimo)',
    implode(',', $cuenta->getRoles()),
    $veLaVista ? 'si' : 'NO',
    $errores ? 'RECHAZA' : 'acepta');
}

\Drupal::currentUser()->setAccount($original);

print "\n";
printf("  el usuario con el que corre drush ahora mismo: uid %s\n", $original->id());
print "\n";
