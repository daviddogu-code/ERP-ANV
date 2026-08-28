<?php

/**
 * @file
 * Prueba de verdad el importador de materiales y luego borra lo que ha metido.
 *
 * Uso:
 *   php vendor/bin/drush.php scr scripts/probar-la-importacion-de-materiales.php
 *   php vendor/bin/drush.php scr scripts/probar-la-importacion-de-materiales.php -- --de-verdad
 *
 * Validar el mapeo leyendo la configuracion no demuestra que importe. Esto lo
 * demuestra: coge la plantilla recien generada, la importa por el modulo Feeds
 * como lo haria una persona, comprueba campo por campo que ha llegado cada dato,
 * y lo borra todo.
 *
 * Dos cosas mas que comprueba, que son las que mas importan:
 *
 *   - Que una fila con una unidad inventada se RECHAZA en vez de crear la unidad.
 *     Es lo que metio los terminos duplicados en su dia. Se cuentan los terminos
 *     de tec_units y tec_materials antes y despues, y tienen que salir iguales.
 *   - Que el proveedor entra. El campo field_tec_vendor filtra las organizaciones
 *     por la vista tec_crm_references, que tiene el acceso restringido por rol.
 *
 * Sobre ese segundo punto hay que tener cuidado al montar la prueba, porque el
 * usuario con el que corre una importacion NO es quien la lanza: Feeds se cambia
 * al AUTOR de la ficha de importacion (FeedsExecutable::switchAccount, que hace
 * switchTo($feed->getOwnerId())). Asi que la ficha de usar y tirar se crea con el
 * mismo autor que la ficha 4, la de verdad; si se dejara en anonimo, el proveedor
 * se rechazaria en todas las filas y la prueba diria que el importador esta roto
 * cuando lo que estaria mal es la prueba.
 *
 * No se toca la ficha 4. Se crea una ficha nueva de usar y tirar con el mismo
 * tipo de importador y el mismo autor, y se borra al final, para no ensuciarle a
 * la ficha buena ni el fichero de origen ni el recuento de importados.
 *
 * Con --de-verdad importa y borra. Sin nada, solo dice lo que haria.
 */

use Drupal\feeds\Entity\Feed;

const IMPORTADOR = 'tec_inventory_csv_importer';
const FICHA_BUENA = 4;
const MARCA = 'ZZ PRUEBA';

$deVerdad = in_array('--de-verdad', $extra ?? [], TRUE);

$gestor = \Drupal::entityTypeManager();
$almacenTerminos = $gestor->getStorage('taxonomy_term');
$sistema = \Drupal::service('file_system');

print "\n" . str_repeat('=', 82) . "\n";
print $deVerdad
  ? " DE VERDAD: se importa y despues se borra todo lo importado\n"
  : " ENSAYO: solo se dice que se probaria. Anade -- --de-verdad para probarlo\n";
print str_repeat('=', 82) . "\n\n";

// ---------------------------------------------------------------------------
// 1. Preparar el fichero de prueba a partir de la plantilla.
// ---------------------------------------------------------------------------
print "  --- 1. el fichero de prueba ---\n\n";

$plantilla = \Drupal::root() . '/docs/plantillas/plantilla-materiales.csv';
if (!is_file($plantilla)) {
  print "      no existe docs/plantillas/plantilla-materiales.csv\n";
  print "      lanza antes scripts/generar-la-plantilla-de-materiales.php\n\n";
  return;
}

$mano = fopen($plantilla, 'r');
$cabecera = fgetcsv($mano);
$filas = [];
while (($fila = fgetcsv($mano)) !== FALSE) {
  if ($fila === [NULL] || $fila === FALSE) {
    continue;
  }
  $filas[] = $fila;
}
fclose($mano);

printf("      la plantilla trae %d columnas y %d filas de ejemplo\n", count($cabecera), count($filas));

// Se marcan los nombres para poder distinguir despues lo que ha metido la prueba
// de lo que ya hubiera, y no borrar nada que no sea suyo.
$posicionNombre = array_search('Material name', $cabecera, TRUE);
$posicionUnidad = array_search('Purchase UoM', $cabecera, TRUE);
if ($posicionNombre === FALSE || $posicionUnidad === FALSE) {
  print "      la plantilla no trae las columnas Material name y Purchase UoM: algo va mal\n\n";
  return;
}

foreach ($filas as $indice => $fila) {
  $filas[$indice][$posicionNombre] = MARCA . ' ' . $fila[$posicionNombre];
}

