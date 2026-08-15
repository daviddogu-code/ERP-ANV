<?php

/**
 * @file
 * Busca terminos duplicados por espacios o mayusculas, y dice quien los usa.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-terminos-estan-duplicados.php
 *
 * La creacion automatica de terminos del importador metio duplicados: nombres
 * que solo se diferencian en un espacio al final o en una mayuscula, como el
 * color "Blue " frente a "Blue". Con 857 materiales por importar, un duplicado
 * de estos no es un detalle: parte el catalogo en dos mitades que el ERP cree
 * distintas.
 *
 * Encontrarlos es facil. Lo que decide si se pueden borrar es la otra mitad:
 * quien los usa. Un termino en uso no se borra, porque el campo que lo apunta se
 * queda apuntando al vacio y eso es lo que para en seco a los automatismos de
 * ECA. Asi que aqui se recorren todos los campos de referencia del ERP para ver
 * quien apunta a cada duplicado.
 *
 * No toca nada. Solo mira.
 */

$bd = \Drupal::database();
$esquema = $bd->schema();
$gestor = \Drupal::entityTypeManager();

print "\n";

// ---------------------------------------------------------------------------
// 1. Todos los terminos, agrupados por nombre normalizado.
// ---------------------------------------------------------------------------
// Normalizar es quitar los espacios de los extremos, juntar los de dentro y
// bajar a minusculas. Dos terminos que caigan en el mismo grupo son el mismo
// nombre escrito de dos maneras.
print "  --- 1. terminos que son el mismo nombre escrito de dos maneras ---\n\n";

