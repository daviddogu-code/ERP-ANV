<?php

/**
 * @file
 * Borra el color duplicado de prueba y comprueba que no deja restos.
 *
 * Se ejecuta con: drush scr scripts/se-fue-entero-el-duplicado.php
 *
 * Es la segunda mitad de la prueba del boton de duplicar. La primera mira que
 * la copia naciera entera; esta mira que se vaya entera. Antes de la cascada
 * del 18 de agosto, borrar un color se llevaba sus tallas y dejaba las lineas
 * de BoM colgando, y a una linea de BoM sin talla no se llega desde ninguna
 * pantalla: se quedaba en la tabla para siempre sin que nadie pudiera verla.
 *
 * Apunta primero quien cuelga de quien, borra, y luego pregunta por cada ficha
 * una por una. No borra nada que no sea el color que se le dice.
 */

const COLOR = 1124;

$productos = \Drupal::entityTypeManager()->getStorage('tec_product');
$almacen_bom = \Drupal::entityTypeManager()->getStorage('tec_inventory');

print "\n";
print "=====================================================================\n";
print "  Se fue entero el duplicado, color " . COLOR . "\n";
print "=====================================================================\n\n";

$color = $productos->load(COLOR);
if (!$color) {
  print "  El color " . COLOR . " no existe. Nada que borrar.\n\n";
  return;
}

$producto = (int) ($color->get('field_tec_product')->target_id ?? 0);

$tallas = array_map(
  static fn(array $v): int => (int) $v['target_id'],
  $color->get('field_tec_size_variations')->getValue()
);

$lineas = [];
foreach ($tallas as $id_talla) {
  $talla = $productos->load($id_talla);
  if (!$talla) {
    continue;
  }
  foreach ($talla->get('field_tec_bom')->getValue() as $valor) {
    $lineas[] = (int) $valor['target_id'];
  }
}

print "  Antes de borrar:\n";
print "    color    " . COLOR . " (" . $color->label() . "), cuelga del producto " . $producto . "\n";
print "    tallas   " . implode(', ', $tallas) . "\n";
print "    BoM      " . count($lineas) . " lineas: " . implode(', ', $lineas) . "\n";

$color->delete();
print "\n  Borrado el color " . COLOR . ".\n\n";

/**
 * Da por buena o por mala una respuesta, y la imprime.
 *
 * La cuenta se lleva aqui dentro y no en dos variables sueltas porque drush
 * incluye el guion dentro de una funcion, asi que lo que aqui parece el ambito
 * global no lo es y un `global $bien` acaba contando en otro sitio.
 *
 * @return int[]
 *   Cuantas bien y cuantas mal llevamos. Sin argumentos solo devuelve eso.
 */
function dictamen(?bool $correcto = NULL, string $texto = '', string $detalle = ''): array {
  static $bien = 0;
  static $mal = 0;
  if ($correcto === NULL) {
    return [$bien, $mal];
  }
  if ($correcto) {
    $bien++;
    print "  BIEN   " . $texto . ($detalle !== '' ? ': ' . $detalle : '') . "\n";
  }
  else {
    $mal++;
    print "  MAL    " . $texto . ($detalle !== '' ? ': ' . $detalle : '') . "\n";
  }
  return [$bien, $mal];
}

print "  Despues de borrar:\n";

dictamen($productos->load(COLOR) === NULL, 'el color se fue');

$quedan = [];
foreach ($tallas as $id_talla) {
  if ($productos->load($id_talla) !== NULL) {
    $quedan[] = $id_talla;
  }
}
dictamen($quedan === [], 'sus tallas se fueron con el', $quedan ? 'quedan ' . implode(', ', $quedan) : count($tallas) . ' retiradas');

$sueltas = [];
foreach ($lineas as $id_linea) {
  if ($almacen_bom->load($id_linea) !== NULL) {
    $sueltas[] = $id_linea;
  }
}
dictamen($sueltas === [], 'y sus lineas de BoM tambien', $sueltas ? 'quedan ' . implode(', ', $sueltas) : count($lineas) . ' retiradas');

if ($producto) {
  $padre = $productos->load($producto);
  $lista = $padre ? array_map(
    static fn(array $v): int => (int) $v['target_id'],
    $padre->get('field_tec_color_variations')->getValue()
  ) : [];
  dictamen(!in_array(COLOR, $lista, TRUE), 'el producto ya no lo tiene en su lista', 'quedan ' . (implode(', ', $lista) ?: 'ninguno'));
  foreach ($lista as $id_color) {
    dictamen($productos->load($id_color) !== NULL, 'y lo que queda en la lista existe', 'color ' . $id_color);
  }
}

$huerfanas = array_filter($lineas, static function (int $id) use ($almacen_bom): bool {
  return $almacen_bom->load($id) !== NULL;
});
if ($huerfanas) {
  print "\n  Las lineas que quedan no las ve nadie desde ninguna pantalla:\n";
  foreach ($huerfanas as $id_linea) {
    print "    " . $id_linea . "\n";
  }
}

[$bien, $mal] = dictamen();

print "\n=====================================================================\n";
print "  " . $bien . " bien, " . $mal . " mal\n";
print "=====================================================================\n\n";
