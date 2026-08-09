<?php

namespace Drupal\commerce_pricelist\Controller;

use Drupal\commerce_pricelist\Entity\PriceListInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class PriceListController {

  use StringTranslationTrait;

  /**
   * Provides the title callback for the price list items collection route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return string
   *   The title.
   */
  public function priceListItemsTitle(RouteMatchInterface $route_match) {
    $price_list = $route_match->getParameter('commerce_pricelist');
    assert($price_list instanceof PriceListInterface);
    return $this->t('Prices for %label', ['%label' => $price_list->label()]);
  }

}
