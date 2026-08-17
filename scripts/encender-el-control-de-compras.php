<?php

/**
 * @file
 * Deja lista la pantalla de control de compras: quien puede entrar.
 *
 *   php vendor\bin\drush.php scr scripts/encender-el-control-de-compras.php
 *   php vendor\bin\drush.php scr scripts/encender-el-control-de-compras.php -- --aplicar
 *
 * Sin --aplicar solo cuenta lo que haria.
 *
 * El permiso, para quien lleva las compras. Manager y Executive, que es lo que
 * pidio el dueno. No se le da al supervisor de produccion: mira el stock y la
 * cola de fabricacion, no persigue proveedores.
 *
 * Este guion tuvo una segunda parte que sembraba "On process" en los pedidos
 * que ya existian, porque el campo nacio con ese valor por defecto. Duro un
 * dia: el estado de partida es Open, que es la ausencia de respuesta, y
 * sembrar el primer escalon hacia que once pedidos afirmasen que alguien los
 * estaba gestionando. Lo deshizo el-estado-empieza-en-open.php, y la siembra se
 * quito de aqui para que volver a lanzar esto no la repusiera.
 *
 * Se puede lanzar dos veces.
 */

use Drupal\user\Entity\Role;

$aplicar = in_array('--aplicar', $extra ?? [], TRUE) || in_array('--aplicar', $_SERVER['argv'] ?? [], TRUE);

const PERMISO = 'access tec purchase queue';
const ROLES = ['tec_manager', 'tec_executive'];

print "\nQuien puede entrar\n";
print "------------------\n";

foreach (ROLES as $rid) {
  $role = Role::load($rid);
  if (!$role) {
    print "[MAL ] no existe el rol $rid\n";
    continue;
  }
  if ($role->hasPermission(PERMISO)) {
    print "[ya  ] $rid ya podia entrar\n";
    continue;
  }
  if ($aplicar) {
    $role->grantPermission(PERMISO)->save();
    print "[hecho] $rid puede entrar\n";
  }
  else {
    print "[haria] dar el permiso a $rid\n";
  }
}

if (!$aplicar) {
  print "\nEsto ha sido en seco. Con --aplicar se escribe.\n";
}
