<?php

/**
 * @file
 * Barrer los restos del cliente del producto: lo que quedo nombrado sin existir.
 *
 *   php vendor\bin\drush.php scr scripts/barrer-los-restos-del-cliente.php
 *
 * El campo ya esta borrado y el ERP funciona, pero la configuracion sigue
 * nombrandolo en tres sitios donde ya no hay campo que nombrar:
 *
 *   la busqueda del bloque de la portada  el buscador «combine» lo tenia en su
 *                                         lista de campos donde buscar
 *   los ajustes del filtro                los del filtro de cliente, que ya no
 *                                         existe, se quedaron huerfanos
 *   los grupos de los formularios         el del producto y el de la marca lo
 *                                         siguen listando como hijo
 *
 * Nada de esto rompe hoy: Views se salta los campos que faltan y un hijo que no
 * existe no se pinta. Se limpia porque un nombre muerto en la configuracion es
 * una trampa para el siguiente que abra esa pantalla y la de por buena.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir.
 */

const CAMPO = 'field_tec_customer';

$hechas = [];

print "\n";
print "=====================================================================\n";
print "  Barrer los restos del cliente del producto\n";
print "=====================================================================\n\n";

// --- 1. La vista de productos ----------------------------------------------
$vista = \Drupal::configFactory()->getEditable('views.view.tec_products');
$pantallas = $vista->get('display') ?? [];
$tocadas = FALSE;

foreach ($pantallas as $id => $pantalla) {
  $titulo = $pantalla['display_title'] ?? $id;

  // El buscador de una sola caja: lleva dentro la lista de campos por los que
  // busca, y ahi seguia el cliente.
  foreach ($pantalla['display_options']['filters'] ?? [] as $filtro => $ajustes) {
    if (($ajustes['plugin_id'] ?? '') !== 'combine') {
      continue;
    }
    if (!isset($ajustes['fields'][CAMPO])) {
      continue;
    }
    unset($pantallas[$id]['display_options']['filters'][$filtro]['fields'][CAMPO]);
    $tocadas = TRUE;
    $hechas[] = sprintf('%s: el buscador ya no busca por cliente', $titulo);
  }

  // Los ajustes del filtro que ya no esta.
  $bef = $pantalla['display_options']['exposed_form']['options']['bef']['filter'] ?? [];
  foreach (array_keys($bef) as $nombre) {
    if (!str_starts_with((string) $nombre, CAMPO)) {
      continue;
    }
    unset($pantallas[$id]['display_options']['exposed_form']['options']['bef']['filter'][$nombre]);
    $tocadas = TRUE;
    $hechas[] = sprintf('%s: fuera los ajustes huerfanos de %s', $titulo, $nombre);
  }
}

if ($tocadas) {
  $vista->set('display', $pantallas)->save();
}
else {
  print "  La vista de productos ya estaba limpia.\n";
}

// --- 2. Los grupos de los formularios --------------------------------------
// Solo estos dos, y solo este nombre. Un barrido general de hijos que no existen
// se llevaria por delante cosas que no son campos y que si tienen que estar.
$formularios = [
  'core.entity_form_display.tec_product.tec_product.default' => 'el formulario del producto',
  'core.entity_form_display.taxonomy_term.tec_brands.default' => 'el formulario de la marca',
];

foreach ($formularios as $nombre => $comoSeLlama) {
  $config = \Drupal::configFactory()->getEditable($nombre);
  if ($config->isNew()) {
    continue;
  }
  $grupos = $config->get('third_party_settings.field_group') ?? [];
  $cambio = FALSE;

  foreach ($grupos as $grupo => $ajustes) {
    $hijos = $ajustes['children'] ?? [];
    $sinCliente = array_values(array_filter($hijos, fn ($hijo) => $hijo !== CAMPO));
    if (count($sinCliente) === count($hijos)) {
      continue;
    }
    $grupos[$grupo]['children'] = $sinCliente;
    $cambio = TRUE;
    $hechas[] = sprintf('%s: el grupo «%s» ya no lo lista', $comoSeLlama, $grupo);
  }

  // Y el sitio del campo en la fila de pesos, si quedara.
  $contenido = $config->get('content') ?? [];
  $escondidos = $config->get('hidden') ?? [];
  if (isset($contenido[CAMPO])) {
    unset($contenido[CAMPO]);
    $config->set('content', $contenido);
    $cambio = TRUE;
    $hechas[] = sprintf('%s: fuera el widget que quedaba', $comoSeLlama);
  }
  if (isset($escondidos[CAMPO])) {
    unset($escondidos[CAMPO]);
    $config->set('hidden', $escondidos);
    $cambio = TRUE;
    $hechas[] = sprintf('%s: fuera de la lista de escondidos', $comoSeLlama);
  }

  if ($cambio) {
    $config->set('third_party_settings.field_group', $grupos)->save();
  }
}

// --- 3. Lo que quede, dicho en voz alta ------------------------------------
// Aviso, no arreglo: si aparece algo aqui es que hay un sitio que este guion no
// conoce, y eso se mira a mano antes de darlo por hecho.
$sospechosas = [];
foreach (\Drupal::configFactory()->listAll() as $nombre) {
  if (str_contains($nombre, CAMPO)) {
    // Los campos de verdad que se llaman igual en otras entidades: pedidos,
    // patrones. Esos se quedan.
    continue;
  }
  $datos = \Drupal::config($nombre)->getRawData();
  $texto = (string) json_encode($datos);
  if (!str_contains($texto, CAMPO)) {
    continue;
  }
  // Del producto y de la marca es lo que nos toca; de pedidos y patrones, no.
  if (str_contains($texto, 'tec_product__' . CAMPO)
    || str_contains($texto, 'tec_product.' . CAMPO)
    || str_contains($nombre, 'tec_product.tec_product')
    || str_contains($nombre, 'taxonomy_term.tec_brands')) {
    $sospechosas[] = $nombre;
  }
}

print "\n";
print "---------------------------------------------------------------------\n";
if ($hechas) {
  print "  Barrido:\n";
  foreach ($hechas as $una) {
    print '    - ' . $una . "\n";
  }
}
else {
  print "  Nada que barrer. Ya estaba todo limpio.\n";
}

if ($sospechosas) {
  print "\n  OJO, esto sigue nombrando al cliente del producto o de la marca:\n";
  foreach ($sospechosas as $una) {
    print '    - ' . $una . "\n";
  }
  print "  Miralo a mano. Este guion no sabe que hacer con eso.\n";
}
else {
  print "\n  No queda ninguna configuracion del producto ni de la marca que lo nombre.\n";
}
print "---------------------------------------------------------------------\n\n";
