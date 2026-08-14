<?php

/**
 * Crea un material rellenando solo los campos legitimos, y luego lo borra.
 *
 * Es la prueba de que el importador de materias primas puede funcionar. Hasta
 * ahora era imposible: cuatro campos del sistema de unidades opcionales seguian
 * marcados como obligatorios, escondidos del formulario y sin valor por
 * defecto, asi que la validacion de la entidad tumbaba cualquier alta que no
 * viniera de la pantalla de Drupal. Feeds valida la entidad entera, no solo las
 * columnas que se mapean, asi que un campo obligatorio oculto basta para que no
 * entre ni una fila.
 *
 * El guion valida primero y guarda despues, que es el mismo orden que sigue
 * Feeds, y al terminar borra el material para no dejar basura.
 *
 * Uso: drush php:script scripts/crear-un-material-por-codigo
 */

// Los diecinueve campos que de verdad describen un material, sin contar la
// foto ni el historial de movimientos, que no se importan, ni los dos costes
// derivados, que salen de un calculo.
$loQueTraeriaElImportador = [
  'name' => 'PRUEBA importador ' . date('His'),
  'field_tec_material_type' => 'primer termino de tipo de material',
  'field_tec_vendor' => 'primer proveedor',
  'field_tec_cost' => '125.50',
  'field_tec_unit_purchase' => 'primera unidad',
  'field_tec_uos' => 'primera unidad',
  'field_tec_unit_use' => 'primera unidad',
  'field_tec_units' => '2.50',
  'field_tec_split_into' => '100.00',
  'field_tec_traceability' => 1,
  'field_tec_internal_sku' => 'PRU-001',
  'field_tec_lead_time_days' => 14,
  'field_tec_moq_quantity' => 5,
  'field_tec_reorder_point' => '20.00',
  'field_tec_safety_stock' => '10.00',
  'field_tec_scrap_margin' => '3.00',
  'field_tec_volume_cbm' => '0.0250',
  'field_tec_importer_item_id' => 'PRUEBA-' . date('His'),
];

// El campo de proveedor elige entre los contactos con una vista, y las vistas
// comprueban permisos. Ejecutado desde la linea de ordenes se es anonimo, asi
// que la comprobacion falla y parece que la vista no existe cuando lo que pasa
// es que no se tiene acceso. Se hace la prueba como el usuario uno, que es lo
// que ocurrira de verdad cuando alguien importe.
$cambiador = \Drupal::service('account_switcher');
$administrador = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$cambiador->switchTo($administrador);

$gestor = \Drupal::entityTypeManager();
$almacen = $gestor->getStorage('taxonomy_term');

/**
 * Devuelve el primer termino de un vocabulario, para rellenar referencias.
 */
$primerTermino = function (string $vocabulario) use ($almacen) {
  $tids = $almacen->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vocabulario)
    ->sort('tid')
    ->range(0, 1)
    ->execute();
  return $tids ? reset($tids) : NULL;
};

// Las referencias necesitan identificadores reales, no nombres.
$tipoMaterial = $primerTermino('tec_materials');
$unidad = $primerTermino('tec_units');
$proveedores = $gestor->getStorage('tec_crm')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_contact_organization')
  ->sort('id')
  ->range(0, 1)
  ->execute();
$proveedor = $proveedores ? reset($proveedores) : NULL;

echo "\n";
echo "Referencias que se van a usar:\n";
echo "  tipo de material: " . ($tipoMaterial ?? 'NO HAY') . "\n";
echo "  unidad de medida: " . ($unidad ?? 'NO HAY') . "\n";
echo "  proveedor: " . ($proveedor ?? 'NO HAY') . "\n";

if (!$tipoMaterial || !$unidad || !$proveedor) {
  echo "\nFaltan referencias en la base, la prueba no puede seguir.\n";
  return;
}

