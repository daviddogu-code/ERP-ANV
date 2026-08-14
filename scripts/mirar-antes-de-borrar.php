<?php

/**
 * Mira lo que se va a borrar antes de borrarlo.
 *
 * La auditoria del 14 de agosto encontro nueve cosas que no las usa nadie: un
 * tipo de entidad, siete vistas, siete almacenes de campo, dos carpetas de
 * modulo y trece tablas temporales. "No las usa nadie" salio de leer la
 * configuracion, y la configuracion no sabe si hay datos dentro.
 *
 * Esto lo pregunta a la base de datos, que es la que lo sabe. Borrar un almacen
 * de campo se lleva su tabla, y borrar un tipo de entidad se lleva las suyas: si
 * hay algo dentro, se va sin avisar y sin vuelta atras.
 *
 * No toca nada. Solo mira.
 *
 * Uso: php vendor/bin/drush.php scr scripts/mirar-antes-de-borrar.php
 */

// Las vistas que la auditoria dio por muertas. Las cinco primeras estan
// apagadas; `dev` no tiene ni pagina ni bloque, y el bloque de la ultima no esta
// colocado en ninguna parte.
const VISTAS = [
  'tec_order_material_calculation',
  'duplicate_of_tec_order_material_calculation_terms_copy',
  'tec_order_create_draft_order_ii_excel_lover_style_eca_model',
  'archive',
  'glossary',
  'dev',
  'material_calculation_inventory_based',
];

// Almacenes de campo en nodos sin ninguna instancia, segun la auditoria. Los
// cinco `field_dth_*` los dejo el tema DXPR al subir a la version 8.
const CAMPOS = [
  'field_image_browser',
  'field_files_over_ajax',
  'field_dth_page_title_backgrou',
  'field_dth_page_layout',
  'field_dth_main_content_width',
  'field_dth_hide_regions',
  'field_dth_body_background',
];

const ENTIDAD_MUERTA = 'tec_app_link';

const CUENTA = 'devT';

$etm = \Drupal::entityTypeManager();
$conexion = \Drupal::database();
$esquema = $conexion->schema();

print "\n";

// -----------------------------------------------------------------------------
// 1. El tipo de entidad de los enlaces a redes sociales.
// -----------------------------------------------------------------------------
print "  --- 1. " . ENTIDAD_MUERTA . ", seis subtipos para redes sociales ---\n\n";

try {
  $definicion = $etm->getDefinition(ENTIDAD_MUERTA);
  $tabla = $definicion->getBaseTable();

  printf("      tabla base: %s%s\n", $tabla, $esquema->tableExists($tabla) ? '' : ' (NO EXISTE)');

  if ($esquema->tableExists($tabla)) {
    $n = (int) $conexion->select($tabla)->countQuery()->execute()->fetchField();
    printf("      fichas dentro: %d\n", $n);
  }

  $subtipos = $etm->getStorage($definicion->getBundleEntityType())->loadMultiple();
  printf("      subtipos: %s\n", implode(', ', array_keys($subtipos)));

  // Todas las tablas que se llevaria el borrado por delante.
  $todas = [];
  foreach ($conexion->query("SHOW TABLES LIKE '%" . ENTIDAD_MUERTA . "%'")->fetchCol() as $t) {
    $n = (int) $conexion->select($t)->countQuery()->execute()->fetchField();
    $todas[] = sprintf('%s (%d filas)', $t, $n);
  }
  printf("      tablas suyas: %s\n", $todas ? implode(', ', $todas) : 'ninguna');

  // Quien la referencia: campos de otras entidades que apunten aqui.
  $apuntan = [];
  foreach ($etm->getStorage('field_config')->loadMultiple() as $campo) {
    $ajustes = $campo->getSettings();
    if (($ajustes['target_type'] ?? '') === ENTIDAD_MUERTA) {
      $apuntan[] = $campo->id();
    }
  }
  printf("      campos que apuntan aqui: %s\n", $apuntan ? implode(', ', $apuntan) : 'ninguno');
}
catch (\Throwable $e) {
  print "      no esta declarada: " . $e->getMessage() . "\n";
}

print "\n";

// -----------------------------------------------------------------------------
// 2. Los siete almacenes de campo.
// -----------------------------------------------------------------------------
print "  --- 2. los siete almacenes de campo en nodos ---\n\n";
printf("      %-32s %-9s %-8s %s\n", 'campo', 'instancias', 'filas', 'tabla');
print '      ' . str_repeat('-', 74) . "\n";

