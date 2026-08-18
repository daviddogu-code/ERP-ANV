<?php

/**
 * @file
 * La ficha del cliente ensena sus marcas, no solo su formulario.
 *
 *   php vendor\bin\drush.php scr scripts/la-ficha-del-cliente-ensena-sus-marcas.php
 *
 * Hasta hoy las marcas que compra un cliente solo se veian entrando a editarlo. En
 * la ficha no salian, y eso deja un agujero concreto: una marca recien asignada y
 * todavia sin productos no aparece en ningun sitio de la ficha, porque la pestana
 * Products agrupa por marca y una marca sin filas no pinta grupo. Asi que el
 * cliente parecia no tener esa marca.
 *
 * Se ponen al lado del tipo de contacto, con el mismo aspecto de etiqueta, y cada
 * una enlazando a su marca: desde ahi se le pueden crear productos.
 *
 * La ficha se pinta con Layout Builder, asi que no basta con sacar el campo de la
 * lista de escondidos: hay que anadir el bloque a la maqueta.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir.
 */

const CAMPO = 'field_tec_brands';
const PANTALLA = 'core.entity_view_display.tec_crm.tec_contact_organization.default';
const REGION = 'blb_region_col_1';
const BLOQUE = 'field_block:tec_crm:tec_contact_organization:field_tec_brands';

print "\n";
print "=====================================================================\n";
print "  La ficha del cliente ensena sus marcas\n";
print "=====================================================================\n\n";

$config = \Drupal::configFactory()->getEditable(PANTALLA);
if ($config->isNew()) {
  print "  No existe " . PANTALLA . ". Nada que hacer.\n\n";
  return;
}

$secciones = $config->get('third_party_settings.layout_builder.sections') ?? [];
if (!$secciones) {
  print "  Esa ficha no usa Layout Builder. Este guion no sirve aqui.\n\n";
  return;
}

$componentes = $secciones[0]['components'] ?? [];

// Ya puesto: nada que hacer.
foreach ($componentes as $componente) {
  if (($componente['configuration']['id'] ?? '') === BLOQUE) {
    printf("  Las marcas ya estan en la maqueta, en la region %s.\n\n", $componente['region'] ?? '?');
    return;
  }
}

// El tipo de contacto es el vecino: se copia su aspecto de etiqueta y se pone
// justo detras, para que las dos cosas que dicen «que es este contacto» vayan
// juntas.
$vecino = NULL;
foreach ($componentes as $componente) {
  if (($componente['configuration']['id'] ?? '') === 'field_block:tec_crm:tec_contact_organization:field_tec_contact_type') {
    $vecino = $componente;
    break;
  }
}

$peso = $vecino ? ((int) ($vecino['weight'] ?? 1)) + 1 : 2;
$uuid = \Drupal::service('uuid')->generate();

$nuevo = [
  'uuid' => $uuid,
  'region' => $vecino['region'] ?? REGION,
  'configuration' => [
    'id' => BLOQUE,
    'label' => 'Brands',
    'label_display' => '0',
    'provider' => 'layout_builder',
    'context_mapping' => [
      'entity' => 'layout_builder.entity',
      'view_mode' => 'view_mode',
    ],
    'formatter' => [
      'type' => 'entity_reference_label',
      'label' => 'hidden',
      'settings' => [
        // Con enlace: la marca es el sitio donde se le crean productos.
        'link' => TRUE,
      ],
      'third_party_settings' => [
        'field_formatter_class' => ['class' => ''],
      ],
    ],
  ],
  'weight' => $peso,
  'additional' => [],
];

// El aspecto de etiqueta del vecino, si lo tiene.
if ($vecino && isset($vecino['additional'])) {
  $nuevo['additional'] = $vecino['additional'];
}

$secciones[0]['components'][$uuid] = $nuevo;
$config->set('third_party_settings.layout_builder.sections', $secciones);

// Y fuera de la lista de escondidos, con su formateador, para que la pantalla de
// «gestionar la presentacion» diga la verdad sobre lo que se ensena.
$escondidos = $config->get('hidden') ?? [];
unset($escondidos[CAMPO]);
$config->set('hidden', $escondidos);

$contenido = $config->get('content') ?? [];
if (!isset($contenido[CAMPO])) {
  $contenido[CAMPO] = [
    'type' => 'entity_reference_label',
    'label' => 'above',
    'settings' => ['link' => TRUE],
    'third_party_settings' => [],
    'weight' => $peso + 10,
    'region' => $vecino['region'] ?? REGION,
  ];
  $config->set('content', $contenido);
}

$config->save();

printf("  Puestas en la maqueta, region %s, peso %d.\n", $nuevo['region'], $peso);
print "  Cada marca enlaza a su pagina, que es donde se le crean productos.\n";
print "  En un contacto que no compra ninguna marca no se pinta nada.\n\n";
