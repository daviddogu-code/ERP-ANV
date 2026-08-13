<?php

/**
 * @file
 * Pide las paginas importantes del ERP por dentro y comprueba que responden.
 *
 *   php vendor/bin/drush scr scripts/cargan-las-paginas.php
 *   php vendor/bin/drush scr scripts/cargan-las-paginas.php -- /o/queue /stock
 *
 * scripts/comprobacion.php mira los datos y los automatismos, pero no dibuja ni
 * una pantalla. Y dibujar es justo donde asoman las subidas de modulos: el
 * fallo de views_aggregator de la subida del nucleo no rompio ni un dato, solo
 * devolvia un 500 en la pagina de pedidos, y no habia forma de verlo sin pedir
 * la pagina.
 *
 * Lanza peticiones internas al nucleo como usuario 1, asi que no hace falta ni
 * servidor web ni sesion. Devuelve el codigo HTTP y, si algo revienta, el
 * mensaje y el fichero donde paso.
 *
 * No escribe nada. Lo unico que cambia es lo que las propias paginas hagan al
 * pintarse, que en un ERP de solo lectura no deberia ser nada.
 */

use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$rutas = $extra ?? [];
if (!$rutas) {
  $rutas = array_merge(
    [
      // Lo que usa la fabrica todos los dias.
      '/start',
      '/o/queue',
      '/stock',
      '/production/log',
      '/production/report',
      // Administracion, que es donde se nota un modulo mal subido.
      '/admin/modules',
      '/admin/reports/status',
      '/admin/reports/dblog',
      '/admin/structure/views',
      '/admin/config/workflow/eca',
      '/admin/structure/quicktabs',
    ],
    paginasDeVista(),
    exportaciones(),
    fichasDeEjemplo()
  );
}

/**
 * Las descargas: el inventario a Excel y las ordenes de compra a PDF.
 *
 * Van aparte de las paginas normales porque no se pintan igual y porque tiran
 * de librerias que nadie mira: `xls_serialization` arrastra `phpspreadsheet`,
 * que en la tanda de agosto salto de la 1.30 a la 3.10 de golpe. Una descarga
 * rota no se nota hasta que alguien la necesita.
 */
function exportaciones(): array {
  $rutas = [];
  $factory = \Drupal::configFactory();

  foreach ($factory->listAll('views.view.') as $nombre) {
    $vista = $factory->get($nombre);
    if (!$vista->get('status')) {
      continue;
    }
    $base = $vista->get('base_table');
    $vistaId = substr($nombre, strlen('views.view.'));
    foreach ($vista->get('display') ?? [] as $displayId => $display) {
      if (($display['display_plugin'] ?? '') !== 'data_export') {
        continue;
      }
      $ruta = $display['display_options']['path'] ?? NULL;
      if (!$ruta) {
        continue;
      }
      $ruta = conArgumentosReales($ruta, $vistaId, $displayId, $base);
      if (!$ruta) {
        continue;
      }
      $rutas[] = '/' . ltrim($ruta, '/');
    }
  }
  return $rutas;
}

/**
 * La sesion que hay que colgar de cada peticion.
 *
 * No vale una sesion de mentira montada a mano. Hay modulos que sustituyen la
 * bolsa de metadatos de la sesion por una suya con metodos propios: masquerade
 * lo hace, y una bolsa normal revienta con "getMasquerade() no existe" en todas
 * las paginas a la vez. Como esa cascada aparece justo despues de subir un
 * modulo, se confunde con un fallo de la subida y se pierde media tarde. Se
 * coge la del contenedor, que es la de verdad.
 */
function sesion(): \Symfony\Component\HttpFoundation\Session\SessionInterface {
  static $sesion = NULL;
  if ($sesion !== NULL) {
    return $sesion;
  }
  try {
    return $sesion = \Drupal::service('session');
  }
  catch (\Throwable $e) {
    return $sesion = new \Symfony\Component\HttpFoundation\Session\Session(
      new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage(
        'MOCK',
        '',
        \Drupal::service('session_manager.metadata_bag')
      )
    );
  }
}

/**
 * Las direcciones de las vistas que tienen pagina propia.
 */
