<?php

/**
 * @file
 * Borra los 23 campos del sistema de unidades opcionales de Oscar.
 *
 * El borrado va en ultimo lugar por una razon concreta: cuando se borra un
 * campo, Drupal recorre la configuracion que dependia de el y le pide que se
 * arregle sola. Los displays saben quitarse el campo de encima, pero para no
 * depender de eso y de paso dejar los ficheros exportados limpios, aqui se
 * hace al reves: primero se quitan todas las dependencias a mano, despues se
 * comprueba que no queda ninguna, y solo entonces se borra.
 *
 * Si al comprobar aparece algo que este guion no sabe limpiar, no borra nada y
 * lo dice. Es mejor parar que borrar un campo que alguien sigue nombrando.
 *
 * Por defecto solo cuenta lo que haria. Para hacerlo de verdad:
 *   drush php:script scripts/borrar-los-campos-de-oscar -- de-verdad
 */

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

$condenados = [
  // Los cuatro interruptores de opcionalidad.
  'field_tec_split_inventory',
  'field_tec_package_units',
  'field_tec_stock_unit_check',
  'field_tec_moq_package',
  // La cadena de embalaje, la cuarta unidad paralela a las tres buenas.
  'field_tec_packaging',
  'field_tec_split_unit',
  'field_tec_price_by_package',
  'field_tec_purchasing_moq',
  'field_tec_moq_unit',
  // La matriz de conversion por pares.
  'field_tec_uop_uou',
  'field_tec_uos_uop',
  'field_tec_uos_uou',
  'field_tec_uou_uop',
  'field_tec_uou_uos',
  'field_tec_uop_pu_uou',
  'field_tec_uou_uop_pu',
  'field_tec_uos_pu_uop',
  'field_tec_uos_pu_uou',
  'field_1_uou_uos_pu',
  'field_uop_to_uos',
  // Los dos duplicados con nombre tramposo, y el precio por unidad de uso.
  'field_tec_uou_split_qty',
  'field_tec_uou_control',
  'field_tec_price_uou',
];

$fabrica = \Drupal::configFactory();
$gestor = \Drupal::entityTypeManager();

/**
 * Busca los campos condenados dentro de una estructura, y devuelve la ruta.
 */
$rastrear = function (array $datos, array $condenados, string $camino = '') use (&$rastrear): array {
  $encontrados = [];
  foreach ($datos as $clave => $valor) {
    $aqui = $camino === '' ? (string) $clave : $camino . '.' . $clave;
    // La clave puede ser el propio nombre del campo, que es el caso de las
    // columnas de una tabla y de las piezas de un formulario.
    foreach ($condenados as $campo) {
      if ((string) $clave === $campo) {
        $encontrados[] = $aqui;
      }
    }
    if (is_array($valor)) {
      $encontrados = array_merge($encontrados, $rastrear($valor, $condenados, $aqui));
      continue;
    }
    if (!is_string($valor)) {
      continue;
    }
    foreach ($condenados as $campo) {
      // Con frontera por la derecha, para que field_tec_uou_uop no cace a
      // field_tec_uou_uop_pu.
      if (preg_match('/' . preg_quote($campo, '/') . '(?![a-z0-9_])/', $valor)) {
        $encontrados[] = $aqui . ' = ' . $valor;
      }
    }
  }
  return $encontrados;
};

/**
 * Recorre la configuracion activa y dice quien nombra a los condenados.
 */
$barrer = function () use ($fabrica, $condenados, $rastrear): array {
  $hallazgos = [];
  foreach ($fabrica->listAll() as $nombre) {
    // Los ficheros que definen el campo en si se borran enteros, no se
    // limpian, asi que no cuentan como dependencia.
    $esDefinicion = str_starts_with($nombre, 'field.field.') || str_starts_with($nombre, 'field.storage.');
    $ultimo = substr($nombre, strrpos($nombre, '.') + 1);
    if ($esDefinicion && in_array($ultimo, $condenados, TRUE)) {
      continue;
    }
    $datos = $fabrica->get($nombre)->getRawData();
    if (!is_array($datos)) {
      continue;
    }
    $rutas = $rastrear($datos, $condenados);
    if ($rutas) {
      $hallazgos[$nombre] = $rutas;
    }
  }
  return $hallazgos;
};

echo "\n";
echo str_repeat('=', 78) . "\n";
echo ' Quien nombra todavia a los 23 campos' . ($deVerdad ? '' : '   (solo mirando)') . "\n";
echo str_repeat('=', 78) . "\n\n";

