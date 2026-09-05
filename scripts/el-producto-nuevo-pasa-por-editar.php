<?php

/**
 * @file
 * Del catalogo al producto: alta, editar colores, ficha. Sin volver a /node/1.
 *
 *   php vendor\bin\drush.php scr scripts/el-producto-nuevo-pasa-por-editar.php
 *
 * El boton + Product del catalogo lleva destination=/node/1. Si Save respetara
 * eso, el alta rebotaria al listado sin colores ni fotos. Save en el alta tiene
 * que abrir la edicion de ese producto (con el destination pegado por si acaso
 * se cancela), y Save en la edicion tiene que abrir la ficha, no el catalogo.
 */

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\tec_inventory\PatternRecipe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const NOMBRE = 'PRUEBA flujo alta producto';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$nucleo = \Drupal::service('http_kernel');
$sesion = \Drupal::service('session');
$productos = $gestor->getStorage('tec_product');
$problemas = 0;
$creado = NULL;

$mirar = static function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$pedir = static function (string $ruta, string $metodo = 'GET', array $datos = []) use ($nucleo, $sesion): Response {
  $peticion = Request::create($ruta, $metodo, $datos);
  $peticion->setSession($sesion);
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (EnforcedResponseException $e) {
    return $e->getResponse();
  }
  catch (HttpExceptionInterface $e) {
    return new Response('', $e->getStatusCode());
  }
  catch (\Throwable $e) {
    printf("      HA REVENTADO: %s: %s (%s:%d)\n", get_class($e), $e->getMessage(), basename($e->getFile()), $e->getLine());
    return new Response('', 500);
  }
};

$campos = static function (string $html): array {
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
      $valor = $opcion->getAttribute('value');
      if ($opcion->hasAttribute('selected') && $valor !== '' && $valor !== '_none') {
        $elegido = $valor;
      }
    }
    if ($elegido === '') {
      foreach ($campo->getElementsByTagName('option') as $opcion) {
        $valor = $opcion->getAttribute('value');
        if ($valor !== '' && $valor !== '_none') {
          $elegido = $valor;
          break;
        }
      }
    }
    $datos[$nombre] = $elegido;
  }
  return $datos;
};

