<?php

/**
 * @file
 * Genera la plantilla para importar materiales, en CSV y en Excel.
 *
 * Uso: php vendor/bin/drush.php scr scripts/generar-la-plantilla-de-materiales.php
 *
 * Las columnas NO se escriben aqui a mano: se leen del importador tal y como
 * esta guardado en la base. Asi la plantilla no se puede desincronizar del
 * importador. Las cabeceras son las etiquetas del formulario de anadir material
 * (Material name, Traceable, Active, Purchase Cost (No VAT), etc.).
 *
 * Se generan dos ficheros, y no por gusto:
 *
 *   - El CSV es el que come el importador. Su analizador es de tipo csv y su
 *     lector solo acepta txt, csv, tsv, xml y opml: un .xlsx no lo dejaria ni
 *     subir.
 *   - El .xlsx es para rellenar. Es el unico de los dos donde se puede marcar en
 *     color cuales son las nueve columnas obligatorias, dejar las instrucciones
 *     en una hoja aparte y las listas cerradas -unidades, tipos, colores,
 *     proveedores- en otra. Cuando esta relleno se guarda como CSV y ese es el
 *     que se sube.
 *
 * Antes de escribir nada valida las filas de ejemplo contra los campos de
 * verdad, para que la plantilla no salga con un ejemplo que no importaria.
 *
 * No toca la base de datos. Solo escribe ficheros en docs/plantillas/.
 */

use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const IMPORTADOR = 'tec_inventory_csv_importer';
const CARPETA = 'docs/plantillas';

/**
 * Texto de ayuda por campo de destino. Lo unico que se escribe a mano aqui.
 *
 * Los nombres de columna vienen del importador; esto es solo lo que hay que
 * contarle a quien rellena el Excel.
 */
const AYUDA = [
  'name' => 'Nombre del material. Es la clave: si se repite, actualiza el material que ya existe en vez de crear otro.',
  'field_tec_material_type' => 'Tipo de material. Texto EXACTO de la hoja "Tipos de material". Una errata no crea nada: la fila falla.',
  'field_tec_vendor' => 'Proveedor. Texto EXACTO de la hoja "Proveedores". Solo valen las organizaciones del CRM marcadas como Supplier.',
  'field_tec_cost' => 'Coste de UNA unidad de compra, sin IVA. Punto decimal, nunca coma: 1850.50, no 1850,50.',
  'field_tec_unit_purchase' => 'Unidad en la que se le compra al proveedor. Texto EXACTO de la hoja "Unidades".',
  'field_tec_uos' => 'Unidad en la que se cuenta el stock en el almacen. Texto EXACTO de la hoja "Unidades".',
  'field_tec_unit_use' => 'Unidad en la que se consume en produccion. Texto EXACTO de la hoja "Unidades".',
  'field_tec_units' => 'Cuantas unidades de inventario trae una de compra. Un rollo de 50 metros son 50. Si la unidad no cambia, 1.',
  'field_tec_split_into' => 'Cuantas unidades de consumo trae una de inventario. Un metro son 100 centimetros. Si no cambia, 1.',
  'description' => 'Texto libre. Entra como texto plano.',
  'field_tec_traceability' => 'Escribir 1 o 0, o dejar vacio. OJO: cualquier otro texto, incluido "No", cuenta como 1.',
  'field_tec_colors' => 'Color del material. Texto EXACTO de la hoja "Colores". Un color por material.',
  'field_tec_internal_sku' => 'Referencia interna del material. Texto libre.',
  'field_tec_importer_item_id' => 'Identificador de la fila en el fichero de origen, para poder rastrear de donde salio cada material.',
  'field_tec_lead_time_days' => 'Dias que tarda el proveedor en servir. Numero entero.',
  'field_tec_moq_quantity' => 'Pedido minimo, en unidades de compra. Numero entero.',
  'field_tec_reorder_point' => 'Nivel de stock al que hay que volver a pedir, en unidades de inventario.',
  'field_tec_safety_stock' => 'Stock de seguridad, en unidades de inventario.',
  'field_tec_scrap_margin' => 'Merma en tanto por ciento. 8.5 son ocho y medio por ciento.',
  'field_tec_volume_cbm' => 'Metros cubicos que ocupa una unidad de compra. Para calcular el flete.',
  'status' => '1 deja el material activo, 0 lo oculta de listados (Inactive). Vacio = activo. OJO: escribir "Inactive" cuenta como 1.',
  'field_tec_colors' => 'Color del material. Texto EXACTO de la hoja "Colores". Un color por material.',
  'field_tec_internal_sku' => 'Referencia interna del material. Texto libre.',
  'field_tec_importer_item_id' => 'Identificador de la fila en el fichero de origen, para poder rastrear de donde salio cada material.',
  'field_tec_lead_time_days' => 'Dias que tarda el proveedor en servir. Numero entero.',
  'field_tec_moq_quantity' => 'Pedido minimo, en unidades de compra. Numero entero.',
  'field_tec_reorder_point' => 'Nivel de stock al que hay que volver a pedir, en unidades de inventario.',
  'field_tec_safety_stock' => 'Stock de seguridad, en unidades de inventario.',
  'field_tec_scrap_margin' => 'Merma en tanto por ciento. 8.5 son ocho y medio por ciento.',
  'field_tec_volume_cbm' => 'Metros cubicos que ocupa una unidad de compra. Para calcular el flete.',
];

