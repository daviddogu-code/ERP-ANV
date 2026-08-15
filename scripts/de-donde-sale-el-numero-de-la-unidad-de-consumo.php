<?php

/**
 * @file
 * Rastrea el numero que la pantalla maestra del resumen ensena en la columna
 * de unidad de consumo, hasta la consulta que lo produce.
 *
 *   php vendor/bin/drush scr scripts/de-donde-sale-el-numero-de-la-unidad-de-consumo.php
 *
 * La pantalla maestra de `tec_order_material_calculation_summary` pone `2, 706`
 * donde deberia poner `Centimeter (cm)`. Un numero asi puede ser tres cosas muy
 * distintas: un identificador de termino que se ensena crudo porque falta el
 * formateador, un numero de verdad que no tiene nada que ver, o el resultado de
 * una operacion que nadie queria. Suponer cual es sale caro, asi que esto lo
 * mira.
 *
 * Lo que hace: ejecuta la pantalla, saca del manejador de la columna su alias y
 * sus columnas de agrupacion, ensena la SQL que se manda, y despues va a la
 * tabla del campo a contar cuantas filas trae cada material y que identificador
 * de termino tiene. Con eso se puede comprobar a mano si el numero de la celda
 * es una suma de identificadores o no.
 *
 * No escribe nada.
 */

use Drupal\views\Views;

$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$nombreVista = 'tec_order_material_calculation_summary';
$nombreDisplay = 'default';
$laColumna = 'field_tec_unit_use';

$almacenPedidos = \Drupal::entityTypeManager()->getStorage('tec_order');
$ids = $almacenPedidos->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_sales_order')
  ->sort('id', 'DESC')
  ->range(0, 1)
  ->execute();
$pedido = $ids ? reset($ids) : NULL;

$vista = Views::getView($nombreVista);
$vista->setDisplay($nombreDisplay);
$vista->setArguments([$pedido]);
$vista->preExecute();
$vista->execute();

echo "\n";
echo str_repeat('=', 96) . "\n";
echo " $nombreVista / $nombreDisplay   pedido $pedido   columna $laColumna\n";
echo str_repeat('=', 96) . "\n";

$manejador = $vista->field[$laColumna];

echo "\n Como esta montada la columna:\n\n";
printf("   plugin                 %s\n", $manejador->getPluginId());
printf("   tabla.campo            %s.%s\n", $manejador->table, $manejador->realField ?: $manejador->field);
printf("   formateador            %s\n", $manejador->options['type'] ?? '?');
printf("   group_type             %s\n", $manejador->options['group_type'] ?? '?');
printf("   separador multivalor   '%s'\n", $manejador->options['separator'] ?? '');
printf("   alias en la consulta   %s\n", $manejador->field_alias);
printf("   aliases                %s\n", json_encode($manejador->aliases));
printf("   group_fields           %s\n", json_encode($manejador->group_fields ?? NULL));

echo "\n La consulta que se manda (solo la parte que importa):\n\n";
$sql = (string) $vista->build_info['query'];
foreach (explode("\n", $sql) as $linea) {
  $linea = trim($linea);
  if ($linea !== '' && (str_contains($linea, 'unit_use') || str_starts_with($linea, 'GROUP BY') || str_starts_with($linea, 'SELECT'))) {
    echo '   ' . $linea . "\n";
  }
}

echo "\n Lo que trae cada fila de resultados en esa columna:\n\n";
foreach ($vista->result as $numero => $fila) {
  $crudo = property_exists($fila, $manejador->field_alias) ? $fila->{$manejador->field_alias} : '(no esta)';
  // Dibujar una columna a mano tiene dos trampas, y las dos se han pisado aqui.
  //
  // La primera: cuando la agregacion no es "agrupar", Views cambia el manejador
  // de la columna por el numerico, y ese devuelve una cadena pelada en lugar de
  // un arbol de render; pasarsela al renderizador revienta con un TypeError.
  //
  // La segunda, justo al contrario: el manejador de campo de verdad renderiza
  // por dentro, y eso exige estar dentro de un contexto de render. Sin el, falla
  // con "Render context is empty". O sea que el mismo guion se rompe de dos
  // maneras distintas segun como este la columna, y hay que cubrir las dos para
  // poder mirar el antes y el despues con la misma herramienta.
  $contexto = new \Drupal\Core\Render\RenderContext();
  $dibujado = \Drupal::service('renderer')->executeInRenderContext(
    $contexto,
    static fn() => $manejador->advancedRender($fila)
  );
  $rendido = trim(strip_tags(is_array($dibujado)
    ? (string) \Drupal::service('renderer')->renderInIsolation($dibujado)
    : (string) $dibujado));
  printf("   fila %d   valor crudo: %-12s   celda dibujada: %s\n", $numero + 1, var_export($crudo, TRUE), $rendido === '' ? '(vacia)' : $rendido);
}

echo "\n Que hay de verdad en la base de datos para esos materiales:\n\n";
$bd = \Drupal::database();
$almacenTerminos = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

foreach ($vista->result as $numero => $fila) {
  $material = $fila->_entity ?? NULL;
  if (!$material) {
    continue;
  }
  $referencias = $bd->select('taxonomy_term__field_tec_unit_use', 'u')
    ->fields('u', ['field_tec_unit_use_target_id'])
    ->condition('entity_id', $material->id())
    ->execute()
    ->fetchCol();

  printf("   %s (termino %s)\n", $material->label(), $material->id());
  foreach ($referencias as $destino) {
    $unidad = $almacenTerminos->load($destino);
    printf("      unidad de consumo: termino %s = %s\n", $destino, $unidad ? $unidad->label() : 'NO EXISTE');
  }

  // Cuantas veces se repite el material en la consulta antes de agrupar. Es el
  // numero por el que se multiplica el identificador cuando la agregacion suma
  // en vez de agrupar, asi que aqui se ve si la cuenta cuadra.
  $repeticiones = cuantasFilasTraeElMaterial($nombreVista, $nombreDisplay, $pedido, $material->id());
  $suma = $repeticiones * (int) reset($referencias);
  printf(
    "      filas sin agrupar: %d;  %d x %s = %s  ->  con separador de miles: %s\n",
    $repeticiones,
    $repeticiones,
    reset($referencias),
    $suma,
    number_format($suma, 0, '.', ', ')
  );
}

echo "\n";

/**
 * Cuenta las filas que la consulta de la vista trae para un material.
 *
 * Se pregunta a la propia consulta de la vista con la agregacion apagada, que
 * es la unica manera de contar las filas de verdad: las relaciones inversas de
 * lineas de pedido y de piezas multiplican el material tantas veces como
 * caminos haya, y ese numero no esta escrito en ninguna parte.
 */
function cuantasFilasTraeElMaterial(string $nombreVista, string $nombreDisplay, $pedido, $material): int {
  $vista = Views::getView($nombreVista);
  $vista->setDisplay($nombreDisplay);
  $vista->setArguments([$pedido]);
  $vista->display_handler->setOption('group_by', FALSE);
  $vista->preExecute();
  $vista->execute();
  $cuenta = 0;
  foreach ($vista->result as $fila) {
    if (isset($fila->_entity) && (string) $fila->_entity->id() === (string) $material) {
      $cuenta++;
    }
  }
  $vista->destroy();
  return $cuenta;
}
