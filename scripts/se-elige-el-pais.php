<?php

/**
 * @file
 * The country list on a contact card behaves itself.
 *
 * Picking a country has to swap the address fields underneath it without
 * touching the rest of the page. For a long time it did the opposite: the
 * module stripped the Address AJAX because it was crashing the Apache worker,
 * and a hidden button reloaded the entire form on every change event. With two
 * hundred and fifty options in the list, any keystroke or stray scroll posted
 * the form half way through choosing, which read as the country deselecting
 * itself.
 *
 * Run: drush scr scripts/se-elige-el-pais.php
 */

use Drupal\Core\Session\UserSession;

$fallos = 0;
$decir = function (bool $bien, string $frase, string $detalle = '') use (&$fallos) {
  if (!$bien) {
    $fallos++;
  }
  printf("  [%s] %s%s\n", $bien ? 'bien' : 'MAL ', $frase, $detalle === '' ? '' : "  ({$detalle})");
};

\Drupal::service('account_switcher')->switchTo(new UserSession(['uid' => 1]));

try {
  foreach (['tec_contact_organization', 'tec_contact_person'] as $tipo) {
    print "\n{$tipo}\n";

    $contacto = \Drupal::entityTypeManager()->getStorage('tec_crm')->create(['type' => $tipo]);
    $formulario = \Drupal::service('entity.form_builder')->getForm($contacto, 'default');

    // The address_country wrapper holds the real select under the same key.
    $envoltorio = $formulario['field_tec_address']['widget'][0]['address']['country_code'] ?? NULL;
    $pais = $envoltorio['country_code'] ?? $envoltorio;
    $decir(is_array($pais), 'la ficha trae selector de pais');
    if (!is_array($pais)) {
      continue;
    }

    $decir(
      !empty($pais['#ajax']['callback']),
      'el pais cambia por ajax, sin recargar la pagina',
      empty($pais['#ajax']) ? 'no tiene #ajax: alguien lo ha vuelto a quitar' : ''
    );

    // An AJAX pointed at the wrong box replaces nothing and looks broken.
    $recuadro = $formulario['field_tec_address']['widget'][0]['address']['#wrapper_id'] ?? '';
    $decir(
      $recuadro !== '' && ($pais['#ajax']['wrapper'] ?? '') === $recuadro,
      'el ajax apunta al recuadro de la direccion',
      'apunta a ' . ($pais['#ajax']['wrapper'] ?? '(nada)')
    );

    $decir(
      !isset($formulario['tec_crm_ux_address_rebuild']),
      'no queda el boton escondido que recargaba la pagina entera'
    );

    $opciones = count($pais['#options'] ?? []);
    $decir($opciones > 200, 'la lista de paises esta completa', "{$opciones} paises");
  }

  // The point of the AJAX is that the country decides which fields exist. That
  // is the same rebuild the callback performs, so it can be asked directly:
  // set a country, build the form, see what the address grew.
  $campos_de = function (string $pais): array {
    $contacto = \Drupal::entityTypeManager()->getStorage('tec_crm')->create([
      'type' => 'tec_contact_organization',
      'field_tec_address' => [['country_code' => $pais]],
    ]);
    $formulario = \Drupal::service('entity.form_builder')->getForm($contacto, 'default');
    $direccion = $formulario['field_tec_address']['widget'][0]['address'] ?? [];

    $nombres = [];
    foreach (\Drupal\Core\Render\Element::children($direccion) as $clave) {
      if ($clave !== 'langcode') {
        $nombres[] = $clave;
      }
    }
    return $nombres;
  };

  print "\nEl pais manda sobre los campos\n";

  $tailandia = $campos_de('TH');
  $decir(in_array('administrative_area', $tailandia, TRUE), 'Tailandia pide provincia');
  print '       ' . implode(', ', $tailandia) . "\n";

  $singapur = $campos_de('SG');
  $decir(!in_array('administrative_area', $singapur, TRUE), 'Singapur no pide provincia, y no la saca');
  print '       ' . implode(', ', $singapur) . "\n";
}
finally {
  \Drupal::service('account_switcher')->switchBack();
}

echo "\n";
echo $fallos === 0
  ? "El pais se elige y se queda, y arrastra los campos que le tocan.\n\n"
  : "$fallos cosa(s) mal.\n\n";
