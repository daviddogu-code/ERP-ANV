<?php

namespace Drupal\base_field_override_ui\ConfigTranslation;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Configuration mapper for base field override configuration.
 */
class BaseFieldOverrideMapper extends ConfigEntityMapper {

  /**
   * Loaded entity instance to help produce the translation interface.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteParameters() {
    $parameters = parent::getBaseRouteParameters();
    $base_entity_info = $this->entityTypeManager->getDefinition($this->pluginDefinition['base_entity_type']);
    $bundle_parameter_key = $base_entity_info->getBundleEntityType() ?: 'bundle';
    $parameters[$bundle_parameter_key] = $this->entity->getTargetBundle();
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverviewRouteName() {
    return 'entity.base_field_override_config.config_translation_overview.' . $this->pluginDefinition['base_entity_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeLabel() {
    $base_entity_info = $this->entityTypeManager->getDefinition($this->pluginDefinition['base_entity_type']);
    return $this->t('@label base fields', ['@label' => $base_entity_info->getLabel()]);
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(ConfigEntityInterface $entity) {
    if (parent::setEntity($entity)) {

      $config = $this->configFactory->get("core.base_field_override.{$entity->id()}");
      if (!$config->isNew()) {
        $this->addConfigName($config->getName());
        return TRUE;
      }
    }
    return FALSE;
  }

}
