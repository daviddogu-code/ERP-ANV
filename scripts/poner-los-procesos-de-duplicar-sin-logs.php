<?php

/**
 * @file
 * Pone en marcha los procesos de duplicar ya sin Logs de depuracion.
 *
 *   php vendor/bin/drush.php scr scripts/poner-los-procesos-de-duplicar-sin-logs.php
 *
 * El cim completo no se puede: hay deriva en otras configs. Este guion lee
 * los YAML de config/sync y los escribe en la config activa, que es lo que
 * ECA corre. El dibujo (eca.model) va con ellos: si alguien abre el modelador
 * y guarda, no deben volver los cajones Log.
 */

use Drupal\Core\Config\FileStorage;

const PROCESOS = [
  'process_fc3hjta' => '1.6',
  'process_u1bemhe' => '1.5',
  'process_llpx4tp' => '1.6',
  'process_hkreor6' => '1.1',
  'process_ocmwa9c' => '0.5',
  'process_qlf96jn' => '1.1',
  'process_dxgyftk' => '1.3',
  'process_b20b2si' => '1.1',
];

$disco = new FileStorage(\Drupal::root() . '/config/sync');
$fabrica = \Drupal::configFactory();
$problemas = 0;

print "\nPoniendo los procesos de duplicar sin logs\n";
print str_repeat('-', 70) . "\n";

foreach (array_keys(PROCESOS) as $id) {
  foreach (['eca.eca.', 'eca.model.'] as $prefijo) {
    $nombre = $prefijo . $id;
    $datos = $disco->read($nombre);
    if ($datos === FALSE) {
      printf("  MAL    no esta en disco %s\n", $nombre);
      $problemas++;
      continue;
    }

    $editable = $fabrica->getEditable($nombre);
    $uuidActivo = $editable->get('uuid');
    if ($uuidActivo && isset($datos['uuid']) && $datos['uuid'] !== $uuidActivo) {
      $datos['uuid'] = $uuidActivo;
    }
    $editable->setData($datos)->save();
  }

  $activo = $fabrica->get('eca.eca.' . $id);
  $version = (string) $activo->get('version');
  $esperada = PROCESOS[$id];
  $logs = 0;
  foreach ($activo->get('actions') ?? [] as $accion) {
    if (($accion['plugin'] ?? '') === 'eca_write_log_message') {
      $logs++;
    }
  }
  $ids = array_merge(
    array_keys($activo->get('events') ?? []),
    array_keys($activo->get('gateways') ?? []),
    array_keys($activo->get('actions') ?? [])
  );
  $sueltos = [];
  foreach (['events', 'gateways', 'actions'] as $seccion) {
    foreach ($activo->get($seccion) ?? [] as $nodo) {
      foreach ($nodo['successors'] ?? [] as $sig) {
        $sid = $sig['id'] ?? '';
        if ($sid !== '' && !in_array($sid, $ids, TRUE)) {
          $sueltos[] = $sid;
        }
      }
    }
  }

  $ok = $version === $esperada && $logs === 0 && $sueltos === [];
  if (!$ok) {
    $problemas++;
  }
  printf(
    "  %-6s %s  v%s  logs=%d%s\n",
    $ok ? 'BIEN' : 'MAL',
    $id,
    $version,
    $logs,
    $sueltos ? '  sueltos: ' . implode(',', array_unique($sueltos)) : ''
  );
}

$tostada = (string) ($fabrica->get('eca.eca.process_fc3hjta')->get('actions.Activity_0fha3xh.configuration.message') ?? '');
if ($tostada !== 'Color magically duplicated.') {
  printf("  MAL    falta el aviso al usuario: %s\n", $tostada === '' ? '(vacio)' : $tostada);
  $problemas++;
}
else {
  print "  BIEN   el aviso al usuario sigue: Color magically duplicated.\n";
}

print "\n";
if ($problemas) {
  throw new \RuntimeException("los procesos de duplicar no quedaron limpios ($problemas)");
}

drupal_flush_all_caches();
print "  Cache vaciada. Listo.\n\n";
