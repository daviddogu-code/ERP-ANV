<?php

/**
 * @file
 * Si el formulario de material nuevo abre con el proveedor de la direccion puesto.
 *
 *   php vendor\bin\drush.php scr scripts/abre-el-formulario-de-material-con-el-proveedor.php
 *   php vendor\bin\drush.php scr scripts/abre-el-formulario-de-material-con-el-proveedor.php -- 31
 *
 * El boton 'Add new material' de la pestana del proveedor abre el formulario de
 * material con ?target_id=<proveedor> en la direccion, y el modulo epp lee ese
 * numero y lo pone en el campo de proveedor antes de dibujar el formulario.
 *
 * Copiar el ajuste de epp de un campo a otro no basta para dar por hecho que
 * funciona, por dos motivos:
 *
 *   - epp valida lo que pone y, si el valor no pasa la validacion, lo quita sin
 *     decir nada en pantalla: solo deja una nota en el registro. field_tec_vendor
 *     tiene manejador de tipo views, que solo admite las fichas que devuelve la
 *     vista tec_crm_references, asi que hay que comprobar que el proveedor sale
 *     en esa lista.
 *   - el widget es select2, y lo que importa es que el valor llegue a la casilla
 *     que ve el usuario, no solo a la configuracion del campo.
 *
 * Por eso este guion pide la pagina de verdad por dentro del nucleo, con el
 * parametro en la direccion, y mira el HTML que sale.
 *
 * No cambia nada.
 */

use Drupal\Core\Form\FormState;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const RUTA_DE_ANADIR = '/admin/structure/taxonomy/manage/tec_inventory/add';
const CAMPO_BUENO = 'field_tec_vendor';

$bd = \Drupal::database();
\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

$proveedor = (string) (($extra[0] ?? '') !== ''
  ? $extra[0]
  : $bd->select('taxonomy_term__field_tec_vendor', 'v')
    ->fields('v', ['field_tec_vendor_target_id'])
    ->range(0, 1)
    ->execute()
    ->fetchField());

if ($proveedor === '') {
  echo "\n  Ningun material tiene proveedor y no se ha dado ninguno. Nada que probar.\n\n";
  return;
}

$ficha = \Drupal::entityTypeManager()->getStorage('tec_crm')->load($proveedor);
$titulo('Se prueba con el proveedor ' . $proveedor);
printf("  se llama: %s\n", $ficha ? $ficha->label() : '(ya no existe)');

$titulo('Quien tiene el ajuste de epp');
foreach (['field_tec_suppliers', CAMPO_BUENO] as $nombre) {
  $campo = \Drupal::entityTypeManager()->getStorage('field_config')->load('taxonomy_term.tec_inventory.' . $nombre);
  if (!$campo) {
    printf("  %-24s ya no existe\n", $nombre);
    continue;
  }
  $valor = $campo->getThirdPartySetting('epp', 'value', '');
  printf(
    "  %-24s %s\n",
    $nombre,
    $valor !== '' ? 'se rellena con ' . $valor : 'no se rellena solo'
  );
}

// El manejador del campo bueno es de tipo views: solo admite lo que devuelve la
// vista. Si el proveedor no sale ahi, epp pone el valor, la validacion lo tumba
// y epp lo quita. Es la trampa principal de este cambio.
$titulo('Admite el manejador del campo bueno a este proveedor');
$campoBueno = \Drupal::entityTypeManager()->getStorage('field_config')->load('taxonomy_term.tec_inventory.' . CAMPO_BUENO);
if (!$campoBueno) {
  echo "  el campo no existe\n";
}
else {
  $ajustes = $campoBueno->getSetting('handler_settings') ?? [];
  printf("  manejador: %s\n", $campoBueno->getSetting('handler'));
  if (isset($ajustes['view'])) {
    printf("  vista:     %s / %s\n", $ajustes['view']['view_name'], $ajustes['view']['display_name']);
  }
  $manejador = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($campoBueno);
  $admitidos = $manejador->validateReferenceableEntities([$proveedor]);
  printf(
    "  admite el %s: %s\n",
    $proveedor,
    in_array($proveedor, array_map('strval', $admitidos), TRUE) ? 'SI' : 'NO, epp lo quitaria'
  );
}

