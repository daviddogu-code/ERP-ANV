<?php

/**
 * @file
 * Que numero le sale a un pedido de venta con codigo corto y sin el.
 *
 *   php vendor\bin\drush.php scr scripts/como-queda-el-numero-de-pedido.php
 *
 * El titulo del pedido de venta lo escribe el proceso de ECA process_sclj26d con
 * la plantilla '[codigo] [ano]-[numero]'. El espacio esta puesto a mano en medio,
 * asi que cuando la organizacion no tiene codigo la ficha se queda vacia y el
 * pedido nace con un espacio delante: ' 26-001'. Se arregla con la casilla trim
 * de esa accion, que recorta el valor ya montado.
 *
 * Este guion no dispara el proceso entero, que fabricaria un pedido de verdad
 * con sus lineas. Repite los dos pasos que importan, y los repite leyendo la
 * plantilla y la casilla trim de la configuracion viva, no copiadas aqui: si
 * manana alguien cambia el formato, esta comprobacion sigue diciendo la verdad
 * en vez de aprobar una plantilla que ya no existe.
 *
 * No escribe nada.
 */

$config = \Drupal::config('eca.eca.process_sclj26d');
$accion = $config->get('actions.Activity_0xwr7vw.configuration');
if (!$accion) {
  echo "\n  No encuentro la accion que pone el numero. Se habra renombrado.\n\n";
  return;
}

$plantilla = $accion['field_value'];
$recorta = !empty($accion['trim']);
$quitaEtiquetas = !empty($accion['strip_tags']);

echo "\n";
echo "La plantilla que hay puesta\n";
echo str_repeat('-', 78) . "\n";
printf("  %s\n", $plantilla);
printf("  trim: %s      strip_tags: %s\n", $recorta ? 'si' : 'no', $quitaEtiquetas ? 'si' : 'no');

$fichas = \Drupal::service('eca.token_services');
$gestor = \Drupal::entityTypeManager();

// Una organizacion de verdad, para que la ficha del codigo resuelva contra un
// campo que existe. El codigo se le pone en memoria y no se guarda.
$ids = $gestor->getStorage('tec_crm')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_contact_organization')
  ->sort('id')
  ->range(0, 1)
  ->execute();
if (!$ids) {
  echo "\n  No hay ninguna organizacion en el CRM con la que probar.\n\n";
  return;
}
$organizacion = $gestor->getStorage('tec_crm')->load(reset($ids));

/**
 * Monta el titulo igual que la accion de ECA: replaceClear y luego los filtros.
 */
$titulo = function (?string $codigo) use ($plantilla, $recorta, $quitaEtiquetas, $fichas, $organizacion): array {
  $organizacion->set('field_tec_customer_code', $codigo);
  $fichas->clearTokenData();
  $fichas->addTokenData('entity', $organizacion);
  $fichas->addTokenData('soPad', '001');

  $crudo = (string) $fichas->replaceClear($plantilla);
  $final = $crudo;
  if ($quitaEtiquetas) {
    $final = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags($final));
  }
  if ($recorta) {
    $final = trim($final);
  }
  return [$crudo, $final];
};

echo "\n";
echo "Que sale\n";
echo str_repeat('-', 78) . "\n";
printf("  %-18s %-22s %s\n", 'codigo corto', 'antes de recortar', 'lo que se guarda');

$casos = [
  'ACTA' => 'ACTA',
  'vacio' => NULL,
  'con espacios' => '  AB  ',
];
$fallos = 0;
foreach ($casos as $nombre => $codigo) {
  [$crudo, $final] = $titulo($codigo);
  printf("  %-18s %-22s %s\n", $nombre, "'" . $crudo . "'", "'" . $final . "'");
  if ($final !== trim($final) || str_starts_with($final, ' ')) {
    $fallos++;
  }
}

echo "\n";
echo $fallos === 0
  ? "Ningun numero de pedido nace con un espacio colgando.\n\n"
  : "HAY $fallos numeros con espacios. La casilla trim no esta puesta.\n\n";
