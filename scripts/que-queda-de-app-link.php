<?php

/**
 * @file
 * Reconocimiento: que queda de tec_app_link en el ERP. No modifica nada.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-queda-de-app-link.php
 *
 * Que es tec_app_link: un tipo de entidad de ECK con seis subtipos para los
 * enlaces a redes sociales de la portada (Facebook, Instagram, LINE, WhatsApp,
 * X y telefono). El tipo y sus dos tablas ya se fueron el 14 de agosto con
 * borrar-lo-que-no-usa-nadie.php. Esto busca lo que se quedo atras.
 *
 * El apunte de deuda tecnica dice "sin tablas, con permisos sueltos por los
 * roles". Este guion no da por bueno ese apunte: pregunta a la configuracion
 * activa y a la base de datos, que es lo unico que manda. La configuracion
 * exportada de config/sync puede estar vieja, asi que no se mira.
 *
 * Se busca en todas partes por el mismo motivo que en tec_gui: alli lo caro no
 * eran las tablas, era un campo obligatorio colgado de las lineas de pedido que
 * apuntaba al tipo muerto. Aqui se repasa lo mismo, campo por campo.
 */

$bd = \Drupal::database();
$esquema = $bd->schema();
$gestorTipos = \Drupal::entityTypeManager();
$fabricaConfig = \Drupal::configFactory();

const AGUJA = 'tec_app_link';

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n\n";
}

/**
 * Busca la aguja dentro de cualquier estructura y devuelve donde la encontro.
 */
function buscarDentro($valor, string $aguja, string $camino = ''): array {
  $encontrado = [];
  if (is_array($valor)) {
    foreach ($valor as $clave => $hijo) {
      $sub = $camino === '' ? (string) $clave : $camino . '.' . $clave;
      if (is_string($clave) && str_contains($clave, $aguja)) {
        $encontrado[] = $sub . '   (en la clave)';
      }
      $encontrado = array_merge($encontrado, buscarDentro($hijo, $aguja, $sub));
    }
    return $encontrado;
  }
  if (is_string($valor) && str_contains($valor, $aguja)) {
    $encontrado[] = $camino . ' = ' . mb_substr($valor, 0, 60);
  }
  return $encontrado;
}

print "\n";
print str_repeat('=', 78) . "\n";
print "  QUE QUEDA DE " . AGUJA . "\n";
print str_repeat('=', 78) . "\n";

// ---------------------------------------------------------------------------
seccion('1. el tipo de entidad, sus subtipos y su definicion instalada');

$definiciones = $gestorTipos->getDefinitions();
if (isset($definiciones[AGUJA])) {
  print "  OJO: el gestor de entidades TODAVIA conoce " . AGUJA . "\n";
  print "       clase: " . $definiciones[AGUJA]->getClass() . "\n";
}
else {
  print "  El gestor de entidades no conoce " . AGUJA . ": el tipo ya no esta.\n";
}

$tipoEck = $fabricaConfig->get('eck.eck_entity_type.' . AGUJA)->getRawData();
print $tipoEck
  ? "  OJO: sigue la configuracion eck.eck_entity_type." . AGUJA . "\n"
  : "  No hay configuracion eck.eck_entity_type." . AGUJA . ".\n";

$subtipos = $fabricaConfig->listAll('eck_type.' . AGUJA);
printf("  Subtipos eck_type.%s.*: %d%s\n", AGUJA, count($subtipos),
  $subtipos ? ' -> ' . implode(', ', $subtipos) : '');

// La definicion "instalada" vive en el almacen de claves, y es la que hace que
// el informe de estado saque el rojo de "definiciones descuadradas" cuando se
// queda a medias. En tec_gui esto pintaba de rojo el informe.
$instaladas = \Drupal::keyValue('entity.definitions.installed')->getAll();
$restos = [];
foreach (array_keys($instaladas) as $clave) {
  if (str_contains($clave, AGUJA)) {
    $restos[] = $clave;
  }
}
printf("  Definiciones instaladas en el almacen de claves: %d%s\n",
  count($restos), $restos ? ' -> ' . implode(', ', $restos) : '');

