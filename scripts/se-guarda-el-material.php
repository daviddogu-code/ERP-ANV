<?php

/**
 * @file
 * Checks that a material with real figures in it can actually be saved.
 *
 * It could not. A material at 799.70 baht came back with *"Consumption Cost is
 * not a valid number"*, and the number it was complaining about was 799.700000.
 * Later the same refusal appeared on a conversion factor of 10000, which is
 * simply how many centimetres come out of a roll.
 *
 * Drupal validates a decimal box against a step it derives from the column's
 * scale, and `Number::validStep()` cannot do that for large numbers: it measures
 * the floating-point leftover of dividing the value by the step and compares it
 * against a fixed margin of `step / 2^24`. The leftover grows with the size of
 * the value and the margin does not, so past about 10^(9 - decimals) nothing
 * passes, however round. Six decimals refuse anything over a thousand, five
 * refuse anything over ten thousand.
 *
 * The bug was invisible while the test material cost pennies and every factor
 * was 1, and it surfaced the first time somebody priced a real roll of fabric.
 * Then it surfaced again, on a different field, the week the two conversion
 * factors were given five decimals instead of two.
 *
 * This asks for the form and posts it back, the way a browser does, because the
 * whole failure lived in form validation and calling the entity API directly
 * would have sailed straight past it.
 *
 * Cleans up after itself.
 *
 * Run: php vendor\bin\drush.php scr scripts/se-guarda-el-material.php
 */

use Drupal\Component\Utility\Number;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const RUTA = '/admin/structure/taxonomy/manage/tec_inventory/add';
const MARCA = 'ZZPRUEBA coste';

$problemas = 0;
$basura = [];

$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas) {
  if (!$bien) {
    $problemas++;
  }
  print ($bien ? '  ok    ' : '  WRONG ') . $que . ($detalle === '' ? '' : '   [' . $detalle . ']') . "\n";
};

/**
 * Clears out anything a previous run left behind.
 *
 * A run that dies halfway leaves a material in the catalog, and the next run
 * would then make a second one with the same name rather than say so.
 */
$barrer = function () use (&$problemas): int {
  $cuantos = 0;
  foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'tec_inventory']) as $termino) {
    if (str_starts_with((string) $termino->label(), MARCA)) {
      $termino->delete();
      $cuantos++;
    }
  }
  return $cuantos;
};

$sobras = $barrer();
if ($sobras) {
  print "Cleared " . $sobras . " material(s) left over by an earlier run.\n\n";
}

// -----------------------------------------------------------------------------
// Plumbing: ask for a page as user 1, with a session, like a browser.
// -----------------------------------------------------------------------------

