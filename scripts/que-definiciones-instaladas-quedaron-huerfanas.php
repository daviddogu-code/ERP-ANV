<?php

/**
 * @file
 * Reconocimiento: que definiciones de entidad instaladas quedaron huerfanas.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-definiciones-instaladas-quedaron-huerfanas.php
 *
 * Drupal guarda en la coleccion `entity.definitions.installed` del almacen de
 * claves una copia de cada tipo de entidad tal y como estaba el dia que se
 * instalo. Es lo que usa para saber si la estructura de hoy cuadra con la que
 * hay montada en la base de datos.
 *
 * Cuando se retira un tipo de entidad y la limpieza se queda a medias, esa
 * copia se queda dentro para siempre. No molesta, no pinta nada de rojo y no
 * rompe ninguna pantalla, pero es un tipo de entidad que el sitio ya no conoce
 * describiendose a si mismo en un rincon que nadie mira.
 *
 * Por que no se ve: el informe de estado tira de getChangeList(), y esa funcion
 * recorre los tipos que el sitio conoce HOY para compararlos con su copia
 * instalada. A un tipo que ya no existe no lo recorre, asi que nunca lo compara
 * con nada y nunca sale en rojo. El unico sitio donde asoma es getEntityTypes()
 * del gestor de definiciones, que devuelve todas las instaladas sin preguntar
 * si siguen vivas. Este guion mira justo ahi.
 *
 * Este guion es el hermano generico de que-es-la-definicion-que-quedo.php, que
 * miraba una sola: la de tec_app_link_type, el 15 de agosto de 2026. Aqui no
 * hay ninguna lista de nombres a mano, se recorre lo que haya.
 */

$almacenClaves = \Drupal::keyValue('entity.definitions.installed');
$gestorTipos = \Drupal::entityTypeManager();
$bd = \Drupal::database();
$fabricaConfig = \Drupal::configFactory();

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n\n";
}

print "\n";
print str_repeat('=', 78) . "\n";
print "  DEFINICIONES DE ENTIDAD INSTALADAS QUE QUEDARON HUERFANAS\n";
print str_repeat('=', 78) . "\n";

// ---------------------------------------------------------------------------
seccion('1. quien esta instalado y a quien conoce el sitio');

$todas = $almacenClaves->getAll();
$instalados = [];
foreach (array_keys($todas) as $clave) {
  if (str_ends_with($clave, '.entity_type')) {
    $instalados[] = substr($clave, 0, -strlen('.entity_type'));
  }
}
sort($instalados);

$vivos = array_keys($gestorTipos->getDefinitions());
sort($vivos);

$huerfanos = array_values(array_diff($instalados, $vivos));
// Al reves tambien interesa: un tipo que el sitio conoce y que NO tiene copia
// instalada esta a medio instalar, que es el problema contrario y mas grave.
$sinInstalar = array_values(array_diff($vivos, $instalados));

printf("  Tipos con definicion instalada: %d\n", count($instalados));
printf("  Tipos que el sitio conoce hoy:  %d\n", count($vivos));
printf("  Instalados que el sitio ya NO conoce (huerfanos): %d%s\n",
  count($huerfanos), $huerfanos ? ' -> ' . implode(', ', $huerfanos) : '');
printf("  Conocidos SIN definicion instalada: %d%s\n",
  count($sinInstalar), $sinInstalar ? ' -> ' . implode(', ', $sinInstalar) : '');

if (!$huerfanos) {
  print "\n  No queda ninguna definicion huerfana. Nada que investigar.\n\n";
  return;
}

// ---------------------------------------------------------------------------
seccion('2. que es cada huerfano por dentro');

// Lo que se guarda es el objeto entero serializado, asi que se le puede
// preguntar lo mismo que a un tipo vivo. De quien lo trajo (`provider`) sale la
// mitad de la historia: si el modulo ya no esta instalado, el fantasma se quedo
// cuando se fue el modulo; si sigue instalado, se quedo al borrar el tipo.
$manejadorModulos = \Drupal::moduleHandler();

