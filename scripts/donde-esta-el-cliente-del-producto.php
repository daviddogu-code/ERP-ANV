<?php

/**
 * @file
 * El mapa exacto de donde aparece el cliente DEL PRODUCTO, pantalla por pantalla.
 *
 *   php vendor\bin\drush.php scr scripts/donde-esta-el-cliente-del-producto.php
 *
 * Antes de retirar un campo hay que saber en cuantos sitios esta escrito, y no de
 * memoria. Este guion no toca nada: lee la configuracion y dice, para cada
 * pantalla de cada vista, en que seccion aparece y con que papel, porque el papel
 * es lo que decide el trabajo:
 *
 *   - argumento: la pantalla RECIBE el cliente y filtra por el. Hay que recablear.
 *   - relacion:  el camino por el que salta. Se va con el argumento.
 *   - filtro:    se quita.
 *   - campo o columna: se quita.
 *   - texto:     un boton escrito a mano con el campo en la direccion. Se reescribe.
 *
 * OJO CON EL NOMBRE, que es la trampa de este campo. `field_tec_customer` existe
 * en varias entidades a la vez y solo se retira el del producto:
 *
 *   tec_product.field_tec_customer   se va          (quien compra el producto)
 *   tec_order.field_tec_customer     SE QUEDA       (de quien es el pedido)
 *
 * Buscar por nombre da un mapa tres veces mas grande que el trabajo y con las
 * piezas equivocadas dentro -las pantallas de pedidos, la proforma, el contador de
 * pedidos de venta-, asi que aqui se busca por la TABLA, que es lo unico que
 * distingue al del producto. Lo mismo en las ECA: se mira sobre que entidad actua
 * el paso, no como se llama.
 */

const CAMPO = 'field_tec_customer';
const TABLA = 'tec_product__field_tec_customer';

$fabrica = \Drupal::configFactory();

/**
 * Los papeles que juega el campo del producto en una pantalla.
 */
$papelesDe = function (array $opciones): array {
  $papeles = [];

  // Primero las relaciones que salen del campo del producto, porque un argumento
  // que salta por una de ellas tambien cuenta aunque su propia tabla sea otra.
  $suyas = [];
  foreach ($opciones['relationships'] ?? [] as $id => $trozo) {
    if (is_array($trozo) && ($trozo['table'] ?? '') === TABLA) {
      $suyas[$id] = $id;
      $papeles[] = 'relacion ' . $id;
    }
  }

  foreach (['arguments' => 'argumento', 'filters' => 'filtro', 'fields' => 'campo', 'sorts' => 'orden'] as $seccion => $papel) {
    foreach ($opciones[$seccion] ?? [] as $id => $trozo) {
      if (!is_array($trozo)) {
        continue;
      }
      if (($trozo['table'] ?? '') === TABLA || isset($suyas[$trozo['relationship'] ?? ''])) {
        $papeles[] = $papel . ' ' . $id;
      }
    }
  }

  $conNombre = [];
  foreach (['fields', 'filters'] as $seccion) {
    foreach ($opciones[$seccion] ?? [] as $id => $trozo) {
      if (is_array($trozo) && ($trozo['table'] ?? '') === TABLA) {
        $conNombre[$id] = $id;
      }
    }
  }
  $columnas = $opciones['style']['options']['columns'] ?? [];
  foreach (is_array($columnas) ? $columnas : [] as $columna => $donde) {
    if (isset($conNombre[$columna]) || (is_string($donde) && isset($conNombre[$donde]))) {
      $papeles[] = 'columna ' . $columna;
    }
  }

  // Un boton escrito a mano que rellena el cliente del producto nuevo. Se
  // reconoce porque nombra el campo dentro de una direccion de crear producto.
  foreach (['header', 'footer', 'empty'] as $zona) {
    foreach ($opciones[$zona] ?? [] as $trozo) {
      $escrito = is_array($trozo) ? ($trozo['content'] ?? '') : '';
      $dice = is_array($escrito) ? (string) json_encode($escrito) : (string) $escrito;
      if (str_contains($dice, 'tec_product/add') && (str_contains($dice, CAMPO) || str_contains($dice, 'target_id='))) {
        $papeles[] = 'boton en ' . $zona;
      }
    }
  }

  return $papeles;
};

