<?php

/**
 * @file
 * Dice que roles pueden ver y editar los campos restringidos.
 *
 *   php vendor/bin/drush scr scripts/quien-ve-el-coste.php
 *
 * De los noventa y siete campos que pasan por field_permissions, noventa y seis
 * son publicos y uno no: `field_tec_cost`, el precio de coste de los materiales.
 * Todo el secreto comercial del ERP cuelga de ese unico ajuste.
 *
 * Por eso hay que mirarlo a mano cada vez que se toca field_permissions. Si una
 * actualizacion pierde la restriccion, el campo pasa a publico y nadie se entera:
 * no da error, no rompe ninguna pagina, y la comprobacion funcional lo da por
 * bueno porque los datos siguen ahi. Simplemente los ve quien no debe.
 *
 * Solo lee.
 */

$factory = \Drupal::configFactory();
$restringidos = [];

foreach ($factory->listAll('field.storage.') as $nombre) {
  $tipo = $factory->get($nombre)->get('third_party_settings.field_permissions.permission_type');
  if ($tipo && $tipo !== 'public') {
    $restringidos[$nombre] = $tipo;
  }
}

echo "\n=== campos que no son publicos ===\n\n";
if (!$restringidos) {
  echo "  NINGUNO. Si antes habia alguno, la restriccion se ha perdido.\n\n";
  return;
}

$roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();

foreach ($restringidos as $nombre => $tipo) {
  $campo = str_replace('field.storage.', '', $nombre);
  printf("  %-52s %s\n", $campo, $tipo);

  [, $maquina] = explode('.', $campo, 2);
  foreach (['view' => 'ver', 'view own' => 'ver los suyos', 'create' => 'crear', 'edit' => 'editar', 'edit own' => 'editar los suyos'] as $accion => $etiqueta) {
    $permiso = "$accion $maquina";
    $quienes = [];
    foreach ($roles as $rol) {
      if ($rol->id() === 'administrator' && $rol->isAdmin()) {
        continue;
      }
      if ($rol->hasPermission($permiso)) {
        $quienes[] = $rol->id();
      }
    }
    printf("      %-18s %s\n", $etiqueta . ':', $quienes ? implode(', ', $quienes) : 'nadie (salvo el administrador)');
  }
  echo "\n";
}
