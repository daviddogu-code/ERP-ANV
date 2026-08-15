<?php

/**
 * @file
 * Reconocimiento: que queda de Commerce en el ERP. No modifica nada.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-queda-de-commerce.php
 *
 * Por que existe este guion. Dos de las tres definiciones de entidad instaladas
 * huerfanas del 15 de agosto de 2026 se llaman `commerce_product_variation_type`
 * y `commerce_store_type`, y el nombre hacia pensar en Drupal Commerce. La
 * primera lectura fue que no habia ningun modulo de Commerce instalado y que por
 * tanto eran dos fantasmas inofensivos.
 *
 * Al mirar de cerca aparecio otra cosa, y es la razon de este reconocimiento:
 *
 *   - Las definiciones instaladas NO son de Commerce. Las trae `eck`, su clase
 *     es EckEntityBundle y sus etiquetas son "temp1 type" y "assd type", o sea
 *     dos pruebas de alguien. Son la misma especie de resto que tec_gui_type y
 *     tec_app_link_type: el tipo de subtipo que ECK deriva y que se queda atras
 *     cuando se borra el tipo de entidad.
 *
 *   - Pero los tipos de los que decian ser subtipo, `commerce_product_variation`
 *     y `commerce_store`, SI dejaron dieciseis tablas en la base de datos, y
 *     esas tablas tienen columnas que solo puede haber puesto Drupal Commerce
 *     de verdad: dimensions, weight, billing_countries, tax_registrations.
 *
 * O sea que aqui han pasado dos cosas distintas con el mismo nombre, y este
 * guion sirve para separarlas antes de tocar nada: cuanto hay de Commerce de
 * verdad, si queda vivo algo suyo, y si esas tablas guardan datos que le
 * importen a alguien.
 */

$bd = \Drupal::database();
$esquema = $bd->schema();
$gestorTipos = \Drupal::entityTypeManager();
$fabricaConfig = \Drupal::configFactory();

const AGUJA = 'commerce';

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n\n";
}

print "\n";
print str_repeat('=', 78) . "\n";
print "  QUE QUEDA DE COMMERCE\n";
print str_repeat('=', 78) . "\n";

// ---------------------------------------------------------------------------
seccion('1. modulos: instalados, en el disco y en composer');

$listaModulos = \Drupal::service('extension.list.module');
$instalados = array_keys($listaModulos->getAllInstalledInfo());
$enDisco = array_keys($listaModulos->getList());

$suyosInstalados = array_values(array_filter($instalados, fn(string $m): bool => str_contains($m, AGUJA)));
$suyosEnDisco = array_values(array_filter($enDisco, fn(string $m): bool => str_contains($m, AGUJA)));

printf("  Modulos instalados con '%s' en el nombre: %d%s\n", AGUJA, count($suyosInstalados),
  $suyosInstalados ? ' -> ' . implode(', ', $suyosInstalados) : '');
printf("  Modulos en el disco con '%s' en el nombre: %d%s\n", AGUJA, count($suyosEnDisco),
  $suyosEnDisco ? ' -> ' . implode(', ', $suyosEnDisco) : '');

// core.extension es por donde volveria a colarse en un config:import, asi que
// se mira aparte de lo que el sitio tenga cargado ahora mismo.
$enExtension = array_keys(array_filter(
  (array) \Drupal::config('core.extension')->get('module'),
  fn(string $m): bool => str_contains($m, AGUJA),
  ARRAY_FILTER_USE_KEY
));
printf("  En core.extension: %d%s\n", count($enExtension),
  $enExtension ? ' -> ' . implode(', ', $enExtension) : '');

// La version de esquema es lo que dice si un modulo se desinstalo bien o si se
// borro su carpeta a lo bruto y el sitio sigue creyendo que esta puesto.
$esquemasSistema = $bd->query('SELECT name FROM {key_value} WHERE collection = :c', [':c' => 'system.schema'])->fetchCol();
$suyosEnEsquema = array_values(array_filter($esquemasSistema, fn(string $m): bool => str_contains($m, AGUJA)));
printf("  Con version de esquema guardada (system.schema): %d%s\n", count($suyosEnEsquema),
  $suyosEnEsquema ? ' -> ' . implode(', ', $suyosEnEsquema) : '');