/**
 * Las filas de ejemplo, con datos de ANV Fight Gear.
 *
 * Son tres a proposito, porque hay tres formas distintas de encadenar las
 * unidades y verlas juntas ahorra la explicacion: la piel se compra por hojas y
 * se consume por pies cuadrados, el nylon y el velcro se compran por rollos y se
 * consumen por centimetros, y hay materiales donde la unidad no cambia nunca.
 *
 * Se indexan por campo de destino, no por nombre de columna, para que sigan
 * valiendo si algun dia se cambia el nombre de una columna.
 */
const EJEMPLOS = [
  [
    'name' => 'Leather Cowhide Black 1.2mm',
    'field_tec_material_type' => 'Leather',
    'field_tec_vendor' => '',
    'field_tec_cost' => '420.00',
    'field_tec_unit_purchase' => 'Sheet',
    'field_tec_uos' => 'Square feet (sqf)',
    'field_tec_unit_use' => 'Square feet (sqf)',
    'field_tec_units' => '22',
    'field_tec_split_into' => '1',
    'description' => 'Full grain cowhide for boxing glove shells',
    'field_tec_traceability' => '1',
    'field_tec_colors' => 'Black',
    'field_tec_internal_sku' => 'LTH-COW-BLK-12',
    'field_tec_importer_item_id' => 'MAT-0001',
    'field_tec_lead_time_days' => '30',
    'field_tec_moq_quantity' => '50',
    'field_tec_reorder_point' => '120',
    'field_tec_safety_stock' => '60',
    'field_tec_scrap_margin' => '8.5',
    'field_tec_volume_cbm' => '0.004',
    'status' => '1',
  ],
  [
    'name' => 'Nylon Lining Fabric Black 150cm',
    'field_tec_material_type' => 'Nylon',
    'field_tec_vendor' => '',
    'field_tec_cost' => '1850.00',
    'field_tec_unit_purchase' => 'Roll',
    'field_tec_uos' => 'Meter (m)',
    'field_tec_unit_use' => 'Centimeter (cm)',
    'field_tec_units' => '50',
    'field_tec_split_into' => '100',
    'description' => 'Lining fabric for glove interior, 150 cm width',
    'field_tec_traceability' => '0',
    'field_tec_colors' => 'Black',
    'field_tec_internal_sku' => 'NYL-LIN-BLK-150',
    'field_tec_importer_item_id' => 'MAT-0002',
    'field_tec_lead_time_days' => '21',
    'field_tec_moq_quantity' => '10',
    'field_tec_reorder_point' => '5',
    'field_tec_safety_stock' => '200',
    'field_tec_scrap_margin' => '5',
    'field_tec_volume_cbm' => '0.06',
    'status' => '1',
  ],
  [
    'name' => 'Velcro Hook 50mm Black',
    'field_tec_material_type' => 'Velcro',
    'field_tec_vendor' => '',
    'field_tec_cost' => '640.00',
    'field_tec_unit_purchase' => 'Roll',
    'field_tec_uos' => 'Meter (m)',
    'field_tec_unit_use' => 'Centimeter (cm)',
    'field_tec_units' => '25',
    'field_tec_split_into' => '100',
    'description' => 'Hook side of the wrist strap closure',
    'field_tec_traceability' => '0',
    'field_tec_colors' => 'Black',
    'field_tec_internal_sku' => 'VEL-HK-50-BLK',
    'field_tec_importer_item_id' => 'MAT-0003',
    'field_tec_lead_time_days' => '14',
    'field_tec_moq_quantity' => '20',
    'field_tec_reorder_point' => '4',
    'field_tec_safety_stock' => '100',
    'field_tec_scrap_margin' => '3',
    'field_tec_volume_cbm' => '0.02',
    'status' => '1',
  ],
];

