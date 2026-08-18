<?php

/**
 * @file
 * Quien compra cada marca, y cuantos productos tiene.
 *
 *   php vendor\bin\drush.php scr scripts/quien-compra-cada-marca.php
 *
 * Esta pregunta estuvo contestada en tres sitios a la vez, y los tres se
 * contradecian sin que nada avisara:
 *
 *   1. La marca lo decia de si misma, en su campo Customer. Una sola casilla, o sea
 *      que una marca solo podia tener un cliente. Retirado el 19 de agosto de 2026.
 *   2. Cada producto lo decia por su cuenta, con su propio campo Customer, que
 *      ademas era obligatorio. Retirado el 19 de agosto de 2026.
 *   3. Y el cliente lo dice de sus marcas, en una lista suya y ordenada a mano. Este
 *      es el que se quedo, y hoy es el unico.
 *
 * Asi que ahora solo hay una respuesta y este guion la ensena, con las dos cosas que
 * pueden estar sueltas: una marca que nadie compra, que no es un error pero conviene
 * saberlo, y un producto sin marca, que si lo es. Desde el corte, un producto sin
 * marca no aparece en la ficha de ningun cliente y no da ningun aviso.
 *
 * Solo mira. No arregla nada, no escribe nada.
 */

const CAMPO = 'field_tec_brands';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$terminos = $gestor->getStorage('taxonomy_term');
$productos = $gestor->getStorage('tec_product');
$contactos = $gestor->getStorage('tec_crm');

$marcas = $terminos->loadByProperties(['vid' => 'tec_brands']);

print "\n";
print "=====================================================================\n";
print "  Quien compra cada marca\n";
print "=====================================================================\n";

printf("\n  marcas: %d, productos: %d, contactos: %d\n", count($marcas),
  $productos->getQuery()->accessCheck(FALSE)->condition('type', 'tec_product')->count()->execute(),
  $contactos->getQuery()->accessCheck(FALSE)->count()->execute());

$nadieLaCompra = 0;

foreach ($marcas as $marca) {
  $cuantos = (int) $productos->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_product')
    ->condition('field_tec_brand', $marca->id())
    ->count()
    ->execute();

  $suyos = $contactos->loadMultiple($contactos->getQuery()
    ->accessCheck(FALSE)
    ->condition(CAMPO, $marca->id())
    ->execute());

  $quienes = [];
  foreach ($suyos as $cliente) {
    // La posicion en la lista del cliente es lo que ordena los bloques de su pestana
    // de productos, asi que se ensena: es un dato que se arrastra y no se ve.
    $posicion = NULL;
    foreach ($cliente->get(CAMPO)->getValue() as $delta => $item) {
      if ((string) ($item['target_id'] ?? '') === (string) $marca->id()) {
        $posicion = $delta;
        break;
      }
    }
    $quienes[] = sprintf('%s (%s)%s', $cliente->label(), $cliente->id(),
      $posicion === NULL ? '' : ', la ' . ($posicion + 1) . '.ª de su lista');
  }

  if ($quienes === []) {
    $nadieLaCompra++;
  }

  printf("\n  %s (%s), %d producto%s\n", $marca->label(), $marca->id(),
    $cuantos, $cuantos === 1 ? '' : 's');
  print '  ' . str_repeat('-', 66) . "\n";
  print $quienes === []
    ? "    no la compra nadie\n"
    : '    ' . implode("\n    ", $quienes) . "\n";
}

print "\n";
print "  Productos sin marca, que no salen en la ficha de ningun cliente\n";
print '  ' . str_repeat('-', 66) . "\n";
$sinMarca = $productos->loadMultiple($productos->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_product')
  ->notExists('field_tec_brand')
  ->execute());
if ($sinMarca === []) {
  print "    ninguno\n";
}
foreach ($sinMarca as $producto) {
  printf("    %s (%s)\n", $producto->label(), $producto->id());
}

print "\n";
printf("  %d marcas que no compra nadie.\n", $nadieLaCompra);
print "=====================================================================\n\n";
