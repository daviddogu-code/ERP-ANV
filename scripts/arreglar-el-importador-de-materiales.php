<?php

/**
 * @file
 * Arregla el importador de materiales: columnas, mapeos, limite y autocreacion.
 *
 * Uso: php vendor/bin/drush.php scr scripts/arreglar-el-importador-de-materiales.php
 *      php vendor/bin/drush.php scr scripts/arreglar-el-importador-de-materiales.php -- --de-verdad
 *
 * Sin --de-verdad no cambia nada: dice como esta y como quedaria. Con
 * --de-verdad guarda la configuracion, y antes deja una copia del YAML de como
 * estaba en backups/.
 *
 * Como estaba: quince columnas de origen declaradas y solo tres llegando a un
 * campo -nombre, descripcion y trazabilidad-, de las cuales la trazabilidad leia
 * una columna "Traceable" que en los ficheros de verdad se llama "Traceability".
 * Los ocho campos obligatorios del material que quedan sin mapear hacen que
 * Feeds rechace la fila entera al validar, o sea que el importador no puede
 * crear ni un material.
 *
 * Lo que se hace, y por que:
 *
 *  - Se rehacen las columnas de origen. Habia duplicados de dos clases: la misma
 *    columna pedida tres veces (Name en name, inventory_name y name_2) y la misma
 *    idea escrita con espacios y con guion bajo (Material Type y Material_type,
 *    Unit of Use y Unit_of_use). Se queda una columna por dato.
 *  - Se mapean los nueve campos obligatorios y once opcionales.
 *  - El proveedor va a field_tec_vendor, el obligatorio que apunta al CRM, y NO
 *    a field_tec_suppliers, que esta vacio y pendiente de retirarse.
 *  - La autocreacion se apaga en todas las referencias. Es un ajuste del mapeo,
 *    no del campo: con ella encendida, "metros" donde el sistema tiene "metro"
 *    no da error, crea una unidad nueva. Asi metio seis terminos duplicados,
 *    entre ellos un color "Blue " con un espacio detras. Apagada, una errata
 *    falla en voz alta y se arregla en el Excel.
 *  - El limite de lineas por pasada sube de 100 a 1000, que cubre los 857
 *    materiales en una sola pasada. No se pone a 0: Feeds envuelve el lector en
 *    un LimitIterator, y ahi el 0 no significa "sin limite" sino "ninguna fila".
 *  - La importacion periodica se queda apagada (-1), y se comprueba al final.
 */

const IMPORTADOR = 'tec_inventory_csv_importer';

// Cuantas filas por pasada. 857 materiales caben en una.
const LIMITE_DE_LINEAS = 1000;

/**
 * El mapa: una fila por columna del Excel.
 *
 * clave      la clave interna del origen en la configuracion de Feeds
 * columna    el nombre EXACTO de la cabecera en el fichero
 * destino    el campo del material
 * obligatoria si el campo es obligatorio en el material
 * nota       para que sirve, y como hay que escribir el valor
 */
