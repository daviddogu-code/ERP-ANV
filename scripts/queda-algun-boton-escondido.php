<?php

/**
 * @file
 * Busca botones de crear puestos para esconderse cuando la lista esta vacia.
 *
 * Este fallo ya ha morido dos veces. El 14 de agosto aparecieron diez botones asi
 * recorriendo las portadas, y aun asi el de marcas se escapo: su portada no
 * existia, o sea que no habia por donde llegar a la vista para verlo. Recorrer
 * pantallas no basta cuando el fallo puede estar detras de una puerta que falta.
 *
 * Asi que esto no mira pantallas: mira la configuracion de todas las vistas, que
 * esta ahi aunque nadie pueda llegar a ellas. Un texto de cabecera o de pie con un
 * enlace de crear y `empty: false` es un boton que desaparece justo cuando es la
 * unica forma de empezar.
 *
 * Uso: drush php:script scripts/queda-algun-boton-escondido
 */

$factory = \Drupal::configFactory();
$escondidos = [];
$bien = 0;

/**
 * Si el texto de un area lleva un enlace que sirve para crear algo.
 *
 * No vale con buscar cualquier enlace: una cabecera con un boton de exportar a
 * Excel puede estar escondida con la lista vacia y hace bien, porque no hay nada
 * que exportar. Lo que no puede esconderse es la unica manera de meter el primer
 * dato.
 */
$esDeCrear = static function (string $texto): bool {
  if (!str_contains($texto, '<a ')) {
    return FALSE;
  }
  return (bool) preg_match('~(\+\s*\w|/add\b|/add/|/add\?)~i', $texto);
};

foreach ($factory->listAll('views.view.') as $nombre) {
  $vista = $factory->get($nombre);
  $id = substr($nombre, strlen('views.view.'));

  foreach ($vista->get('display') ?? [] as $displayId => $display) {
    foreach (['header', 'footer'] as $sitio) {
      foreach ($display['display_options'][$sitio] ?? [] as $areaId => $area) {
        if (($area['plugin_id'] ?? '') !== 'text_custom') {
          continue;
        }
        $texto = (string) ($area['content'] ?? '');
        if (!$esDeCrear($texto)) {
          continue;
        }
        if (!empty($area['empty'])) {
          $bien++;
          continue;
        }
        $escondidos[] = [
          'vista' => $id,
          'apagada' => !$vista->get('status'),
          'display' => $displayId,
          'sitio' => $sitio,
          'area' => $areaId,
          'texto' => trim(preg_replace('~\s+~', ' ', strip_tags($texto))),
        ];
      }
    }
  }
}

echo "\n";
echo str_repeat('=', 82) . "\n";
printf(" %d botones de crear bien puestos, %d escondidos\n", $bien, count($escondidos));
echo str_repeat('=', 82) . "\n\n";

if (!$escondidos) {
  echo "  Ninguno se esconde con la lista vacia.\n\n";
  return;
}

foreach ($escondidos as $uno) {
  printf(
    "  %-44s %s\n      %s / %s%s\n      \"%s\"\n\n",
    $uno['vista'],
    $uno['apagada'] ? '(la vista esta apagada)' : '',
    $uno['display'],
    $uno['sitio'],
    $uno['area'] === 'area_text_custom' ? '' : ' (' . $uno['area'] . ')',
    mb_strimwidth($uno['texto'], 0, 90, '...')
  );
}

echo "\n";
