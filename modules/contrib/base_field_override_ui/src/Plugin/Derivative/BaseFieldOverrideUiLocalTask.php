<?php

namespace Drupal\base_field_override_ui\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity bundles.
 */
class BaseFieldOverrideUiLocalTask extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->routeProvider = $container->get('router.route_provider');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->stringTranslation = $container->get('string_translation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->get('field_ui_base_route')) {
        $this->derivatives["overview_$entity_type_id"] = [
          'route_name' => "entity.$entity_type_id.field_ui_fields",
          'weight' => 1,
          'title' => $this->t('Fields'),
          'parent_id' => "field_ui.fields:overview_$entity_type_id",
        ];

        $this->derivatives["base_field_override_overview_$entity_type_id"] = [
          'route_name' => "entity.base_field_override.{$entity_type_id}.base_field_override_ui_fields",
          'weight' => 2,
          'title' => $this->t('Base fields Override'),
          'parent_id' => "field_ui.fields:overview_$entity_type_id",
        ];

        $this->derivatives["base_field_override_edit_$entity_type_id"] = [
          'route_name' => "entity.base_field_override.{$entity_type_id}_base_field_override_edit_form",
          'title' => $this->t('Edit'),
          'base_route' => "entity.base_field_override.{$entity_type_id}_base_field_override_edit_form",
        ];
      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
