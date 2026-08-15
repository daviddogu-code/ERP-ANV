<?php

/**
 * @file
 * Por que no se guarda el orden de las tallas: si falla al escribir o al leer.
 *
 *   php vendor\bin\drush.php scr scripts/se-guarda-el-orden-de-las-tallas.php
 *
 * En /admin/structure/taxonomy/manage/tec_sizes/overview se arrastran las tallas
 * al orden que se quiere, se pulsa Save, y el orden no se guarda. Importa porque
 * el orden de un catalogo de tallas no es alfabetico ni casual -XS, S, M, L, XL,
 * y las onzas de 6 a 20- y asi sale mal en todos los desplegables y en los
 * documentos de produccion.
 *
 * El culpable era taxonomy_unique. Sus ajustes cuelgan del formulario del
 * vocabulario, y la pantalla de orden comparte ese formulario base, asi que el
 * modulo tambien la retocaba: le colgaba su guardador del boton Save y con eso
 * dejaba fuera al del nucleo. Se pulsaba Save, se guardaban sus ajustes, y los
 * pesos se tiraban a la basura sin una queja. Arreglado en
 * patches/taxonomy-unique-no-tocar-el-orden-de-terminos.patch.
 *
 * Esto se queda como prueba de las dos caras del arreglo:
 *
 *   - que el orden de las tallas se guarda al enviar el formulario
 *   - que taxonomy_unique sigue guardando sus ajustes donde si le toca
 *
 * El formulario se envia pidiendo la pagina y devolviendola con sus campos, como
 * lo hace un navegador, y no llamando al constructor de formularios a mano. El
 * primer intento fue por ahi y dio un falso negativo: OverviewTerms es un
 * formulario de entidad, se quedo sin entidad, revento antes de guardar nada y el
 * guion concluyo alegremente que el guardado no funcionaba. Un arnes a medias
 * miente igual que un fallo.
 *
 * Deja los pesos como estaban al terminar, asi que se puede lanzar de nuevo.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const DIRECCION = '/admin/structure/taxonomy/manage/tec_sizes/overview';

$gestor = \Drupal::entityTypeManager();
$almacen = $gestor->getStorage('taxonomy_term');
$bd = \Drupal::database();
$nucleo = \Drupal::service('http_kernel');

$cuenta = $gestor->getStorage('user')->load(1);
\Drupal::currentUser()->setAccount($cuenta);
$sesion = \Drupal::service('session');

/**
 * Lee los pesos directamente de la base, sin cache de entidades por medio.
 */
$pesos = function () use ($bd): array {
  return $bd->select('taxonomy_term_field_data', 't')
    ->fields('t', ['tid', 'name', 'weight'])
    ->condition('t.vid', 'tec_sizes')
    ->orderBy('t.weight')
    ->orderBy('t.name')
    ->execute()
    ->fetchAllAssoc('tid');
};

/**
 * Pide una pagina como usuario 1 y devuelve la respuesta.
 */
$pedir = function (string $ruta, string $metodo = 'GET', array $datos = []) use ($nucleo, $sesion) {
  $peticion = Request::create($ruta, $metodo, $datos);
  $peticion->setSession($sesion);
  // Sin captura, para ver la excepcion tal cual. Un formulario que guarda bien
  // redirige al acabar, y una redireccion dentro de una peticion interna sale
  // como EnforcedResponseException, asi que esa concreta es buena senal y se
  // traduce a su respuesta. Cualquier otra se ensena entera: el nucleo la
  // convertiria en un 500 pelado sin dejar rastro en el registro.
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (\Drupal\Core\Form\EnforcedResponseException $e) {
    echo "  (el formulario ha redirigido, que es lo que hace cuando guarda)\n";
    return $e->getResponse();
  }
  catch (\Throwable $e) {
    printf("  HA REVENTADO: %s\n      %s\n", get_class($e), $e->getMessage());
    printf("      en %s:%d\n", $e->getFile(), $e->getLine());
    foreach (array_slice($e->getTrace(), 0, 10) as $i => $paso) {
      printf(
        "      %2d  %s%s%s()  %s:%s\n",
        $i,
        $paso['class'] ?? '',
        $paso['type'] ?? '',
        $paso['function'] ?? '',
        isset($paso['file']) ? basename($paso['file']) : '',
        $paso['line'] ?? ''
      );
    }
    return new \Symfony\Component\HttpFoundation\Response('', 500);
  }
};

$antes = $pesos();

