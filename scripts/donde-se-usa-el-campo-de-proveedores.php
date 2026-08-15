<?php

/**
 * @file
 * Donde se usa field_tec_suppliers, el campo de proveedores viejo de los materiales.
 *
 *   php vendor\bin\drush.php scr scripts/donde-se-usa-el-campo-de-proveedores.php
 *
 * Los materiales tienen dos campos de proveedor: field_tec_suppliers, el viejo,
 * que apunta al CRM y admite varios; y field_tec_vendor, el que se usa. El viejo
 * no tiene ni una fila, asi que sobra, pero esta cosido a la vista del listado de
 * materiales, que es la pantalla mas usada del ERP. Antes de tocarla hay que saber
 * exactamente por donde pasa el hilo:
 *
 *   - como columna en cada pantalla de la vista
 *   - como relacion, que es lo peligroso: otras columnas pueden colgar de ella
 *   - como filtro, orden o argumento
 *   - en las pantallas de ver y editar el material
 *
 * No cambia nada: solo cuenta.
 */

const CAMPO = 'field_tec_suppliers';
const NUEVO = 'field_tec_vendor';

$bd = \Drupal::database();

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

$titulo('Lo que guarda cada campo');
foreach ([CAMPO, NUEVO] as $nombre) {
  $tabla = 'taxonomy_term__' . $nombre;
  if (!$bd->schema()->tableExists($tabla)) {
    printf("  %-24s la tabla no existe\n", $nombre);
    continue;
  }
  $filas = (int) $bd->select($tabla, 't')->countQuery()->execute()->fetchField();
  $materiales = (int) $bd->select($tabla, 't')->distinct()->fields('t', ['entity_id'])->countQuery()->execute()->fetchField();
  printf("  %-24s %d filas, en %d materiales\n", $nombre, $filas, $materiales);
}

// Donde aparece en las vistas, tipo de uso por tipo de uso.
$titulo('En las vistas');
$tiposDeMano = ['field' => 'columna', 'filter' => 'filtro', 'sort' => 'orden', 'argument' => 'argumento', 'relationship' => 'relacion'];
$usos = [];
$colgados = [];

foreach (\Drupal::entityTypeManager()->getStorage('view')->loadMultiple() as $vista) {
  foreach ($vista->get('display') as $idPantalla => $pantalla) {
    $opciones = $pantalla['display_options'] ?? [];
    foreach ($tiposDeMano as $tipo => $comoSeLlama) {
      foreach ($opciones[$tipo . 's'] ?? [] as $clave => $mano) {
        if (($mano['field'] ?? NULL) === CAMPO || str_starts_with((string) ($mano['table'] ?? ''), 'taxonomy_term__' . CAMPO)) {
          $usos[] = [$vista->id(), $idPantalla, $comoSeLlama, $clave];
        }
      }
    }
    // Lo que cuelga de la relacion, que es lo que se rompe si se quita.
    foreach ($tiposDeMano as $tipo => $comoSeLlama) {
      foreach ($opciones[$tipo . 's'] ?? [] as $clave => $mano) {
        $relacion = $mano['relationship'] ?? 'none';
        if ($relacion !== 'none' && str_starts_with($relacion, CAMPO)) {
          $colgados[] = [$vista->id(), $idPantalla, $comoSeLlama, $clave, $relacion];
        }
      }
    }
    // Y en las columnas de la tabla, que se guardan aparte del campo.
    $estilo = $opciones['style']['options'] ?? [];
    foreach (['columns', 'info'] as $donde) {
      if (isset($estilo[$donde][CAMPO])) {
        $usos[] = [$vista->id(), $idPantalla, 'tabla (' . $donde . ')', CAMPO];
      }
    }
  }
}

if (!$usos) {
  echo "  en ninguna\n";
}
else {
  printf("  %-18s %-16s %-18s %s\n", 'vista', 'pantalla', 'como', 'clave');
  foreach ($usos as $uso) {
    printf("  %-18s %-16s %-18s %s\n", ...$uso);
  }
}

$titulo('Lo que cuelga de la relacion, y se rompe si se quita');
if (!$colgados) {
  echo "  nada cuelga de ella\n";
}
else {
  printf("  %-18s %-16s %-12s %-28s %s\n", 'vista', 'pantalla', 'como', 'clave', 'relacion');
  foreach ($colgados as $colgado) {
    printf("  %-18s %-16s %-12s %-28s %s\n", ...$colgado);
  }
}

// En las pantallas de ver y de editar el material.
$titulo('En las pantallas del material');
foreach (['entity_view_display' => 'ver', 'entity_form_display' => 'editar'] as $tipo => $comoSeLlama) {
  foreach (\Drupal::entityTypeManager()->getStorage($tipo)->loadMultiple() as $pantalla) {
    if (!str_starts_with($pantalla->id(), 'taxonomy_term.tec_inventory.')) {
      continue;
    }
    $contenido = $pantalla->get('content') ?? [];
    $ocultos = $pantalla->get('hidden') ?? [];
    if (isset($contenido[CAMPO])) {
      printf("  %-8s %-24s se ensena, en la posicion %s\n", $comoSeLlama, $pantalla->getMode(), $contenido[CAMPO]['weight'] ?? '?');
    }
    elseif (isset($ocultos[CAMPO])) {
      printf("  %-8s %-24s esta pero escondido\n", $comoSeLlama, $pantalla->getMode());
    }
  }
}

// Quien mas lo nombra: procesos de ECA, importadores, modulos propios.
$titulo('Quien mas lo nombra');
$otros = [];
foreach (['eca' => 'eca', 'feeds_feed_type' => 'importador', 'field_config' => 'campo'] as $tipo => $comoSeLlama) {
  if (!\Drupal::entityTypeManager()->hasDefinition($tipo)) {
    continue;
  }
  foreach (\Drupal::entityTypeManager()->getStorage($tipo)->loadMultiple() as $cosa) {
    if (str_contains(serialize($cosa->toArray()), CAMPO)) {
      $otros[] = $comoSeLlama . ': ' . $cosa->id();
    }
  }
}
if (!$otros) {
  echo "  nadie mas\n";
}
foreach ($otros as $otro) {
  echo '  ' . $otro . "\n";
}

echo "\n" . str_repeat('=', 78) . "\n";
printf("Resumen: %d usos en vistas, %d columnas colgadas de la relacion.\n", count($usos), count($colgados));
echo str_repeat('=', 78) . "\n\n";
