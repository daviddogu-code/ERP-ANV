<?php

/**
 * Borra lo que la auditoria del 14 de agosto dio por muerto, ya verificado.
 *
 * Nada de esto se borra por sospecha. Antes pasaron dos guiones de reconocimiento
 * y cada cosa tiene su prueba:
 *
 *   - tec_app_link: cero fichas en sus dos tablas, ningun campo apunta a el.
 *   - los siete campos de nodo: sin instancias, cero filas, cero en revisiones.
 *   - las siete vistas: ninguna es el valor de un campo ni esta en un diseno.
 *     Aparecian en listas blancas de casillas, todas desmarcadas, y el resto de
 *     las menciones era ruido de la primera busqueda, que buscaba trozos de texto.
 *   - las trece tablas tmp_: son copias de tec_gui de una actualizacion de tipo de
 *     entidad que se quedo a medias. Las tablas de verdad existen y estan vacias,
 *     y Drupal no ve ninguna ficha.
 *   - devT: bloqueada desde 2024, sin nada colgado, y con rol de administrador
 *     que es justo el motivo de no dejarla ahi.
 *
 * Uso: php vendor/bin/drush.php scr scripts/borrar-lo-que-no-usa-nadie.php
 */

const VISTAS = [
  'tec_order_material_calculation',
  'duplicate_of_tec_order_material_calculation_terms_copy',
  'tec_order_create_draft_order_ii_excel_lover_style_eca_model',
  'archive',
  'glossary',
  'dev',
  'material_calculation_inventory_based',
];

const CAMPOS = [
  'field_image_browser',
  'field_files_over_ajax',
  'field_dth_page_title_backgrou',
  'field_dth_page_layout',
  'field_dth_main_content_width',
  'field_dth_hide_regions',
  'field_dth_body_background',
];

const ENTIDAD = 'tec_app_link';

const CUENTA = 'devT';

$etm = \Drupal::entityTypeManager();
$conexion = \Drupal::database();
$esquema = $conexion->schema();
$informe = [];

print "\n";

// -----------------------------------------------------------------------------
// 1. El tipo de entidad de los enlaces a redes sociales.
// -----------------------------------------------------------------------------
// Primero los subtipos y luego el tipo: al contrario, ECK deja los subtipos
// apuntando a un tipo que ya no existe.
print "  --- 1. " . ENTIDAD . " ---\n\n";

// ECK no guarda los subtipos en un almacen comun: por cada tipo de entidad
// deriva uno propio, `<tipo>_type`, con su prefijo de configuracion
// `eck_type.<tipo>` (eck.module:163). O sea que los subtipos de tec_app_link
// viven en el almacen `tec_app_link_type` y en ningun otro sitio.
foreach ($etm->getStorage(ENTIDAD . '_type')->loadMultiple() as $subtipo) {
  printf("      subtipo %-24s borrado\n", $subtipo->id());
  $subtipo->delete();
  $informe['subtipos'] = ($informe['subtipos'] ?? 0) + 1;
}

$tipo = $etm->getStorage('eck_entity_type')->load(ENTIDAD);
if ($tipo) {
  $tipo->delete();
  print "      el tipo de entidad borrado\n";
  $informe['tipos_de_entidad'] = 1;
}

// ECK no siempre se lleva las tablas al borrar el tipo. Se comprueba y, si
// quedan, se tiran a mano: una tabla huerfana confunde al siguiente que mire.
foreach ($conexion->query("SHOW TABLES LIKE '%" . ENTIDAD . "%'")->fetchCol() as $tabla) {
  $esquema->dropTable($tabla);
  printf("      tabla %-28s tirada a mano\n", $tabla);
  $informe['tablas'] = ($informe['tablas'] ?? 0) + 1;
}

print "\n";

// -----------------------------------------------------------------------------
// 2. Las siete vistas.
// -----------------------------------------------------------------------------
print "  --- 2. las siete vistas ---\n\n";

foreach (VISTAS as $id) {
  $vista = $etm->getStorage('view')->load($id);
  if (!$vista) {
    printf("      %-58s ya no estaba\n", $id);
    continue;
  }
  printf("      %-58s %s\n", $id, $vista->label());
  $vista->delete();
  $informe['vistas'] = ($informe['vistas'] ?? 0) + 1;
}

print "\n";

