<?php

/**
 * @file
 * Devuelve las piezas de la portada de marcas al orden que les toca.
 *
 * El guion que rehizo la portada copio las piezas del molde de colores con sus
 * pesos, pero las metio en la seccion con Section::appendComponent(), y ese
 * metodo empieza por reescribir el peso:
 *
 *     $component->setWeight($this->getNextHighestWeight($component->getRegion()));
 *
 * O sea que el peso copiado se tiraba antes de guardarse y cada pieza acababa
 * con el numero que le tocaba por orden de llegada. Como el bucle recorrio
 * cuerpo, vista y titulo, la portada quedo con el titulo de ultimo: la tarjeta de
 * la marca salia arriba y "Brands" debajo.
 *
 * Aqui no se recrea el nodo. Tiene que seguir siendo el 4 porque la vista
 * tec_brands escribe /node/4 en cuatro enlaces, asi que se corrigen los pesos
 * sobre el nodo que ya existe.
 *
 * Los pesos no se escriben a mano: se leen del nodo 9 emparejando pieza con
 * pieza. Si algun dia se retoca la portada de colores, este guion sigue diciendo
 * la verdad en vez de repetir tres numeros que ya no valen.
 *
 * Se guarda como revision nueva a proposito: si el orden bueno resultara no ser
 * este, la portada torcida sigue en el historial y se puede volver a ella.
 *
 * Uso: drush php:script scripts/poner-en-orden-la-portada-de-marcas
 *      drush php:script scripts/poner-en-orden-la-portada-de-marcas -- de-verdad
 */

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad ? " DE VERDAD: se escribe\n" : " ENSAYO: solo se cuenta lo que haria. Anade -- de-verdad para hacerlo\n";
echo str_repeat('=', 82) . "\n\n";

$almacen = \Drupal::entityTypeManager()->getStorage('node');
$nodo = $almacen->load(4);
$molde = $almacen->load(9);

if (!$nodo || !$molde) {
  echo " Falta el nodo 4 o el nodo 9. Sin los dos no hay nada que comparar.\n\n";
  return;
}

/**
 * El papel que hace una pieza, para emparejarla con la del molde.
 *
 * El titulo y el cuerpo se llaman igual en las dos portadas, pero el bloque de
 * la vista no: uno es tec_colors y el otro tec_brands. Emparejar por el papel y
 * no por el nombre es lo que permite copiar el orden de una portada a otra.
 */
$papel = static function (string $id): string {
  return str_starts_with($id, 'views_block:') ? 'el bloque de la vista' : $id;
};

// ---------------------------------------------------------------------------
// 1. Que orden manda el molde.
// ---------------------------------------------------------------------------
echo "1. Los pesos del molde (nodo 9, colores)\n\n";

$pesosDelMolde = [];
foreach ($molde->get('layout_builder__layout') as $trozo) {
  $seccion = $trozo->section;
  foreach ($seccion->getComponentsByRegion($seccion->getDefaultRegion()) as $componente) {
    $suPapel = $papel($componente->get('configuration')['id']);
    $pesosDelMolde[$suPapel] = $componente->getWeight();
    printf("   peso %-3s %s\n", $componente->getWeight(), $suPapel);
  }
}

// ---------------------------------------------------------------------------
// 2. Que hay que cambiar en marcas.
// ---------------------------------------------------------------------------
echo "\n2. Los pesos de marcas (nodo 4) y lo que deberian ser\n\n";
printf("   %-42s %-8s %-8s %s\n", 'pieza', 'ahora', 'deberia', '');
echo '   ' . str_repeat('-', 74) . "\n";

$cambios = 0;
$sinPareja = [];
$valoresNuevos = [];

foreach ($nodo->get('layout_builder__layout') as $trozo) {
  $seccion = $trozo->section;
  foreach ($seccion->getComponentsByRegion($seccion->getDefaultRegion()) as $componente) {
    $suPapel = $papel($componente->get('configuration')['id']);
    $ahora = $componente->getWeight();

    if (!array_key_exists($suPapel, $pesosDelMolde)) {
      // Una pieza que el molde no tiene: no se toca, porque no hay con que
      // comparar y adivinarle un peso seria inventar.
      $sinPareja[] = $suPapel;
      printf("   %-42s %-8s %-8s %s\n", $suPapel, $ahora, '?', 'no esta en el molde, se deja igual');
      continue;
    }

    $deberia = $pesosDelMolde[$suPapel];
    printf(
      "   %-42s %-8s %-8s %s\n",
      $suPapel,
      $ahora,
      $deberia,
      $ahora === $deberia ? 'ya estaba bien' : 'se corrige'
    );
    if ($ahora !== $deberia) {
      $componente->setWeight($deberia);
      $cambios++;
    }
  }
  // Se rearma el valor del campo con la seccion ya corregida. El campo guarda la
  // seccion como un blob serializado, asi que se vuelve a asignar entera en vez
  // de confiar en que el cambio suelto en el objeto llegue al guardado.
  $valoresNuevos[] = ['section' => $seccion];
}

echo "\n3. El orden en que quedaria la pantalla\n\n";
foreach ($nodo->get('layout_builder__layout') as $trozo) {
  $seccion = $trozo->section;
  $lista = [];
  foreach ($seccion->getComponentsByRegion($seccion->getDefaultRegion()) as $componente) {
    $lista[] = sprintf('%s (peso %s)', $papel($componente->get('configuration')['id']), $componente->getWeight());
  }
  echo '   ' . implode("\n   -> ", $lista) . "\n";
}

echo "\n";
if ($sinPareja) {
  printf("   Piezas sin pareja en el molde: %s\n", implode(', ', $sinPareja));
}

if ($cambios === 0) {
  echo "   No hay nada que corregir: los pesos ya coinciden con el molde.\n\n";
  echo str_repeat('=', 82) . "\n\n";
  return;
}

printf("   %d pesos por corregir.\n\n", $cambios);

if (!$deVerdad) {
  echo str_repeat('=', 82) . "\n";
  echo " Ensayo terminado, no se ha escrito nada.\n";
  echo str_repeat('=', 82) . "\n\n";
  return;
}

$nodo->set('layout_builder__layout', $valoresNuevos);
$nodo->setNewRevision(TRUE);
$nodo->setRevisionLogMessage('Se devuelven los pesos del molde de colores: appendComponent() los habia reescrito al rehacer la portada y el titulo habia quedado debajo de la rejilla.');
$nodo->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$nodo->setRevisionUserId(1);
$nodo->save();

printf("   Guardado el nodo %s, revision %s.\n\n", $nodo->id(), $nodo->getRevisionId());

// La portada se sirve de cache por etiqueta de nodo, y guardar ya la invalida,
// pero el bloque de la vista va por su cuenta y conviene no dejarlo a medias.
\Drupal::service('cache_tags.invalidator')->invalidateTags(['node:4', 'taxonomy_term_list']);
echo "   Cache del nodo 4 invalidada.\n\n";

echo str_repeat('=', 82) . "\n";
echo " Hecho. El nodo es contenido, no configuracion, asi que no hay nada que\n";
echo " exportar: config:export no lo tocaria.\n";
echo str_repeat('=', 82) . "\n\n";
