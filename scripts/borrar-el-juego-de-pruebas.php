<?php

/**
 * Borra todo lo que fabrico scripts/fabricar-el-juego-de-pruebas.php.
 *
 * Trabaja por tres caminos, porque uno solo no basta:
 *
 *   1. El inventario que deja escrito el guion que fabrica, con los
 *      identificadores exactos de lo que creo.
 *   2. Los nombres que empiezan por PRUEBA, para recoger lo que se hubiera
 *      creado a mano o en una tanda anterior sin inventario.
 *   3. Un barrido de partidas de escandallo huerfanas. Al guardar una linea de
 *      pedido, ECA fabrica por su cuenta una copia del escandallo y le pone el
 *      nombre del material, sin PRUEBA delante, asi que por nombre no aparece y
 *      al borrar el pedido se queda suelta.
 *
 * Borra de dentro hacia fuera para no dejar referencias apuntando al vacio.
 *
 * Uso: drush php:script scripts/borrar-el-juego-de-pruebas
 */

$gestor = \Drupal::entityTypeManager();
$inventario = dirname(__DIR__) . '/scripts/tmp-juego-de-pruebas.json';

// Cada entrada es tipo => lista de identificadores, y el orden de este array es
// el orden de borrado.
$candidatos = [
  'tec_line_item' => [],
  'tec_order' => [],
  'tec_inventory' => [],
  'tec_product' => [],
  'tec_crm' => [],
  'taxonomy_term' => [],
];

// Camino uno: el inventario.
$deLaLista = 0;
if (is_file($inventario)) {
  $apuntado = json_decode((string) file_get_contents($inventario), TRUE) ?: [];
  foreach ($apuntado as $cosa) {
    $tipo = $cosa['tipo'] ?? '';
    $id = $cosa['id'] ?? NULL;
    if ($id !== NULL && isset($candidatos[$tipo])) {
      $candidatos[$tipo][$id] = $id;
      $deLaLista++;
    }
  }
}

// Camino dos: los nombres que empiezan por PRUEBA.
$porNombre = 0;
foreach (['tec_line_item' => 'title', 'tec_order' => 'title', 'tec_inventory' => 'title', 'tec_product' => 'title', 'tec_crm' => 'title', 'taxonomy_term' => 'name'] as $tipo => $campo) {
  $ids = $gestor->getStorage($tipo)->getQuery()
    ->accessCheck(FALSE)
    ->condition($campo, 'PRUEBA', 'STARTS_WITH')
    ->execute();
  foreach ($ids as $id) {
    if (!isset($candidatos[$tipo][$id])) {
      $candidatos[$tipo][$id] = $id;
      $porNombre++;
    }
  }
}

// Camino tres: partidas de escandallo huerfanas, las dos clases.
$huerfanas = 0;
$almacenInv = $gestor->getStorage('tec_inventory');
$ids = $almacenInv->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', ['tec_bom_item', 'tec_line_item_bom_item'], 'IN')
  ->execute();

foreach ($almacenInv->loadMultiple($ids) as $id => $partida) {
  if (isset($candidatos['tec_inventory'][$id])) {
    continue;
  }
  // Una partida de escandallo cuelga de una talla; una partida de linea cuelga
  // de una linea de pedido. Si el padre ya no esta, la partida no pinta nada.
  $suelta = FALSE;
  if ($partida->bundle() === 'tec_bom_item') {
    $talla = $partida->hasField('field_tec_size_variation')
      ? $partida->get('field_tec_size_variation')
      : NULL;
    $suelta = !$talla || $talla->isEmpty() || !$talla->entity;
  }
  else {
    // Las partidas de linea no guardan a quien pertenecen, asi que se busca
    // quien las nombra.
    $duenyos = $gestor->getStorage('tec_line_item')->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_tec_line_item_bom', $id)
      ->count()
      ->execute();
    $suelta = ((int) $duenyos) === 0;
  }
  if ($suelta) {
    $candidatos['tec_inventory'][$id] = $id;
    $huerfanas++;
  }
}

$total = array_sum(array_map('count', $candidatos));

if ($total === 0) {
  echo "\nNo queda nada del juego de pruebas.\n";
  if (is_file($inventario)) {
    unlink($inventario);
  }
  return;
}

echo "\n";
echo "De donde sale cada cosa: $deLaLista del inventario, $porNombre por el nombre, $huerfanas huerfanas.\n";

$borradas = 0;
$fallos = [];

foreach ($candidatos as $tipo => $ids) {
  if (!$ids) {
    continue;
  }
  $almacen = $gestor->getStorage($tipo);
  $entidades = $almacen->loadMultiple($ids);
  if (!$entidades) {
    continue;
  }
  echo "\n  $tipo:\n";
  foreach ($entidades as $entidad) {
    printf("    %-6s %s ... ", $entidad->id(), mb_substr((string) $entidad->label(), 0, 40));
    try {
      $entidad->delete();
      $borradas++;
      echo "borrada\n";
    }
    catch (\Throwable $error) {
      $fallos[] = "$tipo " . $entidad->id() . ': ' . $error->getMessage();
      echo "NO SE PUEDE\n";
    }
  }
}

if (is_file($inventario)) {
  unlink($inventario);
}

echo "\n";
echo "Borradas $borradas de $total.\n";
foreach ($fallos as $fallo) {
  echo "  $fallo\n";
}
