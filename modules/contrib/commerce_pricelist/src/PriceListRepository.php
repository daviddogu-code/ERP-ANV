<?php

namespace Drupal\commerce_pricelist;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class PriceListRepository implements PriceListRepositoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A static cache of loaded price list items.
   *
   * @var array
   */
  protected $priceListItems = [];

  /**
   * A static cache of loaded price list IDs.
   *
   * @var array
   */
  protected $priceListIds = [];

  /**
   * Constructs a new PriceListRepository.
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
  public function loadItem(PurchasableEntityInterface $entity, $quantity, Context $context) {
    $price_list_items = $this->loadItems($entity, $context);
    if (empty($price_list_items)) {
      return NULL;
    }

    /** @var  \Drupal\commerce_pricelist\Entity\PriceListItemInterface[] $price_list_items */
    $price_list_items = array_filter($price_list_items, function ($price_list_item) use ($quantity) {
      return $price_list_item->getQuantity() <= $quantity;
    });
    $price_list_ids = $this->loadPriceListIds($entity->getEntityTypeId(), $context);
    $price_list_item = $this->selectPriceListItem($price_list_items, $price_list_ids);

    return $price_list_item;
  }

  /**
   * {@inheritdoc}
   */
  public function loadItems(PurchasableEntityInterface $entity, Context $context) {
    $customer_id = $context->getCustomer()->id();
    $store_id = $context->getStore()->id();
    $date = DrupalDateTime::createFromTimestamp($context->getTime(), $context->getStore()->getTimezone());
    $now = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $cache_key = implode(':', [$entity->id(), $customer_id, $store_id, $now]);
    if (array_key_exists($cache_key, $this->priceListItems)) {
      return $this->priceListItems[$cache_key];
    }

    $price_list_ids = $this->loadPriceListIds($entity->getEntityTypeId(), $context);
    if (empty($price_list_ids)) {
      $this->priceListItems[$cache_key] = [];
      return [];
    }

    $price_list_item_storage = $this->entityTypeManager->getStorage('commerce_pricelist_item');
    $query = $price_list_item_storage->getQuery();
    $query
      ->condition('type', $entity->getEntityTypeId())
      ->condition('price_list_id', $price_list_ids, 'IN')
      ->condition('purchasable_entity', $entity->id())
      ->condition('status', TRUE)
      ->addTag('commerce_pricelist_item_query')
      ->addMetaData('price_list_ids', $price_list_ids)
      ->addMetaData('customer_id', $customer_id)
      ->addMetaData('store_id', $store_id)
      ->addMetaData('context', $context)
      ->sort('quantity')
      ->sort('price_list_id.entity.weight');
    $result = $query->accessCheck(FALSE)->execute();

    $price_list_items = [];
    if (!empty($result)) {
      $price_list_items = $price_list_item_storage->loadMultiple($result);
    }
    $this->priceListItems[$cache_key] = $price_list_items;

    return $price_list_items;
  }

  /**
   * Selects the best matching price list item, based on quantity and weight.
   *
   * Assumes that price list items are ordered by quantity, and that
   * price list IDs are ordered by weight.
   *
   * @param \Drupal\commerce_pricelist\Entity\PriceListItemInterface[] $price_list_items
   *   The price list items.
   * @param int[] $price_list_ids
   *   The price list IDs.
   *
   * @return \Drupal\commerce_pricelist\Entity\PriceListItemInterface
   *   The selected price list item.
   */
  protected function selectPriceListItem(array $price_list_items, array $price_list_ids) {
    if (count($price_list_items) > 1) {
      // Multiple matching price list items found.
      // First, reduce to one per price list, by selecting the quantity tier.
      $grouped_price_list_items = [];
      foreach ($price_list_items as $price_list_item) {
        $price_list_id = $price_list_item->getPriceListId();
        $grouped_price_list_items[$price_list_id] = $price_list_item;
      }
      // Then, select the one whose price list has the smallest weight.
      $price_list_weights = [];
      foreach ($grouped_price_list_items as $price_list_id => $price_list_item) {
        $price_list_weight = array_search($price_list_id, $price_list_ids);
        $price_list_weights[$price_list_id] = $price_list_weight;
      }
      asort($price_list_weights);
      $sorted_price_list_ids = array_keys($price_list_weights);
      $price_list_id = reset($sorted_price_list_ids);
      $price_list_item = $grouped_price_list_items[$price_list_id];
    }
    else {
      $price_list_item = reset($price_list_items);
    }

    return $price_list_item;
  }

  /**
   * Loads the available price list IDs for the given bundle and context.
   *
   * @param string $bundle
   *   The price list bundle.
   * @param \Drupal\commerce\Context $context
   *   The context.
   *
   * @return int[]
   *   The price list IDs.
   */
  protected function loadPriceListIds($bundle, Context $context) {
    $customer_id = $context->getCustomer()->id();
    $store_id = $context->getStore()->id();
    $date = DrupalDateTime::createFromTimestamp($context->getTime(), $context->getStore()->getTimezone());
    $now = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $cache_key = implode(':', [$bundle, $customer_id, $store_id, $now]);
    if (array_key_exists($cache_key, $this->priceListIds)) {
      return $this->priceListIds[$cache_key];
    }

    $price_list_storage = $this->entityTypeManager->getStorage('commerce_pricelist');
    $query = $price_list_storage->getQuery();
    $query
      ->condition('type', $bundle)
      ->condition('stores', [$store_id], 'IN')
      ->condition($query->orConditionGroup()
        ->condition('customers', $customer_id)
        ->notExists('customers')
      )
      ->condition($query->orConditionGroup()
        ->condition('customer_roles', $context->getCustomer()->getRoles(), 'IN')
        ->notExists('customer_roles')
      )
      ->condition('start_date', $now, '<=')
      ->condition($query->orConditionGroup()
        ->condition('end_date', $now, '>')
        ->notExists('end_date')
      )
      ->condition('status', TRUE)
      ->sort('weight')
      ->sort('id', 'DESC')
      ->addTag('commerce_pricelist_query')
      ->addMetaData('customer_id', $customer_id)
      ->addMetaData('store_id', $store_id)
      ->addMetaData('context', $context);
    $result = $query->accessCheck(FALSE)->execute();
    $price_list_ids = array_values($result);
    $this->priceListIds[$cache_key] = $price_list_ids;

    return $price_list_ids;
  }

}
