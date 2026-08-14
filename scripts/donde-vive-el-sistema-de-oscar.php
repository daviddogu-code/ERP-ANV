<?php

/**
 * Busca en toda la configuracion cada rastro del sistema de unidades opcionales.
 *
 * Antes de borrar los campos hay que sacarlos de donde esten nombrados, porque
 * si se borra un campo que una vista todavia usa, Drupal arrastra la vista al
 * borrarlo. Este guion recorre config/sync entera y dice, fichero por fichero,
 * donde aparece cada campo y en calidad de que: columna de una vista, filtro,
 * relacion, hueco de un formulario o interruptor de una condicional.
 *
 * Uso: php scripts/donde-vive-el-sistema-de-oscar.php [--solo-vistas]
 */

$carpeta = dirname(__DIR__) . '/config/sync';
$soloVistas = in_array('--solo-vistas', $argv, TRUE);

// Los campos que se van. Los cuatro primeros son los interruptores que mandaban.
$condenados = [
  'field_tec_split_inventory',
  'field_tec_package_units',
  'field_tec_moq_package',
  'field_tec_stock_unit_check',
  'field_tec_packaging',
  'field_tec_split_unit',
  'field_tec_uop_pu_uou',
  'field_tec_uop_uou',
  'field_tec_uos_pu_uop',
  'field_tec_uos_pu_uou',
  'field_tec_uos_uop',
  'field_tec_uos_uou',
  'field_tec_uou_control',
  'field_tec_uou_split_qty',
  'field_tec_uou_uop',
  'field_tec_uou_uop_pu',
  'field_tec_uou_uos',
  'field_uop_to_uos',
  'field_1_uou_uos_pu',
  'field_tec_price_by_package',
  'field_tec_price_uou',
  'field_tec_moq_unit',
  'field_tec_purchasing_moq',
];

require_once dirname(__DIR__) . '/vendor/autoload.php';

$ficheros = glob($carpeta . '/*.yml');
sort($ficheros);

// Los ficheros que definen los campos en si; esos se borran enteros, no se
// limpian, asi que van aparte.
$definiciones = [];
$vistas = [];
$otros = [];

foreach ($ficheros as $fichero) {
  $nombre = basename($fichero, '.yml');
  $texto = (string) file_get_contents($fichero);

  $aparecen = [];
  foreach ($condenados as $campo) {
    // Con frontera por la derecha, para que field_tec_uos no cace a
    // field_tec_uos_uop ni field_tec_uou_uop a field_tec_uou_uop_pu.
    if (preg_match('/' . preg_quote($campo, '/') . '(?![a-z0-9_])/', $texto)) {
      $aparecen[] = $campo;
    }
  }
  if (!$aparecen) {
    continue;
  }

  // Definicion del propio campo.
  if (preg_match('/^(field\.storage|field\.field|core\.base_field_override)\./', $nombre)) {
    $definiciones[$nombre] = $aparecen;
    continue;
  }

  if (str_starts_with($nombre, 'views.view.')) {
    $vistas[$nombre] = ['fichero' => $fichero, 'campos' => $aparecen];
    continue;
  }

  $otros[$nombre] = $aparecen;
}

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Vistas\n";
echo str_repeat('=', 78) . "\n";

$totalManejadores = 0;

foreach ($vistas as $nombre => $datos) {
  $vista = \Symfony\Component\Yaml\Yaml::parseFile($datos['fichero']);
  echo "\n " . substr($nombre, strlen('views.view.')) . "\n";

  foreach ($vista['display'] ?? [] as $display => $opciones) {
    $hallazgos = [];

    // Manejadores: columnas, filtros, ordenaciones, argumentos y relaciones.
    $clases = [
      'fields' => 'columna',
      'filters' => 'filtro',
      'sorts' => 'ordenacion',
      'arguments' => 'argumento',
      'relationships' => 'relacion',
    ];
    foreach ($clases as $clase => $palabra) {
      foreach ($opciones['display_options'][$clase] ?? [] as $id => $manejador) {
        $campo = $manejador['field'] ?? '';
        if (in_array($campo, $condenados, TRUE)) {
          $visible = $clase === 'fields' && empty($manejador['exclude']);
          $hallazgos[] = sprintf(
            '%s %s (%s)%s',
            $palabra,
            $id,
            $campo,
            $visible ? '  << SE DIBUJA' : ''
          );
        }
        // Las condicionales guardan el interruptor en otra clave.
        $interruptor = $manejador['if'] ?? '';
        if ($interruptor !== '' && in_array($interruptor, $condenados, TRUE)) {
          $visible = empty($manejador['exclude']);
          $hallazgos[] = sprintf(
            'condicional %s decide con %s%s',
            $id,
            $interruptor,
            $visible ? '  << SE DIBUJA' : ''
          );
        }
        // Y las formulas y reescrituras los nombran en texto.
        $textos = [];
        if (!empty($manejador['alter']['alter_text'])) {
          $textos[] = $manejador['alter']['text'] ?? '';
        }
        $textos[] = $manejador['then'] ?? '';
        $textos[] = $manejador['or'] ?? '';
        $textos[] = $manejador['fieldset_one']['formula'] ?? '';
        $juntos = implode(' ', $textos);
        foreach ($condenados as $campo) {
          if (preg_match('/[@{ ]' . preg_quote($campo, '/') . '(?![a-z0-9_])/', $juntos)) {
            $hallazgos[] = sprintf('%s %s nombra %s en su texto', $palabra, $id, $campo);
          }
        }
      }
    }

    if ($hallazgos) {
      echo "   $display:\n";
      foreach ($hallazgos as $hallazgo) {
        echo "     - $hallazgo\n";
        $totalManejadores++;
      }
    }
  }
}

if (!$soloVistas) {
  echo "\n";
  echo str_repeat('=', 78) . "\n";
  echo " Definiciones de los campos (se borran enteras)\n";
  echo str_repeat('=', 78) . "\n\n";
  foreach ($definiciones as $nombre => $campos) {
    echo "   $nombre\n";
  }

  echo "\n";
  echo str_repeat('=', 78) . "\n";
  echo " Otra configuracion que los nombra\n";
  echo str_repeat('=', 78) . "\n\n";
  foreach ($otros as $nombre => $campos) {
    printf("   %-62s %s\n", $nombre, implode(', ', $campos));
  }
}

echo "\n";
echo str_repeat('-', 78) . "\n";
echo "Manejadores de vistas que hay que quitar: $totalManejadores\n";
echo "Definiciones de campo que hay que borrar: " . count($definiciones) . "\n";
echo "Otros ficheros de configuracion tocados: " . count($otros) . "\n";
echo str_repeat('-', 78) . "\n";
