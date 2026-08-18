<?php

/**
 * @file
 * Comprueba que la pantalla de marcas sirve para lo que tiene que servir.
 *
 * Un 200 no basta. Las portadas del CRM devolvian 200 durante meses mientras
 * escondian el boton de crear, y el fallo solo se vio cuando alguien se quedo sin
 * poder crear un cliente. Asi que aqui se mira el contenido: que el icono este en
 * el inicio, que el boton este en la pantalla, y sobre todo que siga estando con
 * la lista vacia, que es el caso en el que fallaba.
 *
 * Para probar la lista vacia se despublica un momento la unica marca que hay y se
 * vuelve a publicar al terminar, incluso si algo revienta en medio.
 *
 * Uso: drush php:script scripts/funciona-la-pantalla-de-marcas
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$kernel = \Drupal::service('http_kernel');
$cuenta = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$anterior = \Drupal::currentUser()->getAccount();
\Drupal::currentUser()->setAccount($cuenta);

/**
 * Pide una pagina por dentro y devuelve el codigo y el html.
 */
$pedir = static function (string $ruta) use ($kernel): array {
  $peticion = Request::create($ruta, 'GET');
  $peticion->setSession(\Drupal::service('session'));
  try {
    $respuesta = $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
    return [$respuesta->getStatusCode(), (string) $respuesta->getContent()];
  }
  catch (\Throwable $e) {
    return ['EXCEPCION: ' . $e->getMessage(), ''];
  }
};

$fallos = 0;

/**
 * Da por bueno o por malo un requisito y lo cuenta.
 */
$comprobar = static function (string $que, bool $bien, string $pista = '') use (&$fallos): void {
  printf("  %-58s %s\n", $que, $bien ? 'bien' : 'MAL');
  if (!$bien) {
    $fallos++;
    if ($pista) {
      echo '      ' . $pista . "\n";
    }
  }
};

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 1. El icono en la pantalla de inicio\n";
echo str_repeat('=', 82) . "\n\n";

[$codigo, $html] = $pedir('/start');
$comprobar('/start responde', $codigo === 200, 'ha devuelto ' . $codigo);
$comprobar('sale la palabra Brands', str_contains($html, 'Brands'));
$comprobar('el icono apunta a /b', (bool) preg_match('~href="/b"~', $html));
$comprobar('se pinta el dibujo del icono', str_contains($html, 'isometric-brand-100'));

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 2. La pantalla, con la marca que hay\n";
echo str_repeat('=', 82) . "\n\n";

[$codigo, $html] = $pedir('/b');
$comprobar('/b responde', $codigo === 200, 'ha devuelto ' . $codigo);
$comprobar('sale el titulo Brands', (bool) preg_match('~<h1[^>]*>\s*Brands~', $html));
$comprobar('sale el boton de crear', str_contains($html, '+ Brand'));
$comprobar('sale la marca de prueba', str_contains($html, 'PRUEBA marca'));
$comprobar('sale el enlace de editar la marca', str_contains($html, '/edit?destination=/node/4'));

// Y que salgan en el orden bueno, no solo que salgan. Esta prueba dio "todo bien"
// con la pantalla visiblemente torcida: la portada nacio con los pesos del diseno
// mal y la rejilla de marcas se pintaba encima del titulo, pero como el titulo y
// el boton estaban los dos en el HTML, las lineas de arriba pasaban. Lo encontro
// el dueno mirando la pantalla, que es justo lo que esto tendria que ahorrarle.
$dondeTitulo = preg_match('~<h1[^>]*>~', $html, $coincide, PREG_OFFSET_CAPTURE) ? $coincide[0][1] : NULL;
$dondeRejilla = preg_match('~view-id-tec_brands~', $html, $coincide, PREG_OFFSET_CAPTURE) ? $coincide[0][1] : NULL;
$comprobar(
  'el titulo va antes de la rejilla',
  $dondeTitulo !== NULL && $dondeRejilla !== NULL && $dondeTitulo < $dondeRejilla,
  'los pesos del diseno del nodo 4 estan desordenados: drush php:script scripts/poner-en-orden-la-portada-de-marcas'
);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 3. La pantalla con la lista vacia, que es donde fallaba\n";
echo str_repeat('=', 82) . "\n\n";

