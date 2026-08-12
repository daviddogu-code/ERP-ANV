<?php

/**
 * @file
 * Ejecuta las actualizaciones de base de datos pendientes. Se lanza con:
 *   drush scr scripts/actualizar-bd.php
 *
 * Hace lo mismo que `drush updatedb`, que en este equipo no funciona: Drush se
 * relanza a si mismo en un subproceso con la ruta en barras normales y cmd.exe
 * no la reconoce. Esto llama a la misma interfaz de Drupal sin subprocesos.
 *
 * Imprime lo que va haciendo y para en cuanto algo falle.
 */

require_once DRUPAL_ROOT . '/core/includes/install.inc';
require_once DRUPAL_ROOT . '/core/includes/update.inc';

drupal_load_updates();

echo "\n=== ACTUALIZACIONES DE ESQUEMA ===\n";

$pendientes = update_get_update_list();
if (!$pendientes) {
  echo "  no hay ninguna pendiente\n";
}

$fallos = 0;
foreach ($pendientes as $modulo => $datos) {
  if (empty($datos['pending'])) {
    continue;
  }
  foreach ($datos['pending'] as $numero => $descripcion) {
    printf("  %-20s %-8s ", $modulo, $numero);
    $contexto = ['sandbox' => []];
    try {
      // Hay que inicializar 'finished' a mano. update_do_one() solo la rellena
      // cuando la actualizacion trabaja por lotes, y sin ella la comprobacion
      // de update.inc:213 falla y la version del esquema no se llega a
      // guardar: la actualizacion se ejecuta pero queda como pendiente.
      $vueltas = 0;
      do {
        $contexto['finished'] = 1;
        update_do_one($modulo, $numero, [], $contexto);
        $vueltas++;
      } while ($contexto['finished'] < 1 && $vueltas < 1000);
      echo "hecho\n";
    }
    catch (\Throwable $e) {
      echo "FALLA: " . $e->getMessage() . "\n";
      $fallos++;
    }
  }
}

echo "\n=== ACTUALIZACIONES POSTERIORES ===\n";

$registro = \Drupal::service('update.post_update_registry');
$posteriores = $registro->getPendingUpdateFunctions();
if (!$posteriores) {
  echo "  no hay ninguna pendiente\n";
}

foreach ($posteriores as $funcion) {
  printf("  %-60s ", $funcion);
  $contexto = ['sandbox' => [], 'results' => []];
  try {
    $vueltas = 0;
    do {
      $contexto['finished'] = 1;
      update_invoke_post_update($funcion, $contexto);
      $vueltas++;
    } while ($contexto['finished'] < 1 && $vueltas < 1000);
    echo "hecho\n";
  }
  catch (\Throwable $e) {
    echo "FALLA: " . $e->getMessage() . "\n";
    $fallos++;
  }
}

echo "\n";
echo $fallos === 0
  ? "Todas las actualizaciones han pasado.\n\n"
  : "HAY $fallos FALLOS. No seguir hasta entenderlos.\n\n";
