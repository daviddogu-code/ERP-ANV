<?php

/**
 * @file
 * Prueba que el guardian de definiciones huerfanas caza de verdad.
 *
 * Uso: php vendor/bin/drush.php scr scripts/probar-el-guardian-de-las-definiciones-huerfanas.php -- --poner
 *      php vendor/bin/drush.php scr scripts/comprobacion.php        (tiene que salir en ROJO)
 *      php vendor/bin/drush.php scr scripts/probar-el-guardian-de-las-definiciones-huerfanas.php -- --quitar
 *      php vendor/bin/drush.php scr scripts/comprobacion.php        (tiene que volver a VERDE)
 *
 * Por que existe. Un guardian que siempre esta en verde puede estarlo por dos
 * motivos muy distintos: porque no hay nada que cazar, o porque no sabe cazar.
 * Desde fuera se ven igual, y el segundo es el peligroso, porque da tranquilidad
 * sin darla. El guardian de definiciones huerfanas nacio el 15 de agosto de 2026
 * justo de ese fallo -el de tec_gui aprobaba con un fantasma dentro-, asi que
 * antes de fiarse de el conviene ponerle uno delante y ver si lo dice.
 *
 * Lo que hace: mete en `entity.definitions.installed` una definicion instalada
 * de mentira, de un tipo de entidad que no existe ni ha existido nunca, y la
 * quita cuando se le pide. Es la misma especie de resto que los tres que se
 * retiraron ese dia.
 *
 * Es inofensivo mientras esta puesto: nada del nucleo lee esa coleccion salvo
 * getEntityTypes() del gestor de definiciones, y getChangeList() ni lo mira,
 * que es precisamente el motivo de que estos restos pasen desapercibidos. Aun
 * asi, no se deja puesto: se pone, se mira y se quita.
 */

$argumentos = (array) ($extra ?? []);
$poner = in_array('poner', $argumentos, TRUE) || in_array('--poner', $argumentos, TRUE);
$quitar = in_array('quitar', $argumentos, TRUE) || in_array('--quitar', $argumentos, TRUE);

// El nombre lleva "de mentira" dentro a proposito: si alguien se lo encuentra
// suelto dentro de un mes, que sepa de que se rie sin tener que investigarlo.
// Corto porque el nucleo no admite identificadores de mas de 32 caracteres, y
// lo comprueba tambien al fabricar el objeto, no solo al guardarlo.
const TIPO_DE_MENTIRA = 'tec_tipo_de_mentira';

$almacenClaves = \Drupal::keyValue('entity.definitions.installed');
$gestorTipos = \Drupal::entityTypeManager();

print "\n";

if (!$poner && !$quitar) {
  print "  No se ha dicho que hacer. Anade -- --poner o -- --quitar.\n\n";
  return;
}

if ($poner) {
  // Se fabrica un tipo de configuracion cualquiera. Da igual lo que diga por
  // dentro: lo que se esta probando es que el guardian se fije en que el sitio
  // no conoce ese identificador, no en lo que el objeto lleve escrito.
  $falsa = new \Drupal\Core\Config\Entity\ConfigEntityType([
    'id' => TIPO_DE_MENTIRA,
    'label' => 'Tipo de mentira para probar el guardian',
    'provider' => 'eck',
    'config_prefix' => 'eck_type.' . TIPO_DE_MENTIRA,
    'entity_keys' => ['id' => 'type', 'label' => 'name'],
  ]);
  $almacenClaves->set(TIPO_DE_MENTIRA . '.entity_type', $falsa);
  printf("  Puesta la definicion instalada de mentira: %s.entity_type\n", TIPO_DE_MENTIRA);
  print "  Ahora ejecuta comprobacion.php: el guardian tiene que salir en ROJO.\n";
  print "  Y cuando lo hayas visto, vuelve aqui con -- --quitar.\n";
}

if ($quitar) {
  if ($almacenClaves->get(TIPO_DE_MENTIRA . '.entity_type') === NULL) {
    print "  No estaba puesta. Nada que quitar.\n";
  }
  else {
    // Por la API del nucleo, igual que en la retirada de verdad: asi se va
    // tambien la entrada de los campos y las dos cacheadas.
    \Drupal::service('entity.last_installed_schema.repository')->deleteLastInstalledDefinition(TIPO_DE_MENTIRA);
    printf("  Quitada la definicion instalada de mentira: %s\n", TIPO_DE_MENTIRA);
  }
}

// Y el repaso, se haya hecho lo uno o lo otro: como queda el almacen ahora.
$instalados = [];
foreach (array_keys($almacenClaves->getAll()) as $clave) {
  if (str_ends_with($clave, '.entity_type')) {
    $instalados[] = substr($clave, 0, -strlen('.entity_type'));
  }
}
$huerfanos = array_values(array_diff($instalados, array_keys($gestorTipos->getDefinitions())));

printf("\n  Definiciones instaladas huerfanas ahora mismo: %d%s\n",
  count($huerfanos), $huerfanos ? ' -> ' . implode(', ', $huerfanos) : '');
print "\n";
