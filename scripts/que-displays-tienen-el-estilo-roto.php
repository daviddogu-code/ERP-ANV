<?php

/**
 * @file
 * Displays cuyo estilo esta a medias, sin decir de que tipo es.
 *
 * Cada display dice como se dibuja en `style`, y ahi dentro `type` es
 * obligatorio: tabla, lista, cuadricula. Si aparece un `style` sin `type`, es
 * que alguien creo la rama sin rellenarla -en PHP basta con tomar una
 * referencia a una ruta que no existe para que se cree vacia- y el nucleo se
 * queja en cada guardado con "Undefined array key type".
 *
 * Uso: drush php:script scripts/que-displays-tienen-el-estilo-roto
 */

$almacen = \Drupal::entityTypeManager()->getStorage('view');
$roto = 0;

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Displays con el estilo a medias\n";
echo str_repeat('=', 78) . "\n\n";

foreach ($almacen->loadMultiple() as $vista) {
  foreach ($vista->get('display') ?? [] as $nombre => $display) {
    $opciones = $display['display_options'] ?? [];
    // Un display sin `style` propio no esta roto: hereda el del maestro.
    if (!array_key_exists('style', $opciones)) {
      continue;
    }
    $estilo = $opciones['style'];
    if (is_array($estilo) && isset($estilo['type']) && $estilo['type'] !== '') {
      continue;
    }
    $roto++;
    echo '  ' . $vista->id() . " / $nombre\n";
    echo '      style = ' . var_export($estilo, TRUE) . "\n";
  }
}

echo $roto === 0 ? "  Ninguno.\n" : "\n  Displays con el estilo a medias: $roto.\n";
echo "\n";
