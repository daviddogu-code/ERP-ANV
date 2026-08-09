<?php

namespace Drupal\commerce_pricelist;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides routes for the price list entity.
 */
class PriceListRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    if ($entity_type->hasLinkTemplate('reorder')) {
      $reorder_form_route = $this->getCollectionRoute($entity_type);
      $reorder_form_route->setPath($entity_type->getLinkTemplate('reorder'));
      $collection->add('entity.commerce_pricelist.reorder', $reorder_form_route);
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    // Use addTitle instead of addBundleTitle because "Add product variation"
    // sounds more confusing than "Add price list".
    $route->setDefault('_title_callback', EntityController::class . '::addTitle');

    return $route;
  }

}
