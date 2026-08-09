<?php

namespace Drupal\commerce_pricelist\Entity;

use Drupal\commerce_store\Entity\EntityStoresInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Defines the interface for price lists.
 */
interface PriceListInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityStoresInterface {

  /**
   * Gets the price list name.
   *
   * @return string
   *   The price list name.
   */
  public function getName();

  /**
   * Sets the price list name.
   *
   * @param string $name
   *   The price list name.
   *
   * @return $this
   */
  public function setName($name);

  /**
   * Gets the customer.
   *
   * @return \Drupal\user\UserInterface|null
   *   The customer user entity, or NULL if the price list is not limited to a
   *   specific customer.
   *
   * @deprecated in commerce_pricelist:8.x-2.0 and is removed from
   *   commerce_pricelist:8.x-3.0. Use $this->getCustomers() instead.
   *
   * @see https://www.drupal.org/project/commerce_pricelist/issues/3171289
   */
  public function getCustomer();

  /**
   * Gets the customers.
   *
   * @return \Drupal\user\UserInterface|null
   *   The customers user entities, or NULL if the price list is not limited to
   *   specific customers.
   */
  public function getCustomers();

  /**
   * Sets the customer.
   *
   * @param \Drupal\user\UserInterface $user
   *   The customer.
   *
   * @return $this
   *
   * @deprecated in commerce_pricelist:8.x-2.0 and is removed from
   *   commerce_pricelist:8.x-3.0. Use $this->setCustomers() instead.
   *
   * @see https://www.drupal.org/project/commerce_pricelist/issues/3171289
   */
  public function setCustomer(UserInterface $user);

  /**
   * Sets the customers.
   *
   * @param \Drupal\user\UserInterface[] $users
   *   The customers.
   *
   * @return $this
   */
  public function setCustomers(array $users);

  /**
   * Gets the customer ID.
   *
   * @return int|null
   *   The customer ID, or NULL if the price list is not limited to a specific
   *   customer.
   *
   * @deprecated in commerce_pricelist:8.x-2.0 and is removed from
   *   commerce_pricelist:8.x-3.0. Use $this->getCustomers() instead.
   *
   * @see https://www.drupal.org/project/commerce_pricelist/issues/3171289
   */
  public function getCustomerId();

  /**
   * Sets the customer ID.
   *
   * @param int $uid
   *   The customer ID.
   *
   * @return $this
   *
   * @deprecated in commerce_pricelist:8.x-2.0 and is removed from
   *   commerce_pricelist:8.x-3.0. Use $this->setCustomers() instead.
   *
   * @see https://www.drupal.org/project/commerce_pricelist/issues/3171289
   */
  public function setCustomerId($uid);

  /**
   * Gets the customer roles.
   *
   * @return string[]|null
   *   The customer role IDs, or NULL if the price list is not limited to
   *   specific customer roles.
   */
  public function getCustomerRoles();

  /**
   * Sets the customer roles.
   *
   * @param string[] $rids
   *   The role IDs.
   *
   * @return $this
   */
  public function setCustomerRoles(array $rids);

  /**
   * Gets the start date/time.
   *
   * The start date/time should always be used in the store timezone.
   * Since the price list can belong to multiple stores, the timezone
   * isn't known at load/save time, and is provided by the caller instead.
   *
   * Note that the returned date/time value is the same in any timezone,
   * the "2019-10-17 10:00" stored value is returned as "2019-10-17 10:00 CET"
   * for "Europe/Berlin" and "2019-10-17 10:00 ET" for "America/New_York".
   *
   * @param string $store_timezone
   *   The store timezone. E.g. "Europe/Berlin".
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The start date/time, NULL if not set.
   */
  public function getStartDate($store_timezone = 'UTC'): ?DrupalDateTime;

  /**
   * Sets the start date.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date.
   *
   * @return $this
   */
  public function setStartDate(DrupalDateTime $start_date);

  /**
   * Gets the end date/time.
   *
   * The end date/time should always be used in the store timezone.
   * Since the price list can belong to multiple stores, the timezone
   * isn't known at load/save time, and is provided by the caller instead.
   *
   * Note that the returned date/time value is the same in any timezone,
   * the "2019-10-17 11:00" stored value is returned as "2019-10-17 11:00 CET"
   * for "Europe/Berlin" and "2019-10-17 11:00 ET" for "America/New_York".
   *
   * @param string $store_timezone
   *   The store timezone. E.g. "Europe/Berlin".
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The end/time date, NULL if not set.
   */
  public function getEndDate($store_timezone = 'UTC'): ?DrupalDateTime;

  /**
   * Sets the end date.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The end date.
   *
   * @return $this
   */
  public function setEndDate(DrupalDateTime $end_date);

  /**
   * Gets the weight.
   *
   * @return int
   *   The weight.
   */
  public function getWeight();

  /**
   * Sets the weight.
   *
   * @param int $weight
   *   The weight.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Get whether the price list is enabled.
   *
   * @return bool
   *   TRUE if the price list is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets whether the price list is enabled.
   *
   * @param bool $enabled
   *   Whether the price list is enabled.
   *
   * @return $this
   */
  public function setEnabled($enabled);

  /**
   * Gets the price list item IDs.
   *
   * No matching getItems() method is provided because there can potentially
   * be thousands of items in a single list, making it too costly to load them
   * all at once.
   *
   * @return int[]
   *   The price list item IDs.
   */
  public function getItemIds();

}
