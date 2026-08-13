<?php

namespace Drupal\eca_tamper\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\Hook\ConfigSchemaHooksTrait;
use Drupal\tamper\TamperManagerInterface;

/**
 * Provides hooks related to config schemas.
 */
class ConfigSchemaHooks {

  use ConfigSchemaHooksTrait;

  /**
   * Constructs the config schema hook object.
   */
  public function __construct(
    protected TamperManagerInterface $tamperManager,
  ) {}

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    foreach ($this->tamperManager->getDefinitions() as $definition) {
      $key = 'tamper.' . $definition['id'];
      if (isset($definitions[$key])) {
        $this->alterSchemaFieldType($definitions, $key);
      }
    }
  }

}