$terminos = $bd->query('
  SELECT d.tid, d.vid, d.name
  FROM {taxonomy_term_field_data} d
  ORDER BY d.vid, d.name
')->fetchAll();

printf("      %d terminos en total, en %d vocabularios\n\n",
  count($terminos),
  count(array_unique(array_column($terminos, 'vid'))));

$grupos = [];
foreach ($terminos as $t) {
  $normal = strtolower(preg_replace('/\s+/', ' ', trim($t->name)));
  $grupos[$t->vid . '|' . $normal][] = $t;
}

$duplicados = [];
foreach ($grupos as $clave => $grupo) {
  if (count($grupo) < 2) {
    continue;
  }
  [$vid, $normal] = explode('|', $clave, 2);
  printf("      vocabulario %-18s nombre \"%s\"\n", $vid, $normal);
  foreach ($grupo as $t) {
    printf("        tid %-6s nombre [%s]%s\n",
      $t->tid,
      $t->name,
      // Se marca el que tiene basura en los extremos: ese es el intruso.
      ($t->name !== trim($t->name)) ? '   <-- LLEVA ESPACIOS' : '');
    $duplicados[$t->tid] = $t;
  }
  print "\n";
}

if (!$duplicados) {
  print "      ninguno\n\n";
}

// ---------------------------------------------------------------------------
// 2. Los terminos con espacios en los extremos, duplicados o no.
// ---------------------------------------------------------------------------
// Un nombre con un espacio detras es basura aunque no tenga gemelo: nadie lo
// escribe a mano, sale de una importacion mal hecha.
print "  --- 2. terminos con espacios sobrantes en los extremos ---\n\n";

$conEspacios = [];
foreach ($terminos as $t) {
  if ($t->name !== trim($t->name)) {
    $conEspacios[$t->tid] = $t;
    $gemelo = NULL;
    foreach ($terminos as $otro) {
      if ($otro->tid !== $t->tid && $otro->vid === $t->vid && trim($otro->name) === trim($t->name)) {
        $gemelo = $otro;
        break;
      }
    }
    printf("      tid %-6s %-18s [%s]  %s\n",
      $t->tid,
      $t->vid,
      $t->name,
      $gemelo ? sprintf('tiene gemelo limpio: tid %s [%s]', $gemelo->tid, $gemelo->name) : 'SIN GEMELO: si se borra, se pierde el nombre');
  }
}
if (!$conEspacios) {
  print "      ninguno\n";
}

// ---------------------------------------------------------------------------
// 3. Quien usa cada sospechoso.
// ---------------------------------------------------------------------------
// Se recorren todos los almacenes de campo del ERP que apunten a terminos, se
// mira su tabla y se cuenta. Ademas del campo de referencia, hay dos sitios mas
// donde un tid puede estar escrito: la columna parent de la taxonomia y la
// configuracion (filtros de vistas, procesos de ECA, valores por defecto).
print "\n  --- 3. quien usa cada sospechoso ---\n\n";

$sospechosos = $duplicados + $conEspacios;

if (!$sospechosos) {
  print "      no hay sospechosos\n\n";
  return;
}

// Todas las tablas de campos que guarden una referencia a termino.
$tablasDeReferencia = [];
foreach ($gestor->getStorage('field_storage_config')->loadMultiple() as $almacen) {
  if (!in_array($almacen->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
    continue;
  }
  if ($almacen->getSetting('target_type') !== 'taxonomy_term') {
    continue;
  }
  $entidad = $almacen->getTargetEntityTypeId();
  $nombre = $almacen->getName();
  foreach ([$entidad . '__' . $nombre, $entidad . '_revision__' . $nombre] as $tabla) {
    if ($esquema->tableExists($tabla)) {
      $tablasDeReferencia[$tabla] = $nombre . '_target_id';
    }
  }
}
printf("      %d tablas de campos de referencia a terminos que revisar\n\n", count($tablasDeReferencia));

// La configuracion entera como texto, para pillar el tid escrito a mano.
$config = [];
foreach ($bd->select('config', 'c')->fields('c', ['name', 'data'])->execute() as $fila) {
  $config[$fila->name] = (string) $fila->data;
}

printf("      %-8s %-18s %-24s %s\n", 'tid', 'vocabulario', 'nombre', 'quien lo usa');
print '      ' . str_repeat('-', 100) . "\n";

$sinUsar = [];

foreach ($sospechosos as $tid => $t) {
  $usos = [];

  foreach ($tablasDeReferencia as $tabla => $columna) {
    if (!$esquema->fieldExists($tabla, $columna)) {
      continue;
    }
    $n = (int) $bd->select($tabla, 'x')->condition($columna, $tid)->countQuery()->execute()->fetchField();
    if ($n) {
      $usos[] = sprintf('%s (%d)', $tabla, $n);
    }
  }

  // Hijos en la propia taxonomia.
  $hijos = (int) $bd->select('taxonomy_term__parent', 'p')
    ->condition('parent_target_id', $tid)
    ->countQuery()->execute()->fetchField();
  if ($hijos) {
    $usos[] = sprintf('%d terminos lo tienen de padre', $hijos);
  }

  // Y menciones en configuracion. Se busca el tid rodeado de comillas o de los
  // separadores que usa la configuracion serializada, para no cazar el 27
  // dentro del 127.
  foreach ($config as $nombre => $datos) {
    foreach (['"' . $tid . '"', ":\"$tid\"", "'" . $tid . "'"] as $patron) {
      if (str_contains($datos, $patron)) {
        $usos[] = 'config ' . $nombre;
        break;
      }
    }
  }

  printf("      %-8s %-18s %-24s %s\n",
    $tid,
    $t->vid,
    '[' . $t->name . ']',
    $usos ? implode('; ', array_unique($usos)) : 'NADIE');

  if (!$usos) {
    $sinUsar[$tid] = $t;
  }
}

printf("\n      %d sospechosos, %d sin usar\n\n", count($sospechosos), count($sinUsar));

// ---------------------------------------------------------------------------
// 4. El vocabulario de unidades y el de tipos, enteros.
// ---------------------------------------------------------------------------
// Son los dos que el importador podia llenar al vuelo, y los que la plantilla
// tiene que respetar: lo que ponga el Excel en esas columnas se busca por
// nombre exacto contra estas listas.
print "  --- 4. las listas contra las que buscara el importador ---\n\n";

foreach (['tec_units', 'tec_materials', 'tec_colors'] as $vid) {
  $suyos = array_filter($terminos, static fn($t) => $t->vid === $vid);
  printf("      %s (%d):\n", $vid, count($suyos));
  $nombres = [];
  foreach ($suyos as $t) {
    $nombres[] = sprintf('%s[%s]', $t->tid, $t->name);
  }
  // En columnas, que si no no se lee.
  foreach (array_chunk($nombres, 6) as $fila) {
    print '        ' . implode('  ', $fila) . "\n";
  }
  print "\n";
}
