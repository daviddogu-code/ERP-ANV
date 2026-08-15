<?php

/**
 * @file
 * Compara la portada de marcas con la de colores para ver que se torcio.
 *
 * El dueno dice que /b funciona pero ha perdido el formato: la tarjeta sale
 * encima del titulo. La sospecha es el orden, pero no se toca nada hasta verlo,
 * porque una portada tiene mas partes de las que parece y cualquiera de ellas
 * puede ser la que falta: la plantilla de la seccion, sus ajustes, los ajustes
 * de terceros, el modo de vista de cada pieza y el peso.
 *
 * Se compara contra el nodo 9 (colores) porque es el molde del que se copio: si
 * una diferencia no aparece aqui, no es una diferencia que explique el fallo.
 *
 * Y se comparan tambien los dos HTML. Los pesos explicarian el orden, pero no
 * la zona gris ni el boton sin estilo, y esas dos cosas pueden ser normales:
 * puede que /cl salga igual y no haya nada que arreglar. Comparar es la unica
 * forma de saber si algo esta roto o simplemente es asi.
 *
 * No escribe nada.
 *
 * Uso: drush php:script scripts/donde-se-desordeno-la-portada-de-marcas
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$almacenDeNodos = \Drupal::entityTypeManager()->getStorage('node');

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 1. El diseno de las dos portadas, lado a lado\n";
echo str_repeat('=', 82) . "\n\n";

/**
 * Saca el diseno de un nodo en forma de lista plana, comparable a ojo.
 */
