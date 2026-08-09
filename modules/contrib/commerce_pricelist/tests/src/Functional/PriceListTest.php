<?php

namespace Drupal\Tests\commerce_pricelist\Functional;

use Drupal\commerce_pricelist\Entity\PriceList;
use Drupal\Core\Url;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the price list UI.
 *
 * @group commerce_pricelist
 */
class PriceListTest extends CommerceBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_product',
    'commerce_pricelist',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_pricelist',
      'access commerce_pricelist overview',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests adding a price list.
   */
  public function testAdd() {
    $this->drupalGet(Url::fromRoute('entity.commerce_pricelist.collection')->toString());
    $this->clickLink('Add price list');

    $roles = $this->adminUser->getRoles();
    $role = reset($roles);
    $this->submitForm([
      'name[0][value]' => 'Black Friday 2018',
      'start_date[0][value][date]' => '2018-07-07',
      'start_date[0][value][time]' => '13:37:00',
      'customer_eligibility' => 'customer_roles',
      "customer_roles[$role]" => $role,
      // The customer should not be persisted due to the role being used.
      'customers[0][target_id]' => $this->adminUser->label() . ' (' . $this->adminUser->id() . ')',
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Black Friday 2018 price list.');

    /** @var \Drupal\commerce_pricelist\Entity\PriceListInterface $price_list */
    $price_list = PriceList::load(1);
    $this->assertEquals('Black Friday 2018', $price_list->getName());
    $this->assertEquals('2018-07-07 13:37:00', $price_list->getStartDate()->format('Y-m-d H:i:s'));
    $this->assertEquals([$role], $price_list->getCustomerRoles());
    $this->assertEmpty($price_list->getCustomers());
  }

  /**
   * Tests editing a price list.
   */
  public function testEdit() {
    $roles = $this->adminUser->getRoles();
    $role = reset($roles);
    $price_list = $this->createEntity('commerce_pricelist', [
      'type' => 'commerce_product_variation',
      'name' => $this->randomMachineName(8),
      'start_date' => '2018-07-07T13:37:00',
      'customer_role' => $role,
    ]);
    $this->drupalGet($price_list->toUrl('edit-form'));
    $prices_tab_uri = Url::fromRoute('entity.commerce_pricelist_item.collection', [
      'commerce_pricelist' => $price_list->id(),
    ])->toString();
    $this->assertSession()->linkByHrefExists($price_list->toUrl('edit-form')->toString());
    $this->assertSession()->linkByHrefExists($price_list->toUrl('duplicate-form')->toString());
    $this->assertSession()->linkByHrefExists($prices_tab_uri);
    $this->submitForm([
      'name[0][value]' => 'Random list',
      'start_date[0][value][date]' => '2018-08-08',
      'start_date[0][value][time]' => '13:37:15',
      'customer_eligibility' => 'customers',
      'customers[0][target_id]' => $this->adminUser->label() . ' (' . $this->adminUser->id() . ')',
      // The role should not be persisted due to the customer being used.
      "customer_roles[$role]" => $role,
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Random list price list.');

    \Drupal::service('entity_type.manager')->getStorage('commerce_pricelist')->resetCache([$price_list->id()]);
    /** @var \Drupal\commerce_pricelist\Entity\PriceListInterface $price_list */
    $price_list = PriceList::load(1);
    $this->assertEquals('Random list', $price_list->getName());
    $this->assertEquals('2018-08-08 13:37:15', $price_list->getStartDate()->format('Y-m-d H:i:s'));
    $this->assertEmpty($price_list->getCustomerRoles());
    $this->adminUser = $this->reloadEntity($this->adminUser);
    $this->assertEquals([$this->adminUser], $price_list->getCustomers());
  }

  /**
   * Tests duplicating a price list.
   */
  public function testDuplicate() {
    $roles = $this->adminUser->getRoles();
    $role = reset($roles);
    $price_list = $this->createEntity('commerce_pricelist', [
      'type' => 'commerce_product_variation',
      'name' => 'Random list',
      'start_date' => '2018-07-07T13:37:00',
      'customer_roles' => [$role],
    ]);
    $this->drupalGet($price_list->toUrl('duplicate-form'));
    $this->assertSession()->pageTextContains('Duplicate Random list');
    $prices_tab_uri = Url::fromRoute('entity.commerce_pricelist_item.collection', [
      'commerce_pricelist' => $price_list->id(),
    ])->toString();
    $this->assertSession()->linkByHrefExists($price_list->toUrl('edit-form')->toString());
    $this->assertSession()->linkByHrefExists($price_list->toUrl('duplicate-form')->toString());
    $this->assertSession()->linkByHrefExists($prices_tab_uri);
    $this->submitForm([
      'name[0][value]' => 'Random list2',
      'start_date[0][value][date]' => '2018-08-08',
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Random list2 price list.');

    \Drupal::service('entity_type.manager')->getStorage('commerce_pricelist')->resetCache([$price_list->id()]);
    // Confirm that the original price list is unchanged.
    $price_list = PriceList::load(1);
    $this->assertEquals('Random list', $price_list->getName());
    $this->assertEquals('2018-07-07 13:37:00', $price_list->getStartDate()->format('Y-m-d H:i:s'));
    $this->assertEquals([$role], $price_list->getCustomerRoles());

    // Confirm that the new price list has the expected data.
    $price_list = PriceList::load(2);
    $this->assertEquals('Random list2', $price_list->getName());
    $this->assertEquals('2018-08-08 13:37:00', $price_list->getStartDate()->format('Y-m-d H:i:s'));
    $this->assertEquals([$role], $price_list->getCustomerRoles());
  }

  /**
   * Tests deleting a price list.
   */
  public function testDelete() {
    $price_list = $this->createEntity('commerce_pricelist', [
      'type' => 'commerce_product_variation',
      'name' => $this->randomMachineName(8),
    ]);
    $this->drupalGet($price_list->toUrl('delete-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->submitForm([], 'Delete');

    \Drupal::service('entity_type.manager')->getStorage('commerce_pricelist')->resetCache([$price_list->id()]);
    $price_list_exists = (bool) PriceList::load($price_list->id());
    $this->assertFalse($price_list_exists);
  }

}