$gestor = \Drupal::entityTypeManager();
$raiz = strtr(\Drupal::root(), ['\\' => '/']);

print "\n";

// ---------------------------------------------------------------------------
// 1. Las columnas, leidas del importador.
// ---------------------------------------------------------------------------
$tipo = $gestor->getStorage('feeds_feed_type')->load(IMPORTADOR);
if (!$tipo) {
  printf("  no existe el importador %s\n\n", IMPORTADOR);
  return;
}

$origenes = $tipo->getCustomSources();
$definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'tec_inventory');

// El orden de las columnas es el orden de los mapeos, que es como esta escrito
// el importador: primero las obligatorias y despues las opcionales.
$columnas = [];
foreach ($tipo->getMappings() as $mapeo) {
  $destino = $mapeo['target'];
  foreach ($mapeo['map'] as $origen) {
    if ($origen === '' || !isset($origenes[$origen])) {
      continue;
    }
    $definicion = $definiciones[$destino] ?? NULL;
    $columnas[] = [
      'columna' => $origenes[$origen]['value'],
      'destino' => $destino,
      'etiqueta' => $definicion ? (string) $definicion->getLabel() : $destino,
      'obligatoria' => $definicion ? $definicion->isRequired() : FALSE,
      'ayuda' => AYUDA[$destino] ?? '',
    ];
  }
}

printf("  --- 1. columnas leidas del importador: %d ---\n\n", count($columnas));

$sinAyuda = [];
foreach ($columnas as $c) {
  printf("      %-4s %-32s -> %-30s %s\n",
    $c['obligatoria'] ? 'OBL' : '-',
    $c['columna'],
    $c['destino'],
    $c['ayuda'] ? '' : 'SIN TEXTO DE AYUDA');
  if (!$c['ayuda']) {
    $sinAyuda[] = $c['destino'];
  }
}
if ($sinAyuda) {
  printf("\n      OJO: %d columnas sin texto de ayuda: %s\n", count($sinAyuda), implode(', ', $sinAyuda));
}

$obligatorias = array_values(array_filter($columnas, static fn($c) => $c['obligatoria']));
printf("\n      %d obligatorias, %d opcionales\n", count($obligatorias), count($columnas) - count($obligatorias));

// ---------------------------------------------------------------------------
// 2. Las listas cerradas.
// ---------------------------------------------------------------------------
print "\n  --- 2. las listas cerradas ---\n\n";

$listas = [];
foreach (['tec_units' => 'Unidades', 'tec_materials' => 'Tipos de material', 'tec_colors' => 'Colores'] as $vid => $titulo) {
  $nombres = [];
  foreach ($gestor->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]) as $termino) {
    $nombres[] = $termino->label();
  }
  sort($nombres);
  $listas[$titulo] = $nombres;
  printf("      %-20s %d valores\n", $titulo, count($nombres));
}

// Los proveedores: las organizaciones marcadas como Supplier, que es lo que
// filtra la vista del campo field_tec_vendor.
$proveedores = [];
foreach ($gestor->getStorage('tec_crm')->loadMultiple() as $ficha) {
  if ($ficha->bundle() !== 'tec_contact_organization') {
    continue;
  }
  foreach ($ficha->get('field_tec_contact_type') as $elemento) {
    if ($elemento->entity && $elemento->entity->label() === 'Supplier') {
      $proveedores[] = $ficha->label();
      break;
    }
  }
}
sort($proveedores);
$listas['Proveedores'] = $proveedores;
printf("      %-20s %d valores\n", 'Proveedores', count($proveedores));

if (!$proveedores) {
  print "\n      OJO: no hay ninguna organizacion marcada como Supplier. La columna\n";
  print "      Supplier es obligatoria, asi que sin eso no entra ni un material.\n";
}

