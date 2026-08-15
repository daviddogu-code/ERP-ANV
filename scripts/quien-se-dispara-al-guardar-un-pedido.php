<?php

/**
 * @file
 * Que automatismos saltan al guardar un pedido, antes de tocar uno a mano.
 *
 *   php vendor\bin\drush.php scr scripts/quien-se-dispara-al-guardar-un-pedido.php
 *
 * Hace falta cambiarle el estado al pedido de pruebas para que la prueba de humo
 * pueda pedir sus dos pantallas. El problema es que en este ERP el titulo del
 * pedido lo escribe ECA, asi que un guardado inocente puede renumerarlo y dejar
 * el juego de pruebas sin el PRUEBA delante, que es como se encuentra para
 * borrarlo despues.
 *
 * Lista los eventos de ECA que miran a tec_order, diciendo cuales saltan al
 * guardar y cuales necesitan una bandera o una accion del usuario.
 *
 * No escribe nada.
 */

$deGuardado = [
  'content_entity:presave',
  'content_entity:insert',
  'content_entity:update',
  'content_entity:create',
];

echo "\n";
echo "Eventos de ECA que miran a los pedidos\n";
echo str_repeat('-', 92) . "\n";
printf("  %-20s %-34s %-26s %s\n", 'proceso', 'evento', 'tipo', 'salta al guardar');
echo '  ' . str_repeat('-', 90) . "\n";

$fabrica = \Drupal::configFactory();
$cuantos = 0;
foreach ($fabrica->listAll('eca.eca.') as $nombre) {
  $proceso = $fabrica->get($nombre);
  if (!$proceso->get('status')) {
    continue;
  }
  foreach ($proceso->get('events') ?? [] as $evento) {
    $plugin = $evento['plugin'] ?? '';
    $config = $evento['configuration'] ?? [];
    $tipo = $config['type'] ?? '';

    // Solo interesan los que apuntan a pedidos. Los eventos de contenido
    // llevan el tipo en la configuracion; las banderas, en el nombre del flag.
    $mira = str_contains((string) $tipo, 'tec_order')
      || str_contains(strtolower((string) ($config['flag_name'] ?? '')), 'order');
    if (!$mira) {
      continue;
    }

    $salta = in_array($plugin, $deGuardado, TRUE) ? 'SI' : 'no, hace falta ' . explode(':', $plugin)[0];
    printf(
      "  %-20s %-34s %-26s %s\n",
      substr($proceso->get('id'), 0, 20),
      substr($plugin, 0, 34),
      substr((string) $tipo, 0, 26),
      $salta
    );
    $cuantos++;
  }
}

if (!$cuantos) {
  echo "  ninguno\n";
}

echo "\n";
echo "Ganchos de modulos propios que miran a los pedidos\n";
echo str_repeat('-', 92) . "\n";

$encontrado = FALSE;
foreach (\Drupal::moduleHandler()->getModuleList() as $modulo => $extension) {
  $fichero = DRUPAL_ROOT . '/' . $extension->getPath() . '/' . $modulo . '.module';
  if (!is_file($fichero) || !str_starts_with($extension->getPath(), 'modules/custom')) {
    continue;
  }
  $codigo = file_get_contents($fichero);
  foreach (['presave', 'insert', 'update'] as $momento) {
    if (preg_match_all('/function\s+(' . preg_quote($modulo, '/') . '_[a-z_]*' . $momento . ')\s*\(/', $codigo, $coincidencias)) {
      foreach ($coincidencias[1] as $funcion) {
        printf("  %-24s %s\n", $modulo, $funcion);
        $encontrado = TRUE;
      }
    }
  }
}
if (!$encontrado) {
  echo "  ninguno\n";
}

echo "\n";
