<?php

/**
 * @file
 * Lista las cuentas de usuario que quedan y cuando se toco cada una.
 *
 * Uso: php vendor/bin/drush.php scr scripts/que-cuentas-quedan.php
 *
 * La comprobacion del ERP espera seis cuentas y encuentra cinco. Falta una, y
 * antes de dar por buena esa cifra hay que saber cual y quien se la llevo: si es
 * una cuenta de verdad, lo que hay que arreglar es la cuenta y no el numero.
 *
 * No toca nada.
 */

$bd = \Drupal::database();

print "\n";
printf("  %-5s %-18s %-34s %-10s %-12s %s\n", 'uid', 'nombre', 'correo', 'estado', 'creada', 'roles');
print '  ' . str_repeat('-', 104) . "\n";

foreach (\Drupal::entityTypeManager()->getStorage('user')->loadMultiple() as $u) {
  printf("  %-5s %-18s %-34s %-10s %-12s %s\n",
    $u->id(),
    $u->id() ? $u->getAccountName() : '(anonimo)',
    $u->getEmail() ?: '(sin correo)',
    $u->isActive() ? 'activa' : 'bloqueada',
    $u->getCreatedTime() ? date('Y-m-d', $u->getCreatedTime()) : '?',
    implode(',', $u->getRoles()));
}

$n = (int) $bd->select('users')->countQuery()->execute()->fetchField();
printf("\n  %d filas en la tabla users (incluye el anonimo)\n", $n);

// Los huecos en la serie de uid dicen que cuentas han desaparecido.
$ids = $bd->query('SELECT uid FROM {users} ORDER BY uid')->fetchCol();
printf("  uid que hay: %s\n", implode(', ', $ids));
$faltan = array_diff(range(0, (int) max($ids)), array_map('intval', $ids));
printf("  uid que faltan en la serie: %s\n", $faltan ? implode(', ', $faltan) : 'ninguno');

// Y lo que diga el registro sobre borrados de cuentas.
print "\n  lo que dice el registro sobre cuentas:\n\n";
$filas = $bd->query("
  SELECT wid, type, timestamp, message, variables
  FROM {watchdog}
  WHERE type IN ('user') OR message LIKE '%deleted%user%' OR message LIKE '%Deleted user%'
  ORDER BY wid DESC LIMIT 15
")->fetchAll();

if (!$filas) {
  print "    nada\n";
}
foreach ($filas as $f) {
  $vars = @unserialize($f->variables) ?: [];
  $mensaje = strtr($f->message, array_map(static fn($v) => is_scalar($v) ? (string) $v : '?', $vars));
  printf("    %s  %-8s %s\n", date('Y-m-d H:i', (int) $f->timestamp), $f->type, substr(strtr($mensaje, ["\n" => ' ']), 0, 110));
}

print "\n";