$anidar = static function (array $datos): array {
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

$hacia = static function (Response $respuesta): array {
  $sitio = (string) $respuesta->headers->get('Location', '');
  if ($sitio === '' && method_exists($respuesta, 'getTargetUrl')) {
    $sitio = (string) $respuesta->getTargetUrl();
  }
  if ($sitio === '') {
    return ['', ''];
  }
  $trozos = parse_url($sitio);
  $path = $trozos['path'] ?? $sitio;
  $completo = $path;
  if (!empty($trozos['query'])) {
    $completo .= '?' . $trozos['query'];
  }
  return [$completo, $path];
};

$avisos = static function (): string {
  $lista = [];
  foreach (\Drupal::messenger()->all() as $tipo => $mensajes) {
    foreach ($mensajes as $mensaje) {
      $lista[] = $tipo . ': ' . mb_substr(strip_tags((string) $mensaje), 0, 80);
    }
  }
  \Drupal::messenger()->deleteAll();
  return implode(' | ', $lista);
};

foreach ($productos->loadByProperties(['type' => 'tec_product', 'title' => NOMBRE]) as $viejo) {
  $viejo->delete();
}
foreach ($productos->loadByProperties(['type' => 'tec_product', 'field_product_name' => NOMBRE]) as $viejo) {
  $viejo->delete();
}

echo "\n";
echo "1. El catalogo ofrece el alta\n";
echo str_repeat('-', 70) . "\n";

$catalogo = $pedir('/p');
if ($catalogo->getStatusCode() !== 200) {
  $catalogo = $pedir('/node/1');
}
$html = (string) $catalogo->getContent();
$mirar('el catalogo carga', in_array($catalogo->getStatusCode(), [200, 301, 302], TRUE),
  'codigo ' . $catalogo->getStatusCode());
$mirar('+ Product apunta al alta con destination del catalogo',
  (bool) preg_match('~href="/admin/content/tec_product/add/tec_product\?destination=/node/1"~', $html));

echo "\n";
echo "2. El alta no pide colores\n";
echo str_repeat('-', 70) . "\n";

$alta = '/admin/content/tec_product/add/tec_product?destination=/node/1';
$respuesta = $pedir($alta);
$html = (string) $respuesta->getContent();
$mirar('el formulario de alta carga', $respuesta->getStatusCode() === 200,
  'codigo ' . $respuesta->getStatusCode());
$mirar('el pattern sale en el formulario',
  str_contains($html, 'field_tec_factory_pattern')
  || str_contains($html, 'name="field_tec_factory_pattern'));
$mirar('no sale el IEF de colores',
  !str_contains($html, 'Add new color')
  && !preg_match('~name="field_tec_color_variations~', $html));

$datos = $campos($html);
$datos['field_product_name[0][value]'] = NOMBRE;
$datos['op'] = 'Save';
$mirar('trae la ficha de seguridad', isset($datos['form_token']) || isset($datos['form_build_id']));

$tieneMarca = FALSE;
$tieneTipo = FALSE;
$tieneMaterial = FALSE;
$tienePattern = FALSE;
foreach ($datos as $nombre => $valor) {
  if ($valor === '' || $valor === '_none') {
    continue;
  }
  $tieneMarca = $tieneMarca || str_contains($nombre, 'field_tec_brand');
  $tieneTipo = $tieneTipo || str_contains($nombre, 'field_tec_product_type');
  $tieneMaterial = $tieneMaterial || str_contains($nombre, 'field_tec_product_material');
  $tienePattern = $tienePattern || str_contains($nombre, PatternRecipe::PATTERN_FIELD);
}
$mirar('hay una marca que elegir', $tieneMarca);
$mirar('hay un tipo que elegir', $tieneTipo);
$mirar('hay un material que elegir', $tieneMaterial);
$mirar('hay un pattern que elegir', $tienePattern);

echo "\n";
echo "3. Save en el alta abre la edicion, no el catalogo\n";
echo str_repeat('-', 70) . "\n";

$respuesta = $pedir($alta, 'POST', $anidar($datos));
[$sitio, $path] = $hacia($respuesta);
$textoAvisos = $avisos();
$productos->resetCache();
$hechos = $productos->loadByProperties(['type' => 'tec_product', 'field_product_name' => NOMBRE]);
if (!$hechos) {
  $hechos = $productos->loadByProperties(['type' => 'tec_product', 'title' => NOMBRE]);
}
$creado = $hechos ? reset($hechos) : NULL;
$mirar('el producto se guarda', (bool) $creado,
  $textoAvisos !== '' ? $textoAvisos : ('codigo ' . $respuesta->getStatusCode() . ' → ' . $sitio));

if ($creado) {
  $id = (int) $creado->id();
  $esperada = '/tec_product/' . $id . '/edit?destination=/node/1';
  $mirar('Save no vuelve al catalogo', $path !== '/node/1' && $path !== '/p',
    'fue a ' . ($sitio === '' ? '(sin Location)' : $sitio));
  $mirar('Save abre /tec_product/' . $id . '/edit?destination=/node/1',
    $sitio === $esperada || str_ends_with($sitio, $esperada),
    'fue a ' . ($sitio === '' ? '(sin Location)' : $sitio));

  echo "\n";
  echo "4. En editar ya se pueden crear colores\n";
  echo str_repeat('-', 70) . "\n";

  $editar = '/tec_product/' . $id . '/edit?destination=/node/1';
  $respuesta = $pedir($editar);
  $html = (string) $respuesta->getContent();
  $mirar('el formulario de editar carga', $respuesta->getStatusCode() === 200,
    'codigo ' . $respuesta->getStatusCode());
  $mirar('sale el IEF de colores',
    str_contains($html, 'Add new color')
    || preg_match('~name="field_tec_color_variations~', $html)
    || str_contains($html, 'field--name-field-tec-color-variations'));

  echo "\n";
  echo "5. Save en editar abre la ficha, no el catalogo\n";
  echo str_repeat('-', 70) . "\n";

  $datos = $campos($html);
  $datos['op'] = 'Save';
  $respuesta = $pedir($editar, 'POST', $anidar($datos));
  [$sitio, $path] = $hacia($respuesta);
  $textoAvisos = $avisos();
  $ficha = '/tec_product/' . $id;
  $mirar('Save no vuelve al catalogo', $path !== '/node/1' && $path !== '/p',
    'fue a ' . ($sitio === '' ? '(sin Location)' : $sitio));
  $mirar('Save abre la ficha /tec_product/' . $id,
    $path === $ficha,
    ($textoAvisos !== '' ? $textoAvisos . ' ' : '') . 'fue a ' . ($sitio === '' ? '(sin Location)' : $sitio));
}

if ($creado) {
  try {
    $creado->delete();
  }
  catch (\Throwable $e) {
  }
}

echo "\n";
if ($problemas) {
  echo $problemas . " fallos.\n\n";
  return 1;
}
echo "Ningun fallo.\n\n";
return 0;
