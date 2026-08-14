<?php

/**
 * Lista los campos de un display, con su relacion y de donde salen.
 *
 * Para rehacer una columna hay que saber si el campo que hay dentro cuelga de
 * una relacion o de otra. Dos columnas pueden llamarse igual y traer cosas
 * distintas: field_tec_unit_plural y field_tec_unit_plural_1 son las dos el
 * plural de una unidad, pero una viene de la unidad de compra y la otra del
 * empaquetado. Si se elige la rama equivocada, la vista sigue funcionando y
 * ensena el dato de otro campo, que es peor que si petara.
 *
 * Uso: php scripts/que-campos-tiene-la-vista.php VISTA [display]
 */

$carpeta = dirname(__DIR__) . '/config/sync';
$vista = $argv[1] ?? NULL;
$soloDisplay = $argv[2] ?? NULL;

if ($vista === NULL) {
  echo "Falta el nombre de la vista.\n";
  exit(1);
}

$fichero = "$carpeta/views.view.$vista.yml";
if (!is_file($fichero)) {
  echo "No existe $fichero\n";
  exit(1);
}

$datos = leerYaml($fichero);

// Las relaciones se heredan del display maestro si el display no las redefine.
$relacionesMaestro = $datos['display']['default']['display_options']['relationships'] ?? [];

foreach ($datos['display'] as $nombre => $display) {
  if ($soloDisplay !== NULL && $nombre !== $soloDisplay) {
    continue;
  }
  $campos = $display['display_options']['fields'] ?? [];
  if (!$campos) {
    continue;
  }

  $relaciones = $display['display_options']['relationships'] ?? $relacionesMaestro;

  echo "\n";
  echo str_repeat('=', 100) . "\n";
  echo " $vista / $nombre\n";
  echo str_repeat('=', 100) . "\n";

  // El estilo decide si la lista de columnas de mas abajo pinta algo o es
  // resto de cuando el display era una tabla.
  $estiloTipo = $display['display_options']['style']['type']
    ?? ($datos['display']['default']['display_options']['style']['type'] ?? '?');
  $filaTipo = $display['display_options']['row']['type']
    ?? ($datos['display']['default']['display_options']['row']['type'] ?? '?');
  echo "\n Estilo: $estiloTipo. Fila: $filaTipo.\n";

  if ($relaciones) {
    echo "\n Relaciones:\n";
    foreach ($relaciones as $id => $relacion) {
      printf("   %-38s %s.%s\n", $id, $relacion['table'] ?? '?', $relacion['field'] ?? '?');
    }
  }

  echo "\n Campos:\n\n";
  printf("   %-38s %-26s %-30s %s\n", 'id', 'plugin', 'relacion', 'etiqueta');
  printf("   %s\n", str_repeat('-', 95));
  foreach ($campos as $id => $campo) {
    $plugin = $campo['plugin_id'] ?? '?';
    $relacion = $campo['relationship'] ?? 'none';
    $etiqueta = trim((string) ($campo['label'] ?? ''));
    $marca = !empty($campo['exclude']) ? ' [oculta]' : '';
    printf("   %-38s %-26s %-30s %s%s\n", $id, $plugin, $relacion, $etiqueta === '' ? '(sin etiqueta)' : $etiqueta, $marca);

    // Lo que hace la columna, si hace algo mas que ensenar el campo.
    if ($plugin === 'field_views_simple_math_field') {
      printf("       formula: %s\n", $campo['fieldset_one']['formula'] ?? '');
    }
    if ($plugin === 'views_conditional_field') {
      printf("       si %s %s '%s'\n", $campo['if'] ?? '', $campo['condition'] ?? '', $campo['equalto'] ?? '');
      printf("       entonces: %s\n", unaLinea($campo['then'] ?? ''));
      printf("       si no:    %s\n", unaLinea($campo['or'] ?? ''));
    }
    if ($plugin === 'custom' && !empty($campo['alter']['alter_text'])) {
      printf("       texto: %s\n", unaLinea($campo['alter']['text'] ?? ''));
    }
    elseif (!empty($campo['alter']['alter_text'])) {
      printf("       reescrito: %s\n", unaLinea($campo['alter']['text'] ?? ''));
    }
  }

  // Quien nombra a quien, para no borrar un campo del que cuelga otra columna.
  echo "\n Columnas que nombran a otras:\n\n";
  $hayReferencias = FALSE;
  foreach ($campos as $id => $campo) {
    $textos = [
      $campo['alter']['text'] ?? '',
      $campo['then'] ?? '',
      $campo['or'] ?? '',
      $campo['fieldset_one']['formula'] ?? '',
    ];
    $juntos = implode(' ', $textos);
    $nombrados = [];
    foreach (array_keys($campos) as $otro) {
      if ($otro === $id) {
        continue;
      }
      if (str_contains($juntos, '{{ ' . $otro . ' }}') || str_contains($juntos, '@' . $otro)) {
        $nombrados[] = $otro;
      }
    }
    if ($nombrados) {
      $hayReferencias = TRUE;
      printf("   %-38s usa %s\n", $id, implode(', ', $nombrados));
    }
  }
  if (!$hayReferencias) {
    echo "   ninguna\n";
  }

  // Y el estilo, que es donde se decide que columnas se dibujan de verdad.
  $estilo = $display['display_options']['style'] ?? [];
  $columnas = $estilo['options']['columns'] ?? [];
  if ($columnas) {
    echo "\n Columnas que dibuja la tabla:\n\n";
    foreach ($columnas as $columna => $donde) {
      printf("   %-38s %s\n", $columna, $columna === $donde ? '' : "agrupada en $donde");
    }
  }
}

/**
 * Deja un texto de varias lineas en una sola, para que la tabla no se rompa.
 */
function unaLinea(string $texto): string {
  $texto = trim(preg_replace('/\s+/', ' ', $texto));
  return $texto === '' ? '(nada)' : $texto;
}

/**
 * Lee un YAML sin depender de Drupal.
 */
function leerYaml(string $fichero): array {
  if (function_exists('yaml_parse_file')) {
    $datos = yaml_parse_file($fichero);
    if (is_array($datos)) {
      return $datos;
    }
  }
  require_once dirname(__DIR__) . '/vendor/autoload.php';
  return \Symfony\Component\Yaml\Yaml::parseFile($fichero) ?: [];
}
