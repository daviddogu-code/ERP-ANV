<?php

/**
 * @file
 * Minimo cero en las tres cantidades de una linea de pedido.
 *
 *   php vendor\bin\drush.php scr scripts/una-cantidad-no-es-negativa.php
 *
 * En un pedido de prueba se escribio -10 en la primera linea y el ERP lo acepto.
 * Los tres campos de cantidad de una linea nacieron con `min: null`, o sea sin
 * suelo, y un campo entero sin suelo admite el signo menos igual que admite el
 * 10. No hay ninguna pantalla que lo impida porque la que valida es la
 * definicion del campo, no la pantalla.
 *
 * Lo que hace una cantidad negativa aguas abajo es peor que aceptarla: **no da
 * error en ningun sitio**. El total de la linea se queda vacio, porque la ECA
 * que multiplica cantidad por precio tiene una rama que borra el total cuando la
 * cantidad es menor que 1 -- pensada para el cero, que ahi significa "esta
 * linea no va" -- y el menos diez cae en esa rama. El documento de produccion,
 * en cambio, si lo suma: manda a cortar diez pares menos de una talla, que en un
 * papel que va al taller no significa nada.
 *
 * **El suelo es cero y no uno**, y eso es deliberado. En las dos pantallas de
 * borrador el cero es como se quita una linea: se escribe 0 y la vista deja de
 * mostrarla, porque filtra por cantidad distinta de cero. Con `min: 1` la unica
 * manera de deshacer un renglon dejaria de funcionar.
 *
 * Son tres campos y no uno. La cantidad de una linea de venta, la de una linea
 * de compra, y lo recibido de una linea de compra, que es la tercera cantidad de
 * la casa y se toca a mano para corregir un albaran. Arreglar solo el de venta
 * seria dejar dos puertas abiertas de la misma habitacion.
 *
 * El suelo lo comprueban dos sitios a la vez sin que haya que pedirselo: el
 * navegador, porque el widget de un campo entero saca su `min` de aqui, y el
 * servidor, porque Drupal le cuelga al campo una restriccion de rango que se
 * mira en cada guardado, venga de un formulario o de codigo.
 *
 * Lo que ya esta escrito se queda: cambiar el minimo no repasa lo guardado. Al
 * final se dice que lineas tienen hoy una cantidad negativa, porque son las que
 * daran error la proxima vez que alguien las guarde -- que es justo lo que se
 * quiere, pero mas vale saberlo antes que descubrirlo.
 *
 * Se puede lanzar dos veces: deja los campos igual.
 */

// Las tres cantidades de una linea, y el minimo que les toca.
const CANTIDADES = [
  'tec_line_item.tec_sales_order_line_item.field_tec_quantity',
  'tec_line_item.tec_po_line_item.field_tec_quantity',
  'tec_line_item.tec_po_line_item.field_tec_quantity_received',
];

const SUELO = 0;

$almacen = \Drupal::entityTypeManager()->getStorage('field_config');

print "\n";
print "=====================================================================\n";
print "  Una cantidad no es negativa\n";
print "=====================================================================\n\n";

$cambios = 0;
foreach (CANTIDADES as $cual) {
  $campo = $almacen->load($cual);
  if (!$campo) {
    printf("  %-62s no existe\n", $cual);
    continue;
  }

  $antes = $campo->getSetting('min');
  if ((string) $antes === (string) SUELO) {
    printf("  %-62s ya iba con minimo %s\n", $cual, var_export($antes, TRUE));
    continue;
  }

  $campo->setSetting('min', SUELO);
  $campo->save();
  $cambios++;
  printf("  %-62s %s -> %d\n", $cual, var_export($antes, TRUE), SUELO);
}

// ---------------------------------------------------------------------------
// Lo que ya hay escrito con signo menos.
// ---------------------------------------------------------------------------
print "\n";
print "Lineas que hoy tienen una cantidad negativa\n";
print str_repeat('-', 70) . "\n";

$sucias = 0;
foreach (['field_tec_quantity', 'field_tec_quantity_received'] as $campo) {
  $tabla = 'tec_line_item__' . $campo;
  if (!\Drupal::database()->schema()->tableExists($tabla)) {
    continue;
  }
  $filas = \Drupal::database()->select($tabla, 't')
    ->fields('t', ['entity_id', $campo . '_value'])
    ->condition($campo . '_value', 0, '<')
    ->execute()
    ->fetchAll();
  foreach ($filas as $fila) {
    $sucias++;
    $linea = \Drupal::entityTypeManager()->getStorage('tec_line_item')->load($fila->entity_id);
    $pedido = $linea && $linea->hasField('field_tec_order') && !$linea->get('field_tec_order')->isEmpty()
      ? $linea->get('field_tec_order')->entity
      : NULL;
    printf("  linea %-6d %-28s %s = %d   pedido %s\n",
      $fila->entity_id,
      $linea ? mb_substr((string) $linea->label(), 0, 28) : '(borrada)',
      $campo === 'field_tec_quantity' ? 'cantidad' : 'recibido',
      $fila->{$campo . '_value'},
      $pedido ? $pedido->label() . ' (' . $pedido->id() . ')' : '-');
  }
}
if (!$sucias) {
  print "  ninguna\n";
}

printf("\n  %d campos con suelo nuevo, %d lineas por arreglar a mano\n\n", $cambios, $sucias);
