<?php

/**
 * @file
 * Checks the company settings screen: the door, the lock, and the saving.
 *
 * The VAT rate used to be reachable only from a command line, which is fine for
 * whoever built the thing and no use to whoever runs the company. This is the
 * screen that fixed that, and there are three ways it could quietly stop
 * working: the page stops loading, the permission stops holding anyone out, or
 * saving stops writing. Each is checked here.
 *
 * The rate is put back exactly as it was found, so this can be run on a live
 * site without changing what anybody is charged.
 *
 * Run: php vendor\bin\drush.php scr scripts/se-tocan-los-ajustes.php
 */

use Drupal\Core\Session\UserSession;
use Drupal\tec_production\EventSubscriber\QueueTileRedirectSubscriber;
use Drupal\tec_production\Vat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const AJUSTES = '/admin/config/tec/company';
const PERMISO = 'administer tec company settings';

$problemas = 0;

$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas) {
  if (!$bien) {
    $problemas++;
  }
  print ($bien ? '  ok    ' : '  WRONG ') . $que . ($detalle === '' ? '' : '   [' . $detalle . ']') . "\n";
};

$kernel = \Drupal::service('http_kernel');
$sesion = \Drupal::service('session');
$cambiador = \Drupal::service('account_switcher');

$pedir = function (string $ruta, string $metodo = 'GET', array $cuerpo = []) use ($kernel, $sesion) {
  if ($cuerpo) {
    parse_str(http_build_query($cuerpo), $cuerpo);
  }
  $peticion = Request::create($ruta, $metodo, $cuerpo);
  $peticion->setSession($sesion);
  try {
    return $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (\Drupal\Core\Form\EnforcedResponseException $e) {
    // A form that saved redirects, and inside a sub-request Drupal delivers
    // that redirect by throwing it. This is success, not failure.
    return $e->getResponse();
  }
  catch (AccessDeniedHttpException $e) {
    return new \Symfony\Component\HttpFoundation\Response('', 403);
  }
};

$campos_de = function (string $html): array {
  $doc = new DOMDocument();
  libxml_use_internal_errors(TRUE);
  $doc->loadHTML($html);
  libxml_clear_errors();
  $buscar = new DOMXPath($doc);
  $campos = [];
  foreach ($buscar->query('//input') as $input) {
    $nombre = $input->getAttribute('name');
    $tipo = strtolower($input->getAttribute('type'));
    if ($nombre === '' || $tipo === 'submit' || $tipo === 'button') {
      continue;
    }
    $campos[$nombre] = $input->getAttribute('value');
  }
  return $campos;
};

$antes = Vat::standardRate();
$ajustes = \Drupal::configFactory()->getEditable('tec_production.settings');

try {
  // ---------------------------------------------------------------------------
  print "The lock\n";
  // ---------------------------------------------------------------------------

  $cambiador->switchTo(new UserSession(['uid' => 0]));
  $mirar('a stranger cannot open it', $pedir(AJUSTES)->getStatusCode() === 403);
  $cambiador->switchBack();

  $mirar('the permission exists and says who it is for',
    array_key_exists(PERMISO, \Drupal::service('user.permissions')->getPermissions()));

  $con_permiso = [];
  foreach (\Drupal\user\Entity\Role::loadMultiple() as $rol) {
    if ($rol->isAdmin() || $rol->hasPermission(PERMISO)) {
      $con_permiso[] = $rol->id();
    }
  }
  $mirar('somebody other than the site administrator has it',
    (bool) array_diff($con_permiso, ['administrator']),
    implode(', ', $con_permiso));

  // ---------------------------------------------------------------------------
  print "\nThe screen\n";
  // ---------------------------------------------------------------------------

  $cambiador->switchTo(new UserSession(['uid' => 1, 'roles' => ['authenticated', 'administrator']]));

  $respuesta = $pedir(AJUSTES);
  $mirar('it opens', $respuesta->getStatusCode() === 200, 'status ' . $respuesta->getStatusCode());
  if ($respuesta->getStatusCode() !== 200) {
    return;
  }
  $html = (string) $respuesta->getContent();

  $mirar('the rate is on it', str_contains($html, 'name="vat_rate"'));
  foreach (array_keys(QueueTileRedirectSubscriber::TILES) as $icono) {
    $mirar('and so is the icon setting ' . $icono, str_contains($html, 'name="' . $icono . '"'));
  }
  $mirar('it says the rate does not rewrite old orders',
    str_contains($html, 'keeps the rate it was raised under'));

  $mirar('the TEC section of Configuration is reachable',
    $pedir('/admin/config/tec')->getStatusCode() === 200);

  // ---------------------------------------------------------------------------
  print "\nSaving\n";
  // ---------------------------------------------------------------------------

  $campos = $campos_de($html);
  $iconos_antes = [];
  foreach (array_keys(QueueTileRedirectSubscriber::TILES) as $icono) {
    $iconos_antes[$icono] = (int) $ajustes->get($icono);
  }

  $campos['vat_rate'] = '10.5';
  $campos['op'] = 'Save configuration';
  $respuesta = $pedir(AJUSTES, 'POST', $campos);

  $mirar('the form accepts a new rate', in_array($respuesta->getStatusCode(), [200, 303], TRUE),
    'status ' . $respuesta->getStatusCode());
  $mirar('and it reaches the setting', Vat::standardRate() === 10.5, (string) Vat::standardRate());
  $mirar('so a new order would now be raised at that rate',
    Vat::forContact(NULL) === 10.5, (string) Vat::forContact(NULL));

  $iconos_despues = [];
  foreach (array_keys(QueueTileRedirectSubscriber::TILES) as $icono) {
    $iconos_despues[$icono] = (int) \Drupal::config('tec_production.settings')->get($icono);
  }
  // Saving the form must not quietly unhook the home page icons, which is
  // exactly what would happen if the autocompletes came back empty.
  $mirar('and the home page icons were left where they were',
    $iconos_antes === $iconos_despues,
    implode(', ', array_map(fn($k, $v) => $k . '=' . $v, array_keys($iconos_despues), $iconos_despues)));

  // ---------------------------------------------------------------------------
  print "\nThe door from the supplier card\n";
  // ---------------------------------------------------------------------------

  $proveedor = NULL;
  foreach (\Drupal::entityTypeManager()->getStorage('tec_crm')->loadMultiple() as $contacto) {
    if (tec_crm_ux_entity_is_supplier($contacto)) {
      $proveedor = $contacto;
      break;
    }
  }

  if (!$proveedor) {
    print "  no supplier on file, so the link could not be looked at\n";
  }
  else {
    $ficha = (string) $pedir('/tec_crm/' . $proveedor->id() . '/edit')->getContent();
    $mirar('the VAT treatment field is on the supplier card',
      str_contains($ficha, Vat::TREATMENT_FIELD));
    $mirar('with a link to the settings next to it',
      str_contains($ficha, AJUSTES));
    // Read live, so it cannot go on advertising a rate nobody uses.
    $mirar('and the link quotes the rate in force, not a hardcoded one',
      str_contains($ficha, 'VAT is currently 10.5%'),
      preg_match('/VAT is currently [^<]*/', $ficha, $m) ? $m[0] : 'not found');
  }
}
finally {
  $ajustes->set('vat_rate', $antes)->save();
  $cambiador->switchBack();
  print "\nRate put back to " . $antes . ".\n";
}

print "\n" . ($problemas === 0 ? "All good." : $problemas . " thing(s) wrong.") . "\n";
