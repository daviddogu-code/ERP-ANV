<?php

namespace Drupal\commerce_pricelist\Event;

final class PriceListEvents {

  /**
   * Name of the event fired after loading a price list.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListEvent
   */
  const PRICELIST_LOAD = 'commerce_pricelist.commerce_pricelist.load';

  /**
   * Name of the event fired after creating a new price list.
   *
   * Fired before the price list is saved.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListEvent
   */
  const PRICELIST_CREATE = 'commerce_pricelist.commerce_pricelist.create';

  /**
   * Name of the event fired before saving a price list.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListEvent
   */
  const PRICELIST_PRESAVE = 'commerce_pricelist.commerce_pricelist.presave';

  /**
   * Name of the event fired after saving a new price list.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListEvent
   */
  const PRICELIST_INSERT = 'commerce_pricelist.commerce_pricelist.insert';

  /**
   * Name of the event fired after saving an existing price list.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListEvent
   */
  const PRICELIST_UPDATE = 'commerce_pricelist.commerce_pricelist.update';

  /**
   * Name of the event fired before deleting a price list.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListEvent
   */
  const PRICELIST_PREDELETE = 'commerce_pricelist.commerce_pricelist.predelete';

  /**
   * Name of the event fired after deleting a price list.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListEvent
   */
  const PRICELIST_DELETE = 'commerce_pricelist.commerce_pricelist.delete';

  /**
   * Name of the event fired after loading a price list item.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListItemEvent
   */
  const PRICELIST_ITEM_LOAD = 'commerce_pricelist.commerce_pricelist_item.load';

  /**
   * Name of the event fired after creating a new price list item.
   *
   * Fired before the price list item is saved.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListItemEvent
   */
  const PRICELIST_ITEM_CREATE = 'commerce_pricelist.commerce_pricelist_item.create';

  /**
   * Name of the event fired before saving a price list item.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListItemEvent
   */
  const PRICELIST_ITEM_PRESAVE = 'commerce_pricelist.commerce_pricelist_item.presave';

  /**
   * Name of the event fired after saving a new price list item.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListItemEvent
   */
  const PRICELIST_ITEM_INSERT = 'commerce_pricelist.commerce_pricelist_item.insert';

  /**
   * Name of the event fired after saving an existing price list item.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListItemEvent
   */
  const PRICELIST_ITEM_UPDATE = 'commerce_pricelist.commerce_pricelist_item.update';

  /**
   * Name of the event fired before deleting a price list item.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListItemEvent
   */
  const PRICELIST_ITEM_PREDELETE = 'commerce_pricelist.commerce_pricelist_item.predelete';

  /**
   * Name of the event fired after deleting a price list item.
   *
   * @Event
   *
   * @see \Drupal\commerce_pricelist\Event\PriceListItemEvent
   */
  const PRICELIST_ITEM_DELETE = 'commerce_pricelist.commerce_pricelist_item.delete';

}
