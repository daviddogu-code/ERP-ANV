<?php

namespace Drupal\Tests\base_field_override_ui\Functional;

use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Tests\base_field_override_ui\Traits\BaseFieldOverrideUiTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests deletion of a base field override in the UI.
 *
 * @group base_field_override_ui
 */
class BaseFieldOverrideUIDeleteTest extends BrowserTestBase {

  use BaseFieldOverrideUiTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['node', 'base_field_override_ui', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create a test user.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that deletion removes field storages and fields as expected.
   */
  public function testDeleteBaseFieldOverride() {
    $title_label = $this->randomMachineName();
    $base_field_name = 'title';

    // Create an additional node type.
    $type_name1 = strtolower($this->randomMachineName(8)) . '_test';
    $type1 = $this->drupalCreateContentType([
      'name' => $type_name1,
      'type' => $type_name1,
    ]);
    $type_name1 = $type1->id();

    // Create a new field.
    $bundle_path1 = 'admin/structure/types/manage/' . $type_name1;
    $this->addNewBaseFieldOverride($bundle_path1, $base_field_name, $title_label);

    // Create an additional node type.
    $type_name2 = strtolower($this->randomMachineName(8)) . '_test';
    $type2 = $this->drupalCreateContentType([
      'name' => $type_name2,
      'type' => $type_name2,
    ]);
    $type_name2 = $type2->id();

    // Add a field to the second node type.
    $bundle_path2 = 'admin/structure/types/manage/' . $type_name2;
    $this->addNewBaseFieldOverride($bundle_path2, $base_field_name, $title_label);

    // Delete the first base field override.
    $this->deleteBaseFieldOverride($bundle_path1, "node.$type_name1.$base_field_name", $title_label, $type_name1);

    // Check that the base field override as deleted.
    $this->assertNull(BaseFieldOverride::loadByName('node', $type_name1, $base_field_name), 'Base Field Override was deleted.');

    // Delete the second base field override.
    $this->deleteBaseFieldOverride($bundle_path2, "node.$type_name2.$base_field_name", $title_label, $type_name2);

    // Check that the base field override as deleted.
    $this->assertNull(BaseFieldOverride::loadByName('node', $type_name2, $base_field_name), 'Base Field Override was deleted.');
  }

}