const MAPA = [
  // --- las nueve obligatorias -----------------------------------------------
  [
    'clave' => 'name',
    'columna' => 'Name',
    'destino' => 'name',
    'unica' => TRUE,
    'nota' => 'nombre del material; es la clave, si se repite actualiza en vez de crear',
  ],
  [
    'clave' => 'material_type',
    'columna' => 'Material Type',
    'destino' => 'field_tec_material_type',
    'nota' => 'nombre exacto de un termino de tec_materials (22 hay)',
  ],
  [
    'clave' => 'supplier',
    'columna' => 'Supplier',
    'destino' => 'field_tec_vendor',
    'nota' => 'nombre exacto de una organizacion del CRM marcada como Supplier',
  ],
  [
    'clave' => 'purchase_cost',
    'columna' => 'Purchase Cost',
    'destino' => 'field_tec_cost',
    'nota' => 'coste de una unidad de compra, sin IVA; punto decimal, no coma',
  ],
  [
    'clave' => 'purchase_uom',
    'columna' => 'Purchase UoM',
    'destino' => 'field_tec_unit_purchase',
    'nota' => 'unidad en la que se compra; nombre exacto de un termino de tec_units',
  ],
  [
    'clave' => 'inventory_uom',
    'columna' => 'Inventory UoM',
    'destino' => 'field_tec_uos',
    'nota' => 'unidad en la que se cuenta el stock; termino de tec_units',
  ],
  [
    'clave' => 'consumption_uom',
    'columna' => 'Consumption UoM',
    'destino' => 'field_tec_unit_use',
    'nota' => 'unidad en la que se consume en produccion; termino de tec_units',
  ],
  [
    'clave' => 'purchase_to_inventory_factor',
    'columna' => 'Purchase to Inventory Factor',
    'destino' => 'field_tec_units',
    'nota' => 'cuantas unidades de inventario trae una de compra; 1 si no cambia',
  ],
  [
    'clave' => 'inventory_to_consumption_factor',
    'columna' => 'Inventory to Consumption Factor',
    'destino' => 'field_tec_split_into',
    'nota' => 'cuantas unidades de consumo trae una de inventario; 1 si no cambia',
  ],

  // --- las opcionales -------------------------------------------------------
  [
    'clave' => 'description',
    'columna' => 'Description',
    'destino' => 'description',
    'nota' => 'texto libre',
  ],
  [
    'clave' => 'traceability',
    'columna' => 'Traceability',
    'destino' => 'field_tec_traceability',
    'nota' => 'OJO: escribir 1 o 0. Cualquier otro texto, incluido "No", cuenta como 1',
  ],
  [
    'clave' => 'colors',
    'columna' => 'Colors',
    'destino' => 'field_tec_colors',
    'nota' => 'nombre exacto de un termino de tec_colors (32 hay)',
  ],
  [
    'clave' => 'internal_sku',
    'columna' => 'Internal SKU',
    'destino' => 'field_tec_internal_sku',
    'nota' => 'referencia interna del material',
  ],
  [
    'clave' => 'importer_item_id',
    'columna' => 'Importer Item ID',
    'destino' => 'field_tec_importer_item_id',
    'nota' => 'identificador de la fila en el fichero de origen, para poder rastrearla',
  ],
  [
    'clave' => 'lead_time_days',
    'columna' => 'Supplier Lead Time (Days)',
    'destino' => 'field_tec_lead_time_days',
    'nota' => 'dias que tarda el proveedor en servir; numero entero',
  ],
  [
    'clave' => 'moq_quantity',
    'columna' => 'Minimum Order Quantity (MOQ)',
    'destino' => 'field_tec_moq_quantity',
    'nota' => 'pedido minimo en unidades de compra; numero entero',
  ],
  [
    'clave' => 'reorder_point',
    'columna' => 'Reorder Point (ROP)',
    'destino' => 'field_tec_reorder_point',
    'nota' => 'nivel de stock al que hay que volver a pedir',
  ],
  [
    'clave' => 'safety_stock',
    'columna' => 'Safety Stock Qty',
    'destino' => 'field_tec_safety_stock',
    'nota' => 'stock de seguridad en unidades de inventario',
  ],
  [
    'clave' => 'scrap_margin',
    'columna' => 'Scrap / Loss Margin (%)',
    'destino' => 'field_tec_scrap_margin',
    'nota' => 'merma en tanto por ciento; 3.5 es tres y medio por ciento',
  ],
  [
    'clave' => 'volume_cbm',
    'columna' => 'Volume per Purchase UoM (CBM)',
    'destino' => 'field_tec_volume_cbm',
    'nota' => 'metros cubicos que ocupa una unidad de compra',
  ],
];

$deVerdad = FALSE;
foreach ((array) ($extra ?? []) as $argumento) {
  if (in_array($argumento, ['--de-verdad', 'de-verdad'], TRUE)) {
    $deVerdad = TRUE;
  }
}

