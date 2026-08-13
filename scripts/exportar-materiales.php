<?php

/**
 * Saca los materiales a un CSV antes de borrarlos.
 *
 * Sirve para dos cosas, y por eso se hace justo antes del borrado:
 *
 *   - Es el banco de pruebas del importador. Cuando se arregle, se podra
 *     ensayar contra datos reales en vez de inventados.
 *   - Es la semilla de la plantilla de Excel: ensena, campo por campo, como son
 *     de verdad los datos que el importador tendra que tragar.
 *
 * Sale fuera del proyecto a proposito. Lleva costes y proveedores dentro, y el
 * sitio de eso no es una carpeta que se sincroniza con GitHub.
 *
 * Uso: php vendor/bin/drush.php scr scripts/exportar-materiales.php
 */

const VOCABULARIO = 'tec_inventory';
const DESTINO = 'C:\laragon\backups\materiales-antes-del-borrado';

$etm = \Drupal::entityTypeManager();
$efm = \Drupal::service('entity_field.manager');

if (!is_dir(DESTINO)) {
  mkdir(DESTINO, 0777, TRUE);
}

$definiciones = $efm->getFieldDefinitions('taxonomy_term', VOCABULARIO);

// Cabecera: primero lo basico, despues todos los campos propios en orden.
$columnas = ['tid' => 'tid', 'name' => 'name', 'description' => 'description'];
foreach ($definiciones as $nombre => $definicion) {
  if (!str_starts_with($nombre, 'field_')) {
    continue;
  }
  $columnas[$nombre] = $nombre . ' (' . $definicion->getLabel() . ')';
}

printf("  %d columnas: 3 basicas y %d campos propios\n", count($columnas), count($columnas) - 3);

$ids = $etm->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', VOCABULARIO)
  ->sort('name')
  ->execute();

printf("  %d materiales que exportar\n", count($ids));

$ruta = DESTINO . DIRECTORY_SEPARATOR . 'materiales.csv';
$fichero = fopen($ruta, 'w');

// Con marca de orden de bytes, que si no Excel se come los acentos.
fwrite($fichero, "\xEF\xBB\xBF");
fputcsv($fichero, array_values($columnas));

$almacen = $etm->getStorage('taxonomy_term');
$filas = 0;
$conValor = array_fill_keys(array_keys($columnas), 0);

foreach (array_chunk(array_values($ids), 200) as $trozo) {
  foreach ($almacen->loadMultiple($trozo) as $termino) {
    $fila = [];
    foreach (array_keys($columnas) as $campo) {
      $texto = '';

      if ($campo === 'tid') {
        $texto = (string) $termino->id();
      }
      elseif ($campo === 'name') {
        $texto = (string) $termino->label();
      }
      elseif ($campo === 'description') {
        $texto = trim(strip_tags((string) $termino->getDescription()));
      }
      elseif ($termino->hasField($campo)) {
        $partes = [];
        foreach ($termino->get($campo) as $elemento) {
          $valor = $elemento->getValue();

          // Referencias: se escribe la etiqueta, que es lo que un humano
          // pondria en el Excel, con el identificador detras entre corchetes.
          if (isset($valor['target_id'])) {
            $referida = $elemento->entity ?? NULL;
            $partes[] = $referida
              ? $referida->label() . ' [' . $valor['target_id'] . ']'
              : '[' . $valor['target_id'] . ' BORRADO]';
          }
          elseif (isset($valor['value'])) {
            $partes[] = is_scalar($valor['value']) ? (string) $valor['value'] : json_encode($valor['value']);
          }
          elseif (isset($valor['uri'])) {
            $partes[] = $valor['uri'];
          }
          else {
            $partes[] = json_encode($valor);
          }
        }
        $texto = implode(' | ', $partes);
      }

      if ($texto !== '') {
        $conValor[$campo]++;
      }
      $fila[] = $texto;
    }

    fputcsv($fichero, $fila);
    $filas++;
  }
  print '.';
}

fclose($fichero);

printf("\n\n  escritas %d filas en %s\n", $filas, $ruta);
printf("  tamano: %.1f KB\n\n", filesize($ruta) / 1024);

// Que columnas traen datos de verdad. Es la mitad util del ejercicio: dice
// cuales de los 56 campos estan rellenos hoy y cuales no, o sea por donde
// tendra que empezar el arreglo del importador.
print "  columnas con datos (de " . $filas . " materiales):\n\n";
arsort($conValor);
foreach ($conValor as $campo => $n) {
  $porcentaje = $filas ? round($n * 100 / $filas) : 0;
  printf("      %-44s %5d  %3d%%\n", $campo, $n, $porcentaje);
}

print "\n";
