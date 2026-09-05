<?php

/**
 * @file
 * Devuelve la pantalla de marcas al ERP.
 *
 * Lo que falta no es la funcion, es la puerta. El vocabulario, el campo en
 * productos, la vista y los permisos estan intactos; lo que se borro fue el nodo
 * 4, la portada que embebia el bloque de la vista y que ponia el icono en la
 * pantalla de inicio. Sin ella no hay donde ver ni crear marcas, y como la marca
 * es obligatoria en producto, tampoco se puede crear un producto.
 *
 * Se rehace con el numero 4 a proposito: la vista nombra /node/4 en cuatro
 * enlaces, igual que productos nombra /node/1 y colores /node/9, asi que
 * recuperando el numero los cuatro enlaces vuelven a valer sin tocar rutas.
 *
 * Se copia el pattern de colores, que es el caso gemelo (un vocabulario con una
 * vista en cuadricula dentro de una portada), para que esta quede indistinguible
 * de las que ya funcionan.
 *
 * Y se arregla de paso el boton de crear, que estaba puesto para esconderse justo
 * cuando la lista esta vacia: el mismo fallo que tenian las portadas del CRM.
 *
 * Uso: drush php:script scripts/devolver-la-pantalla-de-marcas
 *      drush php:script scripts/devolver-la-pantalla-de-marcas -- de-verdad
 */

use Drupal\Core\File\FileExists;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad ? " DE VERDAD: se escribe\n" : " ENSAYO: solo se cuenta lo que haria. Anade -- de-verdad para hacerlo\n";
echo str_repeat('=', 82) . "\n\n";

$sistemaDeFicheros = \Drupal::service('file_system');
$almacenDeNodos = \Drupal::entityTypeManager()->getStorage('node');

// ---------------------------------------------------------------------------
// 1. El icono.
// ---------------------------------------------------------------------------
echo "1. El icono\n";

// El icono de marcas se salvo del borrado de fotos, pero en la carpeta publica
// de 2024, que es de donde se mudaron todos los iconos. El campo guarda en
// privado y en carpeta por mes, asi que hay que llevarlo alli y ficharlo de
// nuevo: su ficha murio con el nodo.
$origen = 'public://2024-03/isometric-brand-100.png';
$carpeta = 'private://' . date('Y-m');
$destino = $carpeta . '/isometric-brand-100.png';

if (!file_exists($origen)) {
  echo "   El icono no esta en $origen. Paro: sin icono la portada saldria muda\n";
  echo "   en la pantalla de inicio.\n";
  return;
}
printf("   origen:  %s\n", $origen);
printf("   destino: %s\n", $destino);

$ficheros = \Drupal::entityTypeManager()->getStorage('file');
$yaFichado = $ficheros->loadByProperties(['uri' => $destino]);
$fichero = $yaFichado ? reset($yaFichado) : NULL;

if ($fichero) {
  printf("   ya estaba fichado con el id %s, se reutiliza\n", $fichero->id());
}
elseif ($deVerdad) {
  $sistemaDeFicheros->prepareDirectory($carpeta, $sistemaDeFicheros::CREATE_DIRECTORY);
  $fichero = \Drupal::service('file.repository')->writeData(
    file_get_contents($origen),
    $destino,
    FileExists::Replace
  );
  $fichero->setPermanent();
  $fichero->setOwnerId(1);
  $fichero->save();
  printf("   copiado y fichado con el id %s\n", $fichero->id());
}
else {
  echo "   se copiaria y se ficharia\n";
}

// ---------------------------------------------------------------------------
// 2. La portada.
// ---------------------------------------------------------------------------
echo "\n2. La portada\n";

if ($almacenDeNodos->load(4)) {
  echo "   El nodo 4 ya existe. Paro para no pisarlo.\n";
  return;
}

$pattern = $almacenDeNodos->load(9);
$seccionPattern = $pattern->get('layout_builder__layout')->first()->section;

// Se copian los ajustes de cada pieza del pattern y solo se cambia el bloque de la
// vista, que es lo unico que distingue una portada de otra. Asi los titulos y los
// cuerpos salen con el mismo formato que en el resto del ERP, y los pesos, que son
// los que mandan el orden en pantalla, vienen dados.
//
// CUIDADO CON appendComponent(). La primera version de este guion armaba la
// seccion vacia y metia las piezas una a una con $seccion->appendComponent(),
// poniendoles antes el peso del pattern con setWeight(). No sirve de nada: lo
// primero que hace ese metodo es reescribir el peso.
//
//     public function appendComponent(SectionComponent $component) {
//       $component->setWeight($this->getNextHighestWeight($component->getRegion()));
//
// O sea que el peso copiado se tiraba y cada pieza se quedaba con el numero que
// le tocaba por orden de llegada. Como el pattern devuelve las piezas en el orden
// en que estan guardadas y no en el que se pintan, el bucle recorrio cuerpo,
// vista y titulo, y la portada nacio con el titulo de ultimo: la rejilla de
// marcas arriba y "Brands" debajo de las tarjetas.
//
// Se le pasan al constructor de Section, que es el unico camino que respeta el
// peso, porque llama a setComponent() sin tocarlo. El otro camino valido seria
// appendComponent() y despues setWeight(), pero eso deja el orden de dos lineas
// mandando sobre el resultado, y esta trampa ya se ha pagado una vez.
$piezas = [];

