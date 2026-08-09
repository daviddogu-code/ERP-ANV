<?php

namespace Drupal\base_field_override_ui\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for base field override routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route_name = $entity_type->get('field_ui_base_route')) {
        // Try to get the route from the current collection.
        if (!$entity_route = $collection->get($route_name)) {
          continue;
        }
        $path = $entity_route->getPath();

        $options = $entity_route->getOptions();
        if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
          $options['parameters'][$bundle_entity_type] = [
            'type' => 'entity:' . $bundle_entity_type,
          ];
        }
        $options['_field_ui'] = TRUE;

        $defaults = [
          'entity_type_id' => $entity_type_id,
        ];
        // If the entity type has no bundles and it doesn't use {bundle} in its
        // admin path, use the entity type.
        if (strpos($path, '{bundle}') === FALSE) {
          $defaults['bundle'] = !$entity_type->hasKey('bundle') ? $entity_type_id : '';
        }

        $route = new Route(
          "$path/fields/base-field-override/{base_field_name}/add",
          [
            '_controller' => '\Drupal\base_field_override_ui\Controller\BaseFieldOverrideController::add',
            '_title_callback' => '\Drupal\base_field_override_ui\Controller\BaseFieldOverrideController::getAddTitle',
          ] + $defaults,
          ['_custom_access' => '\Drupal\base_field_override_ui\Controller\BaseFieldOverrideController::addAccess'],
          $options
        );
        $collection->add("entity.base_field_override.{$entity_type_id}_base_field_override_add_form", $route);

        $route = new Route(
          "$path/fields/base-field-override/{base_field_override}",
          [
            '_entity_form' => 'base_field_override.edit',
            '_title_callback' => '\Drupal\base_field_override_ui\Form\BaseFieldOverrideForm::getTitle',
          ] + $defaults,
          ['_entity_access' => 'base_field_override.update'],
          $options
        );
        $collection->add("entity.base_field_override.{$entity_type_id}_base_field_override_edit_form", $route);

        $route = new Route(
          "$path/fields/base-field-override/{base_field_override}/delete",
          [
            '_entity_form' => 'base_field_override.delete',
          ] + $defaults,
          ['_entity_access' => 'base_field_override.delete'],
          $options
        );
        $collection->add("entity.base_field_override.{$entity_type_id}_base_field_override_delete_form", $route);

        $route = new Route(
          "$path/fields/base-field-override",
          [
            '_controller' => '\Drupal\base_field_override_ui\Controller\BaseFieldOverrideController::listing',
            '_title' => 'Manage base fields',
          ] + $defaults,
          ['_permission' => 'administer ' . $entity_type_id . ' fields'],
          $options
        );
        $collection->add("entity.base_field_override.{$entity_type_id}.base_field_override_ui_fields", $route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -101];
    return $events;
  }

}
