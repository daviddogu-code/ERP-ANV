<?php

/**
 * @file
 * Un cliente tiene que decir el pais, y cambiar de pais no rompe la direccion.
 *
 *   php vendor\bin\drush.php scr scripts\el-pais-del-cliente.php
 *
 * El IVA de venta se decide por el pais de destino. Sin pais no hay regla, asi
 * que el selector es obligatorio en un cliente. No se marca obligatoria la
 * direccion entera: Address entonces rellena un pais por defecto (a menudo
 * Estados Unidos) y el usuario cree que ya lo eligio.
 *
 * Cambiar de pais solia fallar despues de haber rellenado la direccion. Tailandia
 * pide provincia, Singapur no. El ajax de Address solo vaciaba un campo si
 * seguia existiendo en el formulario nuevo, asi que la provincia tailandesa
 * sobrevivia al cambio y el validador de formato rechazaba el guardado.
 *
 * Se monta su propio juego y lo barre al empezar y al acabar. Todo lo suyo se
 * llama «PRUEBA pais del cliente …».
 */

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Symfony\Component\HttpFoundation\Request;

const RASTRO = 'PRUEBA pais del cliente';
const CLIENTE = 1395;
const PROVEEDOR = 1396;

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$contactos = $gestor->getStorage('tec_crm');
$problemas = 0;
$nCodigo = 0;

$mirar = static function (string $que, bool $bien, string $pista = '') use (&$problemas): void {
  printf("  %-6s %s\n", $bien ? 'BIEN' : 'MAL', $que);
  if (!$bien) {
    $problemas++;
    if ($pista !== '') {
      print '         ' . $pista . "\n";
    }
  }
};

$codigo = static function () use (&$nCodigo): string {
  $nCodigo++;
  return 'Z' . substr(md5(RASTRO . $nCodigo), 0, 5);
};

$barrer = static function () use ($contactos): int {
  $ids = $contactos->getQuery()->accessCheck(FALSE)
    ->condition('title', RASTRO, 'STARTS_WITH')
    ->execute();
  $cuantas = 0;
  foreach ($contactos->loadMultiple($ids) as $ficha) {
    $ficha->delete();
    $cuantas++;
  }
  return $cuantas;
};

$guardar = static function (array $valores) use ($contactos) {
  $contacto = $contactos->create($valores + ['type' => 'tec_contact_organization']);
  $contacto->save();
  return $contacto;
};

$selectorPais = static function (array $formulario): array {
  $caja = $formulario['field_tec_address']['widget'][0]['address'] ?? [];
  $pais = $caja['country_code']['country_code'] ?? $caja['country_code'] ?? [];
  return is_array($pais) ? $pais : [];
};

$camposDe = static function (array $valores) use ($contactos): array {
  $contacto = $contactos->create($valores + ['type' => 'tec_contact_organization']);
  $formulario = \Drupal::service('entity.form_builder')->getForm($contacto, 'default');
  $direccion = $formulario['field_tec_address']['widget'][0]['address'] ?? [];
  $nombres = [];
  foreach (Element::children($direccion) as $clave) {
    if ($clave !== 'langcode') {
      $nombres[] = $clave;
    }
  }
  return [$formulario, $nombres, $direccion];
};

$validar = static function (array $enLaFicha, array $enviado) use ($contactos): FormState {
  $contacto = $contactos->create($enLaFicha + ['type' => 'tec_contact_organization']);
  $form_object = \Drupal::entityTypeManager()->getFormObject('tec_crm', 'default');
  $form_object->setEntity($contacto);
  $form_state = new FormState();
  $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
  $form_state->setValues($enviado);
  $form_state->setSubmitted();
  tec_crm_ux_contact_form_validate($form, $form_state);
  return $form_state;
};

$grupoEntero = NULL;
$grupoEntero = static function (array $element) use (&$grupoEntero): bool {
  foreach (Element::children($element) as $clave) {
    if (!isset($element[$clave]) || !is_array($element[$clave])) {
      continue;
    }
    if (isset($element[$clave]['#group']) && !is_string($element[$clave]['#group'])) {
      return TRUE;
    }
    if ($grupoEntero($element[$clave])) {
      return TRUE;
    }
  }
  return FALSE;
};

print "\n";
print "Montando el juego\n";
print str_repeat('-', 70) . "\n";