echo "\n";
echo "Las tallas y su peso ahora mismo\n";
echo str_repeat('-', 78) . "\n";
printf("  %-8s %-30s %s\n", 'tid', 'nombre', 'peso');
foreach ($antes as $fila) {
  printf("  %-8s %-30s %s\n", $fila->tid, mb_substr($fila->name, 0, 30), $fila->weight);
}
printf(
  "\n  %d tallas, la pantalla pagina cada %s, y %d tienen padre\n",
  count($antes),
  \Drupal::config('taxonomy.settings')->get('terms_per_page_admin'),
  count(array_filter($almacen->loadTree('tec_sizes', 0, NULL, FALSE), fn($t) => $t->depth > 0))
);

if (count($antes) < 2) {
  echo "\n  Con menos de dos tallas no hay orden que probar.\n\n";
  return;
}

echo "\n";
echo "Pidiendo la pantalla de orden\n";
echo str_repeat('-', 78) . "\n";

$respuesta = $pedir(DIRECCION);
printf("  codigo: %s\n", $respuesta->getStatusCode());
if ($respuesta->getStatusCode() >= 400) {
  echo "  la pagina no carga, asi que no se puede enviar nada.\n\n";
  return;
}

$html = $respuesta->getContent();
$documento = new \DOMDocument();
libxml_use_internal_errors(TRUE);
$documento->loadHTML($html);
libxml_clear_errors();

// Se copia todo lo que el navegador devolveria: los campos ocultos con las
// fichas de seguridad, y el valor elegido de cada desplegable.
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
$desplegables = [];
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
  $desplegables[] = $nombre;
}

$filas = array_values(array_filter(array_keys($datos), fn($n) => preg_match('/^terms\[tid:\d+:\d+\]\[weight\]$/', $n)));
printf("  campos recogidos: %d, de ellos %d desplegables\n", count($datos), count($desplegables));
printf("  filas de talla en el formulario: %d\n", count($filas));
printf("  ficha de seguridad: %s\n", isset($datos['form_token']) ? 'si' : 'NO, y sin ella no entra');
printf("  form_id: %s\n", $datos['form_id'] ?? '(no viene)');

if (!$filas) {
  echo "\n  El formulario no trae ni una fila con peso. Nada que enviar.\n\n";
  return;
}

// Se le da la vuelta al orden: la primera fila pasa a pesar lo mas alto.
$peso = count($filas) - 1;
foreach ($filas as $nombre) {
  $datos[$nombre] = (string) $peso--;
}
$datos['op'] = 'Save';

// Los nombres del HTML vienen con corchetes -terms[tid:90:0][weight]- y
// Request::create los toma como el nombre literal de un parametro, sin
// interpretarlos. Enviados asi, 'terms' no llega nunca y el nucleo no encuentra
// ninguna fila que actualizar: redirige tan contento sin escribir nada, que es
// exactamente el sintoma que se venia a investigar. Un arnes que reproduce el
// fallo por su cuenta no reproduce nada.
$anidados = [];
foreach ($datos as $nombre => $valor) {
  if (!preg_match_all('/\[([^\]]*)\]/', $nombre, $partes)) {
    $anidados[$nombre] = $valor;
    continue;
  }
  $camino = array_merge([strstr($nombre, '[', TRUE)], $partes[1]);
  \Drupal\Component\Utility\NestedArray::setValue($anidados, $camino, $valor);
}

echo "\n";
echo "Enviando el formulario con el orden al reves\n";
echo str_repeat('-', 78) . "\n";
printf("  filas dentro de 'terms': %d\n", count($anidados['terms'] ?? []));
$primera = key($anidados['terms'] ?? []);
printf("  la primera es '%s' y lleva %s\n", $primera, json_encode($anidados['terms'][$primera] ?? NULL));

$respuesta = $pedir(DIRECCION, 'POST', $anidados);
printf("  codigo: %s\n", $respuesta->getStatusCode());
if ($respuesta->isRedirection()) {
  printf("  redirige a: %s\n", $respuesta->headers->get('Location'));
}
foreach (\Drupal::messenger()->all() as $tipo => $mensajes) {
  foreach ($mensajes as $mensaje) {
    printf("  mensaje (%s): %s\n", $tipo, mb_substr(strip_tags((string) $mensaje), 0, 100));
  }
}
\Drupal::messenger()->deleteAll();

$almacen->resetCache();
$despues = $pesos();

