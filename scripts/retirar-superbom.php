<?php

/**
 * Retira Super BOM y arregla los cuatro iconos que no llevaban a ninguna parte.
 *
 * Dos cosas que salieron de la misma consulta, y se hacen juntas porque las dos
 * tocan las portadas.
 *
 * Super BOM: su funcion la hace ya el tablero de stock. La consulta a la base
 * enseno que solo existe una referencia de verdad, el bloque de
 * `tec_superbom_ii` dentro del diseno del nodo 10, que es la propia pagina
 * `/bom`. La primera vista, `tec_superbom`, no la usaba nadie. Borrando el nodo
 * se va su diseno, y con el el bloque; y el nucleo borra ademas la entrada de
 * menu que apunta al nodo, en MenuLinkContentHooks::entityPredelete.
 *
 * Los iconos: cuatro de los catorce de la portada apuntaban al nodo en vez de a
 * la pantalla. Esos cuatro nodos son los unicos sin diseno propio, asi que caian
 * al diseno por defecto del tipo de contenido, que pinta el cuerpo vacio, el
 * icono otra vez y el numero de orden. Las pantallas de verdad son rutas del
 * modulo tec_production. Se arregla con redirecciones, que es lo que el sitio ya
 * usa para /customers y /products.
 *
 * Las redirecciones son temporales (302) a proposito. Si algun dia se hace bien
 * -un campo de enlace en el tipo de contenido y reescribir la salida de la
 * portada-, una permanente se habria quedado pegada en el navegador de todos y
 * costaria mas quitarla que ponerla.
 *
 * Uso: php vendor/bin/drush.php scr scripts/retirar-superbom.php
 */

use Drupal\Core\Language\LanguageInterface;
use Drupal\redirect\Entity\Redirect;

const NODO_SUPERBOM = 10;

const VISTAS = ['tec_superbom', 'tec_superbom_ii'];

// De donde a donde. La izquierda es el nodo que pinta el icono; la derecha, la
// ruta de tec_production que ensena la pantalla de verdad.
const ICONOS = [
  'node/11' => '/o/queue',
  'node/12' => '/production/log',
  'node/13' => '/production/report',
  'node/14' => '/stock',
];

$etm = \Drupal::entityTypeManager();

print "\n";

// -----------------------------------------------------------------------------
// 1. El nodo, y con el su diseno y su entrada de menu.
// -----------------------------------------------------------------------------
print "  --- 1. la pagina /bom ---\n\n";

$enlacesAntes = count($etm->getStorage('menu_link_content')->loadMultiple());

$nodo = $etm->getStorage('node')->load(NODO_SUPERBOM);
if (!$nodo) {
  print "      ya no estaba\n";
}
else {
  printf("      nodo %d \"%s\", %s\n", $nodo->id(), $nodo->label(), $nodo->toUrl()->toString());
  $nodo->delete();
  print "      borrado\n";
}

$enlacesDespues = count($etm->getStorage('menu_link_content')->loadMultiple());
printf("      entradas de menu: %d antes, %d despues\n\n", $enlacesAntes, $enlacesDespues);

// -----------------------------------------------------------------------------
// 2. Las dos vistas.
// -----------------------------------------------------------------------------
print "  --- 2. las dos vistas ---\n\n";

foreach (VISTAS as $id) {
  $vista = $etm->getStorage('view')->load($id);
  if (!$vista) {
    printf("      %-20s ya no estaba\n", $id);
    continue;
  }
  $pantallas = array_keys($vista->get('display'));
  printf("      %-20s \"%s\", %d pantalla%s: %s\n",
    $id,
    $vista->label(),
    count($pantallas),
    count($pantallas) === 1 ? '' : 's',
    implode(', ', $pantallas));
  $vista->delete();
  print "      " . str_pad('', 20) . " borrada\n";
}
print "\n";

// -----------------------------------------------------------------------------
// 3. Las cuatro redirecciones.
// -----------------------------------------------------------------------------
print "  --- 3. los cuatro iconos ---\n\n";

$almacen = $etm->getStorage('redirect');

foreach (ICONOS as $origen => $destino) {
  $existe = $almacen->getQuery()
    ->accessCheck(FALSE)
    ->condition('redirect_source.path', $origen)
    ->execute();

  if ($existe) {
    printf("      %-10s ya tenia redireccion\n", $origen);
    continue;
  }

  $redireccion = Redirect::create();
  $redireccion->setSource($origen);
  $redireccion->setRedirect($destino);
  $redireccion->setStatusCode(302);
  $redireccion->setLanguage(LanguageInterface::LANGCODE_NOT_SPECIFIED);
  $redireccion->save();

  printf("      %-10s -> %-22s (302, temporal)\n", $origen, $destino);
}

printf("\n      redirecciones en total: %d\n\n",
  (int) $almacen->getQuery()->accessCheck(FALSE)->count()->execute());

\Drupal::service('cache.render')->deleteAll();
\Drupal::service('cache.data')->deleteAll();
print "  cachés de pintado y de datos vaciadas\n\n";
