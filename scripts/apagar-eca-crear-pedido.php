<?php

/**
 * Disable ECA process_sclj26d so Place an order no longer creates a SO of zeros.
 *
 *   php vendor/bin/drush.php scr scripts/apagar-eca-crear-pedido.php
 */
\Drupal::moduleHandler()->loadInclude('tec_portal', 'install');
tec_portal_update_8004();
$eca = \Drupal::entityTypeManager()->getStorage('eca')->load('process_sclj26d');
print 'eca process_sclj26d status=' . ($eca && $eca->status() ? 'on' : 'off') . "\n";