$diseno = static function (int $nid): array {
  $nodo = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
  if (!$nodo) {
    return [];
  }
  $partes = [];
  foreach ($nodo->get('layout_builder__layout') as $indice => $trozo) {
    $seccion = $trozo->section;
    $partes['seccion ' . $indice . ' plantilla'] = $seccion->getLayoutId();
    $partes['seccion ' . $indice . ' ajustes'] = json_encode($seccion->getLayoutSettings(), JSON_UNESCAPED_SLASHES);
    // Los ajustes de terceros no se copiaron en el guion que rehizo la portada,
    // asi que hay que mirarlos: si el molde llevaba alguno, falta.
    $deTerceros = [];
    foreach ($seccion->getThirdPartyProviders() as $proveedor) {
      $deTerceros[$proveedor] = $seccion->getThirdPartySettings($proveedor);
    }
    $partes['seccion ' . $indice . ' terceros'] = $deTerceros ? json_encode($deTerceros, JSON_UNESCAPED_SLASHES) : '(ninguno)';

    // Se ordenan por peso, que es como se van a pintar, no por el orden en que
    // estan guardadas: justo esa diferencia es la que se busca.
    foreach ($seccion->getComponentsByRegion($seccion->getDefaultRegion()) as $componente) {
      $configuracion = $componente->get('configuration');
      $clave = sprintf('  peso %-3s %s', $componente->getWeight(), $configuracion['id']);
      $partes[$clave] = json_encode($configuracion, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
  }
  return $partes;
};

$marcas = $diseno(4);
$colores = $diseno(9);

printf("  MARCAS (nodo 4)\n");
foreach ($marcas as $clave => $valor) {
  printf("    %-34s %s\n", $clave, mb_strimwidth($valor, 0, 240, '...'));
}
echo "\n";
printf("  COLORES (nodo 9), el molde\n");
foreach ($colores as $clave => $valor) {
  printf("    %-34s %s\n", $clave, mb_strimwidth($valor, 0, 240, '...'));
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 2. El orden en que se van a pintar las piezas\n";
echo str_repeat('=', 82) . "\n\n";

/**
 * Solo los nombres de las piezas, en el orden en que las pinta el peso.
 */
$orden = static function (int $nid): array {
  $nodo = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
  $lista = [];
  foreach ($nodo->get('layout_builder__layout') as $trozo) {
    $seccion = $trozo->section;
    foreach ($seccion->getComponentsByRegion($seccion->getDefaultRegion()) as $componente) {
      $lista[] = sprintf('%s (peso %s)', $componente->get('configuration')['id'], $componente->getWeight());
    }
  }
  return $lista;
};

printf("  marcas:  %s\n", implode('  ->  ', $orden(4)));
printf("  colores: %s\n", implode('  ->  ', $orden(9)));

echo "\n  El titulo es el bloque field_block con el campo title. Si en marcas no es\n";
echo "  el primero y en colores si, ahi esta el fallo del orden.\n";

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 3. Los campos de los dos nodos, para ver si falta alguno\n";
echo str_repeat('=', 82) . "\n\n";

$nodo4 = $almacenDeNodos->load(4);
$nodo9 = $almacenDeNodos->load(9);

$nombres = array_unique(array_merge(array_keys($nodo4->getFields()), array_keys($nodo9->getFields())));
sort($nombres);
printf("  %-26s %-24s %s\n", 'campo', 'marcas (4)', 'colores (9)');
echo '  ' . str_repeat('-', 92) . "\n";
foreach ($nombres as $nombre) {
  if ($nombre === 'layout_builder__layout') {
    continue;
  }
  $valor = static function ($nodo, $nombre): string {
    if (!$nodo->hasField($nombre)) {
      return '(no tiene el campo)';
    }
    $campo = $nodo->get($nombre);
    return $campo->isEmpty()
      ? '(vacio)'
      : json_encode($campo->getValue(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  };
  printf(
    "  %-26s %-24s %s\n",
    $nombre,
    mb_strimwidth($valor($nodo4, $nombre), 0, 23, '..'),
    mb_strimwidth($valor($nodo9, $nombre), 0, 40, '..')
  );
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 4. El HTML de las dos pantallas\n";
echo str_repeat('=', 82) . "\n\n";

$kernel = \Drupal::service('http_kernel');
$cuenta = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$anterior = \Drupal::currentUser()->getAccount();
\Drupal::currentUser()->setAccount($cuenta);

/**
 * Pide una pagina por dentro, como usuario 1.
 */
$pedir = static function (string $ruta) use ($kernel): string {
  $peticion = Request::create($ruta, 'GET');
  $peticion->setSession(\Drupal::service('session'));
  try {
    return (string) $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE)->getContent();
  }
  catch (\Throwable $e) {
    return '';
  }
};

$htmlB = $pedir('/b');
$htmlCl = $pedir('/cl');
printf("  /b:  %s bytes\n", number_format(strlen($htmlB)));
printf("  /cl: %s bytes\n\n", number_format(strlen($htmlCl)));

/**
 * Donde aparece cada cosa dentro de la pagina, para ver el orden real.
 *
 * El peso dice el orden que Drupal quiere; esto dice el orden que ha salido.
 * Si no coinciden, el problema no es el peso.
 */
$posiciones = static function (string $html, string $vista): array {
  $marcas = [
    'titulo h1' => '~<h1[^>]*>~',
    'bloque de la vista' => '~view-id-' . $vista . '~',
    'boton de crear' => '~btn-default~',
    'primera tarjeta' => '~views-view-responsive-grid__item~',
  ];
  $donde = [];
  foreach ($marcas as $nombre => $patron) {
    $donde[$nombre] = preg_match($patron, $html, $coincide, PREG_OFFSET_CAPTURE)
      ? $coincide[0][1]
      : NULL;
  }
  return $donde;
};

$enB = $posiciones($htmlB, 'tec_brands');
$enCl = $posiciones($htmlCl, 'tec_colors');

printf("  %-22s %-14s %s\n", 'que', 'en /b', 'en /cl');
echo '  ' . str_repeat('-', 56) . "\n";
foreach ($enB as $que => $donde) {
  printf(
    "  %-22s %-14s %s\n",
    $que,
    $donde === NULL ? 'NO SALE' : $donde,
    $enCl[$que] === NULL ? 'NO SALE' : $enCl[$que]
  );
}

echo "\n  Lo que importa es si el titulo va antes o despues del bloque:\n";
foreach ([['b', $enB], ['cl', $enCl]] as [$nombre, $donde]) {
  if ($donde['titulo h1'] === NULL || $donde['bloque de la vista'] === NULL) {
    printf("    /%-3s no se puede decir, falta una de las dos cosas\n", $nombre);
    continue;
  }
  printf(
    "    /%-3s el titulo va %s del bloque de la vista\n",
    $nombre,
    $donde['titulo h1'] < $donde['bloque de la vista'] ? 'ANTES (bien)' : 'DESPUES (mal)'
  );
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 5. La estructura de la zona de contenido, etiqueta a etiqueta\n";
echo str_repeat('=', 82) . "\n\n";

/**
 * Dibuja el arbol de la zona del diseno, solo etiquetas y clases.
 *
 * Sirve para cazar lo que no se ve en la configuracion: un envoltorio de region
 * distinto, una clase que falta o un bloque que sale vacio. Se comparan los dos
 * arboles a ojo, que con esta profundidad caben en pantalla.
 */
$arbol = static function (string $html): array {
  if ($html === '') {
    return ['(pagina vacia)'];
  }
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML('<?xml encoding="UTF-8">' . $html);
  libxml_clear_errors();
  $buscador = new \DOMXPath($documento);

  // El nodo con Layout Builder pinta un div con la clase layout; ahi empieza lo
  // que nos interesa y acaba el ruido de la plantilla del tema. Se compara la
  // clase entera y no un trozo: buscando "layout" a secas se cuela el envoltorio
  // de la barra de administracion de Gin, que se llama
  // gin-secondary-toolbar__layout-container y sale antes en la pagina, y entonces
  // esto dibuja el arbol de la barra en vez del de la portada.
  $raices = $buscador->query('//div[contains(concat(" ", normalize-space(@class), " "), " layout ")]');
  if (!$raices->length) {
    return ['(no hay ningun div con la clase layout)'];
  }
  $raiz = $raices->item(0);

  $lineas = [];
  $recorrer = static function (\DOMNode $nodo, int $nivel) use (&$recorrer, &$lineas): void {
    if ($nivel > 6) {
      return;
    }
    foreach ($nodo->childNodes as $hijo) {
      if ($hijo->nodeType !== XML_ELEMENT_NODE) {
        continue;
      }
      $clase = $hijo->getAttribute('class');
      $texto = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
      $lineas[] = sprintf(
        '%s%s%s%s',
        str_repeat('  ', $nivel),
        $hijo->nodeName,
        $clase === '' ? '' : '.' . str_replace(' ', '.', $clase),
        $texto === '' ? '   [VACIO]' : '   "' . mb_strimwidth($texto, 0, 40, '..') . '"'
      );
      $recorrer($hijo, $nivel + 1);
    }
  };
  $clase = $raiz->getAttribute('class');
  $lineas[] = $raiz->nodeName . ($clase === '' ? '' : '.' . str_replace(' ', '.', $clase));
  $recorrer($raiz, 1);
  return $lineas;
};

echo "  --- /b ---\n";
foreach ($arbol($htmlB) as $linea) {
  echo '  ' . $linea . "\n";
}
echo "\n  --- /cl ---\n";
foreach ($arbol($htmlCl) as $linea) {
  echo '  ' . $linea . "\n";
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 6. Las hojas de estilo que carga cada pantalla\n";
echo str_repeat('=', 82) . "\n\n";

/**
 * Los CSS que adjunta la pagina, sin la marca de agua de la cache.
 *
 * Si el boton sale sin estilo por falta de una biblioteca, la diferencia esta
 * aqui. Se quita el ?xyz del final porque cambia en cada reconstruccion y
 * ensuciaria la comparacion.
 */
$hojas = static function (string $html): array {
  preg_match_all('~<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"~', $html, $coincidencias);
  $lista = array_map(static fn (string $url): string => preg_replace('~\?.*$~', '', $url), $coincidencias[1] ?? []);
  sort($lista);
  return array_values(array_unique($lista));
};

$hojasB = $hojas($htmlB);
$hojasCl = $hojas($htmlCl);
printf("  /b carga %d hojas, /cl carga %d\n\n", count($hojasB), count($hojasCl));

$soloB = array_diff($hojasB, $hojasCl);
$soloCl = array_diff($hojasCl, $hojasB);
if (!$soloB && !$soloCl) {
  echo "  Las dos pantallas cargan exactamente las mismas hojas de estilo, asi que\n";
  echo "  el boton no puede salir distinto por falta de CSS.\n";
}
else {
  foreach ($soloB as $una) {
    echo "  solo en /b:  $una\n";
  }
  foreach ($soloCl as $una) {
    echo "  solo en /cl: $una\n";
  }
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 7. Como sale escrito el boton en cada pantalla\n";
echo str_repeat('=', 82) . "\n\n";

// El boton lo escribe a mano la cabecera de la vista, asi que si en una sale con
// otras etiquetas es cosa de la vista y no del nodo.
foreach ([['b', $htmlB], ['cl', $htmlCl]] as [$nombre, $html]) {
  if (preg_match('~<div class="text-align-right".{0,400}?</div>\s*</div>~s', $html, $coincide)) {
    printf("  /%s:\n", $nombre);
    echo '      ' . str_replace("\n", "\n      ", trim($coincide[0])) . "\n\n";
  }
  else {
    printf("  /%s: no se ha encontrado el envoltorio del boton\n\n", $nombre);
  }
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 8. La zona gris: hay algo de mas en /b o es el fondo de la pagina\n";
echo str_repeat('=', 82) . "\n\n";

// Una zona gris puede ser dos cosas muy distintas: un bloque vacio que ocupa
// sitio, o simplemente el fondo del tema asomando porque el contenido es corto.
// La segunda no es un fallo y no hay que tocar nada. Para distinguirlas se
// compara el esqueleto de las dos paginas por encima del diseno: si son iguales,
// no hay ningun envoltorio de mas y lo gris es el fondo.
$esqueleto = static function (string $html): array {
  $documento = new \DOMDocument();
  libxml_use_internal_errors(TRUE);
  $documento->loadHTML('<?xml encoding="UTF-8">' . $html);
  libxml_clear_errors();
  $cuerpos = $documento->getElementsByTagName('body');
  if (!$cuerpos->length) {
    return [];
  }
  $lineas = [];
  $recorrer = static function (\DOMNode $nodo, int $nivel) use (&$recorrer, &$lineas): void {
    if ($nivel > 3) {
      return;
    }
    foreach ($nodo->childNodes as $hijo) {
      if ($hijo->nodeType !== XML_ELEMENT_NODE || in_array($hijo->nodeName, ['script', 'style'], TRUE)) {
        continue;
      }
      $clase = $hijo->getAttribute('class');
      $lineas[] = str_repeat('  ', $nivel) . $hijo->nodeName . ($clase === '' ? '' : '.' . str_replace(' ', '.', $clase));
      $recorrer($hijo, $nivel + 1);
    }
  };
  $recorrer($cuerpos->item(0), 0);
  return $lineas;
};

$esqB = $esqueleto($htmlB);
$esqCl = $esqueleto($htmlCl);
printf("  /b tiene %d envoltorios en los tres primeros niveles, /cl tiene %d\n\n", count($esqB), count($esqCl));

// Se comparan como conjuntos: el orden dentro del diseno ya se ha visto arriba,
// aqui lo que se busca es un envoltorio que exista en una pagina y no en la otra.
$soloEnB = array_diff($esqB, $esqCl);
$soloEnCl = array_diff($esqCl, $esqB);
if (!$soloEnB && !$soloEnCl) {
  echo "  Las dos paginas tienen el mismo armazon. No hay ningun bloque de mas en /b,\n";
  echo "  asi que la zona gris es el fondo del tema debajo del contenido: /cl no lo\n";
  echo "  ensena porque tiene muchos colores y llena la pantalla, y marcas tiene una.\n";
}
else {
  foreach ($soloEnB as $uno) {
    echo '  solo en /b:  ' . trim($uno) . "\n";
  }
  foreach ($soloEnCl as $uno) {
    echo '  solo en /cl: ' . trim($uno) . "\n";
  }
}

printf("\n  filas pintadas en /b:  %d\n", preg_match_all('~views-view-responsive-grid__item~', $htmlB));
printf("  filas pintadas en /cl: %d\n", preg_match_all('~views-view-responsive-grid__item~', $htmlCl));
echo "  Con una sola fila el contenido no llega a la mitad de la pantalla, y lo que\n";
echo "  sobra lo pinta el fondo del tema.\n";

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 9. Le importa al tema que bloque va primero\n";
echo str_repeat('=', 82) . "\n\n";

// Si el tema tratara distinto al primer bloque de una region, el desorden no solo
// cambiaria el orden: tambien cambiaria como se ve el bloque que ha quedado
// primero, y eso explicaria el boton sin tocar el boton. Hay que comprobarlo
// antes de creerlo. El CSS del tema viene minimizado en una sola linea, asi que
// no se puede buscar con grep y hay que rebuscar dentro desde aqui.
$reglas = [];
foreach (glob('themes/contrib/dxpr_theme/css/**/*.css') ?: [] as $fichero) {
  $css = (string) file_get_contents($fichero);
  if (!preg_match_all('~[^{}]{0,160}(?:block:first-child|block-hr)[^{}]{0,80}\{[^}]{0,160}\}~', $css, $encontradas)) {
    continue;
  }
  foreach ($encontradas[0] as $regla) {
    $reglas[trim($regla)] = basename($fichero);
  }
}
if (!$reglas) {
  echo "  El tema no distingue al primer bloque, asi que el orden no cambia el estilo.\n";
}
else {
  echo "  Reglas del tema que hablan del primer bloque o de la raya entre bloques:\n\n";
  foreach ($reglas as $regla => $fichero) {
    printf("    %-18s %s\n", $fichero, mb_strimwidth($regla, 0, 150, '...'));
  }
  echo "\n  Ninguna toca al boton: las de first-child solo mueven el titulo h2 de los\n";
  echo "  bloques de las barras laterales, y la raya entre bloques esta escondida\n";
  echo "  siempre, no solo en el primero. O sea que el boton no se ve distinto por\n";
  echo "  el CSS: se ve distinto porque estaba en lo alto de la pagina, sin el h1\n";
  echo "  encima. No hay que tocar ni la vista ni el tema, solo el orden.\n";
}

\Drupal::currentUser()->setAccount($anterior);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Fin. Nada de esto ha escrito en la base de datos.\n";
echo str_repeat('=', 82) . "\n\n";
