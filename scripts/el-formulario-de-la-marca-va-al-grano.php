<?php

/**
 * @file
 * La marca no pregunta lo que nadie contesta, y trae puesto lo que ya se sabe.
 *
 *   php vendor\bin\drush.php scr scripts/el-formulario-de-la-marca-va-al-grano.php
 *
 * Cuatro cosas pequenas alrededor de la marca, y una de ellas con trampa.
 *
 * La trampa es el peso. Se esconde el bloque Relations, y dentro de ese bloque
 * viaja una casilla OBLIGATORIA, Weight. Una casilla obligatoria que nadie puede
 * ver deberia bloquear el formulario para siempre, y no lo hace porque Drupal
 * toma el valor de un elemento inalcanzable de su valor por omision. Eso no se
 * puede comprobar mirando el codigo: hay que enviar el formulario de verdad y
 * ver si la marca se guarda. Es lo que hace la primera seccion.
 *
 * Las otras tres son de mirar: que el canal RSS ya no esta, que el boton de
 * crear producto del catalogo lleva la marca pegada a la direccion, y que al
 * entrar por ahi la marca sale ya elegida.
 *
 * Crea una marca por el formulario y la borra al final.
 */

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\EnforcedResponseException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const NOMBRE = 'PRUEBA marca por formulario';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$nucleo = \Drupal::service('http_kernel');
$sesion = \Drupal::service('session');
$terminos = $gestor->getStorage('taxonomy_term');

$problemas = 0;

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

/**
 * Pide una pagina como usuario 1. Guardar bien redirige, y una redireccion
 * dentro de una peticion interna sale como excepcion, asi que esa se traduce.
 */
$pedir = function (string $ruta, string $metodo = 'GET', array $datos = []) use ($nucleo, $sesion): Response {
  $peticion = Request::create($ruta, $metodo, $datos);
  $peticion->setSession($sesion);
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (EnforcedResponseException $e) {
    return $e->getResponse();
  }
  catch (HttpExceptionInterface $e) {
    // Una direccion sin ruta no da un 404: revienta con una excepcion, porque a
    // una peticion interna se le pide que no la disimule. El codigo que trae es
    // el que el navegador veria.
    return new Response('', $e->getStatusCode());
  }
  catch (\Throwable $e) {
    printf("      HA REVENTADO: %s: %s (%s:%d)\n", get_class($e), $e->getMessage(), basename($e->getFile()), $e->getLine());
    return new Response('', 500);
  }
};

/**
 * Todo lo que un navegador devolveria de un formulario: los campos ocultos con
 * las fichas de seguridad y el valor elegido de cada desplegable.
 */
$campos = function (string $html): array {
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML($html);
  libxml_clear_errors();

  $datos = [];
  foreach ($documento->getElementsByTagName('input') as $campo) {
    $nombre = $campo->getAttribute('name');
    $tipo = strtolower($campo->getAttribute('type'));
    if ($nombre === '' || in_array($tipo, ['submit', 'button', 'image'], TRUE)) {
      continue;
    }
    if (in_array($tipo, ['checkbox', 'radio'], TRUE) && !$campo->hasAttribute('checked')) {
      continue;
    }
    $datos[$nombre] = $campo->getAttribute('value');
  }
  foreach ($documento->getElementsByTagName('select') as $campo) {
    $nombre = $campo->getAttribute('name');
    if ($nombre === '') {
      continue;
    }
    $elegido = '';
    foreach ($campo->getElementsByTagName('option') as $opcion) {
      if ($opcion->hasAttribute('selected')) {
        $elegido = $opcion->getAttribute('value');
      }
    }
    $datos[$nombre] = $elegido;
  }

  return $datos;
};

/**
 * Los nombres del HTML vienen con corchetes y hay que anidarlos, o el nucleo no
 * recibe nada y redirige tan contento sin haber guardado.
 */
$anidar = function (array $datos): array {
  $anidados = [];
  foreach ($datos as $nombre => $valor) {
    if (!preg_match_all('/\[([^\]]*)\]/', $nombre, $partes)) {
      $anidados[$nombre] = $valor;
      continue;
    }
    $camino = array_merge([strstr($nombre, '[', TRUE)], $partes[1]);
    NestedArray::setValue($anidados, $camino, $valor);
  }

  return $anidados;
};

/**
 * Si algun desplegable trae ese valor ya elegido.
 */
$yaElegido = function (string $html, string $valor): bool {
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML($html);
  libxml_clear_errors();

  foreach ($documento->getElementsByTagName('option') as $opcion) {
    if ($opcion->hasAttribute('selected') && $opcion->getAttribute('value') === $valor) {
      return TRUE;
    }
  }
  foreach ($documento->getElementsByTagName('input') as $campo) {
    if ($campo->getAttribute('value') === $valor) {
      return TRUE;
    }
  }

  return FALSE;
};

// Por si quedo una de una vuelta anterior.
foreach ($terminos->loadByProperties(['vid' => 'tec_brands', 'name' => NOMBRE]) as $vieja) {
  $vieja->delete();
}

