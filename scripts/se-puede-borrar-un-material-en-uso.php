<?php

/**
 * @file
 * Comprueba el freno: un material en uso no se borra, uno libre si.
 *
 *   php vendor\bin\drush.php scr scripts/se-puede-borrar-un-material-en-uso.php
 *
 * Las dos mitades importan lo mismo. Que el material en uso no se pueda borrar
 * es lo que se ha pedido; que el material libre siga borrandose es lo que evita
 * que el freno se convierta en un candado. Un guardian que dice no a todo pasa
 * por bueno hasta el dia que hace falta borrar algo de verdad.
 *
 * Fabrica un material suelto para probar la segunda mitad y lo borra al acabar,
 * asi que se puede lanzar tantas veces como se quiera.
 */

$gestor = \Drupal::entityTypeManager();
$terminos = $gestor->getStorage('taxonomy_term');
$constructor = \Drupal::service('entity.form_builder');

$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo($gestor->getStorage('user')->load(1));

/**
 * Construye el formulario de borrado y cuenta lo que se ve en el.
 */
$mirarElFormulario = function ($material) use ($constructor): array {
  $formulario = $constructor->getForm($material, 'delete');
  $texto = (string) \Drupal::service('renderer')->renderInIsolation($formulario);
  return [
    'boton' => isset($formulario['actions']['submit']),
    'aviso' => isset($formulario['tec_inventory_in_use']),
    'texto' => $texto,
  ];
};

echo "\n";
echo "Los materiales que hay y si algo les apunta\n";
echo str_repeat('-', 78) . "\n";

$ids = $terminos->getQuery()->accessCheck(FALSE)->condition('vid', 'tec_inventory')->sort('tid')->execute();
$enUso = NULL;
foreach ($terminos->loadMultiple($ids) as $material) {
  $usos = _tec_inventory_material_usages($material);
  printf("  %-8s %-30s %d usos\n", $material->id(), mb_substr((string) $material->label(), 0, 30), $usos['total']);
  if ($usos['total'] && !$enUso) {
    $enUso = $material;
  }
}

echo "\n";
$fallos = 0;

if (!$enUso) {
  echo "No hay ningun material en uso, asi que la primera mitad no se puede probar.\n";
  $fallos++;
}
else {
  echo "Un material EN USO: " . $enUso->label() . "\n";
  echo str_repeat('-', 78) . "\n";
  $visto = $mirarElFormulario($enUso);
  printf("  boton de borrar:        %s\n", $visto['boton'] ? 'SI, MAL' : 'no, bien');
  printf("  aviso de que esta usado: %s\n", $visto['aviso'] ? 'si, bien' : 'NO, MAL');

  $usos = _tec_inventory_material_usages($enUso);
  echo "  donde se usa, por nombre:\n";
  foreach ($usos['groups'] as $grupo => $cosas) {
    printf("      %s:\n", $grupo);
    foreach ($cosas as $cosa) {
      printf("          %s\n", (string) ($cosa['#title'] ?? $cosa['#markup'] ?? ''));
    }
  }

  // Que el aviso diga nombres y no numeros es la mitad del encargo.
  $hayNombres = FALSE;
  foreach ($usos['groups'] as $cosas) {
    foreach ($cosas as $cosa) {
      $nombre = (string) ($cosa['#title'] ?? $cosa['#markup'] ?? '');
      if ($nombre !== '' && !preg_match('/^\d+$/', $nombre)) {
        $hayNombres = TRUE;
      }
    }
  }
  printf("  el aviso da nombres:     %s\n", $hayNombres ? 'si, bien' : 'NO, MAL');

  if ($visto['boton'] || !$visto['aviso'] || !$hayNombres) {
    $fallos++;
  }
}

echo "\n";
echo "Un material LIBRE, fabricado para la prueba\n";
echo str_repeat('-', 78) . "\n";

$suelto = $terminos->create([
  'vid' => 'tec_inventory',
  'name' => 'PRUEBA material que nadie usa',
]);
$suelto->save();
printf("  creado el %s\n", $suelto->id());

$visto = $mirarElFormulario($suelto);
printf("  boton de borrar:        %s\n", $visto['boton'] ? 'si, bien' : 'NO, MAL');
printf("  aviso de que esta usado: %s\n", $visto['aviso'] ? 'SI, MAL' : 'no, bien');
if (!$visto['boton'] || $visto['aviso']) {
  $fallos++;
}

$suelto->delete();
printf("  borrado, y sin quejas\n");

$cambiador->switchBack();

echo "\n";
echo $fallos === 0
  ? "El freno distingue bien: para lo que esta en uso y deja pasar lo libre.\n\n"
  : "HAY $fallos cosas mal.\n\n";