// Las filas de ejemplo llevan el primer proveedor de verdad que haya, para que
// el ejemplo se pueda importar tal cual.
$ejemplos = EJEMPLOS;
foreach ($ejemplos as $i => $ejemplo) {
  $ejemplos[$i]['field_tec_vendor'] = $proveedores[0] ?? '';
}

// ---------------------------------------------------------------------------
// 3. Validar las filas de ejemplo antes de escribirlas.
// ---------------------------------------------------------------------------
// Se construye el material de verdad y se valida, sin guardarlo. Hay que hacerlo
// como el usuario 1: el campo del proveedor resuelve por una vista con acceso
// por rol, y el anonimo con el que corre drush no la ve.
print "\n  --- 3. las filas de ejemplo, validadas ---\n\n";

$original = \Drupal::currentUser()->getAccount();
\Drupal::currentUser()->setAccount(User::load(1));

$todoBien = TRUE;

foreach ($ejemplos as $numero => $ejemplo) {
  $valores = ['vid' => 'tec_inventory'];

  foreach ($columnas as $c) {
    $valor = $ejemplo[$c['destino']] ?? '';
    if ($valor === '') {
      continue;
    }
    $definicion = $definiciones[$c['destino']] ?? NULL;

    // Las referencias hay que resolverlas a id, que es lo que hace Feeds.
    if ($definicion && in_array($definicion->getType(), ['entity_reference'], TRUE)) {
      $ajustes = $definicion->getSettings();
      $claveEtiqueta = $gestor->getDefinition($ajustes['target_type'])->getKey('label');
      $encontrados = \Drupal::service('feeds.entity_finder')->findEntities(
        $ajustes['target_type'],
        $claveEtiqueta,
        $valor,
        array_keys($ajustes['handler_settings']['target_bundles'] ?? [])
      );
      if (!$encontrados) {
        printf("      fila %d: \"%s\" no existe para %s (%s)\n", $numero + 1, $valor, $c['columna'], $c['destino']);
        $todoBien = FALSE;
        continue;
      }
      $valores[$c['destino']] = reset($encontrados);
      continue;
    }

    $valores[$c['destino']] = $valor;
  }

  $material = $gestor->getStorage('taxonomy_term')->create($valores);
  $violaciones = $material->validate();

  if (count($violaciones) === 0) {
    printf("      fila %d  %-34s valida\n", $numero + 1, $ejemplo['name']);
  }
  else {
    $todoBien = FALSE;
    printf("      fila %d  %-34s NO VALIDA:\n", $numero + 1, $ejemplo['name']);
    foreach ($violaciones as $violacion) {
      printf("                %s: %s\n", $violacion->getPropertyPath(), strip_tags((string) $violacion->getMessage()));
    }
  }
}

\Drupal::currentUser()->setAccount($original);

if (!$todoBien) {
  print "\n      alguna fila de ejemplo no valida. Se escribe igual, pero hay que mirarlo.\n";
}

// ---------------------------------------------------------------------------
// 4. El CSV, que es el que come el importador.
// ---------------------------------------------------------------------------
print "\n  --- 4. el CSV ---\n\n";

$carpeta = $raiz . '/' . CARPETA;
if (!is_dir($carpeta)) {
  mkdir($carpeta, 0775, TRUE);
  printf("      creada la carpeta %s\n", CARPETA);
}

$rutaCsv = $carpeta . '/plantilla-materiales.csv';
$mano = fopen($rutaCsv, 'w');

fputcsv($mano, array_column($columnas, 'columna'));
foreach ($ejemplos as $ejemplo) {
  $fila = [];
  foreach ($columnas as $c) {
    $fila[] = $ejemplo[$c['destino']] ?? '';
  }
  fputcsv($mano, $fila);
}
fclose($mano);

printf("      escrito: %s/plantilla-materiales.csv (%s bytes)\n", CARPETA, number_format(filesize($rutaCsv)));

// Se comprueba que la cabecera escrita es EXACTAMENTE la que espera el
// importador, leyendola de vuelta del fichero.
$mano = fopen($rutaCsv, 'r');
$cabecera = fgetcsv($mano);
fclose($mano);
$esperada = array_column($columnas, 'columna');
printf("      cabecera releida: %s\n", $cabecera === $esperada ? 'coincide con el importador' : 'NO COINCIDE');
if ($cabecera !== $esperada) {
  printf("        escrita:  %s\n", implode(' | ', $cabecera));
  printf("        esperada: %s\n", implode(' | ', $esperada));
}