$terminos = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$marcas = $terminos->loadByProperties(['vid' => 'tec_brands']);

// Se despublican todas las publicadas, no solo la primera: en cuanto la casa tuvo
// una segunda marca, despublicar una sola dejaba la lista con contenido y la prueba
// se quejaba de una pantalla que estaba bien.
$publicadas = array_filter($marcas, fn ($m) => $m->isPublished());

if (!$publicadas) {
  echo "  No hay ninguna marca publicada, asi que la lista ya esta vacia de por si.\n\n";
  [$codigo, $html] = $pedir('/b');
  $comprobar('sale el boton de crear con la lista vacia', str_contains($html, '+ Brand'));
  $comprobar('sale el aviso de que no hay nada', str_contains($html, 'Nothing here yet'));
}
else {
  printf("  Se despublican un momento %d marcas para dejar la lista vacia: %s\n\n",
    count($publicadas),
    implode(', ', array_map(fn ($m) => $m->label(), $publicadas))
  );
  foreach ($publicadas as $m) {
    $m->setUnpublished()->save();
  }
  try {
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['taxonomy_term_list']);
    [$codigo, $html] = $pedir('/b');
    $comprobar('/b responde con la lista vacia', $codigo === 200, 'ha devuelto ' . $codigo);
    $comprobar('sale el boton de crear con la lista vacia', str_contains($html, '+ Brand'));
    $comprobar('sale el aviso de que no hay nada', str_contains($html, 'Nothing here yet'));
    $comprobar('ya no sale ninguna marca despublicada', !str_contains($html, 'PRUEBA marca'));
  }
  finally {
    $malas = [];
    foreach ($publicadas as $m) {
      $m->setPublished()->save();
      if (!$m->isPublished()) {
        $malas[] = $m->label();
      }
    }
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['taxonomy_term_list']);
    printf("  Vuelta a publicar: %s\n", $malas ? 'NO, MIRA A MANO ' . implode(', ', $malas) : 'si, todas');
  }
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 4. Los enlaces de los botones llevan a algun sitio\n";
echo str_repeat('=', 82) . "\n\n";

// El boton de marcas escribe /add/ con barra final y el de colores /add sin ella.
// Una de las dos formas puede no tener ruta, y un boton que da 404 es igual de
// inutil que un boton escondido.
foreach ([
  '/admin/structure/taxonomy/manage/tec_brands/add/?destination=/node/4' => 'como lo escribe la vista de marcas, con barra',
  '/admin/structure/taxonomy/manage/tec_brands/add?destination=/node/4' => 'sin barra, como lo escribe la de colores',
] as $ruta => $comentario) {
  [$codigo, ] = $pedir($ruta);
  printf("  %-6s %s\n         %s\n", $codigo, $comentario, $ruta);
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " 5. Lo que estaba bloqueado: crear un producto\n";
echo str_repeat('=', 82) . "\n\n";

$cuantas = $terminos->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'tec_brands')->condition('status', 1)->count()->execute();
printf("  marcas publicadas para elegir: %s\n", $cuantas);

[$codigo, $html] = $pedir('/admin/content/tec_product/add/tec_product');
$comprobar('el formulario de producto abre', $codigo === 200, 'ha devuelto ' . $codigo);
$comprobar('lleva el campo de marca', str_contains($html, 'field_tec_brand'));

\Drupal::currentUser()->setAccount($anterior);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $fallos === 0
  ? " Todo bien.\n"
  : " HAY $fallos cosas mal. No darlo por hecho.\n";
echo str_repeat('=', 82) . "\n\n";