$gestor = \Drupal::entityTypeManager();
$tipo = $gestor->getStorage('feeds_feed_type')->load(IMPORTADOR);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad
  ? " DE VERDAD: se guarda la configuracion\n"
  : " ENSAYO: solo se dice como quedaria. Anade -- --de-verdad para guardarlo\n";
echo str_repeat('=', 82) . "\n";

if (!$tipo) {
  printf("\n  no existe el importador %s\n\n", IMPORTADOR);
  return;
}

// ---------------------------------------------------------------------------
// Guardias.
// ---------------------------------------------------------------------------
echo "\n  --- guardias ---\n\n";

$abortar = [];
$destinosDisponibles = $tipo->getMappingTargets();
$definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'tec_inventory');

// Cada destino del mapa tiene que existir de verdad y ser mapeable.
foreach (MAPA as $fila) {
  if (!isset($destinosDisponibles[$fila['destino']])) {
    $abortar[] = sprintf('el destino %s no existe o no es mapeable', $fila['destino']);
  }
}
if (!$abortar) {
  printf("    los %d destinos del mapa existen y son mapeables\n", count(MAPA));
}

// El campo viejo de proveedores no se toca y tiene que seguir vacio.
$filasViejas = (int) \Drupal::database()->select('taxonomy_term__field_tec_suppliers')->countQuery()->execute()->fetchField();
if ($filasViejas > 0) {
  $abortar[] = sprintf('field_tec_suppliers tiene %d filas y se esperaban 0', $filasViejas);
}
else {
  echo "    field_tec_suppliers sigue vacio y no se mapea: correcto\n";
}

// Y ningun campo obligatorio puede quedarse fuera del mapa.
$obligatorios = [];
foreach ($definiciones as $nombre => $definicion) {
  if ($definicion->isRequired() && $nombre !== 'vid') {
    $obligatorios[] = $nombre;
  }
}
$enElMapa = array_column(MAPA, 'destino');
$fuera = array_diff($obligatorios, $enElMapa);
if ($fuera) {
  $abortar[] = 'campos obligatorios que el mapa no cubre: ' . implode(', ', $fuera);
}
else {
  printf("    los %d campos obligatorios estan en el mapa\n", count($obligatorios));
}

// Dos columnas no pueden llamarse igual, que es el fallo que se viene a arreglar.
$columnas = array_column(MAPA, 'columna');
if (count($columnas) !== count(array_unique($columnas))) {
  $abortar[] = 'el mapa repite algun nombre de columna';
}
else {
  printf("    las %d columnas del mapa son distintas entre si\n", count($columnas));
}
foreach ($columnas as $columna) {
  if ($columna !== trim($columna)) {
    $abortar[] = sprintf('la columna "%s" lleva espacios en los extremos', $columna);
  }
}

if ($abortar) {
  echo "\n  NO SE SIGUE:\n";
  foreach ($abortar as $motivo) {
    echo '    - ' . $motivo . "\n";
  }
  echo "\n";
  return;
}

// ---------------------------------------------------------------------------
// 1. Como esta.
// ---------------------------------------------------------------------------
echo "\n  --- 1. como esta ahora ---\n\n";

$origenesAntes = $tipo->getCustomSources();
$mapeosAntes = $tipo->getMappings();
$configAnalizadorAntes = $tipo->getParser()->getConfiguration();

printf("    columnas de origen declaradas: %d\n", count($origenesAntes));
printf("    mapeos:                        %d\n", count($mapeosAntes));
printf("    limite de lineas por pasada:   %s\n", $configAnalizadorAntes['line_limit'] ?? '(sin definir)');
printf("    importacion periodica:         %s\n", $tipo->getImportPeriod() === -1 ? '-1, apagada' : $tipo->getImportPeriod());
printf("    campos obligatorios sin mapear: %d de %d\n",
  count(array_diff($obligatorios, array_column($mapeosAntes, 'target'))),
  count($obligatorios));