$ultimas = \Drupal::keyValue('entity.storage_schema.sql')->getAll();
$restosEsquema = [];
foreach (array_keys($ultimas) as $clave) {
  if (str_contains($clave, AGUJA)) {
    $restosEsquema[] = $clave;
  }
}
printf("  Esquemas de almacenamiento guardados: %d%s\n",
  count($restosEsquema), $restosEsquema ? ' -> ' . implode(', ', $restosEsquema) : '');

// ---------------------------------------------------------------------------
seccion('2. tablas en la base de datos');

$tablas = $bd->query("SHOW TABLES LIKE '%app\_link%'")->fetchCol();
if (!$tablas) {
  print "  Ninguna tabla con app_link en el nombre.\n";
}
foreach ($tablas as $tabla) {
  $n = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  printf("  %-50s %d filas\n", $tabla, $n);
}

// ---------------------------------------------------------------------------
seccion('3. campos: los suyos y los de OTRAS entidades que le apunten');

// Este es el apartado que mas dano hace si se pasa por alto. En tec_gui habia
// un field_tec_gui_product OBLIGATORIO en las lineas de pedido de venta.
$almacenes = $gestorTipos->getStorage('field_storage_config')->loadMultiple();
$suyos = [];
$apuntan = [];
foreach ($almacenes as $almacen) {
  if ($almacen->getTargetEntityTypeId() === AGUJA) {
    $suyos[] = $almacen->id() . ' (' . $almacen->getType() . ')';
  }
  if ((string) $almacen->getSetting('target_type') === AGUJA) {
    $apuntan[] = $almacen->id() . ' (' . $almacen->getType() . ')';
  }
}
printf("  Almacenes de campo DE %s: %d%s\n", AGUJA, count($suyos),
  $suyos ? "\n      " . implode("\n      ", $suyos) : '');
printf("  Almacenes de campo QUE APUNTAN A %s: %d%s\n", AGUJA, count($apuntan),
  $apuntan ? "\n      " . implode("\n      ", $apuntan) : '');

$instancias = $gestorTipos->getStorage('field_config')->loadMultiple();
$suyas = [];
$apuntanInst = [];
foreach ($instancias as $instancia) {
  if ($instancia->getTargetEntityTypeId() === AGUJA) {
    $suyas[] = $instancia->id() . ($instancia->isRequired() ? ' OBLIGATORIO' : '');
  }
  $ajustes = $instancia->getSettings();
  $manejador = (string) ($ajustes['handler'] ?? '');
  $destino = (string) ($ajustes['target_type'] ?? '');
  $paquetes = array_keys((array) ($ajustes['handler_settings']['target_bundles'] ?? []));
  $texto = $manejador . ' ' . $destino . ' ' . implode(' ', $paquetes);
  if (str_contains($texto, AGUJA)) {
    $apuntanInst[] = $instancia->id() . ($instancia->isRequired() ? ' OBLIGATORIO' : '');
  }
}
printf("  Instancias de campo DE %s: %d%s\n", AGUJA, count($suyas),
  $suyas ? "\n      " . implode("\n      ", $suyas) : '');
printf("  Instancias de campo QUE APUNTAN A %s: %d%s\n", AGUJA, count($apuntanInst),
  $apuntanInst ? "\n      " . implode("\n      ", $apuntanInst) : '');