$kernel = \Drupal::service('http_kernel');
\Drupal::currentUser()->setAccount(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$sesion = \Drupal::service('session');

$pedir = function (string $ruta, string $metodo = 'GET', array $cuerpo = []) use ($kernel, $sesion) {
  // A form field is named name[0][value], and PHP turns that into nested arrays
  // before a real request ever reaches Symfony. Handing those names to
  // Request::create() does not: they stay as literal keys with brackets in them,
  // Drupal finds nothing where it looks, and the answer is every required field
  // complaining it is empty -- which reads exactly like a broken form and is
  // really a broken test. Round-tripping through parse_str() does the nesting
  // that PHP would have done.
  if ($cuerpo) {
    parse_str(http_build_query($cuerpo), $cuerpo);
  }
  $peticion = Request::create($ruta, $metodo, $cuerpo);
  $peticion->setSession($sesion);
  try {
    return $kernel->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (\Drupal\Core\Form\EnforcedResponseException $e) {
    // A form that saved sends the browser somewhere else, and inside a
    // sub-request Drupal delivers that redirect by throwing it. So this is what
    // success looks like from here, not a failure.
    return $e->getResponse();
  }
};

/**
 * Every field of a form, named and valued as the browser would post them.
 *
 * Selects answer with whatever is already chosen, or the first real option if
 * nothing is. Buttons are left out except the one that saves.
 */
$leer_formulario = function (string $html): array {
  $doc = new DOMDocument();
  libxml_use_internal_errors(TRUE);
  $doc->loadHTML($html);
  libxml_clear_errors();
  $buscar = new DOMXPath($doc);

  $campos = [];

  foreach ($buscar->query('//input') as $input) {
    $nombre = $input->getAttribute('name');
    $tipo = strtolower($input->getAttribute('type'));
    if ($nombre === '' || $tipo === 'submit' || $tipo === 'button' || $tipo === 'file') {
      continue;
    }
    if (($tipo === 'checkbox' || $tipo === 'radio') && !$input->hasAttribute('checked')) {
      continue;
    }
    $campos[$nombre] = $input->getAttribute('value');
  }

  foreach ($buscar->query('//textarea') as $area) {
    if ($area->getAttribute('name') !== '') {
      $campos[$area->getAttribute('name')] = $area->textContent;
    }
  }

  foreach ($buscar->query('//select') as $select) {
    $nombre = $select->getAttribute('name');
    if ($nombre === '') {
      continue;
    }
    $elegido = '';
    $primero = '';
    foreach ($buscar->query('.//option', $select) as $opcion) {
      $valor = $opcion->getAttribute('value');
      if ($valor === '' || $valor === '_none') {
        continue;
      }
      if ($primero === '') {
        $primero = $valor;
      }
      if ($opcion->hasAttribute('selected')) {
        $elegido = $valor;
      }
    }
    $campos[$nombre] = $elegido !== '' ? $elegido : $primero;
  }

  return $campos;
};

/**
 * The control a field is edited with, and the values it will accept.
 *
 * Worth asking rather than assuming: a reference field can be a dropdown, which
 * wants the bare id, or an autocomplete, which wants "Label (id)". Send the
 * wrong shape and Drupal answers "the submitted value is not allowed", which
 * says nothing about which shape it wanted.
 */
$control_de = function (string $html, string $campo): array {
  $doc = new DOMDocument();
  libxml_use_internal_errors(TRUE);
  $doc->loadHTML($html);
  libxml_clear_errors();
  $buscar = new DOMXPath($doc);

  foreach (['select', 'input'] as $etiqueta) {
    foreach ($buscar->query('//' . $etiqueta . '[starts-with(@name,"' . $campo . '[")]') as $nodo) {
      $opciones = [];
      foreach ($buscar->query('.//option', $nodo) as $opcion) {
        $valor = $opcion->getAttribute('value');
        if ($valor !== '' && $valor !== '_none') {
          $opciones[] = $valor;
        }
      }
      return [
        'nombre' => $nodo->getAttribute('name'),
        'lista' => $etiqueta === 'select',
        'opciones' => $opciones,
      ];
    }
  }
  return [];
};

/**
 * The attributes of one input, for looking at what the fix actually rendered.
 */
$atributos_de = function (string $html, string $nombre): array {
  $doc = new DOMDocument();
  libxml_use_internal_errors(TRUE);
  $doc->loadHTML($html);
  libxml_clear_errors();
  $buscar = new DOMXPath($doc);
  foreach ($buscar->query('//input[@name="' . $nombre . '"]') as $input) {
    $salida = [];
    foreach ($input->attributes as $atributo) {
      $salida[$atributo->name] = $atributo->value;
    }
    return $salida;
  }
  return [];
};

/**
 * The error messages Drupal put on the page, if any.
 */
$errores_de = function (string $html): array {
  $doc = new DOMDocument();
  libxml_use_internal_errors(TRUE);
  $doc->loadHTML($html);
  libxml_clear_errors();
  $buscar = new DOMXPath($doc);
  $fuera = [];
  foreach ($buscar->query('//div[contains(@class,"messages--error")]') as $aviso) {
    $texto = preg_replace('/\s+/', ' ', trim($aviso->textContent));
    if ($texto !== '') {
      $fuera[] = $texto;
    }
  }
  return $fuera;
};

// -----------------------------------------------------------------------------
// What core still does, so it is on the record that this was worked around and
// not fixed. If a future Drupal repairs validStep, this line starts failing and
// whoever sees it can take the workaround out.
// -----------------------------------------------------------------------------

print "The core behaviour underneath\n";
$mirar('Drupal still rejects 799.700000 against a step of a millionth',
  !Number::validStep('799.700000', pow(0.1, 6)),
  'Number::validStep() unchanged, so the workaround is still needed');
$mirar('and it still rejects a flat 10000 against a step of 0.00001',
  !Number::validStep('10000', pow(0.1, 5)),
  'the same refusal, one field over');
$mirar('and it is fine with the same figure at two decimals',
  Number::validStep('799.70', 0.01),
  'which is why nobody met this for years');

// -----------------------------------------------------------------------------
// The form as it now renders.
// -----------------------------------------------------------------------------

print "\nThe form\n";
$respuesta = $pedir(RUTA);
$mirar('the add material form opens', $respuesta->getStatusCode() === 200, 'status ' . $respuesta->getStatusCode());
if ($respuesta->getStatusCode() !== 200) {
  print "\nCannot go on without the form.\n";
  return;
}
$html = (string) $respuesta->getContent();

$consumo = $atributos_de($html, 'field_tec_price[0][value]');
$mirar('the consumption cost box is no longer step-checked',
  ($consumo['step'] ?? '') === 'any', 'step="' . ($consumo['step'] ?? 'missing') . '"');
$mirar('and it tells the calculator its six decimals instead',
  ($consumo['data-tec-decimals'] ?? '') === '6', 'data-tec-decimals="' . ($consumo['data-tec-decimals'] ?? 'missing') . '"');

$factor = $atributos_de($html, 'field_tec_split_into[0][value]');
$mirar('the inventory to consumption factor is no longer step-checked either',
  ($factor['step'] ?? '') === 'any', 'step="' . ($factor['step'] ?? 'missing') . '"');
$mirar('and it declares its five decimals',
  ($factor['data-tec-decimals'] ?? '') === '5', 'data-tec-decimals="' . ($factor['data-tec-decimals'] ?? 'missing') . '"');

// Every decimal box goes the same way, including the ones that were never in
// trouble. Leaving some on the broken check and some off it would mean the
// ceiling is still there, just further away, and nobody would know which
// fields still had it.
$compra = $atributos_de($html, 'field_tec_cost[0][value]');
$mirar('the purchase cost is treated the same as the rest',
  ($compra['step'] ?? '') === 'any', 'step="' . ($compra['step'] ?? 'missing') . '"');

// -----------------------------------------------------------------------------
// Saving one.
// -----------------------------------------------------------------------------

$campos = $leer_formulario($html);

$tipos = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'tec_materials']);
$tipo = reset($tipos);

