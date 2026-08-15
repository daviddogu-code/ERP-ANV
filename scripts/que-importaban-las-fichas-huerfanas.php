<?php

/**
 * @file
 * Averigua a donde escribian las fichas de importacion 5 y 6.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-importaban-las-fichas-huerfanas.php
 *
 * Las fichas 5 "Primo products" y 6 "Trust products" declaran el tipo de
 * importador tec_products_csv_importer, que ya no existe. Con el tipo borrado se
 * fue tambien el dato que importa: en que entidad escribia. Y eso decide si al
 * borrar las fichas se pierde algo o no.
 *
 * La pista es que entre las trece tablas de tec_gui -el producto viejo del
 * programador anterior, retirado del ERP- habia una llamada tec_gui__feeds_item.
 * Feeds cuelga una columna feeds_item de cada tipo de entidad al que apunte
 * algun importador, asi que la existencia de esa tabla dice que hubo un
 * importador escribiendo en tec_gui, y su ausencia en tec_product dice que
 * nunca hubo ninguno escribiendo productos nuevos.
 *
 * Aqui se juntan todas las pruebas que quedan: el volcado de antes de quitar
 * tec_gui, el registro del sitio, los ficheros de origen y la configuracion
 * superviviente. No toca nada.
 */

$bd = \Drupal::database();
$esquema = $bd->schema();
$raiz = \Drupal::root();

print "\n";

// ---------------------------------------------------------------------------
// 1. Que tablas *__feeds_item hay hoy, y cuales habia antes de quitar tec_gui.
// ---------------------------------------------------------------------------
// Feeds anade el campo base feeds_item a los tipos de entidad que tengan algun
// importador apuntando a ellos. La lista de tablas es, por tanto, la lista de
// destinos que ha tenido Feeds en este sitio alguna vez.
print "  --- 1. destinos que ha tenido Feeds, segun las tablas feeds_item ---\n\n";

print "      hoy:\n";
foreach ($bd->query("SHOW TABLES LIKE '%feeds_item%'")->fetchCol() as $tabla) {
  $n = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  printf("        %-44s %6d filas\n", $tabla, $n);
}

print "\n      importadores definidos hoy y su procesador:\n";
foreach (\Drupal::configFactory()->listAll('feeds.feed_type.') as $nombre) {
  $datos = \Drupal::configFactory()->get($nombre)->getRawData();
  printf("        %-44s %s\n", $datos['id'] ?? '?', $datos['processor'] ?? '?');
}

// ---------------------------------------------------------------------------
// 2. El volcado de antes de quitar tec_gui.
// ---------------------------------------------------------------------------
// Es la unica foto que queda de las trece tablas de tec_gui. Se busca dentro,
// sin descomprimirlo a disco, la definicion de tec_gui__feeds_item y sus
// inserciones, y cualquier mencion al tipo de importador desaparecido.
print "\n  --- 2. dentro del volcado de antes de quitar tec_gui ---\n\n";

$volcado = $raiz . '/backups/actatec_antes_de_quitar_gui_2026-08-15.sql.gz';

if (!is_file($volcado)) {
  printf("      no esta %s\n", $volcado);
}
else {
  printf("      %s (%s MB)\n\n", basename($volcado), number_format(filesize($volcado) / 1048576, 1));

  $buscar = [
    'tec_products_csv_importer' => 0,
    'tec_gui__feeds_item' => 0,
    'tec_product__feeds_item' => 0,
    'tec_gui_field_data' => 0,
  ];
  // Las lineas que interesan de verdad, guardadas para mirarlas por dentro.
  $lineasClave = [];

  $gz = gzopen($volcado, 'rb');
  $resto = '';
  while (!gzeof($gz)) {
    $trozo = $resto . gzread($gz, 1048576);
    $lineas = explode("\n", $trozo);
    // La ultima puede estar cortada por la mitad; se guarda para la vuelta que viene.
    $resto = array_pop($lineas);
    foreach ($lineas as $linea) {
      foreach (array_keys($buscar) as $aguja) {
        if (str_contains($linea, $aguja)) {
          $buscar[$aguja]++;
          if (count($lineasClave) < 40
            && ($aguja === 'tec_products_csv_importer'
              || str_starts_with($linea, 'INSERT INTO `tec_gui__feeds_item`')
              || str_starts_with($linea, 'CREATE TABLE `tec_gui__feeds_item`')
              || str_starts_with($linea, 'INSERT INTO `tec_gui`'))) {
            $lineasClave[] = substr($linea, 0, 900);
          }
        }
      }
    }
  }
  gzclose($gz);

  foreach ($buscar as $aguja => $n) {
    printf("      %-30s %4d lineas lo mencionan\n", $aguja, $n);
  }

  if ($lineasClave) {
    print "\n      lineas que importan:\n\n";
    foreach ($lineasClave as $linea) {
      print '        ' . $linea . "\n\n";
    }
  }
}

