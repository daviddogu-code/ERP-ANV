<?php

namespace Drupal\Tests\commerce_pricelist\Kernel;

use Drupal\commerce\Context;
use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\Entity\PriceList;
use Drupal\commerce_pricelist\Entity\PriceListItem;
use Drupal\commerce_product\Entity\ProductVariation;

/**
 * Tests the price list resolver.
 *
 * @coversDefaultClass \Drupal\commerce_pricelist\PriceListPriceResolver
 * @group commerce_pricelist
 */
class PriceResolverTest extends PriceListKernelTestBase {

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
   * Tests the that the correct price list is resolved based on the context.
   */
  public function testResolver() {
    $resolver = $this->container->get('commerce_pricelist.price_resolver');

    $context = new Context($this->user, $this->store);
    $resolved_price = $resolver->resolve($this->variation, 1, $context);
    $this->assertEquals(new Price('5.00', 'USD'), $resolved_price);

    $context = new Context($this->user, $this->store, NULL, [
      'field_name' => 'list_price',
    ]);
    $resolved_price = $resolver->resolve($this->variation, 1, $context);
    $this->assertEquals(new Price('7.70', 'USD'), $resolved_price);
  }

}
