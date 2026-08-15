<?php

/**
 * @file
 * Retira del almacen de claves las definiciones de entidad instaladas huerfanas.
 *
 * Uso: php vendor/bin/drush.php scr scripts/quitar-las-definiciones-instaladas-huerfanas.php
 *      php vendor/bin/drush.php scr scripts/quitar-las-definiciones-instaladas-huerfanas.php -- --de-verdad
 *
 * Que es un huerfano aqui: una entrada en la coleccion
 * `entity.definitions.installed` que describe un tipo de entidad que el sitio ya
 * no conoce. Drupal guarda ahi una copia de cada tipo tal y como estaba el dia
 * que se instalo; cuando se retira un tipo y la limpieza se queda a medias, la
 * copia se queda dentro para siempre.
 *
 * El 15 de agosto de 2026 habia tres, y las tres son la misma especie: el tipo
 * de subtipo que ECK deriva por cada tipo de entidad que crea (`<tipo>_type`).
 * Al borrar el tipo se retiro su definicion, pero no la del subtipo derivado.
 * Es exactamente el mismo resto que dejo tec_app_link_type y que se quito con
 * quitar-a-app-link-del-erp.php unas horas antes; este guion es su hermano
 * generico, y no lleva ninguna lista de nombres a mano a proposito: busca lo que
 * haya, para que sirva la proxima vez.
 *
 *   1. tec_gui_type       - cola de la limpieza de tec_gui del 15 de agosto.
 *   2. commerce_store_type
 *   3. commerce_product_variation_type
 *
 * Sobre los dos que empiezan por commerce, que es donde el diagnostico de
 * partida estaba equivocado y conviene dejarlo escrito: NO son de Drupal
 * Commerce. El objeto guardado dice que los trae `eck`, que su clase es
 * EckEntityBundle y que su prefijo de configuracion es `eck.eck_type.*`. Sus
 * etiquetas son "temp1 type" y "assd type", o sea dos pruebas que alguien creo
 * con ECK reutilizando dos nombres de maquina de Commerce y luego borro.
 *
 * Lo que si es de Commerce de verdad son 55 tablas con 180 filas que se quedaron
 * en la base de datos cuando desaparecio aquel modulo. Este guion NO las toca y
 * no le hacen de freno, porque son otra cosa: un tipo de configuracion no tiene
 * tablas, y borrar su definicion instalada no puede tocar ni una fila. Quedan
 * apuntadas en el informe para decidirlas aparte.
 *
 * Se borra por la API del nucleo y no tirando la fila de la tabla a mano: ademas
 * de la entrada del tipo hay que llevarse la de los campos y las dos cacheadas.
 * Si se borra solo la fila, la copia en cache sigue devolviendo el fantasma.
 */

use Drupal\Component\Serialization\Yaml;

$argumentos = (array) ($extra ?? []);
$deVerdad = in_array('de-verdad', $argumentos, TRUE) || in_array('--de-verdad', $argumentos, TRUE);

const COPIA = 'backups/definiciones-huerfanas-antes-de-quitarlas-2026-08-15.yml';

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad ? " DE VERDAD: se borra\n" : " ENSAYO: solo se cuenta lo que haria. Anade -- --de-verdad para hacerlo\n";
echo str_repeat('=', 82) . "\n";

$bd = \Drupal::database();
$gestorTipos = \Drupal::entityTypeManager();
$fabricaConfig = \Drupal::configFactory();
$almacenClaves = \Drupal::keyValue('entity.definitions.installed');
$esquemaGuardado = \Drupal::keyValue('entity.storage_schema.sql');

$hechos = [];
$anotar = function (string $linea) use (&$hechos): void {
  $hechos[] = $linea;
  echo '    ' . $linea . "\n";
};

// ---------------------------------------------------------------------------
// 1. Quienes son. La lista sale de comparar lo instalado con lo que el sitio
//    conoce hoy, nunca de un nombre escrito aqui.
// ---------------------------------------------------------------------------
echo "\n  --- 1. quienes son ---\n\n";

