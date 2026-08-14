<?php

/**
 * Comprueba que el bloque del pedido minimo ensena la unidad de compra.
 *
 * El bloque no dibujaba nada en la prueba, y hacia bien: el material no tiene
 * pedido minimo escrito y la columna se esconde cuando el campo esta vacio. Pero
 * asi no se comprueba lo que importa, que es si al lado del numero sale la
 * unidad de compra. Antes esa unidad la elegia una casilla entre empaquetado y
 * compra; ahora es siempre compra y el texto la nombra a pelo, y solo se ve si
 * hay un numero delante.
 *
 * Pone un pedido minimo, dibuja, y lo deja como estaba.
 *
 * Uso: drush php:script scripts/probar-el-pedido-minimo
 */

$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$tids = $almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'tec_inventory')
  ->sort('tid')
  ->range(0, 1)
  ->execute();

if (!$tids) {
  echo "No hay materiales con los que probar.\n";
  return;
}

$material = $almacen->load(reset($tids));
$comoEstaba = $material->get('field_tec_moq_quantity')->value;

echo "\n";
echo 'Material: ' . $material->label() . ' (tid ' . $material->id() . ")\n";
echo 'Unidad de compra: ' . ($material->get('field_tec_unit_purchase')->entity?->label() ?? 'ninguna') . "\n";
echo 'Pedido minimo antes: ' . ($comoEstaba === NULL ? '(vacio)' : $comoEstaba) . "\n";

$material->set('field_tec_moq_quantity', 5);
$material->save();
\Drupal::service('cache_tags.invalidator')->invalidateTags($material->getCacheTagsToInvalidate());

$vista = \Drupal\views\Views::getView('tec_inventory_elements');
$vista->setDisplay('block_4');
$vista->setArguments([$material->id()]);
$vista->preExecute();
$vista->execute();
$html = (string) \Drupal::service('renderer')->renderInIsolation($vista->render());
$texto = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
$vista->destroy();

echo "\n";
echo "Con pedido minimo 5, el bloque dibuja:\n";
echo '  ' . ($texto === '' ? '(nada)' : $texto) . "\n";

// Se deja como estaba.
$material->set('field_tec_moq_quantity', $comoEstaba);
$material->save();

$unidad = $material->get('field_tec_unit_purchase')->entity?->label() ?? '';
echo "\n";
if ($unidad !== '' && str_contains($texto, (string) $unidad) && str_contains($texto, '5')) {
  echo "Bien: sale el numero y la unidad de compra al lado.\n";
}
else {
  echo "MAL: se esperaba ver '5 $unidad'.\n";
}
echo 'Pedido minimo devuelto a: ' . ($material->get('field_tec_moq_quantity')->value ?? '(vacio)') . "\n\n";