$almacenCampos = $etm->getStorage('field_storage_config');
$almacenInstancias = $etm->getStorage('field_config');

foreach (CAMPOS as $campo) {
  $almacen = $almacenCampos->load('node.' . $campo);

  if (!$almacen) {
    printf("      %-32s NO EXISTE\n", $campo);
    continue;
  }

  // Instancias: en que tipos de contenido esta puesto de verdad.
  $instancias = [];
  foreach ($almacenInstancias->loadMultiple() as $instancia) {
    if ($instancia->getTargetEntityTypeId() === 'node' && $instancia->getName() === $campo) {
      $instancias[] = $instancia->getTargetBundle();
    }
  }

  // Filas: los datos que se irian con la tabla.
  $tabla = 'node__' . $campo;
  $filas = $esquema->tableExists($tabla)
    ? (int) $conexion->select($tabla)->countQuery()->execute()->fetchField()
    : -1;
  $revisiones = 'node_revision__' . $campo;
  $filasRev = $esquema->tableExists($revisiones)
    ? (int) $conexion->select($revisiones)->countQuery()->execute()->fetchField()
    : -1;

  printf("      %-32s %-9s %-8s %s\n",
    $campo,
    $instancias ? implode(',', $instancias) : 'ninguna',
    $filas < 0 ? 'sin tabla' : $filas,
    $filasRev < 0 ? '' : "(+$filasRev en revisiones)");
}

print "\n";

// -----------------------------------------------------------------------------
// 3. Las siete vistas.
// -----------------------------------------------------------------------------
// Lo que importa aqui no es si la vista esta encendida, sino si alguien la
// nombra. Un `viewfield` que apunte a una vista borrada deja la pantalla en
// blanco, y un bloque colocado en un diseno se va en cascada sin preguntar.
print "  --- 3. las siete vistas: quien las nombra ---\n\n";

$almacenVistas = $etm->getStorage('view');

// Todos los bloques colocados por configuracion.
$bloques = [];
foreach ($etm->getStorage('block')->loadMultiple() as $bloque) {
  $bloques[] = $bloque->getPluginId();
}

// Los disenos de Layout Builder viven en columnas binarias, o sea que una
// busqueda de texto en la base no los ve. Hay que abrirlos uno a uno; es la
// leccion que dejo Super BOM el 14 de agosto por la manana.
$enDisenos = [];
foreach ($etm->getStorage('node')->loadMultiple() as $nodo) {
  if (!$nodo->hasField('layout_builder__layout')) {
    continue;
  }
  foreach ($nodo->get('layout_builder__layout') as $elemento) {
    foreach ($elemento->section->getComponents() as $componente) {
      $enDisenos[$componente->getPluginId()][] = $nodo->id();
    }
  }
}

// Los viewfield guardan el identificador de la vista como valor del campo.
$camposDeVista = [];
foreach ($almacenCampos->loadMultiple() as $almacen) {
  if ($almacen->getType() === 'viewfield') {
    $camposDeVista[] = $almacen;
  }
}

// La configuracion entera como texto, para pillar menciones sueltas: procesos de
// ECA, otras vistas que la usen de relacion, ajustes de modulos.
$todaLaConfig = [];
foreach ($conexion->select('config', 'c')->fields('c', ['name', 'data'])->execute() as $fila) {
  $todaLaConfig[$fila->name] = $fila->data;
}

printf("      %-58s %s\n", 'vista', 'quien la nombra');
print '      ' . str_repeat('-', 90) . "\n";

