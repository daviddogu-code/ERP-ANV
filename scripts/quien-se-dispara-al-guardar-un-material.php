<?php

/**
 * @file
 * Que automatismos se disparan al guardar un material.
 *
 * Sirve para decidir donde poner el calculo de los dos costes derivados: si ya
 * hay un proceso de ECA que escucha el guardado del material, el calculo entra
 * ahi; si no hay ninguno, montar el primero solo para dos divisiones es mas
 * maquinaria que trabajo.
 *
 * Uso: drush php:script scripts/quien-se-dispara-al-guardar-un-material
 */

$almacen = \Drupal::entityTypeManager()->getStorage('eca');

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Procesos de ECA que escuchan el guardado de un material\n";
echo str_repeat('=', 78) . "\n\n";

$encontrados = 0;
$sobreTerminos = 0;

foreach ($almacen->loadMultiple() as $proceso) {
  foreach ($proceso->get('events') ?? [] as $evento) {
    $plugin = (string) ($evento['plugin'] ?? '');
    $config = $evento['configuration'] ?? [];
    $tipo = (string) ($config['type'] ?? '');

    // Los eventos de entidad llevan el tipo como "entidad grupo".
    if (!str_starts_with($plugin, 'content_entity:')) {
      continue;
    }
    if (!str_starts_with($tipo, 'taxonomy_term')) {
      continue;
    }
    $sobreTerminos++;
    $esMaterial = str_contains($tipo, 'tec_inventory') || trim($tipo) === 'taxonomy_term';
    if (!$esMaterial) {
      continue;
    }
    $encontrados++;
    printf(
      "  %-22s %-8s %s\n      evento %s, sobre %s\n",
      $proceso->id(),
      $proceso->status() ? 'encendido' : 'apagado',
      $proceso->label(),
      substr($plugin, strlen('content_entity:')),
      $tipo === 'taxonomy_term' ? 'cualquier vocabulario' : $tipo
    );
  }
}

if ($encontrados === 0) {
  echo "  Ninguno. Sobre terminos de taxonomia en general hay $sobreTerminos evento(s),\n";
  echo "  pero ninguno alcanza al material.\n";
}

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Modulos propios donde podria vivir el calculo\n";
echo str_repeat('=', 78) . "\n\n";

$manejador = \Drupal::moduleHandler();
foreach ($manejador->getModuleList() as $nombre => $modulo) {
  if (!str_contains($modulo->getPath(), 'modules/custom')) {
    continue;
  }
  $fichero = $modulo->getPath() . '/' . $nombre . '.module';
  $tiene = file_exists($fichero);
  $ganchos = [];
  if ($tiene) {
    $texto = (string) file_get_contents($fichero);
    foreach (['presave', 'insert', 'update', 'form_alter'] as $gancho) {
      if (preg_match('/function ' . preg_quote($nombre, '/') . '_[a-z_]*' . $gancho . '/', $texto)) {
        $ganchos[] = $gancho;
      }
    }
  }
  printf("  %-24s %s\n", $nombre, $ganchos ? 'ya engancha: ' . implode(', ', $ganchos) : ($tiene ? 'tiene .module, sin ganchos de entidad' : 'sin .module'));
}

echo "\n";