// -----------------------------------------------------------------------------
// 3. Las casillas que quedan nombrando vistas que ya no existen.
// -----------------------------------------------------------------------------
// Los campos `viewfield` guardan una lista blanca con TODAS las vistas del sitio
// y un cero o un uno en cada una. Borrar la vista no limpia la lista, asi que sin
// esto quedarian siete nombres fantasma que el proximo repaso volveria a
// encontrar y habria que volver a investigar.
print "  --- 3. las listas blancas de los campos viewfield ---\n\n";

$tocados = 0;
foreach ($etm->getStorage('field_config')->loadMultiple() as $campo) {
  if ($campo->getType() !== 'viewfield') {
    continue;
  }

  $ajustes = $campo->getSettings();
  $permitidas = $ajustes['allowed_views'] ?? [];
  $quitadas = array_values(array_intersect(array_keys($permitidas), VISTAS));

  if (!$quitadas) {
    continue;
  }

  foreach ($quitadas as $id) {
    unset($permitidas[$id]);
  }

  $ajustes['allowed_views'] = $permitidas;
  $campo->set('settings', $ajustes)->save();

  printf("      %-62s -%d\n", $campo->id(), count($quitadas));
  $tocados++;
}

printf("\n      %d campos limpiados\n\n", $tocados);
$informe['campos_limpiados'] = $tocados;

// -----------------------------------------------------------------------------
// 4. Los siete almacenes de campo de nodos.
// -----------------------------------------------------------------------------
print "  --- 4. los siete almacenes de campo ---\n\n";

foreach (CAMPOS as $campo) {
  $almacen = $etm->getStorage('field_storage_config')->load('node.' . $campo);
  if (!$almacen) {
    printf("      %-32s ya no estaba\n", $campo);
    continue;
  }
  printf("      %-32s %s\n", $campo, $almacen->getType());
  $almacen->delete();
  $informe['almacenes'] = ($informe['almacenes'] ?? 0) + 1;
}

// Borrar un almacen no tira su tabla: la marca para purgar y espera al cron.
// Como estan vacias, se purga aqui mismo y se cierra el asunto en el mismo rato.
// De paso se van los cuatro restos de la IA que llevaban dos anos en la cola.
print "\n      purgando lo marcado, que si no espera al cron:\n";

$repositorio = \Drupal::service('entity_field.deleted_fields_repository');
for ($vuelta = 1; $vuelta <= 12; $vuelta++) {
  $pendientes = count($repositorio->getFieldStorageDefinitions()) + count($repositorio->getFieldDefinitions());
  if (!$pendientes) {
    printf("          vuelta %d: no queda nada pendiente\n", $vuelta);
    break;
  }
  printf("          vuelta %d: %d pendientes\n", $vuelta, $pendientes);
  field_purge_batch(200);
}

print "\n";

// -----------------------------------------------------------------------------
// 5. Las trece tablas de la actualizacion a medias.
// -----------------------------------------------------------------------------
print "  --- 5. las tablas tmp_ de tec_gui ---\n\n";

foreach ($conexion->query("SHOW TABLES LIKE 'tmp\_%'")->fetchCol() as $tabla) {
  $n = (int) $conexion->select($tabla)->countQuery()->execute()->fetchField();
  $esquema->dropTable($tabla);
  printf("      %-46s tirada (%d filas)\n", $tabla, $n);
  $informe['tablas_tmp'] = ($informe['tablas_tmp'] ?? 0) + 1;
}

print "\n";

// -----------------------------------------------------------------------------
// 6. La cuenta devT.
// -----------------------------------------------------------------------------
print "  --- 6. la cuenta " . CUENTA . " ---\n\n";

$cuentas = $etm->getStorage('user')->loadByProperties(['name' => CUENTA]);
$cuenta = reset($cuentas);

if (!$cuenta) {
  print "      ya no estaba\n";
}
else {
  printf("      uid %s, %s, roles: %s\n",
    $cuenta->id(), $cuenta->getEmail(), implode(', ', $cuenta->getRoles()));
  $cuenta->delete();
  print "      borrada\n";
  $informe['cuentas'] = 1;
}

print "\n";

// -----------------------------------------------------------------------------
// Resumen.
// -----------------------------------------------------------------------------
print "  --- resumen ---\n\n";
foreach ($informe as $que => $cuantos) {
  printf("      %-22s %d\n", $que, $cuantos);
}

$usuarios = (int) $etm->getStorage('user')->getQuery()->accessCheck(FALSE)->count()->execute();
printf("\n      usuarios que quedan: %d\n\n", $usuarios);
