<?php

/**
 * @file
 * Retira del ERP los ultimos restos de tec_app_link.
 *
 * Uso: php vendor/bin/drush.php scr scripts/quitar-a-app-link-del-erp.php
 *      php vendor/bin/drush.php scr scripts/quitar-a-app-link-del-erp.php -- --de-verdad
 *
 * Que era tec_app_link: un tipo de entidad de ECK con seis subtipos, uno por
 * cada enlace a redes sociales de la portada (Facebook, Instagram, LINE,
 * WhatsApp, X y telefono). Nunca llego a tener una sola ficha dentro.
 *
 * Este guion es el hermano pequeno de quitar-a-gui-del-erp.php, y pequeno de
 * verdad: el grueso de tec_app_link ya se fue el 14 de agosto con
 * borrar-lo-que-no-usa-nadie.php, que borro los seis subtipos, el tipo de
 * entidad y sus dos tablas. Esto es la cola que quedo.
 *
 * El apunte de deuda tecnica decia "sin tablas, pero con permisos sueltos por
 * los roles". La mitad estaba bien y la mitad ya no: no hay tablas, correcto,
 * pero permisos tampoco queda ninguno. Se fueron solos el 15 de agosto, cuando
 * la limpieza de tec_gui guardo los tres roles: un rol no puede guardar un
 * permiso que el sitio ya no declara, asi que Drupal los retiro por su cuenta y
 * lo aviso. El apunte se escribio antes de ese guardado y nadie lo actualizo.
 *
 * Lo que si queda, y no estaba apuntado, son dos cosas:
 *
 *   1. Ocho entradas en `cancel_button.settings`, no una: las seis parejas
 *      tipo-subtipo, el tipo suelto y el tipo de subtipo `tec_app_link_type`.
 *   2. La definicion instalada de `tec_app_link_type` en el almacen de claves.
 *      ECK deriva por cada tipo de entidad un tipo de configuracion aparte para
 *      sus subtipos, y al borrar tec_app_link se retiro la definicion del tipo
 *      pero no la del subtipo. Se ve comparando con los cinco tipos de ECK
 *      vivos, que guardan las dos: a este le falta una y le sobra la otra.
 *
 * Por que esa segunda no salia en el informe de estado, al reves que en
 * tec_gui: porque getChangeList() del nucleo solo recorre los tipos de entidad
 * que existen hoy, y este ya no existe, asi que nunca lo compara con nada. El
 * fantasma solo asoma por getEntityTypes(), que devuelve todas las instaladas.
 * O sea que no pinta de rojo nada, pero esta ahi, y el dia que alguien recorra
 * las definiciones instaladas se lo va a encontrar sin saber que es.
 *
 * El orden aqui casi da igual, y conviene decir por que, porque en el hermano
 * mayor no era negociable: alli habia banderas, un campo obligatorio y trece
 * tablas que se estorbaban entre si. Aqui no hay datos, ni tablas, ni campos,
 * ni nada que apunte a nada. Lo unico que va primero de verdad es la copia.
 */

use Drupal\Component\Serialization\Yaml;

$argumentos = (array) ($extra ?? []);
$deVerdad = in_array('de-verdad', $argumentos, TRUE) || in_array('--de-verdad', $argumentos, TRUE);

const AGUJA = 'tec_app_link';
const SUBTIPO = 'tec_app_link_type';
const COPIA = 'backups/app-link-antes-de-quitarlo-2026-08-15.yml';

echo "\n";
echo str_repeat('=', 82) . "\n";
echo $deVerdad ? " DE VERDAD: se borra\n" : " ENSAYO: solo se cuenta lo que haria. Anade -- --de-verdad para hacerlo\n";
echo str_repeat('=', 82) . "\n";

$bd = \Drupal::database();
$gestorTipos = \Drupal::entityTypeManager();
$fabricaConfig = \Drupal::configFactory();
$almacenClaves = \Drupal::keyValue('entity.definitions.installed');

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

// 1. El tipo de entidad tiene que estar ya fuera. Si el sitio todavia lo
// conoce, esto no es una cola: es un tipo vivo y el guion no es el que toca.
if ($gestorTipos->hasDefinition(AGUJA)) {
  $abortar[] = 'el sitio todavia conoce el tipo de entidad ' . AGUJA;
}
else {
  echo "    el tipo de entidad ya no existe: correcto\n";
}

if ($fabricaConfig->get('eck.eck_entity_type.' . AGUJA)->getRawData()) {
  $abortar[] = 'sigue la configuracion eck.eck_entity_type.' . AGUJA;
}
if ($subtipos = $fabricaConfig->listAll('eck.eck_type.' . AGUJA . '.')) {
  $abortar[] = sprintf('quedan %d subtipos: %s', count($subtipos), implode(', ', $subtipos));
}
else {
  echo "    ningun subtipo en la configuracion: correcto\n";
}