foreach (['composer.json', 'composer.lock'] as $fichero) {
  $ruta = DRUPAL_ROOT . '/../' . $fichero;
  if (!is_file($ruta)) {
    $ruta = DRUPAL_ROOT . '/' . $fichero;
  }
  if (!is_file($ruta)) {
    printf("  %-16s no se encuentra\n", $fichero);
    continue;
  }
  $texto = (string) file_get_contents($ruta);
  preg_match_all('~drupal/commerce[a-z0-9_\-]*~i', $texto, $encontrados);
  $paquetes = array_values(array_unique($encontrados[0]));
  printf("  %-16s %d paquetes de commerce%s\n", $fichero, count($paquetes),
    $paquetes ? ' -> ' . implode(', ', $paquetes) : '');
}

// ---------------------------------------------------------------------------
seccion('2. las tablas y lo que guardan dentro');

// Esto es lo que decide si son restos o datos. Una tabla vacia es basura de
// esquema; una tabla con filas es informacion de alguien, y entonces la
// conversacion es otra.
$tablas = $bd->query('SHOW TABLES LIKE :t', [':t' => AGUJA . '%'])->fetchCol();
$totalFilas = 0;
$conDatos = [];

if (!$tablas) {
  print "  Ninguna tabla que empiece por commerce.\n";
}
foreach ($tablas as $tabla) {
  $n = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  $totalFilas += $n;
  if ($n > 0) {
    $conDatos[] = $tabla;
  }
  printf("  %-52s %6d filas\n", $tabla, $n);
}
printf("\n  %d tablas, %d filas en total. Con datos dentro: %d%s\n",
  count($tablas), $totalFilas, count($conDatos),
  $conDatos ? ' -> ' . implode(', ', $conDatos) : '');

// ---------------------------------------------------------------------------
seccion('3. de quien son esas tablas de verdad');

// Las columnas son la firma. Drupal Commerce trae campos que ECK no inventa
// -dimensions, weight, billing_countries, tax_registrations-, asi que si estan,
// las tablas las hizo Commerce y no la prueba de ECK que se llamaba igual.
$firmaDeCommerce = ['dimensions', 'weight', 'billing_countries', 'shipping_countries', 'tax_registrations', 'price'];
foreach ($tablas as $tabla) {
  $sufijo = '';
  foreach ($firmaDeCommerce as $pista) {
    if (str_contains($tabla, $pista)) {
      $sufijo = '   <<< columna que solo trae Commerce';
      break;
    }
  }
  if ($sufijo === '') {
    continue;
  }
  printf("  %s%s\n", $tabla, $sufijo);
}

// Y las columnas de la tabla principal, que es donde se ve si es una entidad
// de Commerce con todas sus piezas o un armazon vacio.
foreach ([AGUJA . '_product_variation', AGUJA . '_store'] as $tabla) {
  if (!$esquema->tableExists($tabla)) {
    continue;
  }
  $columnas = array_column($bd->query('SHOW COLUMNS FROM {' . $tabla . '}')->fetchAll(), 'Field');
  printf("\n  %s: %s\n", $tabla, implode(', ', $columnas));
}

// ---------------------------------------------------------------------------
seccion('4. definiciones instaladas y esquema guardado');

$almacenClaves = \Drupal::keyValue('entity.definitions.installed');
$instaladas = array_values(array_filter(array_keys($almacenClaves->getAll()),
  fn(string $c): bool => str_contains($c, AGUJA)));
printf("  entity.definitions.installed: %d%s\n", count($instaladas),
  $instaladas ? "\n      " . implode("\n      ", $instaladas) : '');

// Sin esta entrada, el nucleo no sabe como estan hechas esas tablas: es lo que
// confirma que las tablas quedaron sueltas y que nadie las gobierna ya.
$esquemaSql = \Drupal::keyValue('entity.storage_schema.sql')->getAll();
$suyoEnSql = array_values(array_filter(array_keys($esquemaSql), fn(string $c): bool => str_contains($c, AGUJA)));
printf("  entity.storage_schema.sql: %d%s\n", count($suyoEnSql),
  $suyoEnSql ? "\n      " . implode("\n      ", $suyoEnSql) : '');

foreach (['key_value', 'key_value_expire'] as $tabla) {
  if (!$esquema->tableExists($tabla)) {
    continue;
  }
  $filas = $bd->query('SELECT collection, name FROM {' . $tabla . '} WHERE name LIKE :t OR collection LIKE :t',
    [':t' => '%' . AGUJA . '%'])->fetchAll();
  printf("  %-18s %d entradas\n", $tabla, count($filas));
  foreach ($filas as $fila) {
    printf("      %-46s %s\n", $fila->collection, $fila->name);
  }
}

