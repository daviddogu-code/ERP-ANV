<?php

namespace Drupal\commerce_pricelist;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;

class PriceListPriceResolver implements PriceResolverInterface {

  /**
   * The price list repository.
   *
   * @var \Drupal\commerce_pricelist\PriceListRepositoryInterface
   */
  protected $priceListRepository;

  /**
   * Constructs a new PriceListPriceResolver.
   *
   * @param \Drupal\commerce_pricelist\PriceListRepositoryInterface $price_list_repository
   *   The price list repository.
   */
  public function __construct(PriceListRepositoryInterface $price_list_repository) {
    $this->priceListRepository = $price_list_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {
    $price = NULL;
    $price_list_item = $this->priceListRepository->loadItem($entity, $quantity, $context);
    if ($price_list_item) {
      $field_name = $context->getData('field_name', 'price');
      if ($field_name == 'list_price') {
        $price = $price_list_item->getListPrice();
      }
      elseif ($field_name == 'price') {
        $price = $price_list_item->getPrice();
      }
    }

    return $price;
  }

}