foreach (VISTAS as $id) {
  $vista = $almacenVistas->load($id);

  if (!$vista) {
    printf("      %-58s NO EXISTE\n", $id);
    continue;
  }

  $quien = [];

  foreach ($bloques as $plugin) {
    if (str_starts_with($plugin, 'views_block:' . $id . '-')) {
      $quien[] = 'bloque ' . $plugin;
    }
  }

  foreach ($enDisenos as $plugin => $nodos) {
    if (str_starts_with($plugin, 'views_block:' . $id . '-')) {
      $quien[] = 'diseno del nodo ' . implode('/', array_unique($nodos));
    }
  }

  foreach ($camposDeVista as $almacen) {
    $tabla = $almacen->getTargetEntityTypeId() . '__' . $almacen->getName();
    if (!$esquema->tableExists($tabla)) {
      continue;
    }
    $columna = $almacen->getName() . '_target_id';
    if (!$esquema->fieldExists($tabla, $columna)) {
      continue;
    }
    $n = (int) $conexion->select($tabla)->condition($columna, $id)->countQuery()->execute()->fetchField();
    if ($n) {
      $quien[] = "viewfield $tabla ($n filas)";
    }
  }

  foreach ($todaLaConfig as $nombre => $datos) {
    if ($nombre === 'views.view.' . $id) {
      continue;
    }
    // Se busca el identificador tal cual, que es como lo escribe Drupal en las
    // dependencias y en los ajustes de los bloques.
    if (str_contains((string) $datos, $id)) {
      $quien[] = 'config ' . $nombre;
    }
  }

  printf("      %-58s %s\n", $id, $quien ? implode('; ', array_unique($quien)) : 'nadie');
}

print "\n";

// -----------------------------------------------------------------------------
// 4. Las tablas temporales.
// -----------------------------------------------------------------------------
print "  --- 4. tablas que parecen restos ---\n\n";

$sospechosas = $conexion->query("SHOW TABLES LIKE 'tmp%'")->fetchCol();

if (!$sospechosas) {
  print "      ninguna\n";
}

foreach ($sospechosas as $tabla) {
  $n = (int) $conexion->select($tabla)->countQuery()->execute()->fetchField();
  $columnas = array_map(fn($c) => $c->Field ?? $c->field ?? '?',
    $conexion->query("SHOW COLUMNS FROM {$tabla}")->fetchAll());
  printf("      %-24s %6d filas   %s\n", $tabla, $n, implode(', ', array_slice($columnas, 0, 6)));
}

print "\n";

// -----------------------------------------------------------------------------
// 5. La cuenta devT.
// -----------------------------------------------------------------------------
// Es la que tiene rol de administrador con un correo que no es del dueño. Antes
// de borrarla hay que saber si tiene algo colgado, porque al borrar un usuario
// Drupal pregunta que hacer con lo suyo, y en un guion no hay quien conteste.
print "  --- 5. la cuenta " . CUENTA . " ---\n\n";

$cuentas = $etm->getStorage('user')->loadByProperties(['name' => CUENTA]);
$cuenta = reset($cuentas);

if (!$cuenta) {
  print "      no existe\n\n";
}
else {
  printf("      uid %s, %s, %s\n",
    $cuenta->id(),
    $cuenta->getEmail() ?: '(sin correo)',
    $cuenta->isActive() ? 'ACTIVA' : 'bloqueada');
  printf("      roles: %s\n", implode(', ', $cuenta->getRoles()));
  printf("      creada: %s, ultimo acceso: %s\n",
    date('Y-m-d', $cuenta->getCreatedTime()),
    $cuenta->getLastAccessedTime() ? date('Y-m-d', $cuenta->getLastAccessedTime()) : 'nunca');

  // Que hay colgado de ella. Se recorren todas las entidades que tengan dueño.
  $suyo = [];
  foreach ($etm->getDefinitions() as $tipo => $definicion) {
    if (!$definicion->entityClassImplements('Drupal\user\EntityOwnerInterface')) {
      continue;
    }
    if ($tipo === 'user') {
      continue;
    }
    try {
      $n = (int) $etm->getStorage($tipo)->getQuery()
        ->accessCheck(FALSE)
        ->condition('uid', $cuenta->id())
        ->count()
        ->execute();
      if ($n) {
        $suyo[] = "$tipo: $n";
      }
    }
    catch (\Throwable $e) {
      // Hay tipos sin campo de dueño util; no pasa nada.
    }
  }

  printf("      contenido suyo: %s\n\n", $suyo ? implode(', ', $suyo) : 'nada');

  // Y las otras cuentas, para tener la foto completa de una vez.
  print "      las demas cuentas:\n\n";
  printf("          %-4s %-16s %-34s %-10s %s\n", 'uid', 'nombre', 'correo', 'estado', 'roles');
  foreach ($etm->getStorage('user')->loadMultiple() as $u) {
    if (!$u->id()) {
      continue;
    }
    printf("          %-4s %-16s %-34s %-10s %s\n",
      $u->id(),
      $u->getAccountName(),
      $u->getEmail() ?: '(sin correo)',
      $u->isActive() ? 'activa' : 'bloqueada',
      implode(',', $u->getRoles()));
  }
  print "\n";
}