// ---------------------------------------------------------------------------
seccion('5. configuracion activa, campos, vistas y rutas');

$config = array_values(array_filter($fabricaConfig->listAll(), fn(string $n): bool => str_contains($n, AGUJA)));
printf("  Objetos de configuracion con commerce en el nombre: %d%s\n", count($config),
  $config ? "\n      " . implode("\n      ", $config) : '');

// Un campo suyo, o de otra entidad apuntandole, seria lo que impediria tocar
// nada: es el fallo que hizo cara la limpieza de tec_gui.
$campos = [];
foreach ($gestorTipos->getStorage('field_storage_config')->loadMultiple() as $almacen) {
  if (str_contains($almacen->getTargetEntityTypeId(), AGUJA)
    || str_contains((string) $almacen->getSetting('target_type'), AGUJA)) {
    $campos[] = 'almacen ' . $almacen->id() . ' (destino ' . (string) $almacen->getSetting('target_type') . ')';
  }
}
foreach ($gestorTipos->getStorage('field_config')->loadMultiple() as $instancia) {
  $ajustes = $instancia->getSettings();
  if (str_contains($instancia->getTargetEntityTypeId(), AGUJA)
    || str_contains((string) ($ajustes['target_type'] ?? ''), AGUJA)) {
    $campos[] = 'campo ' . $instancia->id() . ($instancia->isRequired() ? ' OBLIGATORIO' : '');
  }
}
printf("  Campos suyos o que le apunten: %d%s\n", count($campos),
  $campos ? "\n      " . implode("\n      ", $campos) : '');

$vistas = [];
foreach ($fabricaConfig->listAll('views.view.') as $nombre) {
  $datos = $fabricaConfig->get($nombre)->getRawData();
  if (str_contains((string) ($datos['base_table'] ?? ''), AGUJA)) {
    $vistas[] = $datos['id'] . ' (tabla base ' . $datos['base_table'] . ')';
  }
}
printf("  Vistas apoyadas en tablas de commerce: %d%s\n", count($vistas),
  $vistas ? "\n      " . implode("\n      ", $vistas) : '');

$rutas = [];
foreach (\Drupal::service('router.route_provider')->getAllRoutes() as $nombre => $ruta) {
  if (str_contains($nombre, AGUJA) || str_contains($ruta->getPath(), AGUJA)) {
    $rutas[] = $nombre . '  ' . $ruta->getPath();
  }
}
printf("  Rutas registradas: %d%s\n", count($rutas),
  $rutas ? "\n      " . implode("\n      ", $rutas) : '');

$permisos = [];
foreach ($gestorTipos->getStorage('user_role')->loadMultiple() as $rol) {
  foreach ($rol->getPermissions() as $permiso) {
    if (str_contains($permiso, AGUJA)) {
      $permisos[] = $rol->id() . ': ' . $permiso;
    }
  }
}
printf("  Permisos por los roles: %d%s\n", count($permisos),
  $permisos ? "\n      " . implode("\n      ", $permisos) : '');

// ---------------------------------------------------------------------------
seccion('6. la configuracion exportada del disco');

// config/sync puede estar vieja, pero justo por eso interesa: si guardara un
// core.extension con commerce dentro, un config:import lo devolveria a la vida.
$carpetas = [DRUPAL_ROOT . '/../config/sync', DRUPAL_ROOT . '/config/sync'];
$vistos = 0;
foreach ($carpetas as $carpeta) {
  if (!is_dir($carpeta)) {
    continue;
  }
  $vistos++;
  $ficheros = glob($carpeta . '/*' . AGUJA . '*') ?: [];
  printf("  %s: %d ficheros con commerce en el nombre%s\n", $carpeta, count($ficheros),
    $ficheros ? "\n      " . implode("\n      ", array_map('basename', $ficheros)) : '');

  $extension = $carpeta . '/core.extension.yml';
  if (is_file($extension)) {
    $texto = (string) file_get_contents($extension);
    printf("      core.extension.yml nombra commerce: %s\n", str_contains($texto, AGUJA) ? 'SI' : 'no');
  }
}
if ($vistos === 0) {
  print "  No hay carpeta config/sync que mirar.\n";
}

print "\n";
print str_repeat('=', 78) . "\n";
print "  FIN DEL RECONOCIMIENTO\n";
print str_repeat('=', 78) . "\n\n";
