<?php

namespace Drupal\Tests\commerce_pricelist\Functional;

use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\Entity\PriceListItem;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Core\Url;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the interaction between price lists and product caches.
 *
 * @group commerce_pricelist
 */
class ProductCacheTest extends CommerceBrowserTestBase {

  /**
   * A test price list.
   *
   * @var \Drupal\commerce_pricelist\Entity\PriceListInterface
   */
  protected $priceList;

  /**
   * A test product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * A test variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $variation;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_product',
    'commerce_pricelist',
    'commerce_pricelist_test',
    'dynamic_page_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_pricelist',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Turn off title generation to allow explicit values to be used.
    $variation_type = ProductVariationType::load('default');
    $variation_type->setGenerateTitle(FALSE);
    $variation_type->save();

    $this->priceList = $this->createEntity('commerce_pricelist', [
      'type' => 'commerce_product_variation',
      'name' => $this->randomMachineName(8),
      'start_date' => '2018-07-07',
      'stores' => [$this->store],
    ]);
    $this->variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => 'RED-SHIRT',
      'title' => 'Red shirt',
      'price' => new Price('12.00', 'USD'),
    ]);
    $this->product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [$this->variation],
    ]);
    $this->reloadEntity($this->variation);
    $this->variation->save();
    // Use the "calculated" price display for the product, since this will give
    // us the resolved price including the price list.
    $variation_display = commerce_get_entity_display('commerce_product_variation', 'default', 'view');
    $variation_display->setComponent('price', [
      'label' => 'above',
      'type' => 'commerce_price_calculated',
      'settings' => [],
    ]);
    $variation_display->save();
  }

  /**
   * Tests the caching.
   */
  public function testCache() {
    $collection_url = Url::fromRoute('entity.commerce_pricelist_item.collection', [
      'commerce_pricelist' => $this->priceList->id(),
    ]);
    $this->drupalGet(Url::fromRoute('entity.commerce_product.canonical', [
      'commerce_product' => $this->product->id(),
    ]));
    // We expect the page to contain the original price at this point.
    $this->assertSession()->pageTextContains('$12.00');
    $this->drupalGet($collection_url->toString());
    $this->clickLink('Add price');

    $this->submitForm([
      'purchasable_entity[0][target_id]' => 'Red shirt (1)',
      'quantity[0][value]' => '1',
      'price[0][number]' => 5,
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Red shirt: $5.00 price.');

    $price_list_item = PriceListItem::load(1);
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->variation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('1', $price_list_item->getQuantity());
    $this->assertEquals(new Price('5', 'USD'), $price_list_item->getPrice());
    $this->drupalGet(Url::fromRoute('entity.commerce_product.canonical', [
      'commerce_product' => $this->product->id(),
    ]));
    $this->assertSession()->pageTextContains('$5.00');
  }

}
