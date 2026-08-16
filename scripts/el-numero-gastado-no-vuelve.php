<?php

/**
 * @file
 * Siembra el contador de numeros de pedido con lo que ya se ha repartido.
 *
 * Desde el 17 de agosto de 2026 el numero mas alto entregado a cada contacto y
 * ano se recuerda, en vez de deducirse contando los pedidos que hay. Contar
 * hacia que un numero se pudiera recuperar: se borraba el ultimo pedido, la
 * cuenta bajaba, y el siguiente nacia con el numero que el proveedor ya tenia
 * impreso.
 *
 * El recuerdo se siembra solo la primera vez que se numera a un contacto, asi
 * que en rigor esto no hace falta. Hace falta para un caso: si se borran todos
 * los pedidos de un proveedor antes de que su contador exista, no queda nada de
 * donde deducir y la serie volveria a empezar en 001. Sembrandolo hoy, el
 * contador ya sabe por donde iba aunque manana no quede ni un pedido vivo.
 *
 * Solo sube. Un contador que ya vaya por encima de lo que hay se deja como
 * esta, que para eso se guarda. Se puede repetir sin miedo.
 */

use Drupal\tec_production\OrderNumber;

$aplicar = in_array('--aplicar', $extra ?? [], TRUE) || in_array('--aplicar', $_SERVER['argv'] ?? [], TRUE);

$almacen = \Drupal::entityTypeManager()->getStorage('tec_order');
$contador = \Drupal::keyValue(OrderNumber::LEDGER);

// Se recorren los pedidos que existen y se anota, por tipo y prefijo, el numero
// mas alto de cada serie. El prefijo es todo lo que va antes del contador, o
// sea el codigo del contacto y el ano, y se saca del propio titulo para no
// depender de que la ficha siga teniendo hoy el codigo que tenia entonces.
$mas_alto = [];
foreach ($almacen->loadMultiple() as $pedido) {
  if (!OrderNumber::handles($pedido->bundle())) {
    continue;
  }
  if (!preg_match('/^(.*?)(\d+)$/', (string) $pedido->label(), $partes)) {
    continue;
  }
  [, $prefijo, $numero] = $partes;
  if (!str_ends_with($prefijo, '-')) {
    continue;
  }

  $clave = OrderNumber::ledgerKey($pedido->bundle(), $prefijo);
  $mas_alto[$clave] = max($mas_alto[$clave] ?? 0, (int) $numero);
}
ksort($mas_alto);

printf("%-46s %8s %8s   %s\n", 'SERIE', 'EN USO', 'ANOTADO', '');
printf("%s\n", str_repeat('-', 80));

$cambios = 0;
foreach ($mas_alto as $clave => $numero) {
  $anotado = (int) $contador->get($clave, 0);
  $sube = $numero > $anotado;
  if ($sube) {
    $cambios++;
    if ($aplicar) {
      $contador->set($clave, $numero);
    }
  }
  printf("%-46s %8d %8d   %s\n", $clave, $numero, $anotado,
    $sube ? ($aplicar ? 'sembrado' : 'se sembraria') : 'ya iba por delante');
}

printf("\n%d series, %d por sembrar.\n", count($mas_alto), $cambios);
if (!$aplicar) {
  print "Esto ha sido un ensayo. Para hacerlo de verdad: --aplicar\n";
}