// La fila trampa: una unidad que no existe. Tiene que ser rechazada.
$trampa = $filas[0];
$trampa[$posicionNombre] = MARCA . ' unidad inventada';
$trampa[$posicionUnidad] = 'Metro Inventado';
$filas[] = $trampa;

printf("      se anade 1 fila trampa con la unidad \"Metro Inventado\", que no existe\n");
printf("      total a importar: %d filas, de las que 1 debe fallar\n", count($filas));

$nombresEsperados = [];
foreach (array_slice($filas, 0, count($filas) - 1) as $fila) {
  $nombresEsperados[] = $fila[$posicionNombre];
}

// ---------------------------------------------------------------------------
// 2. El estado de antes.
// ---------------------------------------------------------------------------
print "\n  --- 2. como esta antes ---\n\n";

$contar = static function (string $vid) use ($almacenTerminos): int {
  return (int) $almacenTerminos->getQuery()->accessCheck(FALSE)
    ->condition('vid', $vid)->count()->execute();
};

$antes = [];
foreach (['tec_inventory', 'tec_units', 'tec_materials', 'tec_colors'] as $vid) {
  $antes[$vid] = $contar($vid);
  printf("      %-16s %d terminos\n", $vid, $antes[$vid]);
}

// Si ya hubiera restos de una prueba anterior, mejor pararse.
$restos = $almacenTerminos->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->condition('name', MARCA . '%', 'LIKE')
  ->execute();
if ($restos) {
  printf("\n      OJO: ya hay %d materiales con la marca \"%s\" de una prueba anterior.\n", count($restos), MARCA);
  print "      Se borraran tambien al final.\n";
}

if (!$deVerdad) {
  print "\n  --- lo que se haria ---\n\n";
  print "      1. escribir el CSV de prueba en private://feeds\n";
  printf("      2. crear una ficha de usar y tirar del tipo %s\n", IMPORTADOR);
  print "      3. importar como el usuario 1\n";
  print "      4. comprobar campo por campo los materiales creados\n";
  print "      5. comprobar que no ha aparecido ninguna unidad ni tipo nuevo\n";
  print "      6. borrar los materiales, la ficha y el fichero\n";
  print "\n" . str_repeat('=', 82) . "\n";
  print " SE HARIA. Anade -- --de-verdad para probarlo\n";
  print str_repeat('=', 82) . "\n\n";
  return;
}

// ---------------------------------------------------------------------------
// 3. Escribir el fichero y crear la ficha de usar y tirar.
// ---------------------------------------------------------------------------
print "\n  --- 3. la ficha de usar y tirar ---\n\n";

$directorio = 'private://feeds';
$sistema->prepareDirectory($directorio, $sistema::CREATE_DIRECTORY);
$uri = $directorio . '/prueba-plantilla-materiales-' . date('Ymd-His') . '.csv';
$ruta = $sistema->realpath($directorio) . '/' . basename($uri);

$mano = fopen($ruta, 'w');
fputcsv($mano, $cabecera);
foreach ($filas as $fila) {
  fputcsv($mano, $fila);
}
fclose($mano);
printf("      fichero: %s (%s bytes)\n", $uri, number_format(filesize($ruta)));

// El autor se copia de la ficha buena, porque es el autor quien importa. Y se
// deja el fid del lector a 0 a proposito: asi al borrar la ficha, Feeds no
// intenta borrar una entidad de fichero que no existe.
$fichaBuena = $gestor->getStorage('feeds_feed')->load(FICHA_BUENA);
if (!$fichaBuena) {
  printf("      no existe la ficha %d: no se puede copiar su autor\n\n", FICHA_BUENA);
  return;
}

$ficha = Feed::create([
  'type' => IMPORTADOR,
  'title' => MARCA . ' plantilla de materiales',
  'source' => $uri,
  'status' => 1,
  'uid' => $fichaBuena->getOwnerId(),
]);
$ficha->save();
printf("      ficha creada: %d, \"%s\"\n", $ficha->id(), $ficha->label());
printf("      autor copiado de la ficha %d: %s (uid %s)\n",
  FICHA_BUENA,
  $ficha->getOwner() && $ficha->getOwner()->id() ? $ficha->getOwner()->getAccountName() : 'Anonimo',
  $ficha->getOwnerId());

// ---------------------------------------------------------------------------
// 4. Importar.
// ---------------------------------------------------------------------------
print "\n  --- 4. importar ---\n\n";