// ---------------------------------------------------------------------------
// 5. El Excel, que es el que se rellena.
// ---------------------------------------------------------------------------
print "\n  --- 5. el Excel ---\n\n";

if (!class_exists(Spreadsheet::class)) {
  print "      phpoffice/phpspreadsheet no esta disponible: solo se genera el CSV\n\n";
  return;
}

$libro = new Spreadsheet();
$libro->getProperties()
  ->setCreator('ANV Fight Gear ERP')
  ->setTitle('Plantilla de importacion de materiales')
  ->setDescription('Columnas leidas del importador ' . IMPORTADOR . ' el ' . date('Y-m-d'));

// --- hoja 1: los materiales -------------------------------------------------
$hoja = $libro->getActiveSheet();
$hoja->setTitle('Materiales');

$rojo = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'A32020']],
  'font' => ['bold' => TRUE, 'color' => ['rgb' => 'FFFFFF']]];
$gris = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
  'font' => ['bold' => TRUE, 'color' => ['rgb' => '333333']]];

foreach ($columnas as $indice => $c) {
  $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1);

  $hoja->setCellValue($letra . '1', $c['columna']);
  $hoja->getStyle($letra . '1')->applyFromArray($c['obligatoria'] ? $rojo : $gris);
  $hoja->getStyle($letra . '1')->getAlignment()->setWrapText(TRUE)->setVertical(Alignment::VERTICAL_CENTER);

  // El comentario de la celda lleva la ayuda, para tenerla al pasar por encima.
  if ($c['ayuda']) {
    $hoja->getComment($letra . '1')->getText()->createTextRun(
      ($c['obligatoria'] ? 'OBLIGATORIA. ' : 'Opcional. ') . $c['ayuda']
    );
  }

  $hoja->getColumnDimension($letra)->setWidth(max(14, min(34, strlen($c['columna']) + 4)));
}

$hoja->getRowDimension(1)->setRowHeight(34);
$hoja->freezePane('A2');

foreach ($ejemplos as $numero => $ejemplo) {
  $fila = $numero + 2;
  foreach ($columnas as $indice => $c) {
    $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1);
    $valor = $ejemplo[$c['destino']] ?? '';
    // Los numeros como texto, para que Excel no los reescriba con coma decimal
    // segun la configuracion regional del puesto. El importador quiere punto.
    $hoja->setCellValueExplicit($letra . $fila, $valor,
      \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
  }
  $hoja->getStyle('A' . $fila . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columnas)) . $fila)
    ->applyFromArray(['font' => ['italic' => TRUE, 'color' => ['rgb' => '666666']]]);
}

// --- hoja 2: las instrucciones ----------------------------------------------
$instrucciones = $libro->createSheet();
$instrucciones->setTitle('Instrucciones');

$texto = [
  ['Plantilla de importacion de materiales - ANV Fight Gear'],
  [''],
  ['Generada el ' . date('Y-m-d H:i') . ' leyendo el importador ' . IMPORTADOR . '.'],
  ['Los nombres de columna de la hoja Materiales son los que el importador espera. No se cambian.'],
  [''],
  ['Como se usa'],
  ['1. Borrar las ' . count($ejemplos) . ' filas de ejemplo, que estan en gris y en cursiva.'],
  ['2. Rellenar una fila por material. Las ' . count($obligatorias) . ' columnas con cabecera roja son obligatorias.'],
  ['3. Guardar como CSV UTF-8, separado por comas.'],
  ['4. Subir en /feed/4/import, la ficha "Inventory Excel-CSV file".'],
  [''],
  ['Cosas que hacen fallar la importacion en silencio'],
  ['- Coma decimal. El importador quiere punto: 1850.50, no 1850,50. Con coma el numero entra vacio.'],
  ['- Espacios sobrantes en las columnas de lista. "Blue " con un espacio detras no encuentra "Blue".'],
  ['- La columna Traceable: solo 1, 0 o vacio. Escribir "No" cuenta como 1.'],
  ['- La columna Active: solo 1, 0 o vacio. Vacio deja el material activo. Escribir "Inactive" cuenta como 1.'],
  [''],
  ['Que pasa si un valor de lista no existe'],
  ['La creacion automatica de terminos esta APAGADA a proposito. Antes estaba encendida y una errata'],
  ['creaba una unidad o un color nuevo sin avisar: asi entraron seis terminos duplicados, entre ellos'],
  ['un color "Blue " con un espacio detras. Ahora la fila falla y lo dice, y se corrige en el Excel.'],
  [''],
  ['Cabe todo el catalogo de una vez'],
  ['El limite es de ' . $tipo->getParser()->getConfiguration()['line_limit'] . ' filas por pasada, o sea que los 857 materiales entran en una.'],
  [''],
  ['Quien importa no es quien pulsa el boton'],
  ['Feeds se cambia al AUTOR de la ficha de importacion antes de empezar. El autor de la ficha 4 es'],
  ['david, que es administrador y por eso ve la vista que filtra los proveedores. Si algun dia se'],
  ['cambia ese autor por alguien sin rol tec_supervisor, tec_manager, tec_executive o administrator,'],
  ['fallaran TODAS las filas con "This entity cannot be referenced" en la columna Supplier. Poner'],
  ['--user en la linea de ordenes no arregla eso, porque manda el autor guardado en la ficha.'],
  [''],
  ['Las columnas, una por una'],
];

