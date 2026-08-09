<?php

namespace Drupal\Tests\commerce_pricelist\Kernel;

use Drupal\commerce\Context;
use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\Entity\PriceList;
use Drupal\commerce_pricelist\Entity\PriceListItem;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\user\Entity\Role;

/**
 * Tests the price list repository.
 *
 * @coversDefaultClass \Drupal\commerce_pricelist\PriceListRepository
 * @group commerce_pricelist
 */
class PriceListRepositoryTest extends PriceListKernelTestBase {

  /**
   * The test price list.
   *
   * @var \Drupal\commerce_pricelist\Entity\PriceList
   */
  protected $priceList;

  /**
   * The test price list item.
   *
   * @var \Drupal\commerce_pricelist\Entity\PriceListItem
   */
  protected $priceListItem;

  /**
   * The test variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariation
   */
  protected $variation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'price' => new Price('8.00', 'USD'),
    ]);
    $variation->save();
    $this->variation = $this->reloadEntity($variation);

    $price_list = PriceList::create([
      'type' => 'commerce_product_variation',
      'stores' => [$this->store->id()],
      'weight' => '1',
    ]);
    $price_list->save();

    $price_list_item = PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $price_list->id(),
      'purchasable_entity' => $variation->id(),
      'quantity' => '1',
      'list_price' => new Price('7.70', 'USD'),
      'price' => new Price('5.00', 'USD'),
    ]);
    $price_list_item->save();

    $this->priceList = $this->reloadEntity($price_list);
    $this->priceListItem = $this->reloadEntity($price_list_item);
  }

  /**
   * Tests variation-based loading.
   *
   * @covers ::loadItem
   * @covers ::loadItems
   */
  public function testVariation() {
    $repository = $this->container->get('commerce_pricelist.repository');
    $other_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'price' => new Price('8.00', 'USD'),
    ]);
    $other_variation->save();

    $context = new Context($this->user, $this->store);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $price_list_item->getPrice());
    $this->assertEquals(new Price('7.70', 'USD'), $price_list_item->getListPrice());

    $price_list_item = $repository->loadItem($other_variation, 1, $context);
    $this->assertEmpty($price_list_item);
  }

  /**
   * Tests stores-based loading.
   *
   * @covers ::loadItem
   * @covers ::loadItems
   */
  public function testStores() {
    $context = new Context($this->user, $this->store);
    $repository = $this->container->get('commerce_pricelist.repository');

    $new_store = $this->createStore();
    $this->priceList->setStores([$new_store]);
    $this->priceList->save();

    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEmpty($price_list_item);

    $context = new Context($this->user, $new_store);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests customer-based loading.
   *
   * @covers ::loadItem
   * @covers ::loadItems
   */
  public function testCustomers() {
    $repository = $this->container->get('commerce_pricelist.repository');
    $customer = $this->createUser();
    $this->priceList->setCustomers([$customer]);
    $this->priceList->save();

    $context = new Context($this->user, $this->store);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEmpty($price_list_item);

    $context = new Context($customer, $this->store);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests roles-based loading.
   *
   * @covers ::loadItem
   * @covers ::loadItems
   */
  public function testCustomerRoles() {
    $repository = $this->container->get('commerce_pricelist.repository');
    $first_role = Role::create([
      'id' => strtolower($this->randomMachineName(8)),
      'label' => $this->randomMachineName(8),
    ]);
    $first_role->save();
    $second_role = Role::create([
      'id' => strtolower($this->randomMachineName(8)),
      'label' => $this->randomMachineName(8),
    ]);
    $second_role->save();
    $this->priceList->setCustomerRoles([$first_role->id(), $second_role->id()]);
    $this->priceList->save();

    $context = new Context($this->user, $this->store);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEmpty($price_list_item);

    $second_user = $this->createUser();
    $second_user->addRole($first_role->id());
    $second_user->save();

    $context = new Context($second_user, $this->store);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $price_list_item->getPrice());

    $third_user = $this->createUser();
    $third_user->addRole($second_role->id());
    $third_user->save();

    $context = new Context($third_user, $this->store);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests dates-based loading.
   *
   * @covers ::loadItem
   * @covers ::loadItems
   */
  public function testDates() {
    $repository = $this->container->get('commerce_pricelist.repository');
    $this->priceList->setStartDate(new DrupalDateTime('2019-01-01 00:00:00'));
    $this->priceList->setEndDate(new DrupalDateTime('2020-01-01 00:00:00'));
    $this->priceList->save();

    $time = strtotime('2019-11-15 00:00:00');
    $context = new Context($this->user, $this->store, $time);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $price_list_item->getPrice());

    // Future start date.
    $this->priceList->setStartDate(new DrupalDateTime('2019-12-17 00:00:00'));
    $this->priceList->save();

    $context = new Context($this->user, $this->store, $time + 1);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEmpty($price_list_item);

    // Confirm that the end date is not inclusive.
    $this->priceList->setStartDate(new DrupalDateTime('2019-01-01 00:00:00'));
    $this->priceList->setEndDate(new DrupalDateTime('2019-11-15 00:00:02'));
    $this->priceList->save();

    $context = new Context($this->user, $this->store, $time + 2);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEmpty($price_list_item);

    // Past end date.
    $this->priceList->setStartDate(new DrupalDateTime('2018-01-01 00:00:00'));
    $this->priceList->setEndDate(new DrupalDateTime('2019-01-01 00:00:00'));
    $this->priceList->save();

    $context = new Context($this->user, $this->store, $time + 3);
    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEmpty($price_list_item);
  }

  /**
   * Tests price list item selection based on the quantity, weight and status.
   *
   * @covers ::loadItem
   * @covers ::loadItems
   */
  public function testQuantity() {
    $context = new Context($this->user, $this->store);
    $repository = $this->container->get('commerce_pricelist.repository');
    $this->priceListItem->setQuantity(10);
    $this->priceListItem->save();
    // Create a second price list with a smaller weight, which should be
    // selected instead of the first price list.
    $price_list = PriceList::create([
      'type' => 'commerce_product_variation',
      'stores' => [$this->store->id()],
      'weight' => '-1',
    ]);
    $price_list->save();
    // Create two price list items, to test quantity tier selection.
    $price_list_item = PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $price_list->id(),
      'purchasable_entity' => $this->variation->id(),
      'quantity' => '10',
      'price' => new Price('7.00', 'USD'),
    ]);
    $price_list_item->save();
    $another_price_list_item = PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $price_list->id(),
      'purchasable_entity' => $this->variation->id(),
      'quantity' => '3',
      'price' => new Price('6.00', 'USD'),
    ]);
    $another_price_list_item->save();

    $price_list_item = $repository->loadItem($this->variation, 1, $context);
    $this->assertEmpty($price_list_item);
    $price_list_item = $repository->loadItem($this->variation, 15, $context);
    $this->assertEquals(new Price('7.00', 'USD'), $price_list_item->getPrice());

    // Reload the service to clear the static cache.
    $this->container->set('commerce_pricelist.repository', NULL);
    $repository = $this->container->get('commerce_pricelist.repository');

    // Confirm that disabled price list items are skipped.
    $price_list_item->setEnabled(FALSE);
    $price_list_item->save();
    $price_list_item = $repository->loadItem($this->variation, 15, $context);
    $this->assertEquals(new Price('6.00', 'USD'), $price_list_item->getPrice());

    // Reload the repository to clear the static cache.
    $this->container->set('commerce_pricelist.repository', NULL);
    $repository = $this->container->get('commerce_pricelist.repository');

    // Confirm that disabled price lists are skipped.
    $price_list->setEnabled(FALSE);
    $price_list->save();
    $another_user = $this->createUser();
    $context = new Context($another_user, $this->store);
    $price_list_item = $repository->loadItem($this->variation, 15, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests loading price list items for the given context.
   *
   * @covers ::loadItems
   */
  public function testLoadItems() {
    $price_list_item = PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->variation->id(),
      'quantity' => '10',
      'price' => new Price('7.00', 'USD'),
    ]);
    $price_list_item->save();
    $price_list_item = $this->reloadEntity($price_list_item);
    $another_price_list_item = PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->variation->id(),
      'quantity' => '3',
      'price' => new Price('6.00', 'USD'),
    ]);
    $another_price_list_item->save();
    $another_price_list_item = $this->reloadEntity($another_price_list_item);

    /** @var \Drupal\commerce_pricelist\PriceListRepositoryInterface $repository */
    $repository = $this->container->get('commerce_pricelist.repository');
    $context = new Context($this->user, $this->store);
    $price_list_items = $repository->loadItems($this->variation, $context);
    $this->assertCount(3, $price_list_items);
    $this->assertEquals(array_values($price_list_items), [$this->priceListItem, $another_price_list_item, $price_list_item]);
  }

}
