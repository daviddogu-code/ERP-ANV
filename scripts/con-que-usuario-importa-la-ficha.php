<?php

/**
 * @file
 * Dice con que usuario corre cada importacion y si ese usuario puede poner el
 * proveedor de un material.
 *
 * Uso: php vendor/bin/drush.php scr scripts/con-que-usuario-importa-la-ficha.php
 *
 * Esto explica un fallo que no se ve venir. El campo del proveedor
 * (field_tec_vendor) no acepta cualquier organizacion del CRM: las filtra por una
 * vista, tec_crm_references, y esa vista tiene el acceso restringido por rol. Si
 * el usuario que corre la importacion no tiene ninguno de esos roles, la vista no
 * devuelve nada y TODAS las filas se rechazan con "This entity cannot be
 * referenced". Con 857 materiales eso son 857 fallos y ni un material creado.
 *
 * Y el usuario que corre la importacion NO es quien la lanza. Feeds se cambia al
 * autor de la ficha de importacion antes de empezar:
 * FeedsExecutable::switchAccount() hace switchTo($feed->getOwnerId()). O sea que
 * da igual lanzarla con --user=1 o pulsando el boton siendo jefe: manda el autor
 * guardado en la ficha.
 *
 * No toca nada.
 */

use Drupal\Core\Session\AccountProxy;
use Drupal\user\Entity\User;

$gestor = \Drupal::entityTypeManager();
$campos = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'tec_inventory');

print "\n";

// ---------------------------------------------------------------------------
// 1. Que roles deja pasar la vista.
// ---------------------------------------------------------------------------
print "  --- 1. la vista que filtra los proveedores ---\n\n";

$ajustes = $campos['field_tec_vendor']->getSettings();
$manejador = $campos['field_tec_vendor']->getSetting('handler');
$ajustesManejador = $ajustes['handler_settings'] ?? [];

printf("      campo:      field_tec_vendor -> %s\n", $ajustes['target_type']);
printf("      manejador:  %s\n", $manejador);

if (empty($ajustesManejador['view']['view_name'])) {
  print "      no filtra por vista, asi que este problema no aplica\n\n";
  return;
}

$nombreVista = $ajustesManejador['view']['view_name'];
$nombrePantalla = $ajustesManejador['view']['display_name'];
printf("      vista:      %s, pantalla %s\n", $nombreVista, $nombrePantalla);

$vista = $gestor->getStorage('view')->load($nombreVista);
$pantallas = $vista->get('display');
$acceso = $pantallas[$nombrePantalla]['display_options']['access']
  ?? $pantallas['default']['display_options']['access']
  ?? ['type' => 'none'];

printf("      acceso:     %s\n", $acceso['type']);
$rolesQueValen = array_values(array_filter($acceso['options']['role'] ?? []));
if ($rolesQueValen) {
  printf("      roles que dejan ver la vista: %s\n", implode(', ', $rolesQueValen));
}

// El usuario 1 NO se salta esto: el plugin de acceso por rol de Views compara
// roles y no hace excepcion con el uno.
print "\n      OJO: el usuario 1 no se salta el acceso por rol de una vista. Si no\n";
print "      tiene uno de esos roles, tampoco ve la vista.\n";

// ---------------------------------------------------------------------------
// 2. Quien es el autor de cada ficha de importacion.
// ---------------------------------------------------------------------------
print "\n  --- 2. el autor de cada ficha, que es quien importa ---\n\n";

$prueba = 'Prueba de proveedor ' . uniqid();

/**
 * Intenta poner el proveedor en un material nuevo haciendose pasar por alguien.
 */
$puedePonerProveedor = static function (?User $usuario) use ($gestor, $prueba): string {
  $proveedor = NULL;
  foreach ($gestor->getStorage('tec_crm')->loadMultiple() as $ficha) {
    if ($ficha->bundle() === 'tec_contact_organization') {
      foreach ($ficha->get('field_tec_contact_type') as $elemento) {
        if ($elemento->entity && $elemento->entity->label() === 'Supplier') {
          $proveedor = $ficha;
          break 2;
        }
      }
    }
  }
  if (!$proveedor) {
    return 'no hay ningun proveedor con el que probar';
  }

  // Se cambia de usuario igual que lo hace Feeds, con el conmutador de cuentas,
  // para que la prueba valga de verdad.
  $cuenta = new AccountProxy(\Drupal::service('event_dispatcher'));
  $cuenta->setInitialAccountId($usuario ? (int) $usuario->id() : 0);
  $conmutador = \Drupal::service('account_switcher')->switchTo($cuenta);

  try {
    $material = $gestor->getStorage('taxonomy_term')->create([
      'vid' => 'tec_inventory',
      'name' => $prueba,
      'field_tec_vendor' => $proveedor->id(),
    ]);
    $rechazado = FALSE;
    foreach ($material->validate() as $violacion) {
      if (str_starts_with($violacion->getPropertyPath(), 'field_tec_vendor')) {
        $rechazado = TRUE;
      }
    }
    return $rechazado ? 'RECHAZA el proveedor' : 'acepta el proveedor';
  }
  finally {
    $conmutador->switchBack();
  }
};

foreach ($gestor->getStorage('feeds_feed')->loadMultiple() as $ficha) {
  $autor = $ficha->getOwner();
  $roles = $autor ? $autor->getRoles() : ['anonymous'];
  $tieneRol = (bool) array_intersect($rolesQueValen, $roles);

  printf("      ficha %d, \"%s\"\n", $ficha->id(), $ficha->label());
  printf("          autor: %s (uid %s)\n",
    $autor && $autor->id() ? $autor->getAccountName() : 'Anonimo',
    $ficha->getOwnerId());
  printf("          roles: %s\n", implode(', ', $roles));
  printf("          ve la vista: %s\n", $tieneRol ? 'si' : 'NO');
  printf("          resultado:   %s\n\n", $puedePonerProveedor($autor && $autor->id() ? $autor : NULL));
}

// ---------------------------------------------------------------------------
// 3. Quien serviria como autor.
// ---------------------------------------------------------------------------
print "  --- 3. usuarios que si podrian importar ---\n\n";

$sirven = [];
foreach (User::loadMultiple() as $usuario) {
  if (!$usuario->id() || !$usuario->isActive()) {
    continue;
  }
  if (array_intersect($rolesQueValen, $usuario->getRoles())) {
    $sirven[] = $usuario;
    printf("      uid %-4s %-18s roles: %s\n", $usuario->id(), $usuario->getAccountName(),
      implode(', ', $usuario->getRoles()));
  }
}

if (!$sirven) {
  print "      ninguno. Habria que dar uno de esos roles a alguien, o quitarle la\n";
  print "      restriccion por rol a la pantalla de la vista.\n";
}

print "\n";