$antes = $barrer();
if (!$antes) {
  echo "  Nadie. Se puede borrar directamente.\n";
}
foreach ($antes as $nombre => $rutas) {
  echo "  $nombre\n";
  foreach ($rutas as $ruta) {
    echo "      $ruta\n";
  }
}

// ---------------------------------------------------------------------------
// Paso 1. Quitar los campos de los formularios y de las fichas.
// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Paso 1. Los formularios y las fichas del material\n";
echo str_repeat('=', 78) . "\n\n";

$tocados = 0;
foreach (['entity_form_display', 'entity_view_display'] as $tipo) {
  foreach ($gestor->getStorage($tipo)->loadMultiple() as $display) {
    $contenido = $display->get('content') ?? [];
    $escondidos = $display->get('hidden') ?? [];
    $quitar = [];
    $sustituir = NULL;
    foreach ($condenados as $campo) {
      if (isset($contenido[$campo])) {
        $quitar[] = "$campo (se veia)";
        // La ficha del material ensena el pedido minimo de Oscar, que es un
        // desplegable vacio, y tiene escondido el bueno, que es la cantidad.
        // Al llevarse el de Oscar, la pantalla se quedaria sin pedido minimo,
        // asi que el bueno ocupa su sitio.
        if ($campo === 'field_tec_purchasing_moq' && isset($escondidos['field_tec_moq_quantity'])) {
          $sustituir = $contenido[$campo]['weight'] ?? 0;
        }
        unset($contenido[$campo]);
      }
      if (isset($escondidos[$campo])) {
        $quitar[] = "$campo (escondido)";
        unset($escondidos[$campo]);
      }
    }
    if (!$quitar) {
      continue;
    }
    echo '  ' . $tipo . ' ' . $display->id() . "\n";
    foreach ($quitar as $que) {
      echo "      $que\n";
    }
    if ($sustituir !== NULL) {
      echo "      field_tec_moq_quantity pasa a verse, en el sitio que dejo field_tec_purchasing_moq\n";
    }
    $tocados++;
    if (!$deVerdad) {
      continue;
    }
    $display->set('content', $contenido);
    $display->set('hidden', $escondidos);
    if ($sustituir !== NULL) {
      // setComponent rellena el formateador que le toca al tipo de campo, que
      // aqui es un entero, no el desplegable que habia.
      $display->setComponent('field_tec_moq_quantity', [
        'label' => 'above',
        'weight' => $sustituir,
        'region' => 'content',
      ]);
    }
    $display->save();
  }
}
echo $tocados === 0 ? "  Ya estaban limpios.\n" : "\n  Displays tocados: $tocados.\n";

// ---------------------------------------------------------------------------
// Paso 2. Los restos en las opciones de estilo de las vistas.
// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Paso 2. Los restos en las tablas de las vistas\n";
echo str_repeat('=', 78) . "\n\n";

$vistasTocadas = 0;
$atascos = [];
foreach ($gestor->getStorage('view')->loadMultiple() as $vista) {
  $displays = $vista->get('display') ?? [];
  $cambios = [];
  foreach ($displays as $nombre => $display) {
    // Solo las dos listas que van indexadas por nombre de columna: el orden de
    // las columnas de la tabla y los ajustes de cada una.
    foreach (['columns', 'info'] as $lista) {
      if (!isset($display['display_options']['style']['options'][$lista])
        || !is_array($display['display_options']['style']['options'][$lista])) {
        continue;
      }
      foreach ($condenados as $campo) {
        if (!array_key_exists($campo, $displays[$nombre]['display_options']['style']['options'][$lista])) {
          continue;
        }
        unset($displays[$nombre]['display_options']['style']['options'][$lista][$campo]);
        $cambios[] = "$nombre: $lista/$campo";
      }
    }

    // Views guarda lo ultimo que alguien escribio en la casilla de reescribir
    // aunque la casilla quede sin marcar, asi que hay texto que nombra campos
    // de Oscar y no se dibuja. Se limpia para que nadie lo reviva de un clic.
    foreach ($display['display_options']['fields'] ?? [] as $id => $campo) {
      $texto = (string) ($campo['alter']['text'] ?? '');
      if ($texto === '') {
        continue;
      }
      foreach ($condenados as $condenado) {
        if (!preg_match('/' . preg_quote($condenado, '/') . '(?![a-z0-9_])/', $texto)) {
          continue;
        }
        if (!empty($campo['alter']['alter_text'])) {
          $atascos[] = $vista->id() . "/$nombre: $id reescribe con $condenado, y la casilla esta marcada.";
          continue;
        }
        $displays[$nombre]['display_options']['fields'][$id]['alter']['text'] = '';
        $cambios[] = "$nombre: $id, texto muerto que nombraba $condenado";
      }
    }
  }
  if (!$cambios) {
    continue;
  }
  echo '  ' . $vista->id() . "\n";
  foreach ($cambios as $que) {
    echo "      $que\n";
  }
  $vistasTocadas++;
  if (!$deVerdad) {
    continue;
  }
  $vista->set('display', $displays);
  $vista->save();
}
echo $vistasTocadas === 0 ? "  Ya estaban limpias.\n" : "\n  Vistas tocadas: $vistasTocadas.\n";

