<?php

/**
 * @file
 * Post update functions for the Quick Tabs module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Opt existing Quick Tabs instances out of direct linking.
 *
 * Direct linking defaults to enabled for new instances. Instances that were
 * configured before this option existed are left disabled so their behavior
 * does not change on update; site builders can opt in per instance.
 */
function quicktabs_post_update_disable_direct_linking_for_existing(): void {
  $config_factory = \Drupal::configFactory();
  foreach ($config_factory->listAll('quicktabs.quicktabs_instance.') as $config_name) {
    $config = $config_factory->getEditable($config_name);
    // Only set a value when the option is missing, so an explicit choice made
    // between releases is never overwritten.
    if ($config->get('options.quick_tabs.direct_linking') === NULL) {
      $config->set('options.quick_tabs.direct_linking', FALSE)->save();
    }
  }
}

/**
 * Add remember_last_clicked_tab setting to existing quicktabs instances.
 */
function quicktabs_post_update_remember_last_clicked_tab_setting(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'quicktabs_instance', function ($quicktabs_instance) {
    /** @var \Drupal\quicktabs\Entity\QuickTabsInstance $quicktabs_instance */
    if ($quicktabs_instance->get('remember_last_clicked_tab') === NULL) {
      $quicktabs_instance->set('remember_last_clicked_tab', TRUE);
      return TRUE;
    }

    return FALSE;
  });
}
