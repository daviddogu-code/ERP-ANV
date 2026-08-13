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

use Drupal\Core\Session\UserSession;
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
    fichasDeEjemplo()
  );
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
    foreach ($vista->get('display') ?? [] as $display) {
      if (($display['display_plugin'] ?? '') !== 'page') {
        continue;
      }
      // Una pantalla apagada no publica ruta, asi que pedirla da un 404 que no
      // significa nada.
      if (($display['display_options']['enabled'] ?? TRUE) === FALSE) {
        continue;
      }
      $ruta = $display['display_options']['path'] ?? NULL;
      // Las que llevan argumento en la direccion necesitan un valor real y se
      // cubren por otro lado, con las fichas de ejemplo.
      if ($ruta && !str_contains($ruta, '%')) {
        $rutas['/' . ltrim($ruta, '/')] = TRUE;
      }
    }
  }
  return array_keys($rutas);
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