// 2. Cero tablas. Si apareciera una con filas dentro habria contenido de
// verdad y esto dejaria de ser una limpieza.
$tablas = $bd->query("SHOW TABLES LIKE '%app\_link%'")->fetchCol();
foreach ($tablas as $tabla) {
  $n = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
  $abortar[] = sprintf('la tabla %s existe todavia y tiene %d filas', $tabla, $n);
}
if (!$tablas) {
  echo "    ninguna tabla con app_link en el nombre: correcto\n";
}

// 3. Ningun campo suyo y, sobre todo, ningun campo de OTRA entidad que le
// apunte. Este es el guardian que importa: en tec_gui lo caro no fueron las
// tablas sino un campo OBLIGATORIO de las lineas de pedido apuntando al tipo
// muerto. Aqui se mira lo mismo antes de dar nada por abandonado.
$camposTocados = [];
foreach ($gestorTipos->getStorage('field_storage_config')->loadMultiple() as $almacen) {
  if ($almacen->getTargetEntityTypeId() === AGUJA) {
    $camposTocados[] = 'almacen de ' . AGUJA . ': ' . $almacen->id();
  }
  if ((string) $almacen->getSetting('target_type') === AGUJA) {
    $camposTocados[] = 'almacen que apunta a ' . AGUJA . ': ' . $almacen->id();
  }
}
foreach ($gestorTipos->getStorage('field_config')->loadMultiple() as $instancia) {
  if ($instancia->getTargetEntityTypeId() === AGUJA) {
    $camposTocados[] = 'campo de ' . AGUJA . ': ' . $instancia->id();
  }
  $ajustes = $instancia->getSettings();
  $texto = (string) ($ajustes['handler'] ?? '') . ' ' . (string) ($ajustes['target_type'] ?? '')
    . ' ' . implode(' ', array_keys((array) ($ajustes['handler_settings']['target_bundles'] ?? [])));
  if (str_contains($texto, AGUJA)) {
    $camposTocados[] = 'campo que apunta a ' . AGUJA . ': ' . $instancia->id()
      . ($instancia->isRequired() ? ' (OBLIGATORIO)' : '');
  }
}
if ($camposTocados) {
  $abortar = array_merge($abortar, $camposTocados);
}
else {
  echo "    ningun campo suyo y ningun campo de otra entidad que le apunte: correcto\n";
}

// 4. Nada de vistas, pantallas, banderas, marcas, menus ni rutas.
$colgando = [];
foreach ($fabricaConfig->listAll('views.view.') as $nombre) {
  $vista = $fabricaConfig->get($nombre)->getRawData();
  if (str_contains((string) ($vista['base_table'] ?? ''), AGUJA)) {
    $colgando[] = 'vista ' . $vista['id'];
  }
}
foreach (['entity_view_display', 'entity_form_display', 'entity_view_mode', 'entity_form_mode', 'flag'] as $tipo) {
  if (!$gestorTipos->hasDefinition($tipo)) {
    continue;
  }
  foreach ($gestorTipos->getStorage($tipo)->loadMultiple() as $entidad) {
    if (str_contains((string) $entidad->id(), AGUJA)) {
      $colgando[] = $tipo . ' ' . $entidad->id();
    }
  }
}
if ($bd->schema()->tableExists('flagging')) {
  $marcas = (int) $bd->query("SELECT COUNT(*) FROM {flagging} WHERE entity_type LIKE :t", [':t' => '%app_link%'])->fetchField();
  if ($marcas > 0) {
    $colgando[] = sprintf('%d marcas (flagging) sobre %s', $marcas, AGUJA);
  }
}
foreach (\Drupal::service('router.route_provider')->getAllRoutes() as $nombre => $ruta) {
  if (str_contains($nombre, 'app_link') || str_contains($ruta->getPath(), 'app_link')) {
    $colgando[] = 'ruta ' . $nombre;
  }
}
if ($colgando) {
  $abortar = array_merge($abortar, $colgando);
}
else {
  echo "    ni vistas, ni pantallas, ni banderas, ni marcas, ni rutas: correcto\n";
}

