<?php

/**
 * @file
 * Deja las tallas en el orden del catalogo, no en el que salio por accidente.
 *
 *   php vendor\bin\drush.php scr scripts/poner-las-tallas-en-su-orden.php
 *   php vendor\bin\drush.php scr scripts/poner-las-tallas-en-su-orden.php -- --de-verdad
 *
 * El orden de tallas de un catalogo de ropa no es alfabetico: va de la mas
 * pequena a la mas grande, y los guantes van por onzas de menos a mas. Sale en
 * todos los desplegables, en las variaciones de producto y en los documentos de
 * produccion, asi que se nota en cuanto esta mal.
 *
 * Estaba a medias: alguien empezo a ordenarlas a mano -XXS, XS, luego un hueco, y
 * L, XL, XXL- y no pudo acabar, porque arrastrar y guardar no funcionaba
 * (taxonomy_unique se colgaba del boton Save y tiraba los pesos a la basura). Las
 * tres tallas que se crearon despues, S, M y 20 oz, se quedaron con peso 0, o sea
 * por delante de XXS.
 *
 * Sin argumentos solo cuenta lo que haria. Con --de-verdad lo hace.
 */

// El orden que se quiere, de la primera a la ultima.
const ORDEN = [
  'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL',
  '6 oz', '8 oz', '10 oz', '12 oz', '14 oz', '16 oz', '18 oz', '20 oz',
];

$deVerdad = in_array('--de-verdad', $extra ?? [], TRUE) || in_array('--de-verdad', $_SERVER['argv'] ?? [], TRUE);

$almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$tallas = $almacen->loadByProperties(['vid' => 'tec_sizes']);

$porNombre = [];
foreach ($tallas as $talla) {
  $porNombre[$talla->label()] = $talla;
}

echo "\n";
echo "Las tallas y el orden que se quiere\n";
echo str_repeat('-', 78) . "\n";

$faltan = array_diff(ORDEN, array_keys($porNombre));
$sobran = array_diff(array_keys($porNombre), ORDEN);

if ($faltan) {
  printf("  en el orden pero no en el ERP: %s\n", implode(', ', $faltan));
}
if ($sobran) {
  printf("  en el ERP pero no en el orden: %s\n", implode(', ', $sobran));
  echo "      esas se quedan detras, por peso alto, hasta que se decida su sitio.\n";
}
if (!$faltan && !$sobran) {
  printf("  las %d tallas del ERP estan en el orden, ni una de mas ni de menos\n", count($porNombre));
}

$plan = [];
foreach (ORDEN as $peso => $nombre) {
  if (!isset($porNombre[$nombre])) {
    continue;
  }
  $talla = $porNombre[$nombre];
  if ((int) $talla->getWeight() !== $peso) {
    $plan[$talla->id()] = ['talla' => $talla, 'de' => (int) $talla->getWeight(), 'a' => $peso];
  }
}
// Las que no estan en la lista se van al final, sin pisarse entre ellas.
$siguiente = count(ORDEN);
foreach ($sobran as $nombre) {
  $talla = $porNombre[$nombre];
  if ((int) $talla->getWeight() !== $siguiente) {
    $plan[$talla->id()] = ['talla' => $talla, 'de' => (int) $talla->getWeight(), 'a' => $siguiente];
  }
  $siguiente++;
}

echo "\n";
echo "Lo que hay que mover\n";
echo str_repeat('-', 78) . "\n";
if (!$plan) {
  echo "  nada: las tallas ya estan en su orden.\n\n";
  return;
}
printf("  %-30s %-8s %s\n", 'talla', 'de', 'a');
foreach ($plan as $paso) {
  printf("  %-30s %-8s %s\n", $paso['talla']->label(), $paso['de'], $paso['a']);
}
printf("\n  %d tallas se mueven, %d se quedan donde estan\n", count($plan), count($porNombre) - count($plan));

if (!$deVerdad) {
  echo "\n  Esto es solo la cuenta. Para hacerlo:\n";
  echo "  php vendor\\bin\\drush.php scr scripts/poner-las-tallas-en-su-orden.php -- --de-verdad\n\n";
  return;
}

echo "\n";
echo "Moviendolas\n";
echo str_repeat('-', 78) . "\n";
foreach ($plan as $paso) {
  $paso['talla']->setWeight($paso['a']);
  $paso['talla']->save();
  printf("  %s: %d -> %d\n", $paso['talla']->label(), $paso['de'], $paso['a']);
}

$almacen->resetCache();
$arbol = $almacen->loadTree('tec_sizes', 0, NULL, FALSE);
echo "\n";
echo "Como quedan\n";
echo str_repeat('-', 78) . "\n";
foreach ($arbol as $posicion => $talla) {
  printf("  %2d  %-30s peso %s\n", $posicion + 1, $talla->name, $talla->weight);
}
echo "\n";