// El manejador de tipo views ejecuta una vista, y una vista puede filtrar por
// quien la mira. El boton no lo usa el administrador, lo usa la fabrica, asi que
// hay que ver si el manejador admite al proveedor tambien para esas cuentas: si
// no lo admitiese, epp pondria el valor, la validacion lo tumbaria y el
// formulario saldria vacio solo para ellos.
$titulo('Y lo admite para las cuentas que usan el boton');
if ($campoBueno) {
  $antes = \Drupal::currentUser()->getAccount();
  $cuentas = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['status' => 1]);
  $probadas = [];
  foreach ($cuentas as $cuenta) {
    if (!$cuenta->hasPermission('create terms in tec_inventory')) {
      continue;
    }
    $papeles = implode(',', $cuenta->getRoles());
    if (isset($probadas[$papeles])) {
      continue;
    }
    $probadas[$papeles] = TRUE;
    \Drupal::currentUser()->setAccount($cuenta);
    $manejador = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($campoBueno);
    $admitidos = array_map('strval', $manejador->validateReferenceableEntities([$proveedor]));
    printf(
      "  %-22s %-34s %s\n",
      substr($cuenta->getAccountName(), 0, 22),
      substr($papeles, 0, 34),
      in_array($proveedor, $admitidos, TRUE) ? 'lo admite' : 'NO lo admite'
    );
  }
  if (!$probadas) {
    echo "  ninguna cuenta activa puede crear materiales\n";
  }
  \Drupal::currentUser()->setAccount($antes);
}

// Lo que ve el usuario. Se pide la pagina por dentro del nucleo con el parametro
// puesto, porque el token [current-page:query:target_id] lo lee de la peticion.
\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));
$titulo('La pagina de anadir material con ?target_id=' . $proveedor);
$peticion = Request::create(RUTA_DE_ANADIR, 'GET', ['target_id' => $proveedor]);
try {
  $peticion->setSession(\Drupal::service('session'));
}
catch (\Throwable $e) {
  // Sin sesion tambien vale para una peticion interna.
}

$html = '';
try {
  $respuesta = \Drupal::service('http_kernel')->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  printf("  codigo: %d\n", $respuesta->getStatusCode());
  $html = (string) $respuesta->getContent();
}
catch (\Throwable $e) {
  printf("  EXCEPCION: %s: %s\n", get_class($e), $e->getMessage());
}

if ($html !== '') {
  // El widget select2 se dibuja como un select normal con la opcion marcada.
  if (preg_match('~<select[^>]*name="' . CAMPO_BUENO . '[^"]*"[^>]*>(.*?)</select>~s', $html, $bloque)) {
    $marcadas = [];
    if (preg_match_all('~<option[^>]*value="([^"]*)"[^>]*selected[^>]*>([^<]*)</option>~', $bloque[1], $todas, PREG_SET_ORDER)) {
      foreach ($todas as $una) {
        $marcadas[] = trim($una[1]) . ' (' . trim(html_entity_decode($una[2])) . ')';
      }
    }
    $cuantas = preg_match_all('~<option~', $bloque[1]);
    printf("  el widget de %s sale con %d opciones\n", CAMPO_BUENO, $cuantas);
    printf(
      "  opcion marcada: %s\n",
      $marcadas ? implode(', ', $marcadas) : 'ninguna'
    );
    $bien = FALSE;
    foreach ($marcadas as $marcada) {
      if (str_starts_with($marcada, $proveedor . ' ')) {
        $bien = TRUE;
      }
    }
    printf("  abre con el proveedor %s puesto: %s\n", $proveedor, $bien ? 'SI' : 'NO');
  }
  else {
    printf("  no sale ningun select de %s en la pagina\n", CAMPO_BUENO);
  }
}

// Ademas del HTML, el valor que el formulario lleva por dentro. Si aqui hay
// valor y en el HTML no, el problema es del widget; si no hay valor en ninguno,
// el problema es de epp o del token.
$titulo('El valor que lleva el formulario por dentro');
\Drupal::requestStack()->push($peticion);
try {
  $termino = Term::create(['vid' => 'tec_inventory']);
  $formulario = \Drupal::service('entity.form_builder')->getForm($termino, 'default', ['form_state' => new FormState()]);
  $porDentro = $formulario[CAMPO_BUENO]['widget']['#default_value'] ?? NULL;
  printf(
    "  %s: %s\n",
    CAMPO_BUENO,
    $porDentro === NULL || $porDentro === [] || $porDentro === '' ? 'vacio' : var_export($porDentro, TRUE)
  );
}
catch (\Throwable $e) {
  printf("  EXCEPCION: %s: %s\n", get_class($e), $e->getMessage());
}
finally {
  \Drupal::requestStack()->pop();
}

// epp no avisa en pantalla cuando descarta un valor: lo apunta en el registro.
$titulo('Lo ultimo que ha dicho epp en el registro');
if (\Drupal::database()->schema()->tableExists('watchdog')) {
  $notas = $bd->select('watchdog', 'w')
    ->fields('w', ['timestamp', 'message', 'variables'])
    ->condition('w.type', 'epp')
    ->orderBy('w.wid', 'DESC')
    ->range(0, 5)
    ->execute()
    ->fetchAll();
  if (!$notas) {
    echo "  nada, no ha descartado ningun valor\n";
  }
  foreach ($notas as $nota) {
    $variables = @unserialize($nota->variables) ?: [];
    printf(
      "  %s  %s\n",
      date('Y-m-d H:i:s', (int) $nota->timestamp),
      strtr($nota->message, array_map('strval', $variables))
    );
  }
}
else {
  echo "  no hay registro de sucesos encendido\n";
}

echo "\n";
