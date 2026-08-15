<?php

/**
 * Tercera y ultima pasada de reconocimiento. No modifica nada.
 *
 * Cierra dos riesgos concretos que destapo la segunda pasada:
 *
 *   1. Hay terminos de taxonomia que apuntan a fichas de CRM
 *      (`field_tec_customer` con 13 filas, `field_tec_vendor` con 38) y a
 *      inventario (`field_tec_stock_mutations`, 26). Si esos campos viven en un
 *      vocabulario que se queda, borrar las fichas de CRM deja referencias
 *      muertas dentro de algo que conservamos. Hay que saber en que
 *      vocabularios estan.
 *
 *   2. Los catorce nodos no son datos de prueba, son la navegacion del ERP.
 *      Dos de ellos, Brands y Patterns, corresponden a vocabularios que se
 *      borran, asi que se quedarian apuntando al vacio. Hay que ver como se
 *      llega a ellos.
 *
 * Y de paso mira si los registros de produccion cuelgan de pedidos.
 *
 * Uso: php vendor/bin/drush.php scr scripts/dos-riesgos.php
 *
 * -------------------------------------------------------------------------
 * Aviso del 15 de agosto de 2026, para quien lo lea de aqui en adelante.
 *
 * Esto es una foto de la auditoria del 14 de agosto, o sea de ANTES de decidir
 * nada, y se conserva por eso. Dos cosas que decia entonces ya no valen hoy y
 * conviene no leerlas como si valieran:
 *
 *   - El riesgo 2 miraba los seis accesos de la portada, que eran fichas de
 *     tec_app_link. Ese tipo de entidad se retiro: el grueso el 14 de agosto
 *     con borrar-lo-que-no-usa-nadie.php y la cola el 15 con
 *     quitar-a-app-link-del-erp.php. Nunca llego a tener ni una ficha dentro,
 *     asi que aquella lista salia vacia ya entonces. Desde el borrado, la
 *     llamada al almacen reventaba el guion entero y se llevaba por delante los
 *     apartados 3 y 4, que siguen valiendo; ahora se pregunta antes.
 *
 *   - En el riesgo 1, la columna de la derecha dice de tec_inventory,
 *     tec_brands y tec_patterns que "se borra con el vocabulario". Eso era el
 *     plan de aquella noche, no lo que paso: los tres vocabularios siguen en pie
 *     -tec_brands y tec_patterns vacios, pendientes de decidir- porque quitarlos
 *     obliga a desmontar antes los dos procesos que duplican producto. La
 *     columna se deja como estaba, que es el valor de una foto, pero no es el
 *     estado de hoy.
 *
 * Lo que este guion vigilaba no ha pasado a comprobacion.php a proposito: era
 * una pregunta para tomar una decision concreta, y la decision esta tomada.
 * Las referencias rotas, que es lo unico de aqui que conviene mirar siempre, ya
 * las vigila alli el guardian "ninguna referencia a un termino borrado".
 */

$etm = \Drupal::entityTypeManager();
$bd = \Drupal::database();

function seccion(string $texto): void {
  print "\n" . str_repeat('-', 78) . "\n  " . strtoupper($texto) . "\n" . str_repeat('-', 78) . "\n";
}

// --- 1. En que vocabularios viven los campos que apuntan a contenido ------
seccion('riesgo 1: en que vocabularios viven esos campos');

foreach (['field_tec_customer', 'field_tec_vendor', 'field_tec_stock_mutations', 'field_tec_suppliers'] as $campo) {
  $configs = $etm->getStorage('field_config')->loadByProperties([
    'entity_type' => 'taxonomy_term',
    'field_name' => $campo,
  ]);
  if (!$configs) {
    printf("  %-32s no existe en taxonomy_term\n", $campo);
    continue;
  }
  foreach ($configs as $config) {
    $vid = $config->getTargetBundle();
    $seQueda = !in_array($vid, ['tec_inventory', 'tec_brands', 'tec_patterns'], TRUE);

    $tabla = 'taxonomy_term__' . $campo;
    $n = 0;
    if ($bd->schema()->tableExists($tabla)) {
      $n = (int) $bd->select($tabla, 'f')->condition('f.bundle', $vid)->countQuery()->execute()->fetchField();
    }

    printf(
      "  %-32s en %-22s %4d filas   %s\n",
      $campo,
      $vid,
      $n,
      $seQueda ? '<<< ESE VOCABULARIO SE QUEDA' : 'se borra con el vocabulario'
    );
  }
}

// --- 2. Como se llega a los nodos de navegacion ---------------------------
seccion('riesgo 2: los accesos de la portada (tec_app_link)');

// Se pregunta si el tipo existe antes de pedirle el almacen. Sin esta pregunta,
// getStorage() lanza una excepcion y se lleva por delante lo que viene despues,
// que es lo que pasaba desde el 14 de agosto.
if (!$etm->hasDefinition('tec_app_link')) {
  print "  El tipo de entidad tec_app_link ya no existe: se retiro entre el 14 y el\n";
  print "  15 de agosto de 2026 sin llegar a tener ni una ficha. Nada que listar.\n";
}
else {
  foreach ($etm->getStorage('tec_app_link')->loadMultiple() as $enlace) {
    $destino = '';
    foreach ($enlace->getFields() as $nombre => $campo) {
      if (str_contains($nombre, 'link') || str_contains($nombre, 'url') || str_contains($nombre, 'uri')) {
        $valor = $campo->getValue();
        if ($valor && isset($valor[0]['uri'])) {
          $destino = $valor[0]['uri'];
        }
      }
    }
    printf("  %-5s %-34s %s\n", $enlace->id(), mb_substr((string) $enlace->label(), 0, 33), $destino);
  }
}

// --- 3. De que cuelgan los registros de produccion -----------------------
seccion('los registros de produccion: a que apuntan');

$primero = $etm->getStorage('tec_production_entry')->load(2);
if ($primero) {
  foreach ($primero->getFields() as $nombre => $campo) {
    if (str_starts_with($nombre, 'field_') || in_array($nombre, ['id', 'created'], TRUE)) {
      $valor = $campo->getValue();
      $texto = '';
      if ($valor) {
        $primeraFila = reset($valor);
        $texto = is_array($primeraFila) ? implode(',', array_map('strval', $primeraFila)) : (string) $primeraFila;
      }
      printf("  %-40s %s\n", $nombre, mb_substr($texto, 0, 34));
    }
  }
}

// --- 4. Los registros de importacion ------------------------------------
seccion('registros de importacion de feeds');

foreach ($bd->select('feeds_import_log', 'l')->fields('l')->execute()->fetchAll() as $fila) {
  $campos = (array) $fila;
  printf("  %s\n", implode('  ', array_map(fn($k, $v) => "$k=" . mb_substr((string) $v, 0, 18), array_keys($campos), $campos)));
}

print "\n";
