<?php

/**
 * @file
 * Escribe las listas cerradas que la plantilla del importador tiene que respetar.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-listas-hay-para-la-plantilla.php
 *
 * Con la autocreacion apagada, las columnas de referencia del Excel solo valen
 * si el texto coincide exactamente con algo que ya existe. Estas son esas
 * listas: las unidades, los tipos de material, los colores y las organizaciones
 * del CRM que estan marcadas como proveedor.
 *
 * Sirve para dos cosas: elegir valores de verdad para la fila de ejemplo de la
 * plantilla, y para que el dueno tenga a mano lo que puede escribir.
 *
 * No toca nada.
 */

$gestor = \Drupal::entityTypeManager();

print "\n";

foreach ([
  'tec_units' => 'unidades (Purchase UoM, Inventory UoM, Consumption UoM)',
  'tec_materials' => 'tipos de material (Material Type)',
  'tec_colors' => 'colores (Colors)',
] as $vid => $para) {
  $terminos = $gestor->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
  printf("  --- %s: %d, %s ---\n\n", $vid, count($terminos), $para);
  $nombres = [];
  foreach ($terminos as $termino) {
    $nombres[] = $termino->label();
  }
  sort($nombres);
  foreach (array_chunk($nombres, 5) as $fila) {
    print '      ' . implode('  |  ', $fila) . "\n";
  }
  print "\n";
}

// ---------------------------------------------------------------------------
// Los proveedores. Solo valen las organizaciones marcadas como Supplier, que es
// lo que filtra la vista del campo.
// ---------------------------------------------------------------------------
print "  --- proveedores validos (Supplier) ---\n\n";

$hay = 0;
foreach ($gestor->getStorage('tec_crm')->loadMultiple() as $ficha) {
  if ($ficha->bundle() !== 'tec_contact_organization') {
    continue;
  }
  $tipos = [];
  foreach ($ficha->get('field_tec_contact_type') as $elemento) {
    $tipos[] = $elemento->entity ? $elemento->entity->label() : '?';
  }
  $esProveedor = in_array('Supplier', $tipos, TRUE);
  printf("      id %-5s %-32s tipos: %-24s %s\n",
    $ficha->id(), $ficha->label(), implode(',', $tipos),
    $esProveedor ? 'VALIDO' : 'no vale para field_tec_vendor');
  if ($esProveedor) {
    $hay++;
  }
}
printf("\n      %d organizaciones valen como proveedor\n\n", $hay);