// Campos borrados que siguen en la cola de purga, que es donde se esconden los
// restos que no se ven ni en la configuracion ni en las tablas.
$repositorio = \Drupal::service('entity_field.deleted_fields_repository');
$purga = [];
foreach ($repositorio->getFieldStorageDefinitions() as $definicion) {
  if (str_contains($definicion->getTargetEntityTypeId() . '.' . $definicion->getName(), AGUJA)) {
    $purga[] = 'almacen ' . $definicion->getTargetEntityTypeId() . '.' . $definicion->getName();
  }
}
foreach ($repositorio->getFieldDefinitions() as $definicion) {
  if (str_contains($definicion->getTargetEntityTypeId() . '.' . $definicion->getName(), AGUJA)) {
    $purga[] = 'campo ' . $definicion->getTargetEntityTypeId() . '.' . $definicion->getName();
  }
}
printf("  En la cola de purga: %d%s\n", count($purga),
  $purga ? "\n      " . implode("\n      ", $purga) : '');

// ---------------------------------------------------------------------------
seccion('4. permisos por los roles');

$totalPermisos = 0;
foreach ($gestorTipos->getStorage('user_role')->loadMultiple() as $rol) {
  $sobran = array_values(array_filter($rol->getPermissions(),
    static fn(string $p): bool => str_contains($p, AGUJA) || str_contains($p, 'app_link')));
  if (!$sobran) {
    continue;
  }
  $totalPermisos += count($sobran);
  printf("  %-28s %d permisos\n", $rol->id(), count($sobran));
  foreach ($sobran as $permiso) {
    print "      " . $permiso . "\n";
  }
}
if ($totalPermisos === 0) {
  print "  Ningun rol guarda permisos de " . AGUJA . " en la configuracion ACTIVA.\n";
}

// Los permisos que el sitio reconoce hoy, por si alguno sigue declarado.
$declarados = [];
foreach (\Drupal::service('user.permissions')->getPermissions() as $nombre => $datos) {
  if (str_contains($nombre, 'app_link')) {
    $declarados[] = $nombre;
  }
}
printf("  Permisos de app_link que el sitio declara hoy: %d%s\n",
  count($declarados), $declarados ? ' -> ' . implode(', ', $declarados) : '');

// ---------------------------------------------------------------------------
seccion('5. vistas, pantallas, modos, menus y banderas');

$vistas = [];
foreach ($fabricaConfig->listAll('views.view.') as $nombre) {
  $datos = $fabricaConfig->get($nombre)->getRawData();
  if (str_contains(($datos['base_table'] ?? '') . ' ' . ($datos['base_field'] ?? ''), AGUJA)) {
    $vistas[] = $datos['id'] . ' (tabla base ' . $datos['base_table'] . ')';
  }
}
printf("  Vistas apoyadas en %s: %d%s\n", AGUJA, count($vistas),
  $vistas ? "\n      " . implode("\n      ", $vistas) : '');

foreach (['entity_view_display', 'entity_form_display', 'entity_view_mode', 'entity_form_mode'] as $tipo) {
  $encontrados = [];
  foreach ($gestorTipos->getStorage($tipo)->loadMultiple() as $entidad) {
    if (str_contains((string) $entidad->id(), AGUJA)) {
      $encontrados[] = $entidad->id();
    }
  }
  printf("  %-20s %d%s\n", $tipo, count($encontrados),
    $encontrados ? ' -> ' . implode(', ', $encontrados) : '');
}

// Banderas: en tec_gui quedaba una colgando y habia que irse por la base.
$banderas = [];
foreach ($fabricaConfig->listAll('flag.flag.') as $nombre) {
  $datos = $fabricaConfig->get($nombre)->getRawData();
  if (str_contains(($datos['entity_type'] ?? '') . ' ' . $nombre, AGUJA)) {
    $banderas[] = $datos['id'];
  }
}
printf("  Banderas sobre %s: %d%s\n", AGUJA, count($banderas),
  $banderas ? ' -> ' . implode(', ', $banderas) : '');

if ($esquema->tableExists('flagging')) {
  $marcas = $bd->query("SELECT entity_type, COUNT(*) n FROM {flagging} WHERE entity_type LIKE :t GROUP BY entity_type",
    [':t' => '%app_link%'])->fetchAll();
  printf("  Marcas (flagging) sobre %s: %d\n", AGUJA, count($marcas));
  foreach ($marcas as $fila) {
    printf("      %-30s %d\n", $fila->entity_type, $fila->n);
  }
}

