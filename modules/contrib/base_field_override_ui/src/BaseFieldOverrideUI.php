<?php

namespace Drupal\base_field_override_ui;

use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;

/**
 * Static service container wrapper for Base Field Override UI.
 */
class BaseFieldOverrideUI extends FieldUI {

  /**
   * Returns the route info for the field overview of a given entity bundle.
   *
   * @param string $entity_type_id
   *   An entity type.
   * @param string $bundle
   *   The entity bundle.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public static function getOverviewRouteInfo($entity_type_id, $bundle) {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    if ($entity_type->get('field_ui_base_route')) {
      return new Url("entity.base_field_override.{$entity_type_id}.base_field_override_ui_fields", static::getRouteBundleParameter($entity_type, $bundle));
    }
  }

  /**
   * Returns the route info for add a new configuration.
   *
   * @param \Drupal\Core\Field\Entity\BaseFieldOverride $config
   *   The base field override entity.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public static function getAddRouteInfo(BaseFieldOverride $config) {
    $entity_type_id = $config->getTargetEntityTypeId();
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $route_parameters = [
      'base_field_name' => $config->getName(),
    ] + self::getRouteBundleParameter($entity_type, $config->getTargetBundle());
    if ($entity_type->get('field_ui_base_route')) {
      return new Url("entity.base_field_override.{$entity_type_id}_base_field_override_add_form", $route_parameters);
    }
  }

  /**
   * Returns the route info for edit the configuration.
   *
   * @param \Drupal\Core\Field\Entity\BaseFieldOverride $config
   *   The base field override entity.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public static function getEditRouteInfo(BaseFieldOverride $config) {
    $entity_type_id = $config->getTargetEntityTypeId();
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $route_parameters = [
      'base_field_override' => $config->id(),
    ] + self::getRouteBundleParameter($entity_type, $config->getTargetBundle());
    if ($entity_type->get('field_ui_base_route')) {
      return new Url("entity.base_field_override.{$entity_type_id}_base_field_override_edit_form", $route_parameters);
    }
  }

  /**
   * Returns the route info for delete the configuration.
   *
   * @param \Drupal\Core\Field\Entity\BaseFieldOverride $config
   *   The base field override entity.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public static function getDeleteRouteInfo(BaseFieldOverride $config) {
    $entity_type_id = $config->getTargetEntityTypeId();
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $route_parameters = [
      'base_field_override' => $config->id(),
    ] + self::getRouteBundleParameter($entity_type, $config->getTargetBundle());
    if ($entity_type->get('field_ui_base_route')) {
      return new Url("entity.base_field_override.{$entity_type_id}_base_field_override_delete_form", $route_parameters);
    }
  }

  /**
   * Returns the route info for translate the configuration.
   *
   * @param \Drupal\Core\Field\Entity\BaseFieldOverride $config
   *   The base field override entity.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public static function getTranslateRouteInfo(BaseFieldOverride $config) {
    $entity_type_id = $config->getTargetEntityTypeId();
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $route_parameters = [
      'base_field_override' => $config->id(),
    ] + self::getRouteBundleParameter($entity_type, $config->getTargetBundle());
    if ($entity_type->get('field_ui_base_route')) {
      return new Url("entity.base_field_override_config.config_translation_overview.{$entity_type_id}", $route_parameters);
    }
  }

}
