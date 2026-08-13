<?php

namespace Drupal\Tests\quicktabs\Functional;

use Drupal\node\NodeInterface;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the AJAX tab content response is cached and invalidated correctly.
 *
 * The controller returns a CacheableAjaxResponse and bubbles cache metadata
 * from both the QuickTabs instance and the rendered tab content. This test does
 * not assert on the response *type*; instead it proves the behavior through
 * the full HTTP stack: Dynamic Page Cache serves the stored response on a
 * repeat request (MISS then HIT), and the cache entry is invalidated by the
 * correct cache tags (the rendered content's tag and the instance config tag).
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QuickTabsAjaxCacheTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'filter',
    'text',
    'node',
    'user',
    'dynamic_page_cache',
    'quicktabs',
  ];

  /**
   * The QuickTabs instance under test.
   */
  protected QuickTabsInstance $instance;

  /**
   * The node rendered by the AJAX tab.
   */
  protected NodeInterface $node;

  /**
   * The AJAX content path for the tab under test.
   */
  protected string $ajaxPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Uninstall the internal Page Cache so the Dynamic Page Cache HIT/MISS
    // state is observable for anonymous requests; Page Cache would otherwise
    // serve anonymous responses before Dynamic Page Cache and mask the header.
    // @see \Drupal\Tests\dynamic_page_cache\Functional\DynamicPageCacheIntegrationTest
    if (\Drupal::moduleHandler()->moduleExists('page_cache')) {
      \Drupal::service('module_installer')->uninstall(['page_cache']);
    }

    // The AJAX route requires the 'access content' permission. Keep the test
    // requests anonymous so the responses are cleanly cacheable.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Cached tab node',
      'body' => [['value' => 'QUICKTABS_BODY_ORIGINAL', 'format' => 'plain_text']],
      'status' => 1,
    ]);

    $this->instance = QuickTabsInstance::create([
      'id' => 'ajax_cache',
      'label' => 'AJAX cache',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => TRUE,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [
        0 => [
          'title' => 'Node tab',
          'type' => 'node_content',
          'content' => [
            'node_content' => [
              'options' => [
                'nid' => $this->node->id(),
                'view_mode' => 'full',
                'hide_title' => 0,
              ],
            ],
          ],
        ],
      ],
    ]);
    $this->instance->save();

    // Route: /quicktabs/{js}/{instance}/{tab}. The JS client uses 'ajax' for
    // the {js} argument (see QuickTabsAjaxSourceRequestTrait).
    $this->ajaxPath = '/quicktabs/ajax/' . $this->instance->id() . '/0';
  }

  /**
   * Tests the AJAX response is dynamically cached and tag-invalidated.
   */
  public function testAjaxResponseIsCachedAndInvalidatedByTags(): void {
    // The rendered tab content must actually appear in the AJAX command,
    // otherwise the cache assertions below would be meaningless. The command
    // must target the inner content wrapper so the block title rendered on
    // initial page load is not wiped by the replacement (#3545758).
    $this->drupalGet($this->ajaxPath);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('#quicktabs-tabpage-ajax_cache-0 .quicktabs-tabpage-content');
    $this->assertSession()->responseContains('QUICKTABS_BODY_ORIGINAL');

    // First uncached request stores the response; the repeat request is served
    // from Dynamic Page Cache. This proves the cached response is used.
    $this->assertDynamicCache('MISS');
    $this->drupalGet($this->ajaxPath);
    $this->assertDynamicCache('HIT');

    // Editing the rendered node invalidates its cache tag (node:<nid>), which
    // is only bubbled onto the response via
    // CacheableMetadata::createFromRenderArray(). The cache entry must drop and
    // the fresh content must be served.
    $this->node->set('body', [['value' => 'QUICKTABS_BODY_UPDATED', 'format' => 'plain_text']])->save();
    $this->drupalGet($this->ajaxPath);
    $this->assertDynamicCache('MISS');
    $this->assertSession()->responseContains('QUICKTABS_BODY_UPDATED');
    $this->drupalGet($this->ajaxPath);
    $this->assertDynamicCache('HIT');

    // Invalidating the QuickTabs instance config tag (bubbled via
    // addCacheableDependency($qt)) must also drop the cache entry.
    $this->instance->set('label', 'AJAX cache changed')->save();
    $this->drupalGet($this->ajaxPath);
    $this->assertDynamicCache('MISS');
    $this->drupalGet($this->ajaxPath);
    $this->assertDynamicCache('HIT');
  }

  /**
   * Asserts the Dynamic Page Cache status header of the last response.
   *
   * @param string $expected
   *   One of 'HIT', 'MISS' or 'UNCACHEABLE'.
   */
  protected function assertDynamicCache(string $expected): void {
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', $expected);
  }

}