$instalados = [];
foreach (array_keys($almacenClaves->getAll()) as $clave) {
  if (str_ends_with($clave, '.entity_type')) {
    $instalados[] = substr($clave, 0, -strlen('.entity_type'));
  }
}
$huerfanos = array_values(array_diff($instalados, array_keys($gestorTipos->getDefinitions())));
sort($huerfanos);

if (!$huerfanos) {
  echo "    No hay ninguna definicion instalada huerfana. Nada que hacer.\n\n";
  return;
}

printf("    %d huerfanos: %s\n", count($huerfanos), implode(', ', $huerfanos));

// ---------------------------------------------------------------------------
// 2. Guardias, uno por huerfano. El que no las pase se queda, y los demas
//    siguen: no tiene sentido que un caso raro bloquee a los que estan claros.
// ---------------------------------------------------------------------------
echo "\n  --- 2. guardias ---\n\n";

$aptos = [];
$rechazados = [];

foreach ($huerfanos as $id) {
  $motivos = [];
  $definicion = $almacenClaves->get($id . '.entity_type');

  // a. Tiene que ser un tipo de entidad de verdad. Si lo guardado fuera otra
  // cosa, no se sabe que se esta borrando y no se borra.
  if (!$definicion instanceof \Drupal\Core\Entity\EntityTypeInterface) {
    $motivos[] = 'lo guardado no es un tipo de entidad';
    $rechazados[$id] = $motivos;
    continue;
  }

  // b. Si dice ser el tipo de subtipo de otro tipo, ese otro tampoco puede
  // existir. Si existiera, esto no seria un fantasma: seria el tipo de subtipo
  // vivo de una entidad viva, y borrarlo dejaria la entidad sin subtipos.
  $padre = (string) $definicion->getBundleOf();
  if ($padre !== '' && $gestorTipos->hasDefinition($padre)) {
    $motivos[] = sprintf('es el tipo de subtipo de %s, que SI existe', $padre);
  }

  // c. Ninguna tabla suya con datos dentro. Un tipo de configuracion no tiene
  // tablas; si la definicion declara una y ademas existe, esto no es una cola
  // sino un tipo de contenido a medio borrar, y eso se hace al reves: primero
  // los datos y la definicion al final.
  $tablaBase = (string) $definicion->getBaseTable();
  if ($tablaBase !== '' && $bd->schema()->tableExists($tablaBase)) {
    $filas = (int) $bd->select($tablaBase)->countQuery()->execute()->fetchField();
    $motivos[] = sprintf('su tabla base %s existe todavia y tiene %d filas', $tablaBase, $filas);
  }
  foreach ($bd->query('SHOW TABLES LIKE :t', [':t' => $id . '%'])->fetchCol() as $tabla) {
    $filas = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
    $motivos[] = sprintf('la tabla %s empieza por su nombre y tiene %d filas', $tabla, $filas);
  }

  // d. Ninguna configuracion viva con su prefijo. Si quedaran subtipos
  // guardados, borrar la definicion los dejaria sin quien los describa.
  if (method_exists($definicion, 'getConfigPrefix')) {
    $prefijo = (string) $definicion->getConfigPrefix();
    if ($prefijo !== '' && ($objetos = $fabricaConfig->listAll($prefijo . '.'))) {
      $motivos[] = sprintf('quedan %d objetos de configuracion con el prefijo %s: %s',
        count($objetos), $prefijo, implode(', ', $objetos));
    }
  }

  // e. Ningun campo suyo y, sobre todo, ninguno de otra entidad que le apunte.
  // Este es el guardian que de verdad importa: en tec_gui lo caro no fueron las
  // tablas, fue un campo OBLIGATORIO de las lineas de pedido apuntando al tipo
  // muerto. Aqui se mira lo mismo antes de dar nada por abandonado.
  foreach ($gestorTipos->getStorage('field_storage_config')->loadMultiple() as $almacenCampo) {
    if ($almacenCampo->getTargetEntityTypeId() === $id) {
      $motivos[] = 'tiene un almacen de campo suyo: ' . $almacenCampo->id();
    }
    if ((string) $almacenCampo->getSetting('target_type') === $id) {
      $motivos[] = 'un almacen de campo le apunta: ' . $almacenCampo->id();
    }
  }
  foreach ($gestorTipos->getStorage('field_config')->loadMultiple() as $instancia) {
    if ($instancia->getTargetEntityTypeId() === $id) {
      $motivos[] = 'tiene un campo suyo: ' . $instancia->id();
    }
    if ((string) ($instancia->getSettings()['target_type'] ?? '') === $id) {
      $motivos[] = 'un campo le apunta: ' . $instancia->id()
        . ($instancia->isRequired() ? ' (OBLIGATORIO)' : '');
    }
  }

  // f. Ningun esquema SQL guardado. Si lo hubiera, el nucleo creeria que
  // gobierna tablas suyas y habria que deshacer eso primero.
  foreach (array_keys($esquemaGuardado->getAll()) as $clave) {
    if ($clave === $id || str_starts_with($clave, $id . '.')) {
      $motivos[] = 'guarda esquema SQL: ' . $clave;
    }
  }

  // g. Ninguna vista apoyada en el, y ninguna ruta suya. Con el tipo fuera del
  // sitio no deberia poder haberlas, pero se mira por si la vista quedo escrita
  // en la configuracion apuntando a una tabla que ya no gobierna nadie.
  foreach ($fabricaConfig->listAll('views.view.') as $nombre) {
    $vista = $fabricaConfig->get($nombre)->getRawData();
    if ((string) ($vista['base_table'] ?? '') === $id) {
      $motivos[] = 'la vista ' . $vista['id'] . ' se apoya en el';
    }
  }

  if ($motivos) {
    $rechazados[$id] = $motivos;
    printf("    %-34s SE QUEDA\n", $id);
    foreach ($motivos as $motivo) {
      echo '        - ' . $motivo . "\n";
    }
  }
  else {
    $aptos[$id] = $definicion;
    printf("    %-34s huerfano limpio: sin tablas, sin campos, sin configuracion\n", $id);
  }
}

