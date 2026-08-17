<?php

/**
 * @file
 * Que dejo el boton de duplicar un color. Solo mira, no toca nada.
 *
 * Se ejecuta con: drush scr scripts/que-copio-el-duplicado.php
 *
 * Duplicar un color es el proceso mas ambicioso del ERP: tres bucles metidos
 * uno dentro de otro, el color, cada una de sus tallas y el BoM de cada talla.
 * En pantalla dos colores recien duplicados se ven identicos aunque compartan
 * las mismas lineas de BoM, y entonces cambiar una cantidad en la copia cambia
 * la del original sin avisar. Esto imprime lo que la pantalla no enseña: de
 * quien es hijo cada ficha, quien la tiene en su lista, y si alguna linea esta
 * colgando de dos tallas a la vez.
 */

const PRODUCTO = 1038;

$productos = \Drupal::entityTypeManager()->getStorage('tec_product');
$almacen_bom = \Drupal::entityTypeManager()->getStorage('tec_inventory');

/**
 * Un valor de campo de referencia, en forma de lista de numeros.
 */
function referencias($ficha, string $campo): array {
  if (!$ficha->hasField($campo)) {
    return [];
  }
  return array_map(
    static fn(array $v): int => (int) $v['target_id'],
    $ficha->get($campo)->getValue()
  );
}

/**
 * El primer numero de un campo de referencia, o cero si no hay ninguno.
 */
function referencia($ficha, string $campo): int {
  return referencias($ficha, $campo)[0] ?? 0;
}

/**
 * Un valor suelto de un campo, tal cual esta guardado.
 */
function valor($ficha, string $campo): string {
  if (!$ficha->hasField($campo) || $ficha->get($campo)->isEmpty()) {
    return '';
  }
  return (string) ($ficha->get($campo)->first()->getValue()['value'] ?? '');
}

/**
 * Quien tiene a esta ficha en su lista.
 */
function quien_lo_lista(int $id, string $campo): array {
  return array_values(\Drupal::entityTypeManager()->getStorage('tec_product')->getQuery()
    ->accessCheck(FALSE)
    ->condition($campo, $id)
    ->execute());
}

print "\n";
print "=====================================================================\n";
print "  Que copio el duplicado, producto " . PRODUCTO . "\n";
print "=====================================================================\n";

$producto = $productos->load(PRODUCTO);
if (!$producto) {
  print "  No existe el producto " . PRODUCTO . ".\n";
  return;
}

print "\n  " . $producto->label() . "\n";

$listados = referencias($producto, 'field_tec_color_variations');
$apuntan = array_map('intval', array_values($productos->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_color_variation')
  ->condition('field_tec_product', PRODUCTO)
  ->execute()));

$colores = array_values(array_unique(array_merge($listados, $apuntan)));
sort($colores);

print "  colores en su lista: " . (implode(', ', $listados) ?: 'ninguno') . "\n";
print "  colores que le apuntan: " . (implode(', ', $apuntan) ?: 'ninguno') . "\n";

$sueltos = array_diff($apuntan, $listados);
if ($sueltos) {
  print "  AVISO: apuntan al producto pero no estan en su lista: " . implode(', ', $sueltos) . "\n";
}
$muertos = array_diff($listados, $apuntan);
if ($muertos) {
  print "  AVISO: en la lista pero no apuntan de vuelta: " . implode(', ', $muertos) . "\n";
}

$de_quien_es_la_linea = [];
$de_quien_es_la_talla = [];