foreach ($texto as $numero => $linea) {
  $instrucciones->setCellValue('A' . ($numero + 1), $linea[0]);
}
$instrucciones->getStyle('A1')->applyFromArray(['font' => ['bold' => TRUE, 'size' => 14]]);
foreach ([6, 12, 18, 23, 26, count($texto)] as $fila) {
  $instrucciones->getStyle('A' . $fila)->applyFromArray(['font' => ['bold' => TRUE]]);
}

$fila = count($texto) + 2;
foreach ([['Columna', 'Obligatoria', 'Campo del material', 'Que es']] as $cabecera) {
  foreach ($cabecera as $indice => $celda) {
    $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1);
    $instrucciones->setCellValue($letra . $fila, $celda);
    $instrucciones->getStyle($letra . $fila)->applyFromArray($gris);
  }
}
$fila++;

foreach ($columnas as $c) {
  $instrucciones->setCellValue('A' . $fila, $c['columna']);
  $instrucciones->setCellValue('B' . $fila, $c['obligatoria'] ? 'SI' : '');
  $instrucciones->setCellValue('C' . $fila, $c['destino']);
  $instrucciones->setCellValue('D' . $fila, $c['ayuda']);
  if ($c['obligatoria']) {
    $instrucciones->getStyle('A' . $fila . ':B' . $fila)->applyFromArray(['font' => ['bold' => TRUE]]);
  }
  $fila++;
}

$instrucciones->getColumnDimension('A')->setWidth(34);
$instrucciones->getColumnDimension('B')->setWidth(12);
$instrucciones->getColumnDimension('C')->setWidth(32);
$instrucciones->getColumnDimension('D')->setWidth(96);
$instrucciones->getStyle('D1:D' . $fila)->getAlignment()->setWrapText(TRUE);

// --- hojas 3 y siguientes: las listas cerradas ------------------------------
foreach ($listas as $titulo => $valores) {
  $lista = $libro->createSheet();
  $lista->setTitle($titulo);
  $lista->setCellValue('A1', $titulo . ' que existen hoy (' . count($valores) . ')');
  $lista->getStyle('A1')->applyFromArray($gris);
  foreach ($valores as $indice => $valor) {
    $lista->setCellValueExplicit('A' . ($indice + 2), $valor,
      \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
  }
  $lista->getColumnDimension('A')->setWidth(34);
  $lista->getStyle('A1:A' . (count($valores) + 1))
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

$libro->setActiveSheetIndex(0);

$rutaXlsx = $carpeta . '/plantilla-materiales.xlsx';
(new Xlsx($libro))->save($rutaXlsx);

printf("      escrito: %s/plantilla-materiales.xlsx (%s bytes)\n", CARPETA, number_format(filesize($rutaXlsx)));
printf("      hojas: %s\n", implode(', ', $libro->getSheetNames()));

print "\n" . str_repeat('=', 82) . "\n";
printf(" HECHO: %d columnas (%d obligatorias), %d filas de ejemplo\n",
  count($columnas), count($obligatorias), count($ejemplos));
print str_repeat('=', 82) . "\n\n";
printf("   %s/plantilla-materiales.csv    <- el que come el importador\n", CARPETA);
printf("   %s/plantilla-materiales.xlsx   <- el que se rellena\n\n", CARPETA);