// 5. La definicion instalada que se va a retirar tiene que ser la que se
// comprobo: el tipo de subtipo de tec_app_link y de nada mas. Si el objeto
// guardado dijera que es subtipo de otra cosa, no se toca.
$definicion = $almacenClaves->get(SUBTIPO . '.entity_type');
if ($definicion === NULL) {
  echo "    la definicion instalada de " . SUBTIPO . " ya no esta\n";
}
elseif (!$definicion instanceof \Drupal\Core\Entity\EntityTypeInterface) {
  $abortar[] = 'la definicion instalada de ' . SUBTIPO . ' no es un tipo de entidad';
}
elseif ((string) $definicion->getBundleOf() !== AGUJA) {
  $abortar[] = sprintf('la definicion instalada de %s dice ser subtipo de "%s" y no de %s',
    SUBTIPO, (string) $definicion->getBundleOf(), AGUJA);
}
elseif ($gestorTipos->hasDefinition(SUBTIPO)) {
  $abortar[] = 'el sitio todavia conoce ' . SUBTIPO . ', no es un fantasma';
}
else {
  echo "    la definicion instalada de " . SUBTIPO . " es huerfana: correcto\n";
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
// 1. La copia de lo que se va a borrar, antes de tocar nada.
// ---------------------------------------------------------------------------
echo "\n  --- 1. copia de seguridad ---\n\n";

$destinos = (array) $fabricaConfig->get('cancel_button.settings')->get('entity_type_cancel_destination');
$sobran = array_values(array_filter(array_keys($destinos), static function (string $clave): bool {
  return $clave === AGUJA || str_starts_with($clave, AGUJA . '_');
}));

$guardar = [
  'que_es' => 'restos de ' . AGUJA . ' retirados por scripts/quitar-a-app-link-del-erp.php',
  'cuando' => date('c'),
  'cancel_button.settings' => [
    'entity_type_cancel_destination' => array_intersect_key($destinos, array_flip($sobran)),
  ],
  'entity.definitions.installed' => [
    SUBTIPO . '.entity_type' => $definicion ? serialize($definicion) : NULL,
  ],
];

if ($deVerdad) {
  file_put_contents(COPIA, Yaml::encode($guardar));
  $anotar('copia guardada en ' . COPIA);
}
else {
  $anotar('guardaria la copia en ' . COPIA);
}

// ---------------------------------------------------------------------------
// 2. Las ocho entradas del boton de cancelar.
// ---------------------------------------------------------------------------
echo "\n  --- 2. boton de cancelar ---\n\n";

if (!$sobran) {
  echo "    El boton de cancelar ya esta limpio.\n";
}
else {
  foreach ($sobran as $clave) {
    echo '        ' . $clave . "\n";
    unset($destinos[$clave]);
  }
  if ($deVerdad) {
    $fabricaConfig->getEditable('cancel_button.settings')
      ->set('entity_type_cancel_destination', $destinos)
      ->save();
    $anotar(sprintf('boton de cancelar: %d entradas fuera', count($sobran)));
  }
  else {
    $anotar(sprintf('quitaria %d entradas del boton de cancelar', count($sobran)));
  }
}

// ---------------------------------------------------------------------------
// 3. La definicion instalada del tipo de subtipo.
// ---------------------------------------------------------------------------
echo "\n  --- 3. la definicion instalada huerfana ---\n\n";

if ($definicion === NULL) {
  echo "    Ya no estaba.\n";
}
elseif ($deVerdad) {
  // Se va por la API del nucleo y no borrando la fila a mano, porque ademas de
  // la entrada hay que tirar la de los campos y las dos cacheadas. Si se borra
  // solo la fila, la copia en cache sigue devolviendo el fantasma.
  \Drupal::service('entity.last_installed_schema.repository')->deleteLastInstalledDefinition(SUBTIPO);
  $anotar('definicion instalada de ' . SUBTIPO . ' retirada, con su cache');
}
else {
  $anotar('retiraria la definicion instalada de ' . SUBTIPO . ', con su cache');
}

// ---------------------------------------------------------------------------
// 4. Repaso: que no quede nada.
// ---------------------------------------------------------------------------
echo "\n  --- 4. repaso ---\n\n";

$queda = [];

foreach ($fabricaConfig->listAll() as $nombre) {
  $texto = Yaml::encode($fabricaConfig->get($nombre)->getRawData());
  if (str_contains($texto, AGUJA) || str_contains($nombre, AGUJA)) {
    $queda[] = 'configuracion ' . $nombre;
  }
}
foreach (array_keys($almacenClaves->getAll()) as $clave) {
  if (str_contains($clave, 'app_link')) {
    $queda[] = 'definicion instalada ' . $clave;
  }
}
foreach ($bd->query("SHOW TABLES LIKE '%app\_link%'")->fetchCol() as $tabla) {
  $queda[] = 'tabla ' . $tabla;
}

if (!$queda) {
  echo "    No queda ninguna mencion a " . AGUJA . " en el ERP.\n";
}
else {
  foreach ($queda as $linea) {
    echo '    ' . ($deVerdad ? 'TODAVIA QUEDA: ' : 'quedaria: ') . $linea . "\n";
  }
}

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 82) . "\n";
printf(" %s: %d cosas\n", $deVerdad ? 'HECHO' : 'SE HARIA', count($hechos));
echo str_repeat('=', 82) . "\n\n";

if ($deVerdad) {
  echo "  Cambia una sola clave de configuracion: cancel_button.settings.\n";
  echo "  Queda: drush cr, y pasar cargan-las-paginas.php y comprobacion.php.\n\n";
}