print "\n";
print "El formulario de la marca\n";
print str_repeat('-', 70) . "\n";

$respuesta = $pedir('/admin/structure/taxonomy/manage/tec_brands/add');
$html = (string) $respuesta->getContent();
$mirar('la pantalla de crear una marca carga', $respuesta->getStatusCode() === 200,
  'codigo ' . $respuesta->getStatusCode());

$mirar('ya no pregunta por el termino padre', !str_contains($html, 'Parent terms'));
$mirar('ni por el peso', !preg_match('/name="weight"/', $html));
$mirar('ni por la informacion de revision', !str_contains($html, 'Revision log message'));

$datos = $campos($html);
$datos['name[0][value]'] = NOMBRE;
$datos['op'] = 'Save';
$mirar('trae la ficha de seguridad', isset($datos['form_token']) || isset($datos['form_build_id']));

$respuesta = $pedir('/admin/structure/taxonomy/manage/tec_brands/add', 'POST', $anidar($datos));
$avisos = [];
foreach (\Drupal::messenger()->all() as $tipo => $mensajes) {
  foreach ($mensajes as $mensaje) {
    $avisos[] = $tipo . ': ' . mb_substr(strip_tags((string) $mensaje), 0, 80);
  }
}
\Drupal::messenger()->deleteAll();

$terminos->resetCache();
$creadas = $terminos->loadByProperties(['vid' => 'tec_brands', 'name' => NOMBRE]);
$mirar('y aun asi la marca se guarda, con el peso escondido',
  count($creadas) === 1,
  $avisos === [] ? 'codigo ' . $respuesta->getStatusCode() : implode(' | ', $avisos));

$marca = $creadas ? reset($creadas) : NULL;
if ($marca) {
  $mirar('con el peso que le tocaba por omision', (int) $marca->getWeight() === 0,
    'peso ' . $marca->getWeight());
}

// Para las secciones de abajo hace falta una marca que exista de verdad.
$tid = $marca ? (string) $marca->id() : NULL;
if ($tid === NULL) {
  $ids = $terminos->getQuery()->accessCheck(FALSE)->condition('vid', 'tec_brands')->range(0, 1)->execute();
  $tid = $ids ? (string) reset($ids) : NULL;
}

if ($tid === NULL) {
  print "\n  No hay ninguna marca con la que seguir.\n\n";
  return;
}

print "\n";
print "El canal RSS de las fichas de termino\n";
print str_repeat('-', 70) . "\n";

$respuesta = $pedir('/taxonomy/term/' . $tid . '/feed');
$mirar('la direccion del canal ya no existe', $respuesta->getStatusCode() === 404,
  'codigo ' . $respuesta->getStatusCode());

$respuesta = $pedir('/taxonomy/term/' . $tid);
$html = (string) $respuesta->getContent();
$mirar('la ficha de la marca carga', $respuesta->getStatusCode() === 200,
  'codigo ' . $respuesta->getStatusCode());
$mirar('y ya no ensena el icono naranja',
  !str_contains($html, '/taxonomy/term/' . $tid . '/feed'));

print "\n";
print "Crear un producto desde el catalogo\n";
print str_repeat('-', 70) . "\n";

// La marca que se acaba de crear no tiene ni un producto, que es como nace toda
// marca nueva. Su ficha tiene que decirlo y ofrecer la puerta de todos modos, o
// una marca abierta hoy no tiene por donde recibir su primer producto.
$mirar('una marca sin productos lo dice en su ficha',
  str_contains($html, 'Nothing to show here yet'));
$mirar('y ofrece el boton de crear el primero con su marca pegada',
  str_contains($html, 'brand_id=' . $tid)
  && !str_contains($html, 'add/tec_product?brand_id=' . $tid . '&destination=/taxonomy/term/'));
$mirar('y el boton de ordenar al lado, que no depende de la pestana',
  str_contains($html, '/taxonomy/term/' . $tid . '/organize'));

$respuesta = $pedir('/admin/content/tec_product/add/tec_product?brand_id=' . $tid);
$html = (string) $respuesta->getContent();
$mirar('el formulario del producto carga', $respuesta->getStatusCode() === 200,
  'codigo ' . $respuesta->getStatusCode());
$mirar('y la marca viene ya elegida', $yaElegido($html, $tid), 'marca ' . $tid);

$mirar('con su puerta al catalogo de marcas al lado',
  str_contains($html, 'Manage brands')
  && str_contains($html, '/admin/structure/taxonomy/manage/tec_brands/overview'));

// Sin la marca en la direccion no debe pasar nada raro: el campo se queda vacio.
$respuesta = $pedir('/admin/content/tec_product/add/tec_product');
$mirar('y sin marca en la direccion el formulario sigue entero',
  $respuesta->getStatusCode() === 200, 'codigo ' . $respuesta->getStatusCode());

if ($marca) {
  $terminos->loadUnchanged($marca->id())?->delete();
}

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. La marca de prueba se ha borrado.\n\n";
}
else {
  printf("  %d cosas mal. La marca de prueba se ha borrado.\n\n", $problemas);
}