foreach ($huerfanos as $id) {
  $definicion = $almacenClaves->get($id . '.entity_type');
  printf("  %s\n", $id);
  printf("      clase del objeto  %s\n", is_object($definicion) ? get_class($definicion) : gettype($definicion));

  if (!$definicion instanceof \Drupal\Core\Entity\EntityTypeInterface) {
    print "      OJO: lo guardado no es un tipo de entidad\n\n";
    continue;
  }

  $proveedor = (string) $definicion->getProvider();
  printf("      etiqueta          %s\n", (string) $definicion->getLabel());
  printf("      clase de entidad  %s\n", $definicion->getClass());
  printf("      proveedor         %s   (modulo %s)\n", $proveedor,
    $manejadorModulos->moduleExists($proveedor) ? 'INSTALADO' : 'no instalado');
  printf("      grupo             %s\n", (string) ($definicion->get('group') ?? ''));
  printf("      subtipo de        %s\n", (string) $definicion->getBundleOf() ?: '(no es un tipo de subtipos)');
  printf("      tabla base        %s\n", (string) $definicion->getBaseTable() ?: '(ninguna, es configuracion)');
  printf("      prefijo de conf.  %s\n",
    method_exists($definicion, 'getConfigPrefix') ? (string) $definicion->getConfigPrefix() : '(no es configuracion)');

  // Los campos van en una entrada aparte del mismo almacen, y es la otra mitad
  // de lo que hay que retirar: borrar solo la del tipo deja los campos dentro.
  $campos = $almacenClaves->get($id . '.field_storage_definitions');
  printf("      campos instalados %s\n",
    is_array($campos) ? count($campos) . ' -> ' . implode(', ', array_keys($campos)) : 'ninguna entrada');

  print "\n";
}

// ---------------------------------------------------------------------------
seccion('3. les queda algo colgando: tablas, esquema, configuracion');

// Un fantasma es inofensivo mientras no tenga nada detras. Si apareciera una
// tabla con filas dentro, o configuracion viva con su prefijo, dejaria de ser
// un resto y seria un tipo a medio borrar, que es otra cosa y se trata de otra
// manera: primero los datos, y la definicion al final.
$esquemaGuardado = \Drupal::keyValue('entity.storage_schema.sql')->getAll();

foreach ($huerfanos as $id) {
  printf("  %s\n", $id);

  $definicion = $almacenClaves->get($id . '.entity_type');
  $prefijo = ($definicion instanceof \Drupal\Core\Entity\EntityTypeInterface
    && method_exists($definicion, 'getConfigPrefix'))
    ? (string) $definicion->getConfigPrefix()
    : '';

  $tablas = $bd->query('SHOW TABLES LIKE :t', [':t' => $id . '%'])->fetchCol();
  if (!$tablas) {
    print "      ninguna tabla que empiece por su nombre\n";
  }
  foreach ($tablas as $tabla) {
    $n = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
    printf("      tabla %-44s %d filas\n", $tabla, $n);
  }

  $suEsquema = array_values(array_filter(array_keys($esquemaGuardado),
    static fn(string $c): bool => $c === $id || str_starts_with($c, $id . '.')));
  printf("      esquema SQL guardado: %d%s\n", count($suEsquema),
    $suEsquema ? ' -> ' . implode(', ', $suEsquema) : '');

  if ($prefijo !== '') {
    $objetos = $fabricaConfig->listAll($prefijo . '.');
    printf("      configuracion con el prefijo %s.: %d%s\n", $prefijo, count($objetos),
      $objetos ? ' -> ' . implode(', ', $objetos) : '');
  }

  // Y lo que importa de verdad: que nadie le apunte. Un campo de otra entidad
  // apuntando a un tipo muerto es lo que hizo cara la limpieza de tec_gui.
  $apuntan = [];
  foreach ($gestorTipos->getStorage('field_storage_config')->loadMultiple() as $almacenCampo) {
    if ($almacenCampo->getTargetEntityTypeId() === $id) {
      $apuntan[] = 'almacen suyo: ' . $almacenCampo->id();
    }
    if ((string) $almacenCampo->getSetting('target_type') === $id) {
      $apuntan[] = 'almacen que le apunta: ' . $almacenCampo->id();
    }
  }
  foreach ($gestorTipos->getStorage('field_config')->loadMultiple() as $instancia) {
    if ($instancia->getTargetEntityTypeId() === $id) {
      $apuntan[] = 'campo suyo: ' . $instancia->id();
    }
    $ajustes = $instancia->getSettings();
    if ((string) ($ajustes['target_type'] ?? '') === $id) {
      $apuntan[] = 'campo que le apunta: ' . $instancia->id()
        . ($instancia->isRequired() ? ' (OBLIGATORIO)' : '');
    }
  }
  printf("      campos suyos o que le apunten: %d%s\n", count($apuntan),
    $apuntan ? "\n          " . implode("\n          ", $apuntan) : '');

  print "\n";
}

// ---------------------------------------------------------------------------
seccion('4. lo mismo pero de quien los trajo: los modulos');

