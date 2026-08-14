<?php

/**
 * Desarma lo que el cron haria sin que nadie se lo haya pedido.
 *
 * El cron de este sitio no ha corrido nunca, y hay trece trabajos configurados
 * esperando. El dia que se encienda -y esta en la lista de pendientes- empiezan
 * todos a la vez. Uno de ellos volcaba la base de datos entera a la carpeta
 * privada del proyecto cada noche: es lo que dejo 178 volcados y 111 MB dentro
 * del arbol de ficheros, que hubo que sacar a mano el 12 de agosto.
 *
 * Esto apaga lo que sobra y hace explicito lo que estaba al azar. Los diez
 * trabajos que se quedan encendidos hacen falta y estan revisados uno por uno
 * en el backlog.
 *
 * Uso: php vendor/bin/drush.php scr scripts/desarmar-el-cron.php
 */

// Trabajos de ultimate_cron que se apagan, y por que.
const TRABAJOS_FUERA = [
  // El de las copias: su sitio es scripts/copia-de-seguridad.ps1, que hace mas
  // (codigo, ficheros, historial de Git y verificacion) y escribe fuera del
  // proyecto.
  'backup_migrate_cron' => 'volcaba la base a la carpeta privada cada noche a las 3',
  // Se despierta 96 veces al dia para nada: ninguno de los 36 procesos de ECA
  // escucha el evento de cron. Se vuelve a encender el dia que haga falta un
  // automatismo periodico, por ejemplo el aviso de stock bajo.
  'eca_base_cron' => 'ningun proceso de ECA reacciona al cron',
];

// Filas de registro que se guardan. Sin esto, Drupal usa lo que encuentre, y lo
// de fabrica son mil, que en un ERP que arranca es media manana. El registro de
// las primeras semanas de uso real es donde se buscan los fallos de verdad.
const FILAS_DE_REGISTRO = 100000;

$etm = \Drupal::entityTypeManager();

print "\n";

// -----------------------------------------------------------------------------
// 1. El horario de las copias.
// -----------------------------------------------------------------------------
print "  --- 1. el horario de backup_migrate ---\n\n";

$horarios = $etm->getStorage('backup_migrate_schedule')->loadMultiple();

foreach ($horarios as $horario) {
  // La bandera que manda es `enabled`, no el `status` de la ficha de
  // configuracion: Schedule::run() lee esa y solo esa (Schedule.php:93).
  $estaba = $horario->get('enabled') ? 'encendido' : 'apagado';

  printf("      %-16s %s, cada %d s, guarda %d, a %s\n",
    $horario->id(),
    $estaba,
    $horario->get('period'),
    $horario->get('keep'),
    $horario->get('destination_id'));

  if ($horario->get('enabled')) {
    $horario->set('enabled', FALSE)->save();
    print "      " . str_pad('', 16) . " -> apagado\n";
  }
}

print "\n";

// -----------------------------------------------------------------------------
// 2. Los dos trabajos que sobran.
// -----------------------------------------------------------------------------
print "  --- 2. los trabajos que se apagan ---\n\n";

$almacenTrabajos = $etm->getStorage('ultimate_cron_job');

foreach (TRABAJOS_FUERA as $id => $motivo) {
  $trabajo = $almacenTrabajos->load($id);

  if (!$trabajo) {
    printf("      %-22s no existe\n", $id);
    continue;
  }

  printf("      %-22s %s\n", $id, $motivo);

  if ($trabajo->status()) {
    $trabajo->disable()->save();
    print "      " . str_pad('', 22) . " -> apagado\n";
  }
  else {
    print "      " . str_pad('', 22) . " ya estaba apagado\n";
  }
}

print "\n";

// -----------------------------------------------------------------------------
// 3. El limite del registro, explicito.
// -----------------------------------------------------------------------------
print "  --- 3. cuantas filas de registro se guardan ---\n\n";

$ajustes = \Drupal::configFactory()->getEditable('dblog.settings');
$antes = $ajustes->get('row_limit');

printf("      antes: %s\n", $antes === NULL ? 'sin definir (dblog.settings no existia)' : $antes);

$ajustes->set('row_limit', FILAS_DE_REGISTRO)->save();

printf("      ahora: %d\n\n", FILAS_DE_REGISTRO);

// -----------------------------------------------------------------------------
// 4. Que tiene pendiente el purgador de campos borrados.
// -----------------------------------------------------------------------------
// No se toca: solo se mira. `field_cron` es el unico de los trece que borra
// datos, y como el cron no ha corrido nunca, lo que haya lleva ahi desde
// siempre. Conviene saber que hay antes de encenderlo, no despues.
print "  --- 4. campos borrados esperando purga (solo consulta) ---\n\n";

$repositorio = \Drupal::service('entity_field.deleted_fields_repository');
$almacenes = $repositorio->getFieldStorageDefinitions();
$definiciones = $repositorio->getFieldDefinitions();

printf("      %d almacenes y %d campos marcados para purgar\n\n",
  count($almacenes),
  count($definiciones));

foreach ($almacenes as $definicion) {
  printf("          %-22s %s\n",
    $definicion->getTargetEntityTypeId(),
    $definicion->getName());
}

foreach ($definiciones as $definicion) {
  printf("          %-22s %s en %s\n",
    $definicion->getTargetEntityTypeId(),
    $definicion->getName(),
    $definicion->getTargetBundle());
}

print "\n";

// -----------------------------------------------------------------------------
// 5. Como queda la lista.
// -----------------------------------------------------------------------------
print "  --- 5. los trece trabajos, como quedan ---\n\n";

$trabajos = $almacenTrabajos->loadMultiple();
ksort($trabajos);

$encendidos = 0;
foreach ($trabajos as $trabajo) {
  $reglas = $trabajo->get('scheduler')['configuration']['rules'] ?? [];
  $cuando = $reglas ? implode(' ', $reglas) : '(por defecto)';

  if ($trabajo->status()) {
    $encendidos++;
  }

  printf("      [%s] %-22s %-18s %s\n",
    $trabajo->status() ? 'ON ' : 'off',
    $trabajo->id(),
    $cuando,
    $trabajo->label());
}

printf("\n      %d de %d encendidos\n\n", $encendidos, count($trabajos));
