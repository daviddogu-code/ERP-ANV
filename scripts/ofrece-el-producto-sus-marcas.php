<?php

/**
 * @file
 * El desplegable de marca del formulario de producto ofrece algo.
 *
 * Que el campo aparezca en el html no quiere decir que se pueda elegir nada. La
 * marca es obligatoria en producto, asi que si el desplegable llega vacio el
 * formulario no se puede guardar y da igual que la pantalla de marcas haya vuelto.
 *
 * Uso: drush php:script scripts/ofrece-el-producto-sus-marcas
 */

$cuenta = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$anterior = \Drupal::currentUser()->getAccount();
\Drupal::currentUser()->setAccount($cuenta);

$formulario = \Drupal::service('entity.form_builder')->getForm(
  \Drupal::entityTypeManager()->getStorage('tec_product')->create(['type' => 'tec_product']),
  'default'
);

$campo = $formulario['field_tec_brand']['widget'] ?? NULL;

echo "\n";
if (!$campo) {
  echo "  El formulario no trae el campo de marca. Miralo a mano.\n\n";
  \Drupal::currentUser()->setAccount($anterior);
  return;
}

printf("  obligatorio: %s\n", !empty($campo['#required']) || !empty($campo[0]['target_id']['#required']) ? 'si' : 'no');
printf("  cacharro:    %s\n", $campo['#type'] ?? ($campo[0]['target_id']['#type'] ?? '(sin tipo)'));

$opciones = $campo['#options'] ?? ($campo[0]['target_id']['#options'] ?? NULL);

if ($opciones === NULL) {
  echo "  El cacharro no lleva lista fija de opciones: las busca escribiendo.\n";
  echo "  Se comprueba entonces que el buscador encuentre marcas.\n\n";

  $seleccion = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance([
    'target_type' => 'taxonomy_term',
    'handler' => 'default:taxonomy_term',
    'target_bundles' => ['tec_brands' => 'tec_brands'],
  ]);
  $encontradas = $seleccion->getReferenceableEntities();
  $lista = reset($encontradas) ?: [];
  printf("  marcas que encuentra: %d\n", count($lista));
  foreach ($lista as $id => $etiqueta) {
    printf("      %s (%s)\n", strip_tags((string) $etiqueta), $id);
  }
  echo count($lista) ? "\n  Se puede elegir marca, o sea que se puede guardar un producto.\n" : "\n  NO encuentra ninguna: el producto seguiria bloqueado.\n";
}
else {
  $reales = array_filter(array_keys($opciones), static fn($clave) => $clave !== '_none');
  printf("\n  opciones que ofrece: %d\n", count($reales));
  foreach ($reales as $clave) {
    printf("      %s (%s)\n", strip_tags((string) $opciones[$clave]), $clave);
  }
  echo $reales ? "\n  Se puede elegir marca, o sea que se puede guardar un producto.\n" : "\n  El desplegable llega vacio: el producto seguiria bloqueado.\n";
}

\Drupal::currentUser()->setAccount($anterior);
echo "\n";
