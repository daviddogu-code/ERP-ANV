<?php

/**
 * @file
 * Les da a los dos factores de conversion doce cifras y cinco decimales.
 *
 *   php vendor\bin\drush.php scr scripts/dar-cinco-decimales-a-los-factores.php
 *   php vendor\bin\drush.php scr scripts/dar-cinco-decimales-a-los-factores.php -- de-verdad
 *
 * Los dos factores del material, Purchase to Inventory e Inventory to
 * Consumption, se guardan con dos decimales. Con dos no caben las conversiones
 * finas, y lo grave no es que no quepan: es que el formulario acepta los cinco
 * decimales que le escribas, los guarda redondeados y no avisa. Un factor es un
 * divisor, asi que un redondeo ahi no descuadra un dato, parte por la mitad
 * todas las cantidades que se calculen con el, en la lista de compra, en el
 * tablero de stock y en los escandallos.
 *
 * Cambiar los decimales no se puede desde la pantalla: Drupal los bloquea en
 * cuanto el campo tiene datos, y guardar la configuracion por la via normal
 * lanza FieldStorageDefinitionUpdateForbiddenException. Hay que hacerlo a mano
 * en los cuatro sitios donde estan apuntados, y en este orden:
 *
 *   1. Las columnas de verdad, las dos tablas de cada campo, la de valores y la
 *      de revisiones.
 *   2. El apunte de Drupal en entity.storage_schema.sql, que es lo que el cree
 *      que tienen las tablas. Si se queda diciendo dos, el dia que alguien
 *      guarde el campo desde la pantalla Drupal "arreglara" la columna hacia
 *      atras.
 *   3. La configuracion exportable, escrita en crudo con configFactory a
 *      proposito: guardarla como entidad volveria a disparar la comprobacion
 *      que prohibe el cambio.
 *   4. La copia en entity.definitions.installed, si es que hay.
 *
 * Las columnas se cambian partiendo de la especificacion que Drupal ya tiene
 * apuntada, no de una escrita a mano, para no perder por el camino el not null
 * o cualquier otra propiedad que llevara.
 *
 * Sin "de-verdad" solo cuenta lo que haria.
 */

const CIFRAS = 12;
const DECIMALES = 5;

const CAMPOS = [
  'field_tec_units',
  'field_tec_split_into',
];

const ENTIDAD = 'taxonomy_term';

$deVerdad = (bool) array_intersect(['de-verdad', '--de-verdad'], $extra ?? []);

$esquema = \Drupal::database()->schema();
$apuntes = \Drupal::keyValue('entity.storage_schema.sql');
$instaladas = \Drupal::keyValue('entity.definitions.installed');

echo "\n";

foreach (CAMPOS as $campo) {
  $config = \Drupal::configFactory()->getEditable('field.storage.' . ENTIDAD . '.' . $campo);
  if ($config->isNew()) {
    printf("  %s: no existe\n\n", $campo);
    continue;
  }

  printf("  %s\n", $campo);
  printf("  %s\n", str_repeat('-', 74));
  printf(
    "      configuracion: %s cifras, %s decimales\n",
    var_export($config->get('settings.precision'), TRUE),
    var_export($config->get('settings.scale'), TRUE)
  );

  $clave = ENTIDAD . '.field_schema_data.' . $campo;
  $apuntado = $apuntes->get($clave, []);
  $columna = $campo . '_value';

  foreach ($apuntado as $tabla => $definicion) {
    $spec = $definicion['fields'][$columna] ?? NULL;
    if (!$spec) {
      printf("      %s: no tiene apuntada la columna %s\n", $tabla, $columna);
      continue;
    }
    $real = $esquema->tableExists($tabla)
      ? (\Drupal::database()->query("SHOW COLUMNS FROM {$tabla} LIKE :c", [':c' => $columna])->fetchAssoc()['Type'] ?? '?')
      : 'la tabla no existe';
    printf(
      "      %-46s apuntada %s,%s   de verdad %s\n",
      $tabla,
      $spec['precision'] ?? '?',
      $spec['scale'] ?? '?',
      $real
    );
  }

  if (!$deVerdad) {
    printf("\n      quedaria en %d cifras y %d decimales\n\n", CIFRAS, DECIMALES);
    continue;
  }

  // 1. Las columnas, con la especificacion que Drupal ya tenia apuntada.
  foreach ($apuntado as $tabla => &$definicion) {
    if (!isset($definicion['fields'][$columna])) {
      continue;
    }
    $spec = $definicion['fields'][$columna];
    $spec['precision'] = CIFRAS;
    $spec['scale'] = DECIMALES;

    if ($esquema->tableExists($tabla)) {
      $esquema->changeField($tabla, $columna, $columna, $spec);
      printf("      cambiada la columna de %s\n", $tabla);
    }

    // 2. Y el apunte, para que diga lo mismo que la columna.
    $definicion['fields'][$columna] = $spec;
  }
  unset($definicion);
  $apuntes->set($clave, $apuntado);

  // 3. La configuracion, en crudo.
  $config->set('settings.precision', CIFRAS)->set('settings.scale', DECIMALES)->save(TRUE);
  printf("      cambiada la configuracion\n");

  // 4. La copia de la definicion instalada, si la hay.
  $copias = $instaladas->get(ENTIDAD . '.field_storage_definitions', []);
  if (isset($copias[$campo])) {
    $copias[$campo]->setSetting('precision', CIFRAS)->setSetting('scale', DECIMALES);
    $instaladas->set(ENTIDAD . '.field_storage_definitions', $copias);
    printf("      cambiada la copia de la definicion instalada\n");
  }

  echo "\n";
}

if (!$deVerdad) {
  echo "  Simulacion. Con -- de-verdad se cambia.\n\n";
  return;
}

\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
drupal_flush_all_caches();

echo "  Hecho. Exporta la configuracion para que el cambio viaje por git.\n\n";
