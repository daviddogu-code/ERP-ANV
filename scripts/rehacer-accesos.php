<?php

/**
 * @file
 * Rehace la tabla de permisos de nodos. Se ejecuta con:
 *   drush scr scripts/rehacer-accesos.php
 *
 * Drupal levanta una bandera cuando algo ha cambiado en como se decide quien
 * ve cada nodo, y hasta que no se rehace la tabla el aviso sigue en rojo en
 * el informe de estado. Aqui se rehace y se baja la bandera.
 *
 * En este ERP casi todo son entidades de ECK, no nodos, asi que la operacion
 * es corta. Se cuenta cuantos nodos hay antes, para saber a que atenerse.
 */

if (!\Drupal::moduleHandler()->moduleExists('node')) {
  print "\n  El modulo node no esta encendido, no hay nada que rehacer.\n\n";
  return;
}

$nodos = \Drupal::entityQuery('node')->accessCheck(FALSE)->count()->execute();
printf("\n  %d nodos en el sitio\n", $nodos);

if (!node_access_needs_rebuild()) {
  print "  La bandera no estaba levantada. No hay nada que hacer.\n\n";
  return;
}

print "  Rehaciendo...\n";
node_access_rebuild();

printf(
  "  Hecho. La bandera %s\n\n",
  node_access_needs_rebuild() ? 'SIGUE levantada, hay que mirarlo' : 'ya esta bajada'
);