foreach ($seccionPattern->getComponents() as $componente) {
  $configuracion = $componente->get('configuration');
  if ($configuracion['id'] === 'views_block:tec_colors-block_1') {
    $configuracion['id'] = 'views_block:tec_brands-block_1';
  }

  $pieza = new SectionComponent(
    \Drupal::service('uuid')->generate(),
    $componente->getRegion(),
    $configuracion
  );
  $pieza->setWeight($componente->getWeight());
  $piezas[] = $pieza;

  printf(
    "   pieza: %-46s region %s, peso %s\n",
    $configuracion['id'],
    $componente->getRegion(),
    $componente->getWeight()
  );
}

// Los ajustes de terceros van tambien. El pattern de colores no tiene ninguno, asi
// que hoy da igual, pero el dia que se copie una portada que si los tenga se
// perderian sin que nadie lo notase hasta ver la pantalla.
$deTerceros = [];
foreach ($seccionPattern->getThirdPartyProviders() as $proveedor) {
  $deTerceros[$proveedor] = $seccionPattern->getThirdPartySettings($proveedor);
}

$seccion = new Section(
  $seccionPattern->getLayoutId(),
  $seccionPattern->getLayoutSettings(),
  $piezas,
  $deTerceros
);

// Se comprueba antes de guardar, porque el fallo del peso no da ningun error: la
// portada se crea, responde 200 y solo se ve al mirarla con los ojos.
$comprobados = [];
foreach ($seccion->getComponentsByRegion($seccion->getDefaultRegion()) as $unaPieza) {
  $comprobados[] = $unaPieza->get('configuration')['id'] . ' (' . $unaPieza->getWeight() . ')';
}
printf("   orden que va a quedar: %s\n", implode(' -> ', $comprobados));

printf("   titulo:    Brands\n");
printf("   direccion: /b\n");
printf("   destino:   internal:/b\n");
printf("   orden del icono: 8, justo detras de colores, que es el otro catalogo\n");

if ($deVerdad) {
  $nodo = Node::create([
    'nid' => 4,
    'type' => 'tec_landing_page',
    'title' => 'Brands',
    'uid' => 1,
    'status' => 1,
    'field_icon_order' => 8,
    'field_tec_target' => ['uri' => 'internal:/b'],
    'field_tec_icon' => [
      'target_id' => $fichero->id(),
      'alt' => '',
      'title' => '',
    ],
    'path' => ['alias' => '/b', 'pathauto' => 0],
    'layout_builder__layout' => [['section' => $seccion]],
  ]);
  // Con el numero puesto a mano hay que decirle que es nuevo, o Drupal creeria
  // que esta actualizando un nodo que no existe.
  $nodo->enforceIsNew();
  $nodo->save();
  printf("   creado el nodo %s\n", $nodo->id());
}
else {
  echo "   se crearia el nodo 4\n";
}

// ---------------------------------------------------------------------------
// 3. El boton de crear.
// ---------------------------------------------------------------------------
echo "\n3. El boton de crear\n";

$configuracion = \Drupal::configFactory()->getEditable('views.view.tec_brands');
$displays = $configuracion->get('display');
$opciones = &$displays['default']['display_options'];

printf(
  "   el boton se muestra con la lista vacia: %s\n",
  !empty($opciones['header']['area_text_custom']['empty']) ? 'si' : 'NO, y por eso no se puede crear la primera marca'
);

$opciones['header']['area_text_custom']['empty'] = TRUE;

// Y el mensaje de lista vacia, con el mismo texto que se le puso al CRM cuando
// tenia este mismo fallo, para que la pantalla no parezca rota estando vacia.
if (empty($opciones['empty'])) {
  $opciones['empty']['area_text_custom'] = [
    'id' => 'area_text_custom',
    'table' => 'views',
    'field' => 'area_text_custom',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'text_custom',
    'empty' => TRUE,
    'content' => '<div class="text-align-center"><h3 class="subtle">Nothing here yet. Use the button above to add a brand.</h3></div>',
    'tokenize' => FALSE,
  ];
  echo "   se anade el mensaje para cuando no hay marcas\n";
}
else {
  echo "   ya tenia mensaje de lista vacia\n";
}

if ($deVerdad) {
  $configuracion->set('display', $displays)->save();
  echo "   guardado\n";
}
else {
  echo "   se guardaria\n";
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad
  ? " Hecho. Queda exportar la configuracion y reconstruir la cache.\n"
  : " Ensayo terminado, no se ha escrito nada.\n";
echo str_repeat('=', 82) . "\n\n";
