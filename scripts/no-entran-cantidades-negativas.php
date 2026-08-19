<?php

/**
 * @file
 * Una linea de pedido no acepta una cantidad negativa, ni escrita ni por codigo.
 *
 *   php vendor\bin\drush.php scr scripts/no-entran-cantidades-negativas.php
 *
 * Cuatro cosas, que son cuatro maneras distintas de que el menos vuelva a entrar:
 *
 *   1. Que los **tres** campos de cantidad de una linea digan minimo cero. El de
 *      venta, el de compra y lo recibido.
 *   2. Que el **navegador** lo sepa: el widget saca su min de la definicion del
 *      campo, y sin eso la casilla deja teclear el signo menos sin quejarse.
 *   3. Que el **servidor** lo sepa: guardando por codigo, sin pasar por ninguna
 *      pantalla, una cantidad negativa tiene que dar una violacion. Esta es la
 *      que de verdad cierra la puerta; la del navegador solo la hace visible.
 *   4. Que el **cero siga entrando**. En las dos pantallas de borrador el cero es
 *      como se quita un renglon, asi que un minimo de uno arreglaria el signo y
 *      rompería la manera de deshacer.
 *
 * Deshace todo lo que crea. Se puede lanzar dos veces.
 */

const CANTIDADES = [
  'tec_line_item.tec_sales_order_line_item.field_tec_quantity',
  'tec_line_item.tec_po_line_item.field_tec_quantity',
  'tec_line_item.tec_po_line_item.field_tec_quantity_received',
];

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$problemas = 0;

$mirar = function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, !$bien && $pista !== '' ? ': ' . $pista : '');
  if (!$bien) {
    $problemas++;
  }
};

print "\n";
print "=====================================================================\n";
print "  No entran cantidades negativas\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. Lo que dicen los tres campos.
// ---------------------------------------------------------------------------
print "El suelo de los tres campos\n";
print str_repeat('-', 70) . "\n";

foreach (CANTIDADES as $cual) {
  $campo = $gestor->getStorage('field_config')->load($cual);
  $minimo = $campo ? $campo->getSetting('min') : NULL;
  printf("  %-62s min %s\n", $cual, var_export($minimo, TRUE));
  $mirar('minimo cero en ' . substr($cual, strrpos($cual, '.') + 1) . ' de ' . explode('.', $cual)[1],
    $campo !== NULL && (string) $minimo === '0',
    $campo === NULL ? 'no existe el campo' : 'dice ' . var_export($minimo, TRUE));
}

$basura = [];

try {
  $almacen = $gestor->getStorage('tec_line_item');
  $linea = $almacen->create([
    'type' => 'tec_sales_order_line_item',
    'title' => 'PRUEBA linea de la cantidad negativa',
    'field_tec_quantity' => 10,
  ]);
  $linea->save();
  $basura[] = $linea->id();
  printf("\n  Linea de prueba %d\n", $linea->id());

  // -------------------------------------------------------------------------
  // 2. La casilla que ve quien escribe.
  // -------------------------------------------------------------------------
  print "\n";
  print "La casilla de escribir\n";
  print str_repeat('-', 70) . "\n";

  $formulario = \Drupal::service('entity.form_builder')->getForm($linea, 'default');
  $casilla = $formulario['field_tec_quantity']['widget'][0]['value'] ?? [];
  printf("  tipo %s, min %s, max %s\n",
    $casilla['#type'] ?? 'no hay casilla',
    var_export($casilla['#min'] ?? NULL, TRUE),
    var_export($casilla['#max'] ?? NULL, TRUE));
  $mirar('la casilla de la cantidad sale con min 0',
    (string) ($casilla['#min'] ?? '') === '0',
    'min = ' . var_export($casilla['#min'] ?? NULL, TRUE));

  $html = (string) \Drupal::service('renderer')->renderInIsolation($formulario['field_tec_quantity']);
  $mirar('y el navegador recibe el min="0" en el html',
    (bool) preg_match('/<input[^>]*name="field_tec_quantity\[0\]\[value\]"[^>]*min="0"/', $html)
    || (bool) preg_match('/<input[^>]*min="0"[^>]*name="field_tec_quantity\[0\]\[value\]"/', $html),
    'el input no lleva min="0"');

  // -------------------------------------------------------------------------
  // 3. Y por codigo, que es la puerta de verdad.
  // -------------------------------------------------------------------------
  print "\n";
  print "Guardando sin pasar por ninguna pantalla\n";
  print str_repeat('-', 70) . "\n";

  /**
   * Que dice la validacion de la cantidad con este valor.
   */
  $quejas = static function (int $valor) use ($linea): array {
    $linea->set('field_tec_quantity', $valor);
    $dichas = [];
    foreach ($linea->validate() as $violacion) {
      if (str_starts_with($violacion->getPropertyPath(), 'field_tec_quantity')) {
        $dichas[] = (string) $violacion->getMessage();
      }
    }
    return $dichas;
  };

  foreach ([-1 => TRUE, -10 => TRUE, 0 => FALSE, 5 => FALSE] as $valor => $tieneQueQuejarse) {
    $dichas = $quejas($valor);
    printf("  cantidad %-4d %s\n", $valor, $dichas ? implode(' / ', $dichas) : 'la acepta');
    $mirar(
      $tieneQueQuejarse ? 'la cantidad ' . $valor . ' se rechaza' : 'la cantidad ' . $valor . ' se acepta',
      $tieneQueQuejarse ? $dichas !== [] : $dichas === [],
      $dichas ? implode(' / ', $dichas) : 'no dice nada');
  }
}
finally {
  foreach ($basura as $id) {
    $gestor->getStorage('tec_line_item')->loadUnchanged($id)?->delete();
  }
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Todo bien. Una cantidad de linea no baja de cero.\n";
}
else {
  printf("  %d cosas mal.\n", $problemas);
}
print "=====================================================================\n\n";