// Si el modulo que declaraba el tipo sigue instalado, borrar su definicion es
// una limpieza. Si no lo esta, hay que asegurarse de que tampoco esta a medio
// instalar por otro lado, porque entonces lo que toca es desinstalarlo bien.
$proveedores = [];
foreach ($huerfanos as $id) {
  $definicion = $almacenClaves->get($id . '.entity_type');
  if ($definicion instanceof \Drupal\Core\Entity\EntityTypeInterface) {
    $proveedores[(string) $definicion->getProvider()][] = $id;
  }
}

$listaModulos = \Drupal::service('extension.list.module');
$instaladosModulos = array_keys($listaModulos->getAllInstalledInfo());

foreach ($proveedores as $modulo => $suyos) {
  printf("  modulo %-24s trae %d huerfanos: %s\n", $modulo, count($suyos), implode(', ', $suyos));
  printf("      instalado ahora mismo: %s\n", in_array($modulo, $instaladosModulos, TRUE) ? 'SI' : 'no');
  printf("      esta el codigo en el disco: %s\n",
    array_key_exists($modulo, $listaModulos->getList()) ? 'si' : 'no');
  // Un modulo desinstalado que deja rastro en el esquema del sistema es un
  // modulo a medio ir, y eso ya no es un fantasma suelto.
  $enEsquema = (int) $bd->query('SELECT COUNT(*) FROM {key_value} WHERE collection = :c AND name = :n',
    [':c' => 'system.schema', ':n' => $modulo])->fetchField();
  printf("      guarda version de esquema (system.schema): %s\n", $enEsquema ? 'SI' : 'no');
  printf("      aparece en core.extension: %s\n",
    array_key_exists($modulo, (array) \Drupal::config('core.extension')->get('module')) ? 'SI' : 'no');
  print "\n";
}

// ---------------------------------------------------------------------------
seccion('5. queda algo de esos modulos en el resto del ERP');

// Barrido a lo bruto por el nombre del proveedor, que es como se destapan las
// sorpresas: tablas, configuracion activa, permisos por los roles y rutas.
foreach (array_keys($proveedores) as $modulo) {
  printf("  --- %s ---\n", $modulo);

  $tablas = $bd->query('SHOW TABLES LIKE :t', [':t' => $modulo . '%'])->fetchCol();
  printf("      tablas que empiezan por %s: %d%s\n", $modulo, count($tablas),
    $tablas ? ' -> ' . implode(', ', $tablas) : '');

  $config = [];
  foreach ($fabricaConfig->listAll() as $nombre) {
    if (str_starts_with($nombre, $modulo . '.')) {
      $config[] = $nombre;
    }
  }
  printf("      configuracion activa que empieza por %s.: %d%s\n", $modulo, count($config),
    $config ? ' -> ' . implode(', ', $config) : '');

  $permisos = [];
  foreach ($gestorTipos->getStorage('user_role')->loadMultiple() as $rol) {
    foreach ($rol->getPermissions() as $permiso) {
      if (str_contains($permiso, $modulo)) {
        $permisos[] = $rol->id() . ': ' . $permiso;
      }
    }
  }
  printf("      permisos por los roles: %d%s\n", count($permisos),
    $permisos ? "\n          " . implode("\n          ", $permisos) : '');

  $rutas = [];
  foreach (\Drupal::service('router.route_provider')->getAllRoutes() as $nombre => $ruta) {
    if (str_starts_with($nombre, $modulo . '.')) {
      $rutas[] = $nombre;
    }
  }
  printf("      rutas registradas: %d%s\n", count($rutas),
    $rutas ? ' -> ' . implode(', ', array_slice($rutas, 0, 10)) : '');

  print "\n";
}

// ---------------------------------------------------------------------------
seccion('6. barrido del almacen de claves por cada huerfano');

// Los fantasmas se esconden en mas de una coleccion. Aqui se mira si el nombre
// asoma por alguna otra, que es lo que diria que la retirada tiene mas cola.
foreach ($huerfanos as $id) {
  $filas = $bd->query('SELECT collection, name FROM {key_value} WHERE name LIKE :t OR collection LIKE :t',
    [':t' => '%' . $id . '%'])->fetchAll();
  printf("  %-40s %d entradas en key_value\n", $id, count($filas));
  foreach ($filas as $fila) {
    printf("      %-46s %s\n", $fila->collection, $fila->name);
  }
}

// ---------------------------------------------------------------------------
seccion('7. de quien decian ser subtipo, y si ese nombre suena a otra cosa');