// Enlaces de menu guardados como contenido, y los del arbol de menus.
$enlaces = [];
foreach ($gestorTipos->getStorage('menu_link_content')->loadMultiple() as $enlace) {
  $uri = $enlace->get('link')->uri ?? '';
  if (str_contains((string) $uri, AGUJA)) {
    $enlaces[] = $enlace->id() . ' ' . $enlace->label() . ' -> ' . $uri;
  }
}
printf("  Enlaces de menu (contenido): %d%s\n", count($enlaces),
  $enlaces ? "\n      " . implode("\n      ", $enlaces) : '');

if ($esquema->tableExists('menu_tree')) {
  $arbol = $bd->query("SELECT id, route_name FROM {menu_tree} WHERE id LIKE :t OR route_name LIKE :t",
    [':t' => '%app_link%'])->fetchAll();
  printf("  Enlaces en el arbol de menus: %d\n", count($arbol));
  foreach ($arbol as $fila) {
    printf("      %-50s %s\n", $fila->id, $fila->route_name);
  }
}

// ---------------------------------------------------------------------------
seccion('6. rutas');

$rutas = [];
foreach (\Drupal::service('router.route_provider')->getAllRoutes() as $nombre => $ruta) {
  if (str_contains($nombre, 'app_link') || str_contains($ruta->getPath(), 'app_link')) {
    $rutas[] = $nombre . '  ' . $ruta->getPath();
  }
}
printf("  Rutas registradas con app_link: %d%s\n", count($rutas),
  $rutas ? "\n      " . implode("\n      ", $rutas) : '');

// ---------------------------------------------------------------------------
seccion('7. barrido completo de la configuracion activa');

// Lo anterior busca en los sitios donde se sabe que hay que mirar. Esto busca
// en todos los demas: recorre TODA la configuracion activa y saca cualquier
// mencion, este donde este. Es lo que destapa las sorpresas.
$tocadas = [];
foreach ($fabricaConfig->listAll() as $nombre) {
  $datos = $fabricaConfig->get($nombre)->getRawData();
  $donde = buscarDentro($datos, AGUJA);
  if (str_contains($nombre, AGUJA)) {
    $donde[] = '(el nombre de la propia configuracion)';
  }
  if ($donde) {
    $tocadas[$nombre] = $donde;
  }
}

if (!$tocadas) {
  print "  Ninguna configuracion activa menciona " . AGUJA . ".\n";
}
foreach ($tocadas as $nombre => $donde) {
  printf("  %s\n", $nombre);
  foreach ($donde as $linea) {
    print "      " . $linea . "\n";
  }
}

// ---------------------------------------------------------------------------
seccion('8. barrido del almacen de claves y de la cola');

foreach (['key_value', 'key_value_expire'] as $tabla) {
  if (!$esquema->tableExists($tabla)) {
    continue;
  }
  $filas = $bd->query("SELECT collection, name FROM {" . $tabla . "} WHERE name LIKE :t OR collection LIKE :t OR value LIKE :t",
    [':t' => '%' . AGUJA . '%'])->fetchAll();
  printf("  %-18s %d entradas\n", $tabla, count($filas));
  foreach ($filas as $fila) {
    printf("      %-46s %s\n", $fila->collection, $fila->name);
  }
}

// La tabla del enrutador guarda las rutas compiladas: si quedara una, la
// pantalla seguiria respondiendo aunque el tipo de entidad ya no exista.
if ($esquema->tableExists('router')) {
  $n = (int) $bd->query("SELECT COUNT(*) FROM {router} WHERE name LIKE :t OR path LIKE :t",
    [':t' => '%app_link%'])->fetchField();
  printf("  %-18s %d rutas compiladas con app_link\n", 'router', $n);
}

print "\n";
print str_repeat('=', 78) . "\n";
print "  FIN DEL RECONOCIMIENTO\n";
print str_repeat('=', 78) . "\n\n";
