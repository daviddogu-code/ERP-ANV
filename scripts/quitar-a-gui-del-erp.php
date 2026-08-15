<?php

/**
 * @file
 * Retira del ERP el tipo de entidad tec_gui y todo lo que cuelga de el.
 *
 * Uso: drush php:script scripts/quitar-a-gui-del-erp
 *      drush php:script scripts/quitar-a-gui-del-erp -- de-verdad
 *
 * Que es tec_gui: el producto original del programador anterior, sustituido en
 * su dia por tec_product. Sus trece tablas siguen en la base, vacias, y el tipo
 * de entidad no tiene ni un subtipo, asi que no se puede crear una ficha ni
 * queriendo. Lo caro era otra cosa: field_tec_gui_product es un campo
 * OBLIGATORIO de las lineas de pedido de venta, la pieza mas usada del ERP. Se
 * hace ahora porque con la tabla practicamente vacia sale gratis.
 *
 * El orden no es negociable:
 *   - Las banderas y el modo de vista se van antes que el tipo de entidad,
 *     porque lo declaran como su entidad y quedarian apuntando al vacio.
 *   - La tabla del campo se vacia antes de borrar el campo. Con datos dentro el
 *     nucleo renombra la tabla y la deja en la cola de purga; vacia, la tira en
 *     el acto. Las 22 filas son basura: 21 no tienen ni linea ni ficha GUI, y la
 *     que queda la escribio el guion de datos de prueba para poder satisfacer un
 *     campo obligatorio muerto.
 *   - El tipo de entidad va al final: ECK, al borrarlo, busca todos los campos
 *     de referencia que apuntan a el, los borra, y desinstala el tipo.
 *
 * Las tablas tec_gui__field_* no se van con la desinstalacion, porque sus
 * definiciones de campo ya se habian borrado de la configuracion hace anos y las
 * tablas sobrevivieron. Esas hay que tirarlas a mano, y por eso el ultimo paso
 * repasa lo que quede.
 */

use Drupal\Core\Site\Settings;
use Drupal\eck\Entity\EckEntityType;
use Drupal\flag\Entity\Flag;
use Drupal\user\Entity\Role;

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad ? " DE VERDAD: se borra\n" : " ENSAYO: solo se cuenta lo que haria. Anade -- de-verdad para hacerlo\n";
echo str_repeat('=', 82) . "\n";

$bd = \Drupal::database();
$esquema = $bd->schema();
$gestorTipos = \Drupal::entityTypeManager();
$fabricaConfig = \Drupal::configFactory();

$hechos = [];
$anotar = function (string $linea) use (&$hechos): void {
  $hechos[] = $linea;
  echo '    ' . $linea . "\n";
};

// ---------------------------------------------------------------------------
// Guardias. Si algo no esta como se comprobo, no se sigue.
// ---------------------------------------------------------------------------
echo "\n  --- guardias ---\n\n";

$abortar = [];

$subtipos = \Drupal::service('entity_type.bundle.info')->getBundleInfo('tec_gui');
if (count($subtipos) > 0) {
  $abortar[] = sprintf('tec_gui tiene %d subtipos definidos, no esta abandonado', count($subtipos));
}
else {
  echo "    tec_gui no tiene ningun subtipo: correcto\n";
}

try {
  $fichas = (int) $gestorTipos->getStorage('tec_gui')->getQuery()->accessCheck(FALSE)->count()->execute();
}
catch (\Throwable $e) {
  $fichas = -1;
}
if ($fichas > 0) {
  $abortar[] = sprintf('hay %d fichas GUI dentro', $fichas);
}
else {
  echo "    cero fichas GUI: correcto\n";
}

$tablasGui = $bd->query("SHOW TABLES LIKE 'tec_gui%'")->fetchCol();
foreach ($tablasGui as $tabla) {
  $cuantas = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  if ($cuantas > 0) {
    $abortar[] = sprintf('la tabla %s tiene %d filas', $tabla, $cuantas);
  }
}
echo sprintf("    %d tablas tec_gui%%, todas vacias: correcto\n", count($tablasGui));