$proveedor = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('tec_crm')->loadMultiple() as $contacto) {
  if (tec_crm_ux_entity_is_supplier($contacto)) {
    $proveedor = $contacto;
    break;
  }
}

if (!$tipo || !$proveedor) {
  print "\nNo material type or no supplier to test with. Nothing was saved.\n";
  return;
}

/**
 * Posts the form with these costs and factors, and says what came back.
 */
$guardar = function (string $nombre, string $coste, string $a_inventario, string $a_consumo, string $consumo_esperado)
  use ($campos, $tipo, $proveedor, $pedir, $errores_de, $control_de, $html, &$basura) {

  $enviar = $campos;
  $enviar['name[0][value]'] = $nombre;

  foreach (['field_tec_material_type' => $tipo, 'field_tec_vendor' => $proveedor] as $campo => $entidad) {
    $control = $control_de($html, $campo);
    if (!$control) {
      continue;
    }
    if (!$control['lista']) {
      $enviar[$control['nombre']] = $entidad->label() . ' (' . $entidad->id() . ')';
      continue;
    }
    // A dropdown only accepts what it offers. Preferring the entity picked
    // above keeps the test honest about which supplier it used, but any option
    // will do: what is being tested here is the cost, not the reference.
    $enviar[$control['nombre']] = in_array((string) $entidad->id(), $control['opciones'], TRUE)
      ? (string) $entidad->id()
      : ($control['opciones'][0] ?? '');
  }

  $enviar['field_tec_cost[0][value]'] = $coste;
  $enviar['field_tec_units[0][value]'] = $a_inventario;
  $enviar['field_tec_split_into[0][value]'] = $a_consumo;
  // What the calculator in the browser would have left in the two read-only
  // boxes. Posting them is the point: this is the value that was refused.
  $enviar['field_tec_price_uos[0][value]'] = number_format((float) $coste / (float) $a_inventario, 2, '.', '');
  $enviar['field_tec_price[0][value]'] = $consumo_esperado;
  $enviar['op'] = 'Save';

  $respuesta = $pedir(RUTA, 'POST', $enviar);
  $html = (string) $respuesta->getContent();
  $errores = $errores_de($html);

  $termino = NULL;
  $encontrados = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'tec_inventory', 'name' => $nombre]);
  if ($encontrados) {
    $termino = reset($encontrados);
    $basura[] = $termino;
  }

  return [$respuesta->getStatusCode(), $errores, $termino];
};