// Los avisos de Feeds van al registro, y ahi es donde se leen: el objeto de
// estado los guarda en una propiedad protegida sin lector.
$desde = \Drupal::time()->getRequestTime();

$fallo = NULL;
try {
  $ficha->import();
}
catch (\Throwable $error) {
  $fallo = $error;
  printf("      la importacion ha terminado con error: %s\n", strip_tags($error->getMessage()));
}

foreach (['fetch', 'parse', 'process'] as $etapa) {
  $estado = $ficha->getState($etapa);
  printf("      %-8s creados %d, actualizados %d, saltados %d, fallidos %d\n",
    $etapa, $estado->created, $estado->updated, $estado->skipped, $estado->failed);
}

print "\n      lo que ha quedado apuntado en el registro:\n";
$apuntes = \Drupal::database()->select('watchdog', 'w')
  ->fields('w', ['severity', 'message', 'variables'])
  ->condition('w.type', 'feeds')
  ->condition('w.timestamp', $desde - 1, '>=')
  ->orderBy('w.wid')
  ->execute()->fetchAll();

if (!$apuntes) {
  print "          nada, ni un aviso\n";
}
foreach ($apuntes as $apunte) {
  $sustituciones = [];
  foreach (@unserialize($apunte->variables) ?: [] as $clave => $valor) {
    if (is_scalar($valor) || $valor instanceof \Stringable) {
      $sustituciones[$clave] = strip_tags((string) $valor);
    }
  }
  $texto = strip_tags(strtr($apunte->message, $sustituciones));
  $texto = preg_replace('/\s+/', ' ', $texto);
  foreach (explode("\n", wordwrap('[' . $apunte->severity . '] ' . trim($texto), 92)) as $trozo) {
    printf("          %s\n", trim($trozo));
  }
}

// A partir de aqui ya hay cosas metidas en la base, asi que la limpieza tiene que
// pasar aunque algo reviente por el camino.
$creados = [];
$camposVacios = [];
$inventada = [];
$trampaCreada = [];