function paginasDeVista(): array {
  $rutas = [];
  $factory = \Drupal::configFactory();
  foreach ($factory->listAll('views.view.tec_') as $nombre) {
    $vista = $factory->get($nombre);
    if (!$vista->get('status')) {
      continue;
    }
    $vistaId = substr($nombre, strlen('views.view.'));
    foreach ($vista->get('display') ?? [] as $displayId => $display) {
      if (($display['display_plugin'] ?? '') !== 'page') {
        continue;
      }
      // Una pantalla apagada no publica ruta, asi que pedirla da un 404 que no
      // significa nada.
      if (($display['display_options']['enabled'] ?? TRUE) === FALSE) {
        continue;
      }
      $ruta = $display['display_options']['path'] ?? NULL;
      if (!$ruta) {
        continue;
      }
      $ruta = conArgumentosReales($ruta, $vistaId, $displayId, $vista->get('base_table'));
      if ($ruta) {
        $rutas['/' . ltrim($ruta, '/')] = TRUE;
      }
    }
  }
  return array_keys($rutas);
}

/**
 * Cambia el % de una direccion por un argumento que devuelve filas de verdad.
 *
 * Hasta agosto de 2026 las vistas con argumento se saltaban, dando por hecho
 * que las fichas de ejemplo ya las cubrian. No las cubren: la ficha va a la
 * direccion canonica de la entidad, y las pantallas de trabajo del ERP son
 * vistas con el identificador en la direccion. La de editar las lineas de un
 * pedido, /o/draft/%, llevaba devolviendo un 500 desde la subida a Drupal 11 y
 * esta prueba decia que todo cargaba, porque nunca la pedia.
 *
 * Y no vale con poner cualquier numero. La primera version de esto rellenaba
 * con la entidad mas reciente del tipo de la tabla base, y en /o/draft metia un
 * identificador de linea donde va uno de pedido: la pagina devolvia 200 pintando
 * una tabla vacia. Con cero filas el fallo no aparece, porque el modulo que se
 * rompia solo trabaja dentro del bucle de filas. O sea que daba un aprobado
 * falso, que es peor que no mirar. Ahora se prueba el argumento ejecutando la
 * vista antes de pedir la pagina, y solo se acepta si trae filas.
 *
 * Devuelve NULL cuando no hay ningun argumento que sirva, que no es un fallo:
 * significa que esa pantalla no tiene datos con los que probarse.
 */
function conArgumentosReales(string $ruta, string $vistaId, string $displayId, ?string $base): ?string {
  if (!str_contains($ruta, '%')) {
    return $ruta;
  }
  // Con dos argumentos habria que probar combinaciones, y no hay ninguna vista
  // asi en el ERP. Si algun dia la hay, aparecera como pantalla sin cubrir.
  if (substr_count($ruta, '%') !== 1) {
    return NULL;
  }
  foreach (argumentosPosibles($vistaId, $displayId, $base) as $valor) {
    if (laVistaTraeFilas($vistaId, $displayId, $valor)) {
      return str_replace('%', (string) $valor, $ruta);
    }
  }
  return NULL;
}

/**
 * Valores que merece la pena probar como argumento, del mejor al peor.
 *
 * El primer sitio donde buscar es la propia vista: sus manejadores de argumento
 * dicen de que tabla y de que columna sale el valor, asi que un valor cogido de
 * ahi esta en los datos por definicion. Como respaldo queda la entidad mas
 * reciente del tipo de la tabla base, que es lo que sirve para las vistas cuyo
 * argumento es el identificador de la propia ficha.
 */
function argumentosPosibles(string $vistaId, string $displayId, ?string $base): array {
  $valores = [];

  $vista = Views::getView($vistaId);
  if ($vista) {
    $vista->setDisplay($displayId);
    $manejadores = $vista->display_handler->getHandlers('argument');
    $manejador = reset($manejadores);
    if ($manejador) {
      $campo = $manejador->realField ?: $manejador->field;
      try {
        $consulta = \Drupal::database()->select($manejador->table, 't');
        $consulta->addField('t', $campo, 'v');
        $consulta->isNotNull($campo);
        $consulta->distinct();
        $consulta->orderBy('v', 'DESC');
        $consulta->range(0, 8);
        $valores = $consulta->execute()->fetchCol();
      }
      catch (\Throwable $e) {
        // Hay argumentos que no salen de una tabla; queda el respaldo.
      }
    }
    $vista->destroy();
  }

  // La tabla base no es el tipo de entidad: cuando la entidad tiene datos
  // traducibles, Views apunta a `<tipo>_field_data`.
  $tipo = preg_replace('/_field_data$|_data$/', '', (string) $base);
  $gestor = \Drupal::entityTypeManager();
  if ($tipo && $gestor->hasDefinition($tipo)) {
    $clave = $gestor->getDefinition($tipo)->getKey('id');
    if ($clave) {
      $ids = $gestor->getStorage($tipo)->getQuery()
        ->accessCheck(FALSE)->sort($clave, 'DESC')->range(0, 3)->execute();
      $valores = array_merge($valores, array_values($ids));
    }
  }

  return array_values(array_unique($valores));
}