// ---------------------------------------------------------------------------
// 3. El registro del sitio.
// ---------------------------------------------------------------------------
// Las dos importaciones se ejecutaron en abril de 2024 (marcas de tiempo
// 1712565492 y 1712565499). Si el registro llega tan atras, dira que creo.
print "\n  --- 3. el registro del sitio ---\n\n";

if (!$esquema->tableExists('watchdog')) {
  print "      no hay tabla watchdog\n";
}
else {
  $total = (int) $bd->select('watchdog')->countQuery()->execute()->fetchField();
  $masViejo = (int) $bd->query('SELECT MIN(timestamp) FROM {watchdog}')->fetchField();
  printf("      %d entradas, la mas vieja del %s\n", $total, $masViejo ? date('Y-m-d', $masViejo) : '?');

  $canales = $bd->query("SELECT type, COUNT(*) n FROM {watchdog} WHERE type LIKE '%feed%' OR type LIKE '%gui%' GROUP BY type")->fetchAll();
  foreach ($canales as $c) {
    printf("      canal %-20s %d entradas\n", $c->type, $c->n);
  }

  // Cualquier mensaje que nombre al importador desaparecido o a las fichas.
  $sospechosos = $bd->query("
    SELECT wid, type, timestamp, message, variables
    FROM {watchdog}
    WHERE message LIKE '%tec_products_csv%' OR message LIKE '%Primo%' OR message LIKE '%Trust%'
       OR variables LIKE '%tec_products_csv%' OR variables LIKE '%Primo%' OR variables LIKE '%Trust%'
    ORDER BY wid LIMIT 20
  ")->fetchAll();
  printf("      menciones a las fichas o al tipo: %d\n", count($sospechosos));
  foreach ($sospechosos as $s) {
    printf("        %s  %-12s %s\n", date('Y-m-d', $s->timestamp), $s->type, substr(strtr($s->message, ["\n" => ' ']), 0, 120));
  }
}

// ---------------------------------------------------------------------------
// 4. Los ficheros de origen que declaran las dos fichas.
// ---------------------------------------------------------------------------
// Si el CSV sigue en disco, sus cabeceras dicen que columnas traia, y con eso
// se ve si eran productos del sistema viejo o materiales.
print "\n  --- 4. los ficheros de origen ---\n\n";

$fuentes = $bd->query('SELECT fid, title, source, config FROM {feeds_feed} WHERE fid IN (5, 6)')->fetchAll();
foreach ($fuentes as $f) {
  printf("      ficha %s (%s)\n", $f->fid, $f->title);
  printf("        source: %s\n", $f->source ?: '(vacio)');

  // El fetcher guarda el fid del fichero subido dentro de config.
  $config = @unserialize($f->config) ?: [];
  $fidFichero = $config['fetcher']['fid'] ?? NULL;
  if ($fidFichero) {
    $fichero = \Drupal::entityTypeManager()->getStorage('file')->load($fidFichero);
    printf("        fichero fid %s: %s\n", $fidFichero, $fichero ? $fichero->getFileUri() : 'la ficha del fichero ya no existe');
    if ($fichero) {
      $ruta = \Drupal::service('file_system')->realpath($fichero->getFileUri());
      printf("        en disco: %s\n", ($ruta && is_file($ruta)) ? $ruta : 'no esta');
      if ($ruta && is_file($ruta)) {
        $mano = fopen($ruta, 'r');
        printf("        cabecera: %s\n", trim((string) fgets($mano)));
        fclose($mano);
      }
    }
  }
  print "\n";
}

// ---------------------------------------------------------------------------
// 5. Configuracion superviviente que nombre al importador desaparecido.
// ---------------------------------------------------------------------------
print "  --- 5. configuracion que nombre a tec_products_csv_importer ---\n\n";

$menciones = $bd->query("SELECT name FROM {config} WHERE name LIKE '%tec_products_csv%' OR data LIKE '%tec_products_csv%'")->fetchCol();
if (!$menciones) {
  print "      ninguna clave de configuracion lo nombra\n";
}
foreach ($menciones as $nombre) {
  printf("      %s\n", $nombre);
}

// Y en la carpeta de configuracion exportada, por si sobrevivio el fichero.
$carpeta = $raiz . '/config/sync';
$ficheros = glob($carpeta . '/*products*');
printf("\n      ficheros en config/sync que lo nombren: %s\n", $ficheros ? implode(', ', array_map('basename', $ficheros)) : 'ninguno');

print "\n";
