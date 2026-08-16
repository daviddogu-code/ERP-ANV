<?php

/**
 * @file
 * Which decimal boxes refuse honest numbers, and which records they lock.
 *
 * Drupal validates every decimal box against a step it derives from the
 * column's scale, and `Number::validStep()` gets that wrong for big numbers:
 * the floating-point remainder it measures grows with the size of the value
 * while the margin it allows stays fixed at `step / 2^24`. Past a certain
 * figure nothing passes, however round.
 *
 * That ceiling drops by a factor of ten with every decimal added. A column with
 * two decimals refuses nothing anyone would type; one with six refuses
 * everything over a thousand. When a column can hold more than its own form
 * will accept, the field is a trap, and the record does not have to be touched
 * to fall in: the value is already stored and the form revalidates it on every
 * save.
 *
 * Read-only. Prints the traps, then the records already caught.
 */

use Drupal\Component\Utility\Number;

/**
 * The first round number the step check refuses.
 */
function techo_del_paso(int $escala): ?float {
  $paso = pow(0.1, $escala);
  for ($exponente = 0; $exponente <= 15; $exponente++) {
    if (!Number::validStep((string) pow(10, $exponente), $paso)) {
      return pow(10, $exponente);
    }
  }
  return NULL;
}

// Every decimal column in the site, with what it can hold and what its form
// will accept.
$trampas = [];
$todos = [];
foreach (\Drupal::configFactory()->listAll('field.storage.') as $nombre) {
  $almacen = \Drupal::config($nombre);
  if ($almacen->get('type') !== 'decimal') {
    continue;
  }
  $escala = (int) $almacen->get('settings.scale');
  $precision = (int) $almacen->get('settings.precision');
  $techo = techo_del_paso($escala);
  // The largest the column itself can hold.
  $capacidad = pow(10, $precision - $escala);
  $ficha = [
    'campo' => $almacen->get('field_name'),
    'entidad' => $almacen->get('entity_type'),
    'escala' => $escala,
    'techo' => $techo,
    'capacidad' => $capacidad,
  ];
  $ficha['trampa'] = $techo !== NULL && $techo < $capacidad;
  $todos[] = $ficha;
  if ($ficha['trampa']) {
    $trampas[] = $ficha;
  }
}

usort($todos, fn($a, $b) => [$b['escala'], $a['campo']] <=> [$a['escala'], $b['campo']]);

print "Todas las columnas decimales\n";
print str_repeat('-', 78) . "\n";
printf("  %-34s %-9s %-14s %s\n", 'campo', 'decimales', 'cabe hasta', 'el formulario acepta hasta');
foreach ($todos as $ficha) {
  printf(
    "  %-34s %-9d %-14s %s\n",
    $ficha['campo'],
    $ficha['escala'],
    number_format($ficha['capacidad']),
    ($ficha['techo'] === NULL ? 'todo' : number_format($ficha['techo']))
      . ($ficha['trampa'] ? '  <-- trampa' : '')
  );
}
print "\n";
print "  Con dos decimales el techo son diez millones, asi que la trampa existe\n";
print "  pero nadie la pisa. Se estrecha diez veces por cada decimal que se anade.\n\n";

// Now the records. Only the entity types that actually carry a trap.
$porEntidad = [];
foreach ($trampas as $ficha) {
  $porEntidad[$ficha['entidad']][] = $ficha;
}

print "Fichas que hoy no se pueden guardar\n";
print str_repeat('-', 78) . "\n";

$gestor = \Drupal::entityTypeManager();
$totalBloqueadas = 0;
$totalMiradas = 0;

foreach ($porEntidad as $tipoEntidad => $fichas) {
  if (!$gestor->hasDefinition($tipoEntidad)) {
    continue;
  }
  $almacenamiento = $gestor->getStorage($tipoEntidad);
  $ids = $almacenamiento->getQuery()->accessCheck(FALSE)->execute();
  foreach ($almacenamiento->loadMultiple($ids) as $entidad) {
    $totalMiradas++;
    $motivos = [];
    foreach ($fichas as $ficha) {
      $campo = $ficha['campo'];
      if (!$entidad->hasField($campo) || $entidad->get($campo)->isEmpty()) {
        continue;
      }
      $valor = $entidad->get($campo)->first()->value;
      if (!is_numeric($valor) || Number::validStep($valor, pow(0.1, $ficha['escala']))) {
        continue;
      }
      $motivos[] = $campo . ' = ' . rtrim(rtrim((string) $valor, '0'), '.');
    }
    if ($motivos) {
      $totalBloqueadas++;
      printf(
        "  %-14s %-6s %-30s %s\n",
        $tipoEntidad,
        $entidad->id(),
        mb_strimwidth((string) $entidad->label(), 0, 30, '...'),
        implode(' | ', $motivos)
      );
    }
  }
}

if ($totalBloqueadas === 0) {
  print "  ninguna\n";
}
print "\n";
printf("  %d de %d fichas bloqueadas, en %d columnas trampa\n", $totalBloqueadas, $totalMiradas, count($trampas));
print "\n";
print "  Aviso: esto mide la comprobacion de Drupal en crudo. Los campos que ya\n";
print "  llevan el rodeo ('any' como paso) se guardan bien aunque salgan aqui.\n";
