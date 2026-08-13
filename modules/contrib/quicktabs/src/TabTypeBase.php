<?php

namespace Drupal\quicktabs;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Base implementation for plugins that add tabbed output.
 */
abstract class TabTypeBase extends PluginBase implements EmptyTabTypeInterface {

  use DependencySerializationTrait;

  /**
   * Gets the name of the plugin.
   */
  protected function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function optionsForm(array $tab);

  /**
   * {@inheritdoc}
   */
  abstract public function render(array $tab);

  /**
   * {@inheritdoc}
   */
  public function isEmpty(array $tab, array $render, bool $is_ajax): bool {
    // A render array holding nothing but cacheability metadata has no content,
    // so it counts as empty. An access-denied tab is returned in that shape so
    // that the access result's cacheability survives.
    return empty(array_diff(array_keys($render), ['#cache']));
  }

}