/**
 * Si la vista, con ese argumento, devuelve alguna fila.
 *
 * Cuando la vista ni se puede ejecutar devuelve TRUE a proposito: se quiere que
 * la pagina se pida igual, para que el fallo salga por donde tiene que salir,
 * con su codigo HTTP y su mensaje, y no se quede escondido aqui.
 */
function laVistaTraeFilas(string $vistaId, string $displayId, $valor): bool {
  $vista = Views::getView($vistaId);
  if (!$vista) {
    return FALSE;
  }
  $vista->setDisplay($displayId);
  $vista->setArguments([$valor]);
  try {
    $vista->preExecute();
    $vista->execute();
  }
  catch (\Throwable $e) {
    return TRUE;
  }
  $filas = count($vista->result);
  $vista->destroy();
  return $filas > 0;
}

/**
 * Una ficha de verdad de cada tipo de entidad del ERP.
 *
 * Coge la mas reciente de cada grupo, que es la que mas probabilidades tiene de
 * usar los campos nuevos y de estar a medio rellenar, que es donde se rompe.
 */
function fichasDeEjemplo(): array {
  $rutas = [];
  $gestor = \Drupal::entityTypeManager();

  foreach (['tec_order', 'tec_product', 'tec_crm', 'tec_line_item', 'tec_inventory'] as $tipo) {
    if (!$gestor->hasDefinition($tipo)) {
      continue;
    }
    $almacen = $gestor->getStorage($tipo);
    $grupos = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($tipo));
    foreach ($grupos as $grupo) {
      $ids = $almacen->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $grupo)
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
      if (!$ids) {
        continue;
      }
      $entidad = $almacen->load(reset($ids));
      try {
        $rutas[] = $entidad->toUrl()->toString();
      }
      catch (\Throwable $e) {
        // Hay grupos sin pagina propia; no es un fallo.
      }
    }
  }
  return $rutas;
}

$kernel = \Drupal::service('http_kernel');
$cuenta = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$sesionAnterior = \Drupal::currentUser()->getAccount();
\Drupal::currentUser()->setAccount($cuenta);

echo "\n";
printf("  %-34s %-6s %-9s %s\n", 'pagina', 'codigo', 'tiempo', 'notas');
echo '  ' . str_repeat('-', 96) . "\n";

$malas = 0;
foreach ($rutas as $ruta) {
  $peticion = Request::create($ruta, 'GET');
  $peticion->setSession(sesion());

  $t0 = microtime(TRUE);
  $nota = '';
  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    $codigo = $respuesta->getStatusCode();
  }
  catch (\Throwable $e) {
    $codigo = 'EXCEPCION';
    $nota = get_class($e) . ': ' . $e->getMessage()
      . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
  }
  $ms = round((microtime(TRUE) - $t0) * 1000);

  // El 403 en una ruta que exige permisos no es un fallo de la subida; el 404
  // y el 500 si lo son.
  $bien = is_int($codigo) && $codigo < 400;
  if (!$bien && $codigo === 403) {
    $nota = $nota ?: 'sin permiso, no es un fallo de la subida';
  }
  if (!$bien && $codigo !== 403) {
    $malas++;
  }

  printf(
    "  %-34s %-6s %6s ms  %s\n",
    substr($ruta, 0, 34),
    $codigo,
    $ms,
    substr($nota, 0, 110)
  );
  // Sin esto no se ve nada hasta el final, y entonces no hay forma de saber en
  // que pagina se ha quedado colgado.
  flush();
}

\Drupal::currentUser()->setAccount($sesionAnterior);

echo "\n";
printf("  %d paginas, %d con problemas\n\n", count($rutas), $malas);
echo $malas === 0
  ? "Todas cargan.\n\n"
  : "HAY $malas paginas rotas. No seguir hasta entenderlas.\n\n";