// ---------------------------------------------------------------------------
// 2. La copia del YAML de como estaba.
// ---------------------------------------------------------------------------
echo "\n  --- 2. copia de la configuracion de antes ---\n\n";

$copia = sprintf('%s/backups/feeds.feed_type.%s.antes-de-arreglarlo-%s.yml',
  \Drupal::root(), IMPORTADOR, date('Y-m-d'));

$datosAntes = \Drupal::config('feeds.feed_type.' . IMPORTADOR)->getRawData();
file_put_contents($copia, \Symfony\Component\Yaml\Yaml::dump($datosAntes, 6, 2));
printf("    escrita: %s (%s bytes)\n",
  str_replace(strtr(\Drupal::root(), ['\\' => '/']) . '/', '', strtr($copia, ['\\' => '/'])),
  number_format(filesize($copia)));

// ---------------------------------------------------------------------------
// 3. Lo que va a quedar.
// ---------------------------------------------------------------------------
// Los ajustes de cada mapeo no se escriben a mano: se le preguntan al
// complemento de destino que Feeds usaria, para que salgan todas las claves que
// espera y ninguna de mas. Encima de eso se ponen las decisiones nuestras.
echo "\n  --- 3. las columnas y sus destinos ---\n\n";

$gestorDestinos = \Drupal::service('plugin.manager.feeds.target');

$origenesNuevos = [];
$mapeosNuevos = [];

printf("    %-4s %-32s %-30s %-16s %s\n", 'obl', 'columna del fichero', 'destino', 'complemento', 'ajustes');
print '    ' . str_repeat('-', 140) . "\n";

foreach (MAPA as $fila) {
  $destino = $destinosDisponibles[$fila['destino']];
  $idComplemento = $destino->getPluginId();

  $complemento = $gestorDestinos->createInstance($idComplemento, [
    'feed_type' => $tipo,
    'target_definition' => $destino,
  ]);

  $ajustes = $complemento->defaultConfiguration();

  // La autocreacion se apaga siempre. Es la que ensucia los vocabularios.
  if (array_key_exists('autocreate', $ajustes)) {
    $ajustes['autocreate'] = 0;
  }
  // El nombre del termino o de la organizacion es por lo que se busca. Es el
  // valor por defecto del complemento, pero se deja escrito para que se vea.
  if (array_key_exists('reference_by', $ajustes)) {
    $ajustes['reference_by'] = $ajustes['reference_by'] ?: 'name';
  }
  // La descripcion entra como texto plano, que es lo que trae un Excel.
  if (array_key_exists('format', $ajustes)) {
    $ajustes['format'] = 'plain_text';
  }

  $definicion = $definiciones[$fila['destino']] ?? NULL;
  $esObligatorio = $definicion && $definicion->isRequired();

  printf("    %-4s %-32s %-30s %-16s %s\n",
    $esObligatorio ? 'SI' : '-',
    $fila['columna'],
    $fila['destino'],
    $idComplemento,
    json_encode($ajustes));

  $origenesNuevos[$fila['clave']] = [
    'value' => $fila['columna'],
    'label' => $fila['columna'],
    'machine_name' => $fila['clave'],
    'type' => 'csv',
  ];

  // La propiedad de destino depende del complemento: las referencias reciben
  // el target_id y todo lo demas el value.
  $propiedad = ($idComplemento === 'entity_reference') ? 'target_id' : 'value';

  $mapeo = [
    'target' => $fila['destino'],
    'map' => [$propiedad => $fila['clave']],
    'settings' => $ajustes,
  ];
  if (!empty($fila['unica'])) {
    $mapeo['unique'] = [$propiedad => 1];
  }

  $mapeosNuevos[] = $mapeo;
}

