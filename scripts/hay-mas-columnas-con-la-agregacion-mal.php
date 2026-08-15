<?php

/**
 * @file
 * Busca en todas las vistas del ERP columnas con la agregacion del nucleo mal
 * puesta, que es lo que hace que se ensene un identificador en vez de una
 * etiqueta.
 *
 *   php vendor/bin/drush scr scripts/hay-mas-columnas-con-la-agregacion-mal.php
 *
 * Cuando una columna tiene la agregacion (`group_type`) en algo que no sea
 * `group`, Views cambia el manejador de la columna por el numerico y el
 * formateador configurado deja de usarse. En una cantidad eso es lo que se
 * quiere: un `sum` sobre un decimal suma, y ya. En una referencia a un termino
 * es un fallo: lo que sale es el numero crudo del identificador, con separador de
 * miles y todo.
 *
 * Y solo cuenta en las pantallas que tienen la agregacion encendida
 * (`group_by: true`). En las demas el `group_type` esta guardado pero no se
 * aplica, asi que un `sum` ahi no hace dano ninguno; se dice aparte para que no
 * se confunda con un fallo y se "arregle" sin motivo.
 *
 * Se escribio despues de arreglar la columna UoU de la pantalla maestra de
 * `tec_order_material_calculation_summary`, para saber si aquello era un caso
 * suelto o el primero de varios.
 *
 * No escribe nada.
 */

// Formateadores que no pueden sobrevivir al cambio de manejador, porque
// necesitan la entidad o el campo de verdad para dar algo legible.
const FORMATEADORES_QUE_SE_PIERDEN = [
  'entity_reference_label',
  'entity_reference_entity_view',
  'entity_reference_entity_id',
  'image',
  'string',
  'basic_string',
  'text_default',
  'datetime_default',
  'boolean',
  'list_default',
  'taxonomy_term_reference_link',
];

$factory = \Drupal::configFactory();

$sospechosas = [];
$inertes = 0;
$legitimas = 0;

foreach ($factory->listAll('views.view.') as $nombre) {
  $vista = $factory->get($nombre);
  $vistaId = substr($nombre, strlen('views.view.'));
  $displays = $vista->get('display') ?? [];
  $maestro = $displays['default']['display_options'] ?? [];

  foreach ($displays as $displayId => $display) {
    $suyo = $display['display_options'] ?? [];
    $heredado = $suyo['defaults'] ?? [];
    $propio = static function (string $clave) use ($displayId, $heredado): bool {
      return $displayId === 'default' || ($heredado[$clave] ?? TRUE) === FALSE;
    };

    $campos = $propio('fields') ? ($suyo['fields'] ?? []) : ($maestro['fields'] ?? []);
    $estilo = $propio('style') ? ($suyo['style'] ?? []) : ($maestro['style'] ?? []);
    $columnas = $estilo['options']['columns'] ?? [];
    $agregacion = $propio('group_by')
      ? !empty($suyo['group_by'])
      : !empty($maestro['group_by']);

    foreach ($campos as $id => $campo) {
      $tipo = $campo['group_type'] ?? 'group';
      if ($tipo === 'group') {
        continue;
      }
      $formateador = $campo['type'] ?? ($campo['plugin_id'] ?? '');

      if (!$agregacion) {
        $inertes++;
        continue;
      }
      if (!in_array($formateador, FORMATEADORES_QUE_SE_PIERDEN, TRUE)) {
        $legitimas++;
        continue;
      }

      // Si la columna esta excluida o no esta en la lista de columnas de la
      // tabla, el fallo esta pero no se ve. Se dice, porque el dia que alguien
      // la ensene aparecera solo.
      $seVe = empty($campo['exclude']) && ($columnas === [] || isset($columnas[$id]));
      $sospechosas[] = [
        'vista' => $vistaId,
        'display' => $displayId,
        'campo' => $id,
        'group_type' => $tipo,
        'formateador' => $formateador,
        'se_ve' => $seVe,
        'etiqueta' => trim((string) ($campo['label'] ?? '')),
      ];
    }
  }
}

echo "\n";
echo str_repeat('=', 100) . "\n";
echo " Columnas con la agregacion del nucleo puesta en algo que no es 'group'\n";
echo str_repeat('=', 100) . "\n\n";

if (!$sospechosas) {
  echo "  Ninguna columna con formateador de los que se pierden en una pantalla con\n";
  echo "  la agregacion encendida. No queda ni un caso como el de la UoU.\n";
}
else {
  printf("  %-48s %-26s %-22s %-10s %s\n", 'vista / pantalla', 'campo', 'formateador', 'group_type', 'se ve');
  printf("  %s\n", str_repeat('-', 132));
  foreach ($sospechosas as $fila) {
    // El nombre del campo entero y sin recortar: `field_tec_quantity` y
    // `field_tec_quantity_input` empiezan igual, y confundirlas manda a arreglar
    // la columna que no era.
    printf(
      "  %-48s %-26s %-22s %-10s %s\n",
      $fila['vista'] . ' / ' . $fila['display'],
      $fila['campo'],
      $fila['formateador'],
      $fila['group_type'],
      $fila['se_ve'] ? 'SI, ES UN FALLO VISIBLE' : 'no, la columna esta oculta'
    );
  }
}

echo "\n";
printf("  %d sospechosas.\n", count($sospechosas));
printf("  %d con agregacion pero con formateador numerico: son sumas de verdad.\n", $legitimas);
printf("  %d en pantallas sin agregacion: el group_type esta guardado pero no se aplica.\n\n", $inertes);