echo "\n";
echo "Los pesos despues de guardar\n";
echo str_repeat('-', 78) . "\n";
printf("  %-8s %-30s %-8s %s\n", 'tid', 'nombre', 'antes', 'despues');
$cambiados = 0;
foreach ($antes as $tid => $fila) {
  $nuevo = $despues[$tid]->weight ?? '?';
  $cambio = (string) $fila->weight !== (string) $nuevo;
  if ($cambio) {
    $cambiados++;
  }
  printf("  %-8s %-30s %-8s %-8s %s\n", $tid, mb_substr($fila->name, 0, 30), $fila->weight, $nuevo, $cambio ? 'cambiado' : '');
}

echo "\n";
echo str_repeat('=', 78) . "\n";
if ($cambiados) {
  printf("Bien: el orden se guarda (%d de %d pesos han cambiado).\n", $cambiados, count($antes));
}
else {
  echo "MAL: ningun peso ha cambiado, el orden no se guarda.\n";
  echo "Mirar si taxonomy_unique ha vuelto a colgarse del boton Save, o si otro\n";
  echo "modulo hace lo mismo: patches/taxonomy-unique-no-tocar-el-orden-de-terminos.patch\n";
}
echo str_repeat('=', 78) . "\n";

// Se devuelve todo a como estaba, que esto es un diagnostico y no un cambio.
echo "\nDevolviendo los pesos originales...\n";
foreach ($antes as $tid => $fila) {
  if ((string) ($despues[$tid]->weight ?? '') !== (string) $fila->weight) {
    $termino = $almacen->loadUnchanged($tid);
    $termino->setWeight((int) $fila->weight);
    $termino->save();
  }
}
$almacen->resetCache();
$final = $pesos();
$mal = 0;
foreach ($antes as $tid => $fila) {
  if ((string) ($final[$tid]->weight ?? '') !== (string) $fila->weight) {
    $mal++;
  }
}
echo $mal === 0 ? "Devueltos.\n" : "OJO: $mal pesos no han vuelto a su sitio.\n";

// La otra cara del arreglo. El parche saca a taxonomy_unique de la pantalla de
// orden, asi que hay que ver que sigue trabajando en la pantalla del vocabulario,
// que es la suya. Si no, se habria cambiado un fallo por otro.
echo "\n";
echo "Y taxonomy_unique en la pantalla que si es suya\n";
echo str_repeat('-', 78) . "\n";

// Se monta el formulario del vocabulario y se mira quien cuelga de su boton
// Save. Ahi esta todo: si el guardador del modulo sigue en la cadena, el modulo
// sigue trabajando; y si ademas estan los dos del nucleo delante, es que se ha
// anadido al final en vez de dejarlos fuera, que es justo lo que hacia mal en la
// pantalla de orden. No se envia nada: montar el formulario no cambia nada y no
// hay fichas de seguridad de las que preocuparse.
$vocabulario = $gestor->getStorage('taxonomy_vocabulary')->load('tec_sizes');
$formObjeto = $gestor->getFormObject('taxonomy_vocabulary', 'default');
$formObjeto->setEntity($vocabulario);
$formulario = \Drupal::formBuilder()->buildForm($formObjeto, new \Drupal\Core\Form\FormState());

$cadena = $formulario['actions']['submit']['#submit'] ?? [];
$cadena = array_map(fn($g) => is_string($g) ? $g : '(cierre)', $cadena);
$tieneCasilla = isset($formulario['unique_container']['unique']);
$suGuardador = (bool) array_filter($cadena, fn($g) => str_starts_with($g, 'taxonomy_unique_'));
$losDelNucleo = in_array('::submitForm', $cadena, TRUE) && in_array('::save', $cadena, TRUE);

printf("  formulario: %s\n", $formulario['#form_id'] ?? '(ninguno)');
printf("  sale su casilla de terminos unicos: %s\n", $tieneCasilla ? 'si' : 'NO');
printf("  guardadores del boton Save:\n");
foreach ($cadena as $guardador) {
  printf("      %s\n", $guardador);
}

echo "\n" . str_repeat('=', 78) . "\n";
if ($tieneCasilla && $suGuardador && $losDelNucleo) {
  echo "Bien: el modulo sigue en su pantalla, y detras de los guardadores del\n";
  echo "nucleo en vez de por delante.\n";
}
elseif (!$tieneCasilla || !$suGuardador) {
  echo "MAL: el parche se ha llevado por delante los ajustes del modulo.\n";
}
else {
  echo "MAL: el modulo ha dejado fuera a los guardadores del nucleo, que es el\n";
  echo "fallo de siempre pero en la pantalla del vocabulario.\n";
}
echo str_repeat('=', 78) . "\n\n";