$cuantasObligatorias = 0;
foreach (MAPA as $fila) {
  $definicion = $definiciones[$fila['destino']] ?? NULL;
  if ($definicion && $definicion->isRequired()) {
    $cuantasObligatorias++;
  }
}
printf("\n    %d columnas: %d obligatorias y %d opcionales\n",
  count(MAPA), $cuantasObligatorias, count(MAPA) - $cuantasObligatorias);

// ---------------------------------------------------------------------------
// 4. Guardar.
// ---------------------------------------------------------------------------
echo "\n  --- 4. guardar ---\n\n";

$cambios = [];
$cambios[] = sprintf('columnas de origen: %d -> %d', count($origenesAntes), count($origenesNuevos));
$cambios[] = sprintf('mapeos: %d -> %d', count($mapeosAntes), count($mapeosNuevos));
$cambios[] = sprintf('limite de lineas: %s -> %d', $configAnalizadorAntes['line_limit'] ?? '?', LIMITE_DE_LINEAS);
$cambios[] = sprintf('autocreacion apagada en las %d referencias',
  count(array_filter($mapeosNuevos, static fn($m) => array_key_exists('autocreate', $m['settings']))));

foreach ($cambios as $cambio) {
  echo '    ' . $cambio . "\n";
}

if (!$deVerdad) {
  echo "\n" . str_repeat('=', 82) . "\n";
  echo " SE HARIA. Anade -- --de-verdad para guardarlo\n";
  echo str_repeat('=', 82) . "\n\n";
  return;
}

// Fuera los origenes viejos, uno por uno, y dentro los nuevos.
$tipo->set('custom_sources', $origenesNuevos);
$tipo->setMappings($mapeosNuevos);

// El limite del analizador. Vive en la configuracion del complemento, no en la
// raiz del tipo, asi que hay que pasar por el complemento para que se guarde.
$analizador = $tipo->getParser();
$configAnalizador = $analizador->getConfiguration();
$configAnalizador['line_limit'] = LIMITE_DE_LINEAS;
$analizador->setConfiguration($configAnalizador);

// Y la importacion periodica se deja apagada, no vaya a ser.
$tipo->set('import_period', -1);

$tipo->save();

echo "\n    guardado\n";

// ---------------------------------------------------------------------------
// 5. Como queda, leido de nuevo de la base.
// ---------------------------------------------------------------------------
echo "\n  --- 5. como queda ---\n\n";

$gestor->getStorage('feeds_feed_type')->resetCache([IMPORTADOR]);
$despues = $gestor->getStorage('feeds_feed_type')->load(IMPORTADOR);

printf("    columnas de origen:            %d\n", count($despues->getCustomSources()));
printf("    mapeos:                        %d\n", count($despues->getMappings()));
printf("    limite de lineas por pasada:   %s\n", $despues->getParser()->getConfiguration()['line_limit']);
printf("    importacion periodica:         %s\n", $despues->getImportPeriod() === -1 ? '-1, apagada' : $despues->getImportPeriod());

$sinMapear = array_diff($obligatorios, array_column($despues->getMappings(), 'target'));
printf("    campos obligatorios sin mapear: %d %s\n", count($sinMapear), $sinMapear ? '(' . implode(', ', $sinMapear) . ')' : '');

$conAutocreacion = [];
foreach ($despues->getMappings() as $delta => $mapeo) {
  if (!empty($mapeo['settings']['autocreate'])) {
    $conAutocreacion[] = $mapeo['target'];
  }
}
printf("    mapeos con autocreacion encendida: %s\n", $conAutocreacion ? implode(', ', $conAutocreacion) : 'ninguno');

echo "\n" . str_repeat('=', 82) . "\n";
echo " HECHO\n";
echo str_repeat('=', 82) . "\n\n";
echo "  Queda: generar la plantilla y pasar la comprobacion.\n";
echo "  Clave de configuracion tocada: feeds.feed_type." . IMPORTADOR . "\n\n";