$valores = [
  'vid' => 'tec_inventory',
  'name' => $loQueTraeriaElImportador['name'],
  'field_tec_material_type' => $tipoMaterial,
  'field_tec_vendor' => $proveedor,
  'field_tec_cost' => $loQueTraeriaElImportador['field_tec_cost'],
  'field_tec_unit_purchase' => $unidad,
  'field_tec_uos' => $unidad,
  'field_tec_unit_use' => $unidad,
  'field_tec_units' => $loQueTraeriaElImportador['field_tec_units'],
  'field_tec_split_into' => $loQueTraeriaElImportador['field_tec_split_into'],
  'field_tec_traceability' => $loQueTraeriaElImportador['field_tec_traceability'],
  'field_tec_internal_sku' => $loQueTraeriaElImportador['field_tec_internal_sku'],
  'field_tec_lead_time_days' => $loQueTraeriaElImportador['field_tec_lead_time_days'],
  'field_tec_moq_quantity' => $loQueTraeriaElImportador['field_tec_moq_quantity'],
  'field_tec_reorder_point' => $loQueTraeriaElImportador['field_tec_reorder_point'],
  'field_tec_safety_stock' => $loQueTraeriaElImportador['field_tec_safety_stock'],
  'field_tec_scrap_margin' => $loQueTraeriaElImportador['field_tec_scrap_margin'],
  'field_tec_volume_cbm' => $loQueTraeriaElImportador['field_tec_volume_cbm'],
  'field_tec_importer_item_id' => $loQueTraeriaElImportador['field_tec_importer_item_id'],
];

$material = $almacen->create($valores);

// Paso uno: validar. Es lo que hace Feeds antes de guardar cada fila, y es
// donde se estrellaba.
echo "\n";
echo "Validando la entidad entera, como hace Feeds ... ";
$quejas = $material->validate();

if (count($quejas) > 0) {
  echo "NO PASA\n\n";
  echo "La validacion se queja de " . count($quejas) . " campos:\n";
  foreach ($quejas as $queja) {
    $camino = $queja->getPropertyPath();
    $etiqueta = '';
    $trozos = explode('.', $camino);
    if (isset($trozos[0]) && $material->hasField($trozos[0])) {
      $etiqueta = ' (' . $material->get($trozos[0])->getFieldDefinition()->getLabel() . ')';
    }
    echo "  - $camino$etiqueta: " . strip_tags((string) $queja->getMessage()) . "\n";
  }
  echo "\nEl importador seguiria bloqueado.\n";
  return;
}

echo "pasa\n";

// Paso dos: guardar.
echo "Guardando ... ";
try {
  $material->save();
}
catch (\Throwable $error) {
  echo "PETA\n";
  echo '  ' . get_class($error) . ': ' . $error->getMessage() . "\n";
  return;
}
echo "guardado con el tid " . $material->id() . "\n";

// Paso tres: releer de la base y comprobar que lo guardado es lo que se pidio.
$almacen->resetCache([$material->id()]);
$leido = $almacen->load($material->id());

$comprobaciones = [
  'field_tec_cost' => '125.50',
  'field_tec_units' => '2.50',
  'field_tec_split_into' => '100.00',
  'field_tec_internal_sku' => 'PRU-001',
  'field_tec_lead_time_days' => '14',
];
$descuadres = 0;
echo "\nLo que ha quedado guardado:\n";
foreach ($comprobaciones as $campo => $esperado) {
  $guardado = $leido->get($campo)->value;
  $bien = (string) $guardado === (string) $esperado;
  if (!$bien) {
    $descuadres++;
  }
  printf("  %-28s %-12s %s\n", $campo, (string) $guardado, $bien ? 'bien' : "MAL, se esperaba $esperado");
}

// Los dos costes derivados: se calculan hoy solo en el navegador, asi que en un
// alta por codigo tienen que salir vacios. Es la comprobacion de la tarea que
// viene despues.
$costeInventario = $leido->get('field_tec_price_uos')->value;
$costeConsumo = $leido->get('field_tec_price')->value;
echo "\nCostes derivados, que hoy solo calcula el navegador:\n";
echo '  Coste de inventario: ' . ($costeInventario === NULL ? '(vacio)' : $costeInventario) . "\n";
echo '  Coste de consumo:    ' . ($costeConsumo === NULL ? '(vacio)' : $costeConsumo) . "\n";
if ($costeInventario === NULL || $costeConsumo === NULL) {
  echo "  Confirmado: en un alta por codigo se quedan vacios.\n";
  echo "  Deberian ser " . number_format(125.50 / 2.50, 2) . ' y ';
  echo number_format(125.50 / 2.50 / 100.00, 4) . " si el calculo estuviera en el servidor.\n";
}

// Y se limpia, que esto era una prueba.
$tid = $leido->id();
$leido->delete();
echo "\nMaterial de prueba borrado (tid $tid).\n";

echo "\n";
if ($descuadres === 0) {
  echo "Se puede crear un material por codigo. El importador ya no esta bloqueado.\n";
}
else {
  echo "Se ha creado, pero hay $descuadres valores que no cuadran.\n";
}
