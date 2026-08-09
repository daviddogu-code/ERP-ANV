<?php

namespace Drupal\commerce_pricelist;

use Drupal\commerce_pricelist\Controller\PriceListController;
use Drupal\commerce_pricelist\Form\PriceListItemExportForm;
use Drupal\commerce_pricelist\Form\PriceListItemImportForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the price list item entity.
 */
class PriceListItemRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $entity_type_id = $entity_type->id();
    if ($export_route = $this->getExportFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.export_form", $export_route);
    }
    if ($import_route = $this->getImportFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.import_form", $import_route);
    }
    foreach (['enable', 'disable'] as $operation) {
      if ($form_route = $this->getEnableDisableFormRoute($entity_type, $operation)) {
        $collection->add("entity.{$entity_type_id}.{$operation}_form", $form_route);
      }
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    $route->setOption('parameters', [
      'commerce_pricelist' => [
        'type' => 'entity:commerce_pricelist',
      ],
    ]);
    // Replace the "Add price list item" title with "Add price".
    // The t() function is used to ensure the string is picked up for
    // translation, even though _title is supposed to be untranslated.
    $route->setDefault('_title_callback', '');
    $route->setDefault('_title', t('Add price')->getUntranslatedString());

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getEditFormRoute($entity_type);
    $route->setOption('parameters', [
      'commerce_pricelist' => [
        'type' => 'entity:commerce_pricelist',
      ],
    ]);

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    $route->setOption('parameters', [
      'commerce_pricelist' => [
        'type' => 'entity:commerce_pricelist',
      ],
    ]);
    // AdminHtmlRouteProvider sets _admin_route for all routes except this one.
    $route->setOption('_admin_route', TRUE);
    $route->setDefault('_title_callback', PriceListController::class . '::priceListItemsTitle');

    return $route;
  }

  /**
   * Gets the export-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getExportFormRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('export-form')) {
      $route = new Route($entity_type->getLinkTemplate('export-form'));
      $route
        ->setDefaults([
          '_form' => PriceListItemExportForm::class,
          '_title' => 'Export prices',
        ])
        ->setRequirement('_permission', $entity_type->getAdminPermission())
        ->setRequirement('commerce_pricelist', '\d+')
        ->setOption('parameters', [
          'commerce_pricelist' => ['type' => 'entity:commerce_pricelist'],
        ])
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the import-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getImportFormRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('import-form')) {
      $route = new Route($entity_type->getLinkTemplate('import-form'));
      $route
        ->setDefaults([
          '_form' => PriceListItemImportForm::class,
          '_title' => 'Import prices',
        ])
        ->setRequirement('_permission', $entity_type->getAdminPermission())
        ->setRequirement('commerce_pricelist', '\d+')
        ->setOption('parameters', [
          'commerce_pricelist' => ['type' => 'entity:commerce_pricelist'],
        ])
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the enable/disable form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $operation
   *   The 'operation' (e.g 'disable', 'enable').
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEnableDisableFormRoute(EntityTypeInterface $entity_type, $operation) {
    if ($entity_type->hasLinkTemplate($operation . '-form')) {
      $route = new Route($entity_type->getLinkTemplate($operation . '-form'));
      $route
        ->addDefaults([
          '_entity_form' => "commerce_pricelist_item.$operation",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
        ])
        ->setRequirement('_permission', $entity_type->getAdminPermission())
        ->setOption('parameters', [
          'commerce_pricelist' => [
            'type' => 'entity:commerce_pricelist',
          ],
        ])
        ->setRequirement('commerce_pricelist', '\d+')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

}
