<?php

/**
 * @file
 * Si la pestana de materiales de un proveedor sigue en pie, con su boton de anadir.
 *
 *   php vendor\bin\drush.php scr scripts/funciona-la-pestana-de-materiales-del-proveedor.php
 *
 * En la ficha de un proveedor hay una pestana con los materiales que suministra
 * -la pantalla block_2 de la vista tec_inventory, dentro de las pestanas de
 * quicktabs- y arriba un boton 'Add new material' que abre el formulario de
 * material con el proveedor ya puesto. Eso ultimo lo hace el modulo epp, que lee
 * el proveedor de la direccion del boton.
 *
 * Se usa antes y despues de retirar field_tec_suppliers, el campo de proveedor
 * viejo, para ver que la pestana sigue contando los mismos materiales y que el
 * boton sigue llevando el proveedor de verdad y no un hueco.
 *
 * No cambia nada.
 */

$bd = \Drupal::database();
\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

// Un proveedor que tenga materiales, para que la pestana tenga algo que ensenar.
$conMateriales = $bd->select('taxonomy_term__field_tec_vendor', 'v')
  ->fields('v', ['field_tec_vendor_target_id'])
  ->groupBy('v.field_tec_vendor_target_id')
  ->execute()
  ->fetchCol();

$titulo('Proveedores con materiales');
if (!$conMateriales) {
  echo "  ninguno: sin materiales asignados no hay pestana que probar.\n\n";
  return;
}
$almacenCrm = \Drupal::entityTypeManager()->getStorage('tec_crm');
foreach ($conMateriales as $id) {
  $proveedor = $almacenCrm->load($id);
  printf("  %-8s %s\n", $id, $proveedor ? $proveedor->label() : '(ya no existe)');
}
$proveedor = (string) reset($conMateriales);

$titulo('La pestana con el proveedor ' . $proveedor);
$vista = \Drupal::entityTypeManager()->getStorage('view')->load('tec_inventory')->getExecutable();
$vista->setDisplay('block_2');
$vista->setArguments([$proveedor]);
$vista->preExecute();
$vista->execute();

printf("  materiales que salen: %d\n", count($vista->result));
foreach (array_slice($vista->result, 0, 5) as $fila) {
  printf("      %s\n", $fila->_entity ? $fila->_entity->label() : '(sin ficha)');
}

$dibujo = \Drupal::service('renderer')->renderInIsolation($vista->buildRenderable('block_2', [$proveedor]));
$html = (string) $dibujo;

$titulo('El boton de anadir material');
if (preg_match('~href="([^"]*tec_inventory/add[^"]*)"~', $html, $coincide)) {
  $enlace = html_entity_decode($coincide[1]);
  printf("  %s\n", $enlace);
  $tieneProveedor = (bool) preg_match('~target_id=(\d+)~', $enlace, $cual);
  if ($tieneProveedor) {
    printf("  lleva el proveedor: si, el %s\n", $cual[1]);
  }
  elseif (str_contains($enlace, '{{')) {
    echo "  lleva el proveedor: NO, se queda la marca sin rellenar\n";
  }
  else {
    echo "  lleva el proveedor: NO\n";
  }
}
else {
  echo "  no sale el boton\n";
}

$titulo('Y quien rellena el proveedor al abrir el formulario');
foreach (['field_tec_suppliers', 'field_tec_vendor'] as $nombre) {
  $campo = \Drupal::entityTypeManager()->getStorage('field_config')->load('taxonomy_term.tec_inventory.' . $nombre);
  if (!$campo) {
    printf("  %-24s ya no existe\n", $nombre);
    continue;
  }
  $relleno = $campo->getThirdPartySetting('epp', 'value', '');
  printf("  %-24s %s\n", $nombre, $relleno !== '' ? 'se rellena con ' . $relleno : 'no se rellena solo');
}

// El camino de vuelta: en la ficha del material tiene que seguir saliendo su
// proveedor. Los materiales tenian los dos campos puestos en la pantalla de ver,
// y al retirar el viejo hay que asegurarse de que el que se ve es el bueno y no
// que se ha quedado la ficha sin proveedor.
$titulo('Sigue saliendo el proveedor en la ficha del material');
$nombreProveedor = $almacenCrm->load($proveedor)?->label() ?? '';
$constructor = \Drupal::entityTypeManager()->getViewBuilder('taxonomy_term');
foreach (array_slice($vista->result, 0, 3) as $fila) {
  $material = $fila->_entity;
  if (!$material) {
    continue;
  }
  foreach (['default', 'full'] as $modo) {
    $html = (string) \Drupal::service('renderer')->renderInIsolation($constructor->view($material, $modo));
    printf(
      "  %-22s pantalla %-8s sale '%s': %s\n",
      substr($material->label(), 0, 22),
      $modo,
      $nombreProveedor,
      $nombreProveedor !== '' && str_contains($html, $nombreProveedor) ? 'si' : 'NO'
    );
  }
}

echo "\n";
