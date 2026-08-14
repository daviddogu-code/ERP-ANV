<?php

/**
 * @file
 * Demuestra que el total de material ya no se come el coste pequeno.
 *
 * Con el coste de consumo dibujado a dos decimales, un material barato entraba
 * en la formula como 0,00 y el total de material salia a cero: el ERP decia que
 * la tela era gratis. Este guion baja a proposito el coste de compra del
 * material de pruebas para que el coste de consumo quede por debajo de un
 * centimo, dibuja la vista, ensena el total y lo deja todo como estaba.
 *
 * Uso: drush php:script scripts/probar-el-total-con-un-coste-pequeno
 */

$almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$materiales = $almacen->loadByProperties(['vid' => 'tec_inventory', 'name' => 'khun']);
$material = reset($materiales);
if (!$material) {
  echo "\n  No encuentro el material de pruebas 'khun'.\n\n";
  return;
}

$costeDeVerdad = $material->get('field_tec_cost')->value;

// Un pedido de venta con el que dibujar la vista.
$pedidos = \Drupal::entityTypeManager()->getStorage('tec_order')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_sales_order')
  ->range(0, 1)
  ->execute();
$pedido = reset($pedidos);
if (!$pedido) {
  echo "\n  No hay ningun pedido de venta con el que dibujar la vista.\n\n";
  return;
}

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " El total de material cuando el coste no llega al centimo\n";
echo str_repeat('=', 78) . "\n";

/**
 * Dibuja un display y devuelve las celdas de su primera fila.
 */
$dibujar = static function (string $nombre, string $display, int $pedido): array {
  $vista = \Drupal\views\Views::getView($nombre);
  if (!$vista) {
    return [];
  }
  $vista->setDisplay($display);
  $vista->setArguments([$pedido]);
  $vista->preExecute();
  $vista->execute();
  $html = (string) \Drupal::service('renderer')->renderInIsolation($vista->render());
  $vista->destroy();

  if (!preg_match('/<tbody.*?<tr[^>]*>(.*?)<\/tr>/s', $html, $fila)) {
    return [];
  }
  if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $fila[1], $celdas)) {
    return [];
  }
  return array_map(
    static fn($celda) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($celda)))),
    $celdas[1]
  );
};

try {
  foreach ([$costeDeVerdad => 'el coste de verdad', '4' => 'un coste bajo, para que el de consumo no llegue al centimo'] as $coste => $comoSeLlama) {
    $material->set('field_tec_cost', $coste);
    $material->save();
    $almacen->resetCache([$material->id()]);
    $recien = $almacen->load($material->id());

    printf("\n  Con %s: compra %s\n", $comoSeLlama, $coste);
    printf("      coste de consumo guardado: %s\n", $recien->get('field_tec_price')->value ?? 'vacio');
    printf("      dibujado a dos decimales seria: %s\n", number_format((float) $recien->get('field_tec_price')->value, 2));

    \Drupal::service('cache_tags.invalidator')->invalidateTags(['taxonomy_term:' . $material->id()]);
    foreach (['tec_order_material_calculation_terms', 'tec_order_material_calculation_summary'] as $cual) {
      $celdas = $dibujar($cual, 'default', (int) $pedido);
      $conBaht = array_values(array_filter($celdas, static fn($celda) => str_contains($celda, '฿')));
      printf("      %-42s %s\n", substr($cual, strlen('tec_order_material_calculation_')) . ':',
        $conBaht ? implode(' | ', $conBaht) : '(sin cifras en baht) ' . implode(' | ', array_slice($celdas, 0, 12)));
    }
  }
}
finally {
  // El coste vuelve a su sitio pase lo que pase.
  $material->set('field_tec_cost', $costeDeVerdad);
  $material->save();
  $almacen->resetCache([$material->id()]);
  $vuelto = $almacen->load($material->id());
  printf(
    "\n  Devuelto: compra %s, consumo %s\n",
    $vuelto->get('field_tec_cost')->value,
    $vuelto->get('field_tec_price')->value
  );
}

echo "\n";