if ($atascos) {
  echo "\n  PARADO. Esto no lo arregla el guion, porque se esta dibujando:\n\n";
  foreach ($atascos as $atasco) {
    echo "      $atasco\n";
  }
  echo "\n  No se ha borrado ningun campo.\n\n";
  return;
}

// ---------------------------------------------------------------------------
// Paso 3. Comprobar que no queda ninguna dependencia.
// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Paso 3. Comprobacion antes de borrar\n";
echo str_repeat('=', 78) . "\n\n";

if (!$deVerdad) {
  echo "  Sin guardar nada no se puede comprobar de verdad. Los que salian\n";
  echo "  arriba deberian quedar en cero al ejecutarlo con de-verdad.\n";
}
else {
  $despues = $barrer();
  if ($despues) {
    echo "  PARADO. Sigue habiendo configuracion que los nombra:\n\n";
    foreach ($despues as $nombre => $rutas) {
      echo "  $nombre\n";
      foreach ($rutas as $ruta) {
        echo "      $ruta\n";
      }
    }
    echo "\n  No se ha borrado ningun campo.\n\n";
    return;
  }
  echo "  Limpio. Ninguna configuracion los nombra.\n";
}

// ---------------------------------------------------------------------------
// Paso 4. Borrar los campos y sus almacenamientos.
// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Paso 4. Los campos\n";
echo str_repeat('=', 78) . "\n\n";

$almacenCampos = $gestor->getStorage('field_config');
$almacenGuardado = $gestor->getStorage('field_storage_config');

$borrados = 0;
foreach ($condenados as $campo) {
  $definicion = $almacenCampos->load("taxonomy_term.tec_inventory.$campo");
  if (!$definicion) {
    echo sprintf("  %-30s no estaba\n", $campo);
    continue;
  }
  echo sprintf("  %-30s %s\n", $campo, $definicion->getLabel());
  $borrados++;
  if ($deVerdad) {
    $definicion->delete();
  }
}
echo "\n  Campos borrados: $borrados.\n";

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Paso 5. Los almacenamientos que hayan sobrevivido\n";
echo str_repeat('=', 78) . "\n\n";

// Al borrar el ultimo uso de un campo, Drupal se lleva el almacenamiento por
// delante. Se comprueba porque si alguno queda, el campo sigue medio vivo.
$huerfanos = 0;
foreach ($condenados as $campo) {
  $guardado = $almacenGuardado->load("taxonomy_term.$campo");
  if (!$guardado) {
    continue;
  }
  $usos = $guardado->getBundles();
  echo sprintf("  %-30s lo usan: %s\n", $campo, $usos ? implode(', ', $usos) : 'nadie');
  $huerfanos++;
  if ($deVerdad && !$usos) {
    $guardado->delete();
  }
}
echo $huerfanos === 0 ? "  Ninguno: se fueron con los campos.\n" : "\n  Almacenamientos sueltos: $huerfanos.\n";

// ---------------------------------------------------------------------------
// Paso 6. Vaciar de la base los datos que quedaron marcados.
// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Paso 6. Las tablas de datos\n";
echo str_repeat('=', 78) . "\n\n";

if (!$deVerdad) {
  echo "  Nada que vaciar mirando.\n\n";
  return;
}

// Borrar un campo no borra sus datos: los marca, y quien los tira es el cron.
// Aqui se hace en el momento para no dejar 23 tablas esperando, que ademas
// enredan la comprobacion de si el borrado ha ido bien.
\Drupal::moduleHandler()->loadInclude('field', 'inc', 'field.purge');
$repositorio = \Drupal::service('entity_field.deleted_fields_repository');

$cuantos = fn (): int => count($repositorio->getFieldDefinitions()) + count($repositorio->getFieldStorageDefinitions());

echo '  Marcados para vaciar: ' . $cuantos() . ".\n";
$vueltas = 0;
while ($cuantos() > 0 && $vueltas < 40) {
  field_purge_batch(500);
  $vueltas++;
}
echo $cuantos() > 0
  ? '  Quedan ' . $cuantos() . " marcados; de esos se encarga el cron.\n"
  : "  Vaciadas. No queda ningun campo marcado para borrar.\n";

echo "\n";