try {

// ---------------------------------------------------------------------------
// 5. Comprobar campo por campo.
// ---------------------------------------------------------------------------
print "\n  --- 5. que ha llegado a cada campo ---\n\n";

$tipo = $gestor->getStorage('feeds_feed_type')->load(IMPORTADOR);
$origenes = $tipo->getCustomSources();
$destinos = [];
foreach ($tipo->getMappings() as $mapeo) {
  foreach ($mapeo['map'] as $origen) {
    if (isset($origenes[$origen])) {
      $destinos[$origenes[$origen]['value']] = $mapeo['target'];
    }
  }
}

$creados = $almacenTerminos->loadByProperties([
  'vid' => 'tec_inventory',
  'name' => $nombresEsperados,
]);
printf("      materiales creados: %d de %d esperados\n\n", count($creados), count($nombresEsperados));

$camposVacios = [];
foreach ($creados as $material) {
  printf("      %s (tid %d)\n", $material->label(), $material->id());
  foreach ($destinos as $columna => $destino) {
    if ($destino === 'name') {
      continue;
    }
    $elemento = $material->get($destino);
    if ($elemento->isEmpty()) {
      printf("          %-32s VACIO\n", $columna);
      $camposVacios[$destino] = TRUE;
      continue;
    }
    // Si es una referencia se ensena la etiqueta de lo que apunta, que es lo que
    // de verdad hay que comprobar.
    $primero = $elemento->first();
    if (isset($primero->entity) && $primero->entity) {
      printf("          %-32s %s (%s %s)\n", $columna, $primero->entity->label(),
        $primero->entity->getEntityTypeId(), $primero->entity->id());
    }
    else {
      printf("          %-32s %s\n", $columna, substr((string) $primero->value, 0, 60));
    }
  }
  print "\n";
}

// ---------------------------------------------------------------------------
// 6. Lo que de verdad importa: que no se ha creado basura.
// ---------------------------------------------------------------------------
print "  --- 6. la fila trampa y la autocreacion ---\n\n";

$despues = [];
foreach (['tec_units', 'tec_materials', 'tec_colors'] as $vid) {
  $despues[$vid] = $contar($vid);
  $diferencia = $despues[$vid] - $antes[$vid];
  printf("      %-16s antes %d, despues %d  %s\n", $vid, $antes[$vid], $despues[$vid],
    $diferencia === 0 ? 'igual: no se ha creado nada' : 'SE HAN CREADO ' . $diferencia . ' TERMINOS');
}

$inventada = $almacenTerminos->loadByProperties(['vid' => 'tec_units', 'name' => 'Metro Inventado']);
printf("\n      la unidad inventada %s\n", $inventada
  ? 'SE HA CREADO: la autocreacion sigue encendida'
  : 'no se ha creado: la autocreacion esta apagada');

$trampaCreada = $almacenTerminos->loadByProperties([
  'vid' => 'tec_inventory',
  'name' => MARCA . ' unidad inventada',
]);
printf("      la fila trampa %s\n", $trampaCreada
  ? 'ha entrado, cosa que no deberia'
  : 'ha sido rechazada, como debe ser');

}
finally {

// ---------------------------------------------------------------------------
// 7. Borrar todo lo de la prueba.
// ---------------------------------------------------------------------------
print "\n  --- 7. limpiar ---\n\n";

// Se borra por la marca del nombre, para no llevarse nunca nada que no sea de la
// prueba. Si una pasada anterior se quedo a medias, esto tambien lo recoge.
$aBorrar = $almacenTerminos->loadMultiple(
  $almacenTerminos->getQuery()->accessCheck(FALSE)
    ->condition('vid', 'tec_inventory')
    ->condition('name', MARCA . '%', 'LIKE')
    ->execute()
);
foreach ($aBorrar as $material) {
  printf("      borrado el material %d, \"%s\"\n", $material->id(), $material->label());
}
$almacenTerminos->delete($aBorrar);
if (!$aBorrar) {
  print "      no habia ningun material que borrar\n";
}

// Aqui no hay una tabla feeds_item suelta: el rastro de que ficha trajo cada cosa
// vive en las tablas de campo *__feeds_item, una por tipo de entidad. Se buscan de
// verdad en vez de suponer el nombre.
$base = \Drupal::database();
$tablasRastro = [];
foreach ($base->query("SHOW TABLES LIKE '%\\_\\_feeds\\_item'")->fetchCol() as $tabla) {
  $tablasRastro[] = $tabla;
}

foreach ($gestor->getStorage('feeds_feed')->loadMultiple() as $otra) {
  if (!str_starts_with((string) $otra->label(), MARCA)) {
    continue;
  }
  $idFicha = $otra->id();
  $etiqueta = $otra->label();
  $otra->delete();

  $restantes = 0;
  foreach ($tablasRastro as $tabla) {
    $restantes += (int) $base->select($tabla, 't')
      ->condition('t.feeds_item_target_id', $idFicha)
      ->countQuery()->execute()->fetchField();
  }
  printf("      borrada la ficha %d, \"%s\" (le quedan %d rastros en %d tablas *__feeds_item)\n",
    $idFicha, $etiqueta, $restantes, count($tablasRastro));
}

// Los ficheros de prueba, incluidos los que dejara una pasada anterior.
foreach (glob($sistema->realpath($directorio) . '/prueba-plantilla-materiales-*.csv') ?: [] as $sobrante) {
  unlink($sobrante);
  printf("      borrado el fichero %s\n", basename($sobrante));
}

// ---------------------------------------------------------------------------
// 8. Que todo esta como estaba.
// ---------------------------------------------------------------------------
print "\n  --- 8. como queda ---\n\n";

$todoIgual = TRUE;
foreach (['tec_inventory', 'tec_units', 'tec_materials', 'tec_colors'] as $vid) {
  $ahora = $contar($vid);
  $igual = $ahora === $antes[$vid];
  printf("      %-16s antes %d, ahora %d  %s\n", $vid, $antes[$vid], $ahora,
    $igual ? 'igual' : 'DISTINTO');
  $todoIgual = $todoIgual && $igual;
}

$fichas = (int) \Drupal::database()->select('feeds_feed', 'f')->countQuery()->execute()->fetchField();
printf("      fichas de importacion: %d\n", $fichas);

}

print "\n" . str_repeat('=', 82) . "\n";
if ($todoIgual && count($creados) === count($nombresEsperados) && !$inventada && !$trampaCreada && !$camposVacios && !$fallo) {
  print " BIEN: importa los 21 campos, rechaza la basura y no deja rastro\n";
}
else {
  print " MIRAR: algo no ha salido como se esperaba, esta arriba\n";
  if ($camposVacios) {
    printf("   campos que han quedado vacios: %s\n", implode(', ', array_keys($camposVacios)));
  }
}
print str_repeat('=', 82) . "\n\n";
