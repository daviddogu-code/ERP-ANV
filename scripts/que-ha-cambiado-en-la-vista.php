<?php

/**
 * @file
 * Compara la configuracion activa de una vista con una copia en YAML y dice
 * exactamente que claves han cambiado.
 *
 *   php vendor/bin/drush scr scripts/que-ha-cambiado-en-la-vista.php -- views.view.tec_order_material_calculation_summary backups/la-copia.yml
 *
 * Guardar una vista con `save()` no escribe solo lo que uno ha tocado: Drupal
 * recalcula las dependencias y los metadatos de cache, y puede reordenar cosas.
 * O sea que "he cambiado una clave" es una afirmacion que hay que comprobar, no
 * suponer, sobre todo en una vista de la que cuelgan las pantallas de pedido de
 * venta y que ya se cayo una vez.
 *
 * Recorre los dos arboles a la vez y saca una linea por hoja distinta, con la
 * ruta de claves completa. Tambien avisa de las claves que aparecen y de las que
 * desaparecen, que son las que rompen las vistas: en este proyecto ya se
 * quedaron entradas `style` sin su clave `type` y la actualizacion de la vista
 * revento con "Undefined array key type".
 *
 * No escribe nada.
 */

$argumentos = $extra ?? [];
if (count($argumentos) < 2) {
  echo "Uso: ... scripts/que-ha-cambiado-en-la-vista.php -- NOMBRE.DE.CONFIG ruta/a/la/copia.yml\n";
  return;
}

[$nombreConfig, $ficheroCopia] = $argumentos;
if (!str_starts_with($ficheroCopia, '/') && !preg_match('/^[A-Za-z]:/', $ficheroCopia)) {
  $ficheroCopia = \Drupal::root() . '/' . $ficheroCopia;
}

if (!is_file($ficheroCopia)) {
  printf("No existe %s\n", $ficheroCopia);
  return;
}

$activa = \Drupal::config($nombreConfig)->getRawData();
if (!$activa) {
  printf("No hay configuracion activa llamada %s\n", $nombreConfig);
  return;
}
$copia = \Symfony\Component\Yaml\Yaml::parseFile($ficheroCopia) ?: [];

echo "\n";
echo str_repeat('=', 92) . "\n";
printf(" %s   contra   %s\n", $nombreConfig, basename($ficheroCopia));
echo str_repeat('=', 92) . "\n\n";

$diferencias = compara($copia, $activa);
if (!$diferencias) {
  echo "  No hay ni una diferencia.\n\n";
  return;
}

foreach ($diferencias as $linea) {
  echo '  ' . $linea . "\n";
}
printf("\n  %d diferencias.\n\n", count($diferencias));

/**
 * Saca una linea por cada hoja que no coincide entre los dos arboles.
 */
function compara($antes, $ahora, string $camino = ''): array {
  if (is_array($antes) && is_array($ahora)) {
    $lineas = [];
    foreach (array_keys($antes + $ahora) as $clave) {
      $donde = $camino === '' ? (string) $clave : $camino . '.' . $clave;
      if (!array_key_exists($clave, $antes)) {
        $lineas[] = sprintf('APARECE  %s = %s', $donde, corta($ahora[$clave]));
        continue;
      }
      if (!array_key_exists($clave, $ahora)) {
        $lineas[] = sprintf('SE VA    %s = %s', $donde, corta($antes[$clave]));
        continue;
      }
      $lineas = array_merge($lineas, compara($antes[$clave], $ahora[$clave], $donde));
    }
    return $lineas;
  }
  if ($antes === $ahora) {
    return [];
  }
  return [sprintf('CAMBIA   %s: %s  ->  %s', $camino, corta($antes), corta($ahora))];
}

/**
 * Un valor en una linea y sin pasarse de largo.
 */
function corta($valor): string {
  $texto = is_scalar($valor) || $valor === NULL
    ? var_export($valor, TRUE)
    : json_encode($valor);
  $texto = preg_replace('/\s+/', ' ', (string) $texto);
  return strlen($texto) > 70 ? substr($texto, 0, 70) . '...' : $texto;
}