try {
  print "\nThe material from the screenshot: 799.70, both factors 1\n";
  [$codigo, $errores, $termino] = $guardar(MARCA . ' 799', '799.70', '1', '1', '799.700000');
  $mirar('it saves', $termino !== NULL, $errores ? implode(' | ', $errores) : 'status ' . $codigo);
  if ($termino) {
    $mirar('and the consumption cost is the figure that was refused before',
      (float) $termino->get('field_tec_price')->value === 799.7,
      (string) $termino->get('field_tec_price')->value);
    $mirar('and the inventory cost came out too',
      (float) $termino->get('field_tec_price_uos')->value === 799.7,
      (string) $termino->get('field_tec_price_uos')->value);
  }

  print "\nA cost that does not divide cleanly: 800 over 3 and then 7\n";
  // 800 / 3 / 7 = 38.095238095..., which is what six decimals are for.
  [$codigo, $errores, $termino] = $guardar(MARCA . ' 38', '800.00', '3', '7', '38.095238');
  $mirar('it saves', $termino !== NULL, $errores ? implode(' | ', $errores) : 'status ' . $codigo);
  if ($termino) {
    // The server divides again and rounds, so it does not matter what the
    // browser put in the box: this is the arithmetic that counts.
    $mirar('and the server worked it out to six decimals itself',
      (string) $termino->get('field_tec_price')->value === '38.095238',
      (string) $termino->get('field_tec_price')->value);
  }

  print "\nA five-figure cost, well past where the old check gave up\n";
  [$codigo, $errores, $termino] = $guardar(MARCA . ' 12345', '12345.67', '1', '1', '12345.670000');
  $mirar('it saves', $termino !== NULL, $errores ? implode(' | ', $errores) : 'status ' . $codigo);
  if ($termino) {
    $mirar('and nothing was lost on the way in',
      (float) $termino->get('field_tec_price')->value === 12345.67,
      (string) $termino->get('field_tec_price')->value);
  }

  // The two that were refused in the browser, by name, so that the day this
  // regresses the failure says which real material stopped saving.
  print "\nOne roll into 10000 cm, the factor that locked a material\n";
  [$codigo, $errores, $termino] = $guardar(MARCA . ' 10000', '169.23', '1', '10000', '0.016923');
  $mirar('it saves', $termino !== NULL, $errores ? implode(' | ', $errores) : 'status ' . $codigo);
  if ($termino) {
    $mirar('and the factor went in whole',
      (float) $termino->get('field_tec_split_into')->value === 10000.0,
      (string) $termino->get('field_tec_split_into')->value);
    $mirar('and the cost per centimetre came out',
      (string) $termino->get('field_tec_price')->value === '0.016923',
      (string) $termino->get('field_tec_price')->value);
  }

  print "\nOne roll into 300000 cm, thirty times past the ceiling\n";
  [$codigo, $errores, $termino] = $guardar(MARCA . ' 300000', '169.23', '1', '300000', '0.000564');
  $mirar('it saves', $termino !== NULL, $errores ? implode(' | ', $errores) : 'status ' . $codigo);
  if ($termino) {
    $mirar('and the factor went in whole',
      (float) $termino->get('field_tec_split_into')->value === 300000.0,
      (string) $termino->get('field_tec_split_into')->value);
    $mirar('and the cost per centimetre came out',
      (string) $termino->get('field_tec_price')->value === '0.000564',
      (string) $termino->get('field_tec_price')->value);
  }

  // Dropping the step must not drop the rule it stood for. A column with five
  // decimals cannot hold six, and MySQL would round the sixth away without
  // saying anything.
  print "\nAnd a factor that does not fit its column is still refused\n";
  [$codigo, $errores, $termino] = $guardar(MARCA . ' decimales', '169.23', '1', '1.234567', '137.078000');
  $mirar('six decimals in a five decimal box is refused', $termino === NULL,
    $termino ? 'it saved as ' . $termino->get('field_tec_split_into')->value : 'not saved');
  $mirar('and the message says what is wrong',
    (bool) preg_grep('/decimal/i', $errores),
    $errores ? implode(' | ', $errores) : 'no message at all');
}
finally {
  $barrer();
}

print "\n" . ($problemas === 0 ? "All good." : $problemas . " thing(s) wrong.") . "\n";