// Ninguna de las filas del campo puede apuntar a una ficha GUI que exista, o
// seria dato de verdad y no basura.
$tablaCampo = 'tec_line_item__field_tec_gui_product';
if ($esquema->tableExists($tablaCampo)) {
  $vivas = (int) $bd->query("
    SELECT COUNT(*)
    FROM {$tablaCampo} f
    INNER JOIN tec_gui g ON g.id = f.field_tec_gui_product_target_id
  ")->fetchField();
  if ($vivas > 0) {
    $abortar[] = sprintf('%d filas del campo apuntan a fichas GUI que existen', $vivas);
  }
  else {
    echo "    ninguna fila del campo apunta a una ficha GUI viva: correcto\n";
  }
}

// Y ninguna vista puede apoyarse en tec_gui, o se quedaria sin tabla base.
$vistasTocadas = [];
foreach ($fabricaConfig->listAll('views.view.') as $nombre) {
  $vista = $fabricaConfig->get($nombre)->getRawData();
  if (in_array($vista['base_table'] ?? '', ['tec_gui', 'tec_gui_field_data'], TRUE)) {
    $vistasTocadas[] = $vista['id'];
  }
}
if ($vistasTocadas) {
  $abortar[] = 'vistas apoyadas en tec_gui: ' . implode(', ', $vistasTocadas);
}
else {
  echo "    ninguna vista se apoya en tec_gui: correcto\n";
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
// 1. Las marcas huerfanas de las dos banderas.
// ---------------------------------------------------------------------------
echo "\n  --- 1. marcas puestas de las dos banderas ---\n\n";

$banderas = ['tec_duplicate_gui', 'tec_eca_gui_lock'];
$marcas = (int) $bd->select('flagging')
  ->condition('flag_id', $banderas, 'IN')
  ->countQuery()
  ->execute()
  ->fetchField();

if ($marcas === 0) {
  echo "    Ninguna.\n";
}
elseif ($deVerdad) {
  // Se van por la base y no por la API de entidades a proposito: son marcas
  // sobre fichas GUI que no existen, y cualquier gancho que intente cargar la
  // ficha marcada se atragantaria.
  $borradas = $bd->delete('flagging')->condition('flag_id', $banderas, 'IN')->execute();
  $anotar(sprintf('%d marcas huerfanas borradas', $borradas));
}
else {
  $anotar(sprintf('borraria %d marcas huerfanas', $marcas));
}

// ---------------------------------------------------------------------------
// 2. Los permisos de los roles.
// ---------------------------------------------------------------------------
echo "\n  --- 2. permisos ---\n\n";

// La vista tec_gui_bom_item_viewfields lleva "gui" en el nombre y NO es de
// esto: la usan dos campos vivos de los despieces. Queda excluida a mano.
$esDeGui = function (string $permiso): bool {
  if (str_contains($permiso, 'bom_item_viewfields')) {
    return FALSE;
  }
  return str_contains($permiso, 'tec_gui')
    || str_contains($permiso, 'tec_duplicate_gui')
    || str_contains($permiso, 'tec_eca_gui_lock');
};

$totalPermisos = 0;
foreach (Role::loadMultiple() as $rol) {
  $sobran = array_values(array_filter($rol->getPermissions(), $esDeGui));
  if (!$sobran) {
    continue;
  }
  $totalPermisos += count($sobran);
  $anotar(sprintf('%s: %d permisos', $rol->id(), count($sobran)));
  foreach ($sobran as $permiso) {
    echo '        ' . $permiso . "\n";
    if ($deVerdad) {
      $rol->revokePermission($permiso);
    }
  }
  if ($deVerdad) {
    $rol->save();
  }
}
if ($totalPermisos === 0) {
  echo "    Ninguno.\n";
}

// ---------------------------------------------------------------------------
// 3. Banderas, selector, modo de vista.
// ---------------------------------------------------------------------------
echo "\n  --- 3. banderas, selector y modo de vista ---\n\n";

foreach ($banderas as $id) {
  $bandera = Flag::load($id);
  if (!$bandera) {
    continue;
  }
  // El modulo de banderas borra solo sus dos acciones al borrar la bandera.
  if ($deVerdad) {
    $bandera->delete();
    $anotar(sprintf('bandera %s borrada, con sus dos acciones', $id));
  }
  else {
    $anotar(sprintf('borraria la bandera %s y sus dos acciones', $id));
  }
}

$sueltos = [
  'entity_browser' => 'tec_gui_products',
  'entity_view_mode' => 'tec_gui.ief_table',
];
foreach ($sueltos as $tipo => $id) {
  $entidad = $gestorTipos->getStorage($tipo)->load($id);
  if (!$entidad) {
    continue;
  }
  if ($deVerdad) {
    $entidad->delete();
    $anotar(sprintf('%s %s borrado', $tipo, $id));
  }
  else {
    $anotar(sprintf('borraria %s %s', $tipo, $id));
  }
}

// ---------------------------------------------------------------------------
// 4. Vaciar la tabla del campo antes de que el nucleo la mire.
// ---------------------------------------------------------------------------
echo "\n  --- 4. la tabla del campo obligatorio ---\n\n";

if ($esquema->tableExists($tablaCampo)) {
  $cuantas = (int) $bd->select($tablaCampo)->countQuery()->execute()->fetchField();
  if ($deVerdad) {
    $bd->truncate($tablaCampo)->execute();
    $anotar(sprintf('%s vaciada: %d filas de basura fuera', $tablaCampo, $cuantas));
  }
  else {
    $anotar(sprintf('vaciaria %s, %d filas', $tablaCampo, $cuantas));
  }
}
else {
  echo "    No existe.\n";
}

// ---------------------------------------------------------------------------
// 5. El tipo de entidad. ECK arrastra los campos y desinstala.
// ---------------------------------------------------------------------------
echo "\n  --- 5. el tipo de entidad ---\n\n";

$camposQueApuntan = EckEntityType::loadReferenceFieldsByType('tec_gui');
foreach ($camposQueApuntan as $campo) {
  $anotar(sprintf(
    'ECK se llevara %s.%s.%s%s',
    $campo->getTargetEntityTypeId(),
    $campo->getTargetBundle(),
    $campo->getName(),
    $campo->isRequired() ? ' (obligatorio)' : ''
  ));
}

$tipo = EckEntityType::load('tec_gui');
if (!$tipo) {
  echo "    Ya no esta.\n";
}
elseif ($deVerdad) {
  $tipo->delete();
  $anotar('tipo de entidad tec_gui borrado y desinstalado');
}
else {
  $anotar('borraria el tipo de entidad tec_gui');
}

// ---------------------------------------------------------------------------
// 6. Lo que la desinstalacion no se lleva: las tablas de campos ya purgados.
// ---------------------------------------------------------------------------
echo "\n  --- 6. tablas que queden ---\n\n";

$restantes = $bd->query("SHOW TABLES LIKE 'tec_gui%'")->fetchCol();
if (!$restantes) {
  echo "    Ninguna.\n";
}
foreach ($restantes as $tabla) {
  $cuantas = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  if ($cuantas > 0) {
    $anotar(sprintf('OJO: %s tiene %d filas, no se toca', $tabla, $cuantas));
    continue;
  }
  if ($deVerdad) {
    $esquema->dropTable($tabla);
    $anotar(sprintf('%s tirada', $tabla));
  }
  else {
    $anotar(sprintf('tiraria %s', $tabla));
  }
}

// ---------------------------------------------------------------------------
// 7. Las dos lineas sueltas de configuracion.
// ---------------------------------------------------------------------------
echo "\n  --- 7. service worker y boton de cancelar ---\n\n";

$sw = $fabricaConfig->getEditable('pwa_service_worker.config');
$excluidas = (string) $sw->get('urls_to_exclude');
$lineas = preg_split('/\r\n|\r|\n/', $excluidas);
$limpias = array_values(array_filter($lineas, static fn(string $linea): bool => !str_contains($linea, 'tec_gui/')));
if (count($limpias) !== count($lineas)) {
  if ($deVerdad) {
    $sw->set('urls_to_exclude', implode("\r\n", $limpias))->save();
    $anotar('service worker: fuera la linea de tec_gui');
  }
  else {
    $anotar('quitaria del service worker la linea de tec_gui');
  }
}
else {
  echo "    El service worker ya esta limpio.\n";
}

$cancelar = $fabricaConfig->getEditable('cancel_button.settings');
$destinos = (array) $cancelar->get('entity_type_cancel_destination');
$sobran = array_values(array_filter(array_keys($destinos), static function (string $clave): bool {
  return $clave === 'tec_gui'
    || str_starts_with($clave, 'tec_gui_')
    || $clave === 'flagging_tec_duplicate_gui'
    || $clave === 'flagging_tec_eca_gui_lock';
}));
if ($sobran) {
  foreach ($sobran as $clave) {
    echo '        ' . $clave . "\n";
    unset($destinos[$clave]);
  }
  if ($deVerdad) {
    $cancelar->set('entity_type_cancel_destination', $destinos)->save();
    $anotar(sprintf('boton de cancelar: %d entradas fuera', count($sobran)));
  }
  else {
    $anotar(sprintf('quitaria %d entradas del boton de cancelar', count($sobran)));
  }
}
else {
  echo "    El boton de cancelar ya esta limpio.\n";
}

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 82) . "\n";
printf(" %s: %d cosas\n", $deVerdad ? 'HECHO' : 'SE HARIA', count($hechos));
echo str_repeat('=', 82) . "\n\n";

if ($deVerdad) {
  echo "  Queda: drush cr, drush config:export, y pasar comprobacion.php.\n\n";
}