$barridas = $barrer();
if ($barridas > 0) {
  printf("  antes barro %d fichas que dejo una prueba anterior\n", $barridas);
}

try {

$provinciasTh = \Drupal::service('address.subdivision_repository')->getList(['TH']);
$provinciaTh = (string) array_key_first($provinciasTh);
$mirar('Tailandia tiene provincias que elegir', $provinciaTh !== '', 'subdivision_repository no devolvio nada');

print "\nEl formulario del cliente pide el pais, y solo el pais\n";
print str_repeat('-', 70) . "\n";

[$formCliente, $camposCliente, $cajaCliente] = $camposDe([
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
]);
$paisCliente = $selectorPais($formCliente);

$mirar('el cliente trae selector de pais', $paisCliente !== []);
$mirar('el pais es obligatorio', !empty($paisCliente['#required']), 'el selector no lleva #required');
$mirar('la direccion entera no es obligatoria', empty($cajaCliente['#required']), 'Address rellenaria un pais por defecto');
$mirar('no viene un pais ya elegido',
  ($paisCliente['#default_value'] ?? '') === '' || ($paisCliente['#default_value'] ?? NULL) === NULL,
  'viene ' . (string) ($paisCliente['#default_value'] ?? ''));
$mirar('el pais cambia por ajax', !empty($paisCliente['#ajax']['callback']), 'alguien ha vuelto a quitar el ajax');
$mirar('el ajax apunta al recuadro de la direccion',
  ($paisCliente['#ajax']['wrapper'] ?? '') === ($cajaCliente['#wrapper_id'] ?? ''),
  'apunta a ' . ($paisCliente['#ajax']['wrapper'] ?? '(nada)'));
$mirar('no queda el boton que recargaba la pagina', !isset($formCliente['tec_crm_ux_address_rebuild']));
$mirar('la lista de paises esta completa', count($paisCliente['#options'] ?? []) > 200,
  count($paisCliente['#options'] ?? []) . ' paises');

[$formProveedor, , $cajaProveedor] = $camposDe([
  'field_tec_contact_type' => [['target_id' => PROVEEDOR]],
]);
$paisProveedor = $selectorPais($formProveedor);
$mirar('un proveedor puede dejar el pais vacio', empty($paisProveedor['#required']),
  'el selector del proveedor tambien es required');

[$formAmbos] = $camposDe([
  'field_tec_contact_type' => [
    ['target_id' => CLIENTE],
    ['target_id' => PROVEEDOR],
  ],
]);
$mirar('cliente y proveedor a la vez tambien pide pais', !empty($selectorPais($formAmbos)['#required']));

$persona = $contactos->create(['type' => 'tec_contact_person']);
$formPersona = \Drupal::service('entity.form_builder')->getForm($persona, 'default');
$mirar('una persona no obliga el pais', empty($selectorPais($formPersona)['#required']));

print "\nAlta con ?tipo=customer\n";
print str_repeat('-', 70) . "\n";

$peticion = Request::create('/admin/content/tec_crm/add/tec_contact_organization', 'GET', ['tipo' => 'customer']);
$peticion->setSession(\Drupal::service('session'));
\Drupal::requestStack()->push($peticion);
try {
  $alta = $contactos->create(['type' => 'tec_contact_organization']);
  $formAlta = \Drupal::service('entity.form_builder')->getForm($alta, 'default');
  $mirar('el alta de cliente marca el pais obligatorio', !empty($selectorPais($formAlta)['#required']));
  $mirar('y no preselecciona un pais',
    ($selectorPais($formAlta)['#default_value'] ?? '') === ''
    || ($selectorPais($formAlta)['#default_value'] ?? NULL) === NULL,
    'viene ' . (string) ($selectorPais($formAlta)['#default_value'] ?? ''));
}
finally {
  \Drupal::requestStack()->pop();
}

print "\nEl pais manda sobre los campos\n";
print str_repeat('-', 70) . "\n";

[, $tailandia, $cajaTh] = $camposDe([
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_address' => [['country_code' => 'TH']],
]);
$mirar('Tailandia pide provincia', in_array('administrative_area', $tailandia, TRUE),
  implode(', ', $tailandia));
print '         ' . implode(', ', $tailandia) . "\n";
$mirar('y no deja #group enteros que rompen el ajax', !$grupoEntero($cajaTh));

[, $singapur, $cajaSg] = $camposDe([
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_address' => [['country_code' => 'SG']],
]);
$mirar('Singapur no pide provincia', !in_array('administrative_area', $singapur, TRUE),
  implode(', ', $singapur));
print '         ' . implode(', ', $singapur) . "\n";

[, $italia] = $camposDe([
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_address' => [['country_code' => 'IT']],
]);
$mirar('Italia saca calle y ciudad', in_array('address_line1', $italia, TRUE) && in_array('locality', $italia, TRUE),
  implode(', ', $italia));
print '         ' . implode(', ', $italia) . "\n";

[, $eeuu] = $camposDe([
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_address' => [['country_code' => 'US']],
]);
$mirar('Estados Unidos pide estado', in_array('administrative_area', $eeuu, TRUE),
  implode(', ', $eeuu));
print '         ' . implode(', ', $eeuu) . "\n";

print "\nGuardar: el cliente sin pais se rechaza\n";
print str_repeat('-', 70) . "\n";

$baseEnvio = [
  'title' => [['value' => RASTRO . ' envio']],
  'field_tec_customer_code' => [['value' => $codigo()]],
];

$sinPais = $validar(
  ['field_tec_contact_type' => [['target_id' => CLIENTE]]],
  $baseEnvio + [
    'field_tec_contact_type' => [CLIENTE => (string) CLIENTE],
    'field_tec_address' => [0 => ['address' => ['country_code' => '']]],
  ]
);
$erroresSinPais = $sinPais->getErrors();
$mirar('un cliente sin pais no pasa el formulario', $erroresSinPais !== [],
  $erroresSinPais ? implode(' | ', $erroresSinPais) : 'no hubo error');
$mirar('el aviso habla del pais',
  (bool) preg_grep('/country/i', $erroresSinPais),
  implode(' | ', $erroresSinPais));

$calleSinPais = $validar(
  ['field_tec_contact_type' => [['target_id' => CLIENTE]]],
  $baseEnvio + [
    'field_tec_contact_type' => [CLIENTE => (string) CLIENTE],
    'field_tec_address' => [0 => ['address' => [
      'country_code' => '',
      'address_line1' => '99 Test Rd',
      'locality' => 'Bangkok',
    ]]],
  ]
);
$mirar('calle y ciudad sin pais tampoco vale', $calleSinPais->getErrors() !== []);

$soloPais = $validar(
  ['field_tec_contact_type' => [['target_id' => CLIENTE]]],
  $baseEnvio + [
    'field_tec_contact_type' => [CLIENTE => (string) CLIENTE],
    'field_tec_address' => [0 => ['address' => ['country_code' => 'TH']]],
  ]
);
$mirar('con el pais y sin calle si vale', $soloPais->getErrors() === [],
  implode(' | ', $soloPais->getErrors()));

$ambosSinPais = $validar(
  ['field_tec_contact_type' => [
    ['target_id' => CLIENTE],
    ['target_id' => PROVEEDOR],
  ]],
  $baseEnvio + [
    'field_tec_contact_type' => [
      CLIENTE => (string) CLIENTE,
      PROVEEDOR => (string) PROVEEDOR,
    ],
    'field_tec_address' => [0 => ['address' => ['country_code' => '']]],
  ]
);
$mirar('cliente y proveedor sin pais tampoco', $ambosSinPais->getErrors() !== []);

$proveedorSinPais = $validar(
  ['field_tec_contact_type' => [['target_id' => PROVEEDOR]]],
  $baseEnvio + [
    'field_tec_contact_type' => [PROVEEDOR => (string) PROVEEDOR],
    'field_tec_address' => [0 => ['address' => ['country_code' => '']]],
  ]
);
$mirar('un proveedor sin pais pasa el formulario', $proveedorSinPais->getErrors() === [],
  implode(' | ', $proveedorSinPais->getErrors()));

print "\nGuardar de verdad: paises y direcciones\n";
print str_repeat('-', 70) . "\n";

$clienteTh = $guardar([
  'title' => RASTRO . ' TH',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_customer_code' => $codigo(),
  'field_tec_address' => [[
    'country_code' => 'TH',
    'administrative_area' => $provinciaTh,
    'locality' => 'Bangkok',
    'address_line1' => '99 Test Rd',
    'postal_code' => '10110',
  ]],
]);
$violacionesTh = $clienteTh->validate();
$mirar('Tailandia con calle, ciudad y provincia se guarda',
  !$clienteTh->isNew() && $violacionesTh->count() === 0,
  $violacionesTh->count() ? (string) $violacionesTh->get(0)->getMessage() : '');
$clienteTh = $contactos->load($clienteTh->id());
$mirar('y el pais sigue siendo TH', tec_crm_ux_country_code($clienteTh) === 'TH',
  tec_crm_ux_country_code($clienteTh));
$mirar('y la provincia se ha quedado',
  (string) $clienteTh->get('field_tec_address')->administrative_area === $provinciaTh,
  (string) $clienteTh->get('field_tec_address')->administrative_area);

$formThGuardado = \Drupal::service('entity.form_builder')->getForm($clienteTh, 'default');
$paisThGuardado = $selectorPais($formThGuardado);
$mirar('al reabrir, el pais sigue elegido', ($paisThGuardado['#default_value'] ?? '') === 'TH',
  (string) ($paisThGuardado['#default_value'] ?? ''));
$mirar('y el ajax sigue en el selector', !empty($paisThGuardado['#ajax']['callback']));

$clienteSg = $guardar([
  'title' => RASTRO . ' SG',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_customer_code' => $codigo(),
  'field_tec_address' => [[
    'country_code' => 'SG',
    'address_line1' => '1 Raffles Place',
    'locality' => 'Singapore',
    'postal_code' => '048616',
  ]],
]);
$mirar('Singapur con calle se guarda', !$clienteSg->isNew() && $clienteSg->validate()->count() === 0);
$mirar('y no arrastra provincia',
  (string) $clienteSg->get('field_tec_address')->administrative_area === '');

$clienteIt = $guardar([
  'title' => RASTRO . ' IT',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_customer_code' => $codigo(),
  'field_tec_address' => [[
    'country_code' => 'IT',
    'address_line1' => 'Via Roma 1',
    'locality' => 'Roma',
    'postal_code' => '00100',
  ]],
]);
$mirar('Italia (exportacion) se guarda', tec_crm_ux_country_code($clienteIt) === 'IT');

$clienteUs = $guardar([
  'title' => RASTRO . ' US',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_customer_code' => $codigo(),
  'field_tec_address' => [[
    'country_code' => 'US',
    'administrative_area' => 'CA',
    'locality' => 'Los Angeles',
    'address_line1' => '100 Test Ave',
    'postal_code' => '90001',
  ]],
]);
$mirar('Estados Unidos con estado se guarda',
  tec_crm_ux_country_code($clienteUs) === 'US'
  && (string) $clienteUs->get('field_tec_address')->administrative_area === 'CA');

$soloPaisGuardado = $guardar([
  'title' => RASTRO . ' solo pais',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_customer_code' => $codigo(),
  'field_tec_address' => [['country_code' => 'GB']],
]);
$mirar('con el pais y sin calle se guarda de verdad', tec_crm_ux_country_code($soloPaisGuardado) === 'GB');

$proveedorVacio = $guardar([
  'title' => RASTRO . ' proveedor sin pais',
  'field_tec_contact_type' => [['target_id' => PROVEEDOR]],
  'field_tec_customer_code' => $codigo(),
]);
$mirar('un proveedor sin direccion se guarda',
  !$proveedorVacio->isNew() && tec_crm_ux_country_code($proveedorVacio) === '');

print "\nCambiar de pais no deja basura de antes\n";
print str_repeat('-', 70) . "\n";

$limpia = tec_crm_ux_scrub_address_value([
  'country_code' => 'SG',
  'administrative_area' => $provinciaTh,
  'locality' => 'Bangkok',
  'address_line1' => '99 Test Rd',
  'postal_code' => '10110',
], tec_crm_ux_address_overrides_for_entity($clienteTh));
$mirar('de TH a SG se tira la provincia', ($limpia['administrative_area'] ?? 'x') === '');
$mirar('la calle se queda', ($limpia['address_line1'] ?? '') === '99 Test Rd');

$limpiaUs = tec_crm_ux_scrub_address_value([
  'country_code' => 'TH',
  'administrative_area' => 'CA',
  'locality' => 'Los Angeles',
  'given_name' => 'No',
  'family_name' => 'Deberia',
], tec_crm_ux_address_overrides_for_entity($clienteTh));
$mirar('los nombres de persona, que estan ocultos, se tiran',
  ($limpiaUs['given_name'] ?? '') === '' && ($limpiaUs['family_name'] ?? '') === '');

$restos = $validar(
  ['field_tec_contact_type' => [['target_id' => CLIENTE]]],
  $baseEnvio + [
    'field_tec_contact_type' => [CLIENTE => (string) CLIENTE],
    'field_tec_address' => [0 => ['address' => [
      'country_code' => 'SG',
      'administrative_area' => $provinciaTh,
      'address_line1' => '1 Raffles Place',
      'locality' => 'Singapore',
    ]]],
  ]
);
$mirar('el formulario no rechaza Singapur con provincia tailandesa de antes',
  $restos->getErrors() === [],
  implode(' | ', $restos->getErrors()));
$despues = tec_crm_ux_form_address_items($restos)[0] ?? [];
$mirar('y ha vaciado esa provincia en lo enviado',
  ($despues['administrative_area'] ?? '') === '',
  (string) ($despues['administrative_area'] ?? ''));

$cambio = $contactos->load($clienteTh->id());
$cambio->set('field_tec_address', [[
  'country_code' => 'SG',
  'administrative_area' => $provinciaTh,
  'address_line1' => '99 Test Rd',
  'postal_code' => '048616',
]]);
tec_crm_ux_scrub_entity_address($cambio);
$violacionesCambio = $cambio->validate();
$mirar('la ficha TH→SG con provincia de antes valida', $violacionesCambio->count() === 0,
  $violacionesCambio->count() ? (string) $violacionesCambio->get(0)->getMessage() : '');
$cambio->save();
$cambio = $contactos->load($cambio->id());
$mirar('despues de guardar el pais es SG', tec_crm_ux_country_code($cambio) === 'SG');
$mirar('y la provincia tailandesa ya no esta',
  (string) $cambio->get('field_tec_address')->administrative_area === '',
  (string) $cambio->get('field_tec_address')->administrative_area);

$formSg = \Drupal::service('entity.form_builder')->getForm($cambio, 'default');
[, $camposSgDespues] = [NULL, (static function (array $formulario): array {
  $direccion = $formulario['field_tec_address']['widget'][0]['address'] ?? [];
  $nombres = [];
  foreach (Element::children($direccion) as $clave) {
    if ($clave !== 'langcode') {
      $nombres[] = $clave;
    }
  }
  return $nombres;
})($formSg)];
$mirar('al reabrir ya no sale el selector de provincia',
  !in_array('administrative_area', $camposSgDespues, TRUE),
  implode(', ', $camposSgDespues));
$mirar('y el pais elegido es Singapur', ($selectorPais($formSg)['#default_value'] ?? '') === 'SG');

$sinScrub = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => RASTRO . ' sucia',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_customer_code' => $codigo(),
  'field_tec_address' => [[
    'country_code' => 'SG',
    'administrative_area' => $provinciaTh,
    'address_line1' => '1 Test St',
  ]],
]);
$antes = $sinScrub->validate()->count();
tec_crm_ux_scrub_entity_address($sinScrub);
$mirar('sin limpiar, Singapur con provincia tailandesa no valida', $antes > 0,
  'validate() no se quejo: el fallo de formato ya no existe o el validador no corre');
$mirar('con la limpieza, valida', $sinScrub->validate()->count() === 0);

$presave = $guardar([
  'title' => RASTRO . ' presave',
  'field_tec_contact_type' => [['target_id' => CLIENTE]],
  'field_tec_customer_code' => $codigo(),
  'field_tec_address' => [[
    'country_code' => 'SG',
    'administrative_area' => $provinciaTh,
    'address_line1' => '2 Test St',
  ]],
]);
$presave = $contactos->load($presave->id());
$mirar('el presave limpia al guardar aunque nadie haya llamado a scrub',
  (string) $presave->get('field_tec_address')->administrative_area === '');

print "\n";
}
finally {
  $barrer();
}
echo $problemas === 0
  ? "El pais del cliente es obligatorio, y cambiar de pais deja la direccion limpia.\n\n"
  : "$problemas cosa(s) mal.\n\n";
