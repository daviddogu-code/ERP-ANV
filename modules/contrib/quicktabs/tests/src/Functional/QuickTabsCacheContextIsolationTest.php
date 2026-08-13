<?php

namespace Drupal\Tests\quicktabs\Functional;

use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that QuickTabs cache entries isolate equal-permission users.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QuickTabsCacheContextIsolationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'dynamic_page_cache',
    'quicktabs',
    'quicktabs_test_block',
  ];

  /**
   * The first equal-permission user.
   */
  protected UserInterface $alice;

  /**
   * The second equal-permission user.
   */
  protected UserInterface $bob;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->alice = $this->drupalCreateUser(['access content'], 'alice');
    $this->bob = $this->drupalCreateUser(['access content'], 'bob');

    $hash_generator = $this->container->get('user_permissions_hash_generator');
    $this->assertSame(
      $hash_generator->generate($this->alice),
      $hash_generator->generate($this->bob),
      'The users must share the required user.permissions cache context.'
    );
  }

  /**
   * Plugin-object cache contexts isolate the normal block render cache.
   */
  public function testPluginObjectCacheContextPreventsCrossUserDisclosure(): void {
    $instance = $this->createInstance(
      'plugin_object_cache_context',
      'quicktabs_test_per_user_cache_context',
      FALSE
    );
    $this->drupalPlaceBlock('quicktabs_block:' . $instance->id(), [
      'id' => 'quicktabs_plugin_object_cache_context',
      'region' => 'content',
    ]);

    $this->assertUserSeesOwnSecret('<front>', 'Plugin secret for ');
  }

  /**
   * Child cache contexts isolate a Dynamic Page Cache AJAX response.
   */
  public function testChildCacheContextPreventsAjaxCrossUserDisclosure(): void {
    $instance = $this->createInstance(
      'ajax_child_cache_context',
      'quicktabs_test_per_user_child_cacheability',
      TRUE
    );
    $path = '/quicktabs/ajax/' . $instance->id() . '/0';

    $this->assertUserSeesOwnSecret($path, 'Child secret for ');
  }

  /**
   * Creates a one-tab QuickTabs instance.
   */
  protected function createInstance(string $id, string $block_id, bool $ajax): QuickTabsInstance {
    $instance = QuickTabsInstance::create([
      'id' => $id,
      'label' => $id,
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => $ajax,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => FALSE,
      'remember_last_clicked_tab' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [
        [
          'title' => 'Per-user content',
          'type' => 'block_content',
          'weight' => 0,
          'content' => [
            'block_content' => [
              'options' => [
                'bid' => $block_id,
                'display_title' => 0,
                'block_title' => '',
              ],
            ],
          ],
        ],
      ],
    ]);
    $instance->save();
    return $instance;
  }

  /**
   * Proves a cached response does not disclose the first user's content.
   */
  protected function assertUserSeesOwnSecret(string $path, string $prefix): void {
    $this->drupalLogin($this->alice);
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains($prefix . 'alice');
    // Make the same request twice so the vulnerable implementation stores and
    // then reuses the response under the shared user.permissions variant. The
    // fixed implementation may instead mark a broad per-user response as
    // uncacheable, which is also safe.
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains($prefix . 'alice');

    $this->drupalLogin($this->bob);
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains($prefix . 'bob');
    $this->assertSession()->pageTextNotContains($prefix . 'alice');
  }

}