foreach ($colores as $id_color) {
  $color = $productos->load($id_color);
  print "\n---------------------------------------------------------------------\n";
  if (!$color) {
    print "  COLOR " . $id_color . ": no existe, es un numero muerto\n";
    continue;
  }

  $termino = referencia($color, 'field_tec_colors');
  $nombre_termino = $termino
    ? (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($termino)?->label() ?? 'roto')
    : 'ninguno';

  print "  COLOR " . $id_color . "  " . $color->label() . "\n";
  print "    creado          " . date('Y-m-d H:i:s', (int) $color->get('created')->value) . "\n";
  print "    producto        " . (referencia($color, 'field_tec_product') ?: 'VACIO') . "\n";
  print "    termino color   " . $termino . " (" . $nombre_termino . ")\n";
  print "    imagenes        " . count(referencias($color, 'field_tec_images')) . "\n";

  $tallas_listadas = referencias($color, 'field_tec_size_variations');
  $tallas_apuntan = array_map('intval', array_values($productos->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_size_variation')
    ->condition('field_tec_color_variation', $id_color)
    ->execute()));

  print "    tallas en lista " . (implode(', ', $tallas_listadas) ?: 'ninguna') . "\n";
  print "    tallas apuntan  " . (implode(', ', $tallas_apuntan) ?: 'ninguna') . "\n";

  if ($fuera = array_diff($tallas_apuntan, $tallas_listadas)) {
    print "    AVISO: apuntan al color pero no estan en su lista: " . implode(', ', $fuera) . "\n";
  }
  if ($rotas = array_diff($tallas_listadas, $tallas_apuntan)) {
    print "    AVISO: en la lista pero no apuntan de vuelta: " . implode(', ', $rotas) . "\n";
  }

  $tallas = array_values(array_unique(array_merge($tallas_listadas, $tallas_apuntan)));
  sort($tallas);

  foreach ($tallas as $id_talla) {
    $de_quien_es_la_talla[$id_talla][] = $id_color;
    $talla = $productos->load($id_talla);
    if (!$talla) {
      print "\n      TALLA " . $id_talla . ": no existe, es un numero muerto\n";
      continue;
    }

    print "\n      TALLA " . $id_talla . "  " . $talla->label() . "\n";
    print "        producto    " . (referencia($talla, 'field_tec_product') ?: 'VACIO') . "\n";
    print "        color       " . (referencia($talla, 'field_tec_color_variation') ?: 'VACIO') . "\n";
    print "        precio      " . (valor($talla, 'field_tec_price') ?: 'vacio') . "\n";
    print "        codigo      " . (valor($talla, 'field_tec_barcode_nr') ?: 'vacio') . "\n";

    $bom = referencias($talla, 'field_tec_bom');
    print "        BoM         " . count($bom) . " lineas: " . (implode(', ', $bom) ?: 'ninguna') . "\n";

    foreach ($bom as $id_linea) {
      $de_quien_es_la_linea[$id_linea][] = $id_talla;
      $linea = $almacen_bom->load($id_linea);
      if (!$linea) {
        print "          " . $id_linea . "  NO EXISTE, numero muerto en la lista\n";
        continue;
      }
      $material = referencia($linea, 'field_tec_inventory');
      $nombre_material = $material
        ? (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($material)?->label() ?? 'roto')
        : 'ninguno';
      print "          " . str_pad((string) $id_linea, 6)
        . " " . str_pad(substr($nombre_material, 0, 26), 27)
        . " guardado " . str_pad(valor($linea, 'field_tec_quantity'), 10)
        . " escrito " . str_pad(valor($linea, 'field_tec_quantity_input') ?: '-', 8)
        . " buzon importador " . (referencia($linea, 'field_tec_size_variation') ?: '-')
        . "\n";
    }
  }
}

print "\n=====================================================================\n";
print "  Lo que la pantalla no enseña\n";
print "=====================================================================\n";

$compartidas = array_filter($de_quien_es_la_linea, static fn(array $d): bool => count(array_unique($d)) > 1);
if ($compartidas) {
  print "\n  GRAVE: hay lineas de BoM colgando de mas de una talla.\n";
  print "  Cambiar la cantidad en una cambia la de la otra, y en pantalla no se ve.\n";
  foreach ($compartidas as $id_linea => $duenos) {
    print "    linea " . $id_linea . " la usan las tallas " . implode(', ', array_unique($duenos)) . "\n";
  }
}
else {
  print "\n  BIEN: cada linea de BoM cuelga de una sola talla.\n";
}

$tallas_compartidas = array_filter($de_quien_es_la_talla, static fn(array $d): bool => count(array_unique($d)) > 1);
if ($tallas_compartidas) {
  print "\n  GRAVE: hay tallas colgando de mas de un color.\n";
  foreach ($tallas_compartidas as $id_talla => $duenos) {
    print "    talla " . $id_talla . " la usan los colores " . implode(', ', array_unique($duenos)) . "\n";
  }
}
else {
  print "\n  BIEN: cada talla cuelga de un solo color.\n";
}

$implicadas = array_merge([PRODUCTO], $colores, array_keys($de_quien_es_la_talla));
$puestas = \Drupal::database()->select('flagging', 'f')
  ->fields('f', ['flag_id', 'entity_id'])
  ->condition('entity_id', $implicadas, 'IN')
  ->execute()
  ->fetchAll();
if ($puestas) {
  print "\n  BANDERAS PUESTAS, que significan un proceso a medias:\n";
  foreach ($puestas as $bandera) {
    print "    " . $bandera->flag_id . " en la ficha " . $bandera->entity_id . "\n";
  }
}
else {
  print "\n  BIEN: ninguna bandera se ha quedado puesta.\n";
}

print "\n  Ultimo cuarto de hora en el registro de ECA:\n";
$desde = \Drupal::time()->getRequestTime() - 900;
$registro = \Drupal::database()->select('watchdog', 'w')
  ->fields('w', ['type', 'severity', 'message', 'variables', 'timestamp'])
  ->condition('timestamp', $desde, '>=')
  ->orderBy('wid')
  ->execute()
  ->fetchAll();
if (!$registro) {
  print "    nada escrito\n";
}
foreach ($registro as $fila) {
  $variables = @unserialize($fila->variables, ['allowed_classes' => FALSE]);
  $texto = is_array($variables)
    ? strtr($fila->message, array_map('strval', array_filter($variables, 'is_scalar')))
    : $fila->message;
  print "    " . date('H:i:s', (int) $fila->timestamp)
    . " [" . $fila->type . "/" . $fila->severity . "] "
    . substr(strip_tags($texto), 0, 150) . "\n";
}

print "\n";