if (!$aptos) {
  echo "\n  Ninguno pasa las guardias. No se toca nada.\n\n";
  return;
}

// ---------------------------------------------------------------------------
// 3. Lo que hay alrededor y que este guion NO toca. Se imprime aparte para que
//    no se confunda con un motivo para pararse: son hallazgos que van al
//    informe, no frenos.
// ---------------------------------------------------------------------------
echo "\n  --- 3. lo que queda alrededor y no se toca ---\n\n";

foreach (array_keys($aptos) as $id) {
  $padre = (string) $aptos[$id]->getBundleOf();
  if ($padre === '') {
    continue;
  }
  $tablas = $bd->query('SHOW TABLES LIKE :t', [':t' => $padre . '%'])->fetchCol();
  if (!$tablas) {
    printf("    %-34s su tipo %s no dejo ninguna tabla\n", $id, $padre);
    continue;
  }
  $filas = 0;
  foreach ($tablas as $tabla) {
    $filas += (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  }
  printf("    %-34s su tipo %s dejo %d tablas con %d filas: NO se tocan\n",
    $id, $padre, count($tablas), $filas);
}

// ---------------------------------------------------------------------------
// 4. La copia de lo que se va a borrar, antes de tocar nada.
// ---------------------------------------------------------------------------
echo "\n  --- 4. copia de seguridad ---\n\n";

$guardar = [
  'que_es' => 'definiciones de entidad instaladas huerfanas retiradas por scripts/quitar-las-definiciones-instaladas-huerfanas.php',
  'cuando' => date('c'),
  'como_se_restaura' => 'cada valor es el objeto entero serializado. Para devolver uno: '
    . '\\Drupal::keyValue(\'entity.definitions.installed\')->set(\'<clave>\', unserialize(<valor>)); y despues drush cr',
  'entity.definitions.installed' => [],
];

foreach ($aptos as $id => $definicion) {
  $guardar['entity.definitions.installed'][$id . '.entity_type'] = serialize($definicion);
  // Los campos van en una entrada aparte del mismo almacen. En estos tres esta
  // vacia, pero se guarda igual: la copia tiene que valer para el proximo, que
  // puede no estarlo.
  $campos = $almacenClaves->get($id . '.field_storage_definitions');
  if ($campos !== NULL) {
    $guardar['entity.definitions.installed'][$id . '.field_storage_definitions'] = serialize($campos);
  }
}

if ($deVerdad) {
  file_put_contents(COPIA, Yaml::encode($guardar));
  $anotar(sprintf('copia de %d definiciones guardada en %s', count($aptos), COPIA));
}
else {
  $anotar(sprintf('guardaria la copia de %d definiciones en %s', count($aptos), COPIA));
}

// ---------------------------------------------------------------------------
// 5. La retirada.
// ---------------------------------------------------------------------------
echo "\n  --- 5. la retirada ---\n\n";

$repositorio = \Drupal::service('entity.last_installed_schema.repository');

foreach (array_keys($aptos) as $id) {
  if ($deVerdad) {
    $repositorio->deleteLastInstalledDefinition($id);
    $anotar('definicion instalada de ' . $id . ' retirada, con su cache');
  }
  else {
    $anotar('retiraria la definicion instalada de ' . $id . ', con su cache');
  }
}

// ---------------------------------------------------------------------------
// 6. Repaso: que no quede ninguna.
// ---------------------------------------------------------------------------
echo "\n  --- 6. repaso ---\n\n";

// Se vuelve a preguntar al almacen, no a la lista de antes, porque lo que
// importa es como queda de verdad despues de tocar.
$instaladosAhora = [];
foreach (array_keys($almacenClaves->getAll()) as $clave) {
  if (str_ends_with($clave, '.entity_type')) {
    $instaladosAhora[] = substr($clave, 0, -strlen('.entity_type'));
  }
}
$quedan = array_values(array_diff($instaladosAhora, array_keys($gestorTipos->getDefinitions())));

if (!$quedan) {
  echo "    No queda ninguna definicion instalada huerfana.\n";
}
else {
  foreach ($quedan as $id) {
    echo '    ' . ($deVerdad ? 'TODAVIA QUEDA: ' : 'quedaria: ') . $id
      . (isset($rechazados[$id]) ? ' (se queda a proposito)' : '') . "\n";
  }
}

// Y lo mismo por la otra puerta, que es la que de verdad los ensena: el informe
// de estado no los ve ni antes ni despues, asi que la unica manera de saber si
// se fueron es preguntarle al gestor de definiciones.
$gestorDefiniciones = \Drupal::service('entity.definition_update_manager');
$porLaOtraPuerta = [];
foreach ($gestorDefiniciones->getEntityTypes() as $id => $definicion) {
  if (!$gestorTipos->hasDefinition($id)) {
    $porLaOtraPuerta[] = $id;
  }
}
printf("    getEntityTypes() con tipos que ya no existen: %d%s\n",
  count($porLaOtraPuerta), $porLaOtraPuerta ? ' -> ' . implode(', ', $porLaOtraPuerta) : '');

$cambios = $gestorDefiniciones->getChangeSummary();
printf("    getChangeSummary() (lo que pinta el informe de estado): %d%s\n",
  count($cambios), $cambios ? ' -> ' . implode(', ', array_keys($cambios)) : ' (sigue vacio, como antes)');

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 82) . "\n";
printf(" %s: %d cosas\n", $deVerdad ? 'HECHO' : 'SE HARIA', count($hechos));
echo str_repeat('=', 82) . "\n\n";

if ($deVerdad) {
  echo "  No cambia ni una clave de configuracion: esto vive en el almacen de\n";
  echo "  claves, que no se exporta. Queda: drush cr, y pasar cargan-las-paginas.php\n";
  echo "  y comprobacion.php.\n\n";
}