print "\n";
print "=====================================================================\n";
print "  Donde esta escrito el cliente del producto\n";
print "=====================================================================\n";

$total = 0;
$recablear = [];

foreach ($fabrica->listAll('views.view.') as $nombre) {
  $pantallas = $fabrica->get($nombre)->get('display') ?? [];
  $lineas = [];

  foreach ($pantallas as $cual => $pantalla) {
    $papeles = $papelesDe($pantalla['display_options'] ?? []);
    if ($papeles === []) {
      continue;
    }
    $total += count($papeles);
    $lineas[] = sprintf("  %-18s %-30s %s",
      $cual,
      mb_substr((string) ($pantalla['display_title'] ?? ''), 0, 29),
      implode(', ', $papeles));
    foreach ($papeles as $papel) {
      if (str_starts_with($papel, 'argumento')) {
        $recablear[] = str_replace('views.view.', '', $nombre) . ':' . $cual;
      }
    }
  }

  if ($lineas !== []) {
    printf("\n%s\n%s\n%s\n", str_replace('views.view.', '', $nombre), str_repeat('-', 70), implode("\n", $lineas));
  }
}

print "\nFuera de las vistas\n";
print str_repeat('-', 70) . "\n";

foreach (['core.entity_form_display.tec_product.', 'core.entity_view_display.tec_product.'] as $familia) {
  foreach ($fabrica->listAll($familia) as $nombre) {
    $config = $fabrica->get($nombre);
    $donde = [];
    if ($config->get('content.' . CAMPO) !== NULL) {
      $donde[] = 'puesto con ' . ($config->get('content.' . CAMPO . '.type') ?? '?');
    }
    // En las fichas con maqueta el campo no esta en `content` sino dentro de una
    // seccion, que es donde no se le ve si solo se mira arriba.
    if (str_contains((string) json_encode($config->get('third_party_settings.layout_builder')), CAMPO)) {
      $donde[] = 'en la maqueta';
    }
    if ($donde !== []) {
      printf("  %-46s %s\n", str_replace('core.entity_', '', $nombre), implode(', ', $donde));
      $total += count($donde);
    }
  }
}

// En las ECA lo que importa no es el nombre del campo sino sobre que entidad
// actua el paso. Los pasos que leen el cliente de un PEDIDO se quedan.
foreach ($fabrica->listAll('eca.eca.') as $nombre) {
  $config = $fabrica->get($nombre);
  foreach ($config->get('actions') ?? [] as $id => $paso) {
    $ajustes = $paso['configuration'] ?? [];
    if (($ajustes['field_name'] ?? '') !== CAMPO) {
      continue;
    }
    printf("  %-46s %s\n", $config->get('label') ?: $nombre, sprintf('%s -> sobre «%s»',
      $paso['label'] ?? $id, $ajustes['object'] ?? '(la entidad del evento)'));
    $total++;
  }
}

$definicion = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('tec_product', 'tec_product')[CAMPO] ?? NULL;
printf("\n  el campo del producto %s\n", $definicion
  ? 'existe, y es ' . ($definicion->isRequired() ? 'OBLIGATORIO' : 'opcional')
  : 'ya no existe');

$conCliente = \Drupal::entityTypeManager()->getStorage('tec_product')->getQuery()
  ->accessCheck(FALSE)
  ->exists(CAMPO)
  ->count()
  ->execute();
printf("  fichas de producto que lo tienen escrito: %s\n", $conCliente);

print "\n=====================================================================\n";
printf("  %d sitios. A recablear: %s\n", $total,
  $recablear ? implode(', ', array_unique($recablear)) : 'ninguno');
print "=====================================================================\n\n";