// Un tipo de subtipo derivado lleva escrito dentro de quien cuelga. Ese nombre
// es la firma de quien lo creo, y conviene leerla entera antes de borrar nada:
// un huerfano llamado `x_type` que dice ser subtipo de `x` significa que alguien
// creo el tipo `x` y luego lo borro. Si ademas el nombre `x` empieza por el de
// un modulo conocido, hay que mirar si ese modulo anda por medio o si solo es
// una coincidencia de nombre, que es lo que despista.
foreach ($huerfanos as $id) {
  $definicion = $almacenClaves->get($id . '.entity_type');
  if (!$definicion instanceof \Drupal\Core\Entity\EntityTypeInterface) {
    continue;
  }
  $padre = (string) $definicion->getBundleOf();
  printf("  %s\n", $id);
  if ($padre === '') {
    print "      no dice ser subtipo de nadie\n\n";
    continue;
  }

  printf("      decia ser subtipo de: %s\n", $padre);
  printf("      el sitio conoce %s: %s\n", $padre, $gestorTipos->hasDefinition($padre) ? 'SI' : 'no');
  printf("      tiene definicion instalada: %s\n",
    $almacenClaves->get($padre . '.entity_type') !== NULL ? 'SI' : 'no');
  printf("      configuracion eck.eck_entity_type.%s: %s\n", $padre,
    \Drupal::config('eck.eck_entity_type.' . $padre)->get('id') ? 'SI' : 'no');

  $tablasPadre = $bd->query('SHOW TABLES LIKE :t', [':t' => $padre . '%'])->fetchCol();
  printf("      tablas que empiezan por %s: %d%s\n", $padre, count($tablasPadre),
    $tablasPadre ? ' -> ' . implode(', ', $tablasPadre) : '');

  // El primer trozo del nombre es lo que hace pensar en un modulo. Se comprueba
  // uno por uno, de mas largo a mas corto, si existe un modulo que se llame asi.
  $trozos = explode('_', $padre);
  $sospechas = [];
  for ($i = count($trozos); $i > 0; $i--) {
    $candidato = implode('_', array_slice($trozos, 0, $i));
    $sospechas[] = sprintf('%s: %s en disco, %s instalado',
      $candidato,
      array_key_exists($candidato, $listaModulos->getList()) ? 'SI' : 'no',
      in_array($candidato, $instaladosModulos, TRUE) ? 'SI' : 'no');
  }
  print "      modulos que podrian llamarse asi:\n";
  foreach ($sospechas as $linea) {
    print '          ' . $linea . "\n";
  }

  // Y el barrido final por el nombre del padre en toda la configuracion activa,
  // que es lo que destaparia que ese nombre lo use algo mas.
  $mencionan = [];
  foreach ($fabricaConfig->listAll() as $nombre) {
    if (str_contains($nombre, $padre)) {
      $mencionan[] = $nombre;
    }
  }
  printf("      objetos de configuracion con %s en el nombre: %d%s\n", $padre, count($mencionan),
    $mencionan ? ' -> ' . implode(', ', $mencionan) : '');

  print "\n";
}

// ---------------------------------------------------------------------------
seccion('8. lo ve el nucleo por algun lado');

$gestorDefiniciones = \Drupal::service('entity.definition_update_manager');

// Esta es la demostracion de por que llevan ahi sin que nadie se enterase: la
// lista de cambios va vacia y el informe de estado dice que todo esta al dia,
// mientras getEntityTypes() los sigue devolviendo uno por uno.
$cambios = $gestorDefiniciones->getChangeSummary();
printf("  getChangeSummary() (lo que pinta el informe de estado): %d%s\n",
  count($cambios), $cambios ? ' -> ' . implode(', ', array_keys($cambios)) : ' (vacio: no sale nada en rojo)');
printf("  needsUpdates(): %s\n", $gestorDefiniciones->needsUpdates() ? 'SI' : 'no');

$porLaOtraPuerta = [];
foreach ($gestorDefiniciones->getEntityTypes() as $id => $definicion) {
  if (!$gestorTipos->hasDefinition($id)) {
    $porLaOtraPuerta[] = $id;
  }
}
printf("  getEntityTypes() con tipos que ya no existen: %d%s\n",
  count($porLaOtraPuerta), $porLaOtraPuerta ? ' -> ' . implode(', ', $porLaOtraPuerta) : '');

print "\n";
print str_repeat('=', 78) . "\n";
print "  FIN DEL RECONOCIMIENTO\n";
print str_repeat('=', 78) . "\n\n";
