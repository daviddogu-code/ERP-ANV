<?php

/**
 * @file
 * Quita los `style` a medias que hacen al nucleo quejarse en cada guardado.
 *
 * Cada display dice como se dibuja en `style`, y ahi `type` es obligatorio. Un
 * `style` sin `type` es una rama que alguien creo sin rellenar, y el nucleo
 * escribe "Undefined array key type" cada vez que se guarda la vista. No rompe
 * nada, pero es ruido en el registro, y un aviso que salta siempre ensena a no
 * mirar los avisos.
 *
 * Solo lo quita cuando el display dice seguir al maestro, que es entonces de
 * donde saca el estilo de verdad. Si un display va por su cuenta y aun asi no
 * dice de que tipo es, eso es otra cosa y aqui se avisa sin tocarlo.
 *
 * Por defecto solo cuenta lo que haria. Para hacerlo de verdad:
 *   drush php:script scripts/arreglar-el-estilo-a-medias -- de-verdad
 */

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

$almacen = \Drupal::entityTypeManager()->getStorage('view');

echo "\n";
echo str_repeat('=', 78) . "\n";
echo ' Los estilos a medias' . ($deVerdad ? '' : '   (solo mirando)') . "\n";
echo str_repeat('=', 78) . "\n\n";

$arreglados = 0;
$avisos = [];

foreach ($almacen->loadMultiple() as $vista) {
  $displays = $vista->get('display') ?? [];
  $cambia = FALSE;

  foreach ($displays as $nombre => $display) {
    $opciones = $display['display_options'] ?? [];
    if (!array_key_exists('style', $opciones)) {
      continue;
    }
    $estilo = $opciones['style'];
    if (is_array($estilo) && isset($estilo['type']) && $estilo['type'] !== '') {
      continue;
    }

    // La bandera sin escribir significa que sigue al maestro.
    $sigueAlMaestro = $opciones['defaults']['style'] ?? TRUE;
    if (!$sigueAlMaestro) {
      $avisos[] = $vista->id() . " / $nombre va por su cuenta y no dice de que tipo es; hay que mirarlo a mano.";
      continue;
    }

    unset($displays[$nombre]['display_options']['style']);
    $cambia = TRUE;
    $arreglados++;
    echo '  ' . $vista->id() . " / $nombre: fuera el style vacio, vuelve a heredar del maestro\n";
  }

  if ($cambia && $deVerdad) {
    $vista->set('display', $displays);
    $vista->save();
  }
}

echo $arreglados === 0 ? "  Ninguno.\n" : "\n  Displays arreglados: $arreglados.\n";

if ($avisos) {
  echo "\n  Para mirar a mano:\n";
  foreach ($avisos as $aviso) {
    echo "      $aviso\n";
  }
}

if (!$deVerdad) {
  echo "\n  Para hacerlo de verdad:\n";
  echo "    drush php:script scripts/arreglar-el-estilo-a-medias -- de-verdad\n";
}
echo "\n";
