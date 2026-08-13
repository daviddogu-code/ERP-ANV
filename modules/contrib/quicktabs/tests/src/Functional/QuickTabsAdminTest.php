<?php

namespace Drupal\Tests\quicktabs\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests creating and saving a QuickTabs instance.
 *
 * @group quicktabs
 */
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class QuickTabsAdminTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'block',
    'menu_ui',
    'user',
    'taxonomy',
    'toolbar',
    'quicktabs',
    'views',
    'quicktabs_test_block',
  ];

  /**
   * A user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $perms = [
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      'bypass node access',
      'administer themes',
      'administer nodes',
      'access content overview',
      'administer blocks',
      'administer menu',
      'administer modules',
      'administer permissions',
      'administer users',
      'access user profiles',
      'administer taxonomy',
      'administer quicktabs',
    ];

    // Create an administrative user and log it in.
    $this->adminUser = $this->drupalCreateUser($perms);

    $this->drupalLogin($this->adminUser);

    // Create an article content type.
    $this->drupalCreateContentType([
      'type' => 'article',
    ]);
  }

  /**
   * Test all vocabularies appear on admin page.
   */
  public function testQuickTabsAdmin() {
    $this->drupalGet('admin/structure/quicktabs');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Quick Tabs');
    $this->drupalGet('admin/structure/quicktabs/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Add QuickTabs Instance');
    $this->assertSession()->responseContains('Name');
    $this->assertSession()->responseContains('Renderer');
    $this->assertSession()->responseContains('Default tab');
    $this->assertSession()->responseContains('Hide empty tabs');
    $this->assertSession()->responseContains('Add tab');
    $this->assertSession()->responseContains('Save');

    $node1 = $this->drupalCreateNode([
      'title' => $this->t('Node 1'),
      'type' => 'article',
    ]);
    $node2 = $this->drupalCreateNode([
      'title' => $this->t('Node 2'),
      'type' => 'article',
    ]);

    $edit = $this->createQuicktabsInstance([
      [
        'title' => $this->randomMachineName(),
        'node' => $node1,
      ],
      [
        'title' => $this->randomMachineName(),
        'node' => $node2,
      ],
    ]);

    $qt = \Drupal::service('entity_type.manager')->getStorage('quicktabs_instance')->load($edit['id']);

    $this->assertEquals('Drupal\quicktabs\Entity\QuickTabsInstance', get_class($qt));
    $this->assertEquals($qt->id(), $edit['id']);
    $this->assertEquals($qt->label(), $edit['label']);
    $this->assertEquals($qt->getRenderer(), $edit['renderer']);
    $this->assertEquals($qt->getHideEmptyTabs(), $edit['hide_empty_tabs']);
    $this->assertEquals($qt->getDefaultTab(), $edit['default_tab']);

    $configurationData = $qt->getConfigurationData();
    $this->assertEquals($configurationData[0]['title'], $edit['configuration_data[0][title]']);
    $this->assertEquals($configurationData[1]['title'], $edit['configuration_data[1][title]']);
    $this->assertEquals($configurationData[0]['type'], $edit['configuration_data[0][type]']);
    $this->assertEquals($configurationData[1]['type'], $edit['configuration_data[1][type]']);
    $this->assertEquals($configurationData[0]['content']['node_content']['options']['nid'], $edit['configuration_data[0][content][node_content][options][nid]']);
    $this->assertEquals($configurationData[1]['content']['node_content']['options']['nid'], $edit['configuration_data[1][content][node_content][options][nid]']);
    $this->assertEquals($configurationData[0]['content']['node_content']['options']['view_mode'], $edit['configuration_data[0][content][node_content][options][view_mode]']);
    $this->assertEquals($configurationData[1]['content']['node_content']['options']['view_mode'], $edit['configuration_data[1][content][node_content][options][view_mode]']);
    $this->assertEquals($configurationData[0]['content']['node_content']['options']['hide_title'], $edit['configuration_data[0][content][node_content][options][hide_title]']);
    $this->assertEquals($configurationData[1]['content']['node_content']['options']['hide_title'], $edit['configuration_data[1][content][node_content][options][hide_title]']);
    $this->drupalGet('admin/structure/quicktabs/' . $qt->id() . '/delete');

    $this->submitForm([], $this->t('Delete'));

    $qt = \Drupal::service('entity_type.manager')->getStorage('quicktabs_instance')->load($edit['id']);
    $this->assertNull($qt, $this->t('QuickTabs instance not found in database'));
  }

  /**
   * Tests placing a Quicktabs block and rendering it on the page.
   */
  public function testPlaceQuicktabsBlock() {
    $tab_title = $this->randomMachineName();
    $tab_body = $this->randomMachineName();
    $node = $this->drupalCreateNode([
      'title' => $this->t('Placed tab node'),
      'type' => 'article',
      'body' => [
        'value' => $tab_body,
        'format' => 'plain_text',
      ],
    ]);

    // Create the instance first so its derived block is discoverable.
    $edit = $this->createQuicktabsInstance([
      [
        'title' => $tab_title,
        'node' => $node,
      ],
    ]);

    $this->drupalGet('admin/structure/block/library/stark');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('QuickTabs - ' . $edit['label']);

    $this->drupalPlaceBlock('quicktabs_block:' . $edit['id'], [
      'region' => 'content',
    ]);

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($tab_title);
    $this->assertSession()->pageTextContains($tab_body);
  }

  /**
   * Tests that a block admin label with "&" is not double-encoded.
   *
   * Block admin labels are Markup objects whose string form is already
   * HTML-encoded. The block selector must render them as plain text so the
   * select element encodes them exactly once ("&amp;") rather than twice
   * ("&amp;amp;").
   *
   * @see https://www.drupal.org/project/quicktabs/issues/3588550
   */
  public function testBlockSelectorDoesNotDoubleEncodeTitle() {
    $this->drupalGet('admin/structure/quicktabs/add');
    $this->assertSession()->statusCodeEquals(200);

    // The "Select a block" option for the test block, single-encoded.
    $this->assertSession()->responseContains('Meetings &amp; Events (quicktabs_test_block)');
    // The bug rendered the ampersand entity a second time.
    $this->assertSession()->responseNotContains('&amp;amp;');
  }

  /**
   * Creates a Quicktabs instance through the UI.
   */
  protected function createQuicktabsInstance(array $tabs): array {
    $edit = [
      'label' => $this->randomMachineName(),
      'id' => strtolower($this->randomMachineName()),
      'renderer' => 'quick_tabs',
      'options[quick_tabs][ajax]' => 0,
      'hide_empty_tabs' => 1,
      'default_tab' => 9999,
    ];

    foreach ($tabs as $delta => $tab) {
      // Match the nested Quicktabs form structure for each configured tab.
      $edit["configuration_data[$delta][title]"] = $tab['title'];
      $edit["configuration_data[$delta][type]"] = 'node_content';
      $edit["configuration_data[$delta][content][node_content][options][nid]"] = $tab['node']->id();
      $edit["configuration_data[$delta][content][node_content][options][view_mode]"] = 'full';
      $edit["configuration_data[$delta][content][node_content][options][hide_title]"] = 1;
    }

    $this->drupalGet('admin/structure/quicktabs/add');
    $this->submitForm($edit, $this->t('Save'));

    return $edit;
  }

}
