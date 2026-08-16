<?php

/**
 * @file
 * Deja lista la pantalla de control de compras: permiso y estado de partida.
 *
 *   php vendor\bin\drush.php scr scripts/encender-el-control-de-compras.php
 *   php vendor\bin\drush.php scr scripts/encender-el-control-de-compras.php -- --aplicar
 *
 * Sin --aplicar solo cuenta lo que haria.
 *
 * Dos cosas, las dos de una sola vez en el despliegue:
 *
 *   1. El permiso, para quien lleva las compras. Manager y Executive, que es lo
 *      que pidio el dueno. No se le da al supervisor de produccion: mira el
 *      stock y la cola de fabricacion, no persigue proveedores.
 *
 *   2. El estado de partida de los pedidos que ya existen. El valor por defecto
 *      solo se aplica a los pedidos nuevos, asi que sin esto los veintitantos
 *      pedidos de hoy abririan la pantalla con el desplegable en blanco. Se
 *      siembra "On process" y nada mas: decir de un pedido viejo que esta "de
 *      camino" seria inventarse una llamada de telefono que nadie hizo.
 *
 * Los pedidos ya entregados no se tocan porque no hay nada que tocar: que la
 * mercancia llego se lee de los movimientos de almacen, no de este campo.
 *
 * Se puede lanzar dos veces.
 */

use Drupal\tec_production\Purchasing;
use Drupal\user\Entity\Role;

$aplicar = in_array('--aplicar', $extra ?? [], TRUE) || in_array('--aplicar', $_SERVER['argv'] ?? [], TRUE);

const PERMISO = 'access tec purchase queue';
const ROLES = ['tec_manager', 'tec_executive'];
const PRIMER_ESTADO = 'on_process';

print "\n1. Quien puede entrar\n";
print "---------------------\n";

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

print "\n2. El estado de partida de los pedidos que ya existen\n";
print "----------------------------------------------------\n";

$storage = \Drupal::entityTypeManager()->getStorage('tec_order');
$ids = $storage->getQuery()
  ->condition('type', 'tec_purchase_order')
  ->accessCheck(FALSE)
  ->execute();

$sembrados = 0;
$tenian = 0;
$fuera = 0;

foreach ($storage->loadMultiple($ids) as $order) {
  $estado = Purchasing::receiptState($order);
  // Ni se persigue lo que nunca salio ni lo que ya esta en la estanteria.
  if ($estado === 'cancelled' || $estado === 'full') {
    $fuera++;
    continue;
  }
  if (!$order->get('field_tec_po_queue_status')->isEmpty()) {
    $tenian++;
    continue;
  }
  if ($aplicar) {
    $order->set('field_tec_po_queue_status', PRIMER_ESTADO)->save();
  }
  $sembrados++;
  print sprintf("%s %s\n", $aplicar ? '[hecho]' : '[haria]', $order->label());
}

print "\nSembrados: $sembrados. Ya tenian: $tenian. No van a la pantalla: $fuera.\n";
if (!$aplicar) {
  print "\nEsto ha sido en seco. Con --aplicar se escribe.\n";
}
