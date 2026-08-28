<?php

/**
 * @file
 * Una variacion creada con + Variation queda en la ficha del producto.
 *
 *   php vendor\bin\drush.php scr scripts/el-color-nuevo-aparece-en-el-producto.php
 *
 * El 20 de agosto de 2026 el dueno creo Black para RISE THAI KICK PAD 1 STRAP.
 * El mensaje de estado lo confirmo, pero la ficha seguia vacia. El boton
 * + Variation llevaba edit[field_tec_product][widget][0][target_id]=1660, que
 * no rellena el campo al guardar. Sin field_tec_product, el ECA "Insert Color
 * entity" no mete el color en field_tec_color_variations del producto, y la
 * ficha no pinta nada.
 *
 * La cura es la misma que brand_id en + Product: EPP lee product_id de la URL
 * y el enlace del boton deja de usar edit[...]. Con field_tec_product relleno,
 * el ECA "Insert Color entity" engancha el color a la lista del producto.
 */

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$fallos = 0;

$mirar = static function (string $que, bool $bien, string $detalle = '') use (&$fallos): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $fallos++;
  }
};

print "\n";
print str_repeat('=', 72) . "\n";
print " El color nuevo aparece en el producto\n";
print str_repeat('=', 72) . "\n\n";

print "1. El boton + Variation ya no usa edit[...]\n";
print str_repeat('-', 72) . "\n";

$vista = $gestor->getStorage('view')->load('tec_product_elements');
$embed4 = (string) ($vista?->getDisplay('embed_4')['display_options']['fields']['nothing']['alter']['text'] ?? '');
$embed5 = (string) ($vista?->getDisplay('embed_5')['display_options']['fields']['nothing']['alter']['text'] ?? '');

$mirar('+ Variation lleva product_id', str_contains($embed4, 'product_id={{ id_1 }}'), $embed4 ?: 'sin embed_4');
$mirar('+ Variation ya no lleva edit[field_tec_product]', !str_contains($embed4, 'edit[field_tec_product]'), $embed4 ?: 'sin embed_4');
$mirar('+ Size lleva color_id', str_contains($embed5, 'color_id={{ id_1 }}'), $embed5 ?: 'sin embed_5');
$mirar('+ Size ya no lleva edit[field_tec_color_variation]', !str_contains($embed5, 'edit[field_tec_color_variation]'), $embed5 ?: 'sin embed_5');

print "\n2. EPP rellena el enlace al padre desde la URL\n";
print str_repeat('-', 72) . "\n";

$configColor = \Drupal::config('field.field.tec_product.tec_color_variation.field_tec_product');
$configTalla = \Drupal::config('field.field.tec_product.tec_size_variation.field_tec_color_variation');

$mirar('field_tec_product del color usa product_id',
  ($configColor->get('third_party_settings.epp.value') ?? '') === '[current-page:query:product_id]',
  (string) ($configColor->get('third_party_settings.epp.value') ?? 'vacio'));
$mirar('field_tec_color_variation de la talla usa color_id',
  ($configTalla->get('third_party_settings.epp.value') ?? '') === '[current-page:query:color_id]',
  (string) ($configTalla->get('third_party_settings.epp.value') ?? 'vacio'));

print "\n3. Guardar engancha el color a la lista del producto\n";
print str_repeat('-', 72) . "\n";

$almacen = $gestor->getStorage('tec_product');
$basura = [];

$producto = $almacen->create(['type' => 'tec_product', 'title' => 'PRUEBA color en ficha']);
$producto->save();
$basura[] = $producto;

$color = $almacen->create([
  'type' => 'tec_color_variation',
  'title' => 'PRUEBA negro',
  'field_tec_product' => $producto->id(),
]);
$color->save();
$basura[] = $color;

$producto = $almacen->loadUnchanged($producto->id());
$lista = array_column($producto->get('field_tec_color_variations')->getValue(), 'target_id');
$mirar('el producto lista el color recien creado',
  in_array((string) $color->id(), array_map('strval', $lista), TRUE),
  implode(', ', $lista) ?: 'lista vacia');

print "\n4. El formulario de anadir lee product_id de la URL\n";
print str_repeat('-', 72) . "\n";

$peticion = Request::create(
  '/admin/content/tec_product/add/tec_color_variation?product_id=' . $producto->id(),
  'GET'
);
$peticion->setSession(\Drupal::service('session'));
$request_stack = \Drupal::requestStack();
$request_stack->push($peticion);
try {
  $entidad = $almacen->create(['type' => 'tec_color_variation']);
  $form = \Drupal::service('entity.form_builder')->getForm($entidad);
  $valor = $form['field_tec_product']['widget'][0]['target_id']['#default_value'] ?? NULL;
  if ($valor instanceof EntityInterface) {
    $valor = $valor->id();
  }
  elseif (is_array($valor)) {
    if (isset($valor['target_id'])) {
      $valor = $valor['target_id'];
    }
    elseif (isset($valor[0])) {
      $valor = $valor[0] instanceof EntityInterface ? $valor[0]->id() : ($valor[0]['target_id'] ?? $valor[0]);
    }
    else {
      $valor = reset($valor);
    }
  }
  $mirar('el formulario trae el producto en field_tec_product',
    (int) $valor === (int) $producto->id(),
    is_scalar($valor) ? (string) $valor : json_encode($valor));
}
finally {
  $request_stack->pop();
}

foreach (array_reverse($basura) as $ficha) {
  try {
    $ficha->delete();
  }
  catch (\Throwable $e) {
    printf("  aviso: no se pudo borrar %s %s: %s\n", $ficha->bundle(), $ficha->id(), $e->getMessage());
  }
}

print "\n";
if ($fallos) {
  printf("RESULTADO: %d fallo(s). No seguir.\n\n", $fallos);
}
else {
  print "RESULTADO: todo bien.\n\n";
}
