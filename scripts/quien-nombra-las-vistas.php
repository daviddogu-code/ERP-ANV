<?php

/**
 * Segunda pasada: quien nombra a esas vistas, esta vez de verdad.
 *
 * La primera pasada busco el identificador como trozo de texto y salio ruido a
 * mansalva: `dev` aparece dentro de cualquier palabra que lleve "dev", y
 * `tec_order_material_calculation` aparece dentro del nombre de otras tres
 * vistas que empiezan igual. Con ese ruido no se puede decidir si borrar.
 *
 * Aqui se busca distinto. Drupal guarda la configuracion serializada, y una
 * cadena serializada lleva su longitud delante: s:14:"identificador". Buscando
 * con la longitud exacta solo se encuentra el valor completo, nunca un trozo. La
 * diferencia entre las dos pasadas es la diferencia entre "aparece" y "es".
 *
 * No toca nada. Solo mira.
 *
 * Uso: php vendor/bin/drush.php scr scripts/quien-nombra-las-vistas.php
 */

const VISTAS = [
  'tec_order_material_calculation',
  'duplicate_of_tec_order_material_calculation_terms_copy',
  'tec_order_create_draft_order_ii_excel_lover_style_eca_model',
  'archive',
  'glossary',
  'dev',
  'material_calculation_inventory_based',
];

$etm = \Drupal::entityTypeManager();
$conexion = \Drupal::database();
$esquema = $conexion->schema();

print "\n";

// -----------------------------------------------------------------------------
// 1. Menciones exactas en la configuracion.
// -----------------------------------------------------------------------------
print "  --- 1. quien nombra a cada vista, con el valor exacto ---\n\n";

$config = [];
foreach ($conexion->select('config', 'c')->fields('c', ['name', 'data'])->execute() as $fila) {
  $config[$fila->name] = (string) $fila->data;
}

printf("  %d objetos de configuracion revisados\n\n", count($config));

foreach (VISTAS as $id) {
  $vista = $etm->getStorage('view')->load($id);

  if (!$vista) {
    printf("      %s: NO EXISTE\n\n", $id);
    continue;
  }

  // La aguja: el identificador como cadena serializada completa.
  $aguja = 's:' . strlen($id) . ':"' . $id . '"';

  $quien = [];
  foreach ($config as $nombre => $datos) {
    if ($nombre === 'views.view.' . $id) {
      continue;
    }
    if (str_contains($datos, $aguja)) {
      $quien[] = $nombre;
    }
  }

  printf("      %s%s\n", $id, $vista->status() ? '' : '  (apagada)');
  printf("          pantallas: %s\n", implode(', ', array_keys($vista->get('display'))));

  if (!$quien) {
    print "          no la nombra nadie\n\n";
    continue;
  }

  printf("          la nombran %d:\n", count($quien));
  foreach ($quien as $nombre) {
    print '              ' . $nombre . "\n";
  }
  print "\n";
}

// -----------------------------------------------------------------------------
// 2. Y en que sitio exacto de esa configuracion aparece.
// -----------------------------------------------------------------------------
// Saber quien la nombra no basta: importa si la nombra como "vista que se pinta
// aqui" o como "vista que el editor podria elegir". Lo primero es un uso; lo
// segundo es una lista blanca, y borrar la vista solo acorta la lista.
print "  --- 2. en que clave aparece, para los campos que la nombran ---\n\n";

$sospechosos = [];
foreach (VISTAS as $id) {
  $aguja = 's:' . strlen($id) . ':"' . $id . '"';
  foreach ($config as $nombre => $datos) {
    if ($nombre !== 'views.view.' . $id && str_starts_with($nombre, 'field.field.') && str_contains($datos, $aguja)) {
      $sospechosos[$nombre][] = $id;
    }
  }
}

foreach ($sospechosos as $nombre => $ids) {
  $objeto = \Drupal::config($nombre)->getRawData();

  printf("      %s\n", $nombre);
  printf("          tipo de campo: %s\n", $objeto['field_type'] ?? '?');

  // Se busca la clave concreta donde vive el identificador.
  $donde = [];
  $buscar = function ($valor, $ruta) use (&$buscar, $ids, &$donde) {
    if (is_array($valor)) {
      foreach ($valor as $clave => $dentro) {
        $buscar($dentro, $ruta === '' ? (string) $clave : $ruta . '.' . $clave);
      }
      return;
    }
    if (is_string($valor) && in_array($valor, $ids, TRUE)) {
      $donde[] = $ruta . ' = ' . $valor;
    }
  };
  $buscar($objeto, '');

  foreach (array_slice($donde, 0, 8) as $linea) {
    print '          ' . $linea . "\n";
  }
  if (count($donde) > 8) {
    printf("          ... y %d mas\n", count($donde) - 8);
  }
  print "\n";
}

// -----------------------------------------------------------------------------
// 3. Las tablas de tec_gui: cual es la de verdad.
// -----------------------------------------------------------------------------
// Las trece tablas `tmp_b44c2b...` no son basura sin dueño: son las tablas de
// tec_gui con cincuenta fichas dentro. Antes de tirarlas hay que saber si son una
// copia sobrante o si son las unicas que hay, porque en el segundo caso tirarlas
// no es limpiar, es borrar.
print "  --- 3. tec_gui: que tablas existen y que hay en cada una ---\n\n";

foreach ($conexion->query("SHOW TABLES LIKE '%tec_gui%'")->fetchCol() as $tabla) {
  $n = (int) $conexion->select($tabla)->countQuery()->execute()->fetchField();
  $temporal = str_starts_with($tabla, 'tmp_') ? 'temporal' : 'DE VERDAD';
  printf("      %-46s %-10s %6d filas\n", $tabla, $temporal, $n);
}

print "\n";

try {
  $definicion = $etm->getDefinition('tec_gui');
  printf("      el tipo de entidad esta declarado: tabla base %s, datos %s\n",
    $definicion->getBaseTable(),
    $definicion->getDataTable() ?: '(no tiene)');

  $n = (int) $etm->getStorage('tec_gui')->getQuery()->accessCheck(FALSE)->count()->execute();
  printf("      fichas que Drupal ve: %d\n", $n);
}
catch (\Throwable $e) {
  printf("      Drupal no puede con el: %s\n", $e->getMessage());
}

print "\n";
