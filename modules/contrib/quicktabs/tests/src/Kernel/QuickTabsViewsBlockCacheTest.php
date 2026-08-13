<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\NodeType;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests QuickTabs block cache behavior with Views block tabs.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QuickTabsViewsBlockCacheTest extends ViewsKernelTestBase {

  use NodeCreationTrait;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'quicktabs_articles',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'views',
    'views_test_config',
    'views_test_data',
    'user',
    'field',
    'filter',
    'node',
    'text',
    'block',
    'quicktabs',
    'quicktabs_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    ViewTestData::createTestViews(static::class, ['quicktabs_test_views']);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'filter', 'node']);
    $this->container->get('theme_installer')->install(['stark']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
  }

  /**
   * Tests Views block tab output invalidation when content changes.
   */
  public function testViewsBlockTabOutputInvalidatesForNewContent(): void {
    $this->createQuickTabsInstance();
    $block = $this->createQuickTabsBlock();

    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request_method = $request->server->get('REQUEST_METHOD');
    $request->setMethod('GET');

    $build = $this->buildBlock($block);
    $first_output = (string) $this->container->get('renderer')->renderRoot($build);

    $this->assertStringNotContainsString('Fresh article', $first_output);

    /** @var \Drupal\Core\Cache\VariationCacheFactoryInterface $variation_cache_factory */
    $variation_cache_factory = $this->container->get('variation_cache_factory');
    $render_cache = $variation_cache_factory->get('render');
    $cache_keys = ['entity_view', 'block', 'quicktabs_articles'];
    $this->assertNotEmpty(
      $render_cache->get($cache_keys, CacheableMetadata::createFromRenderArray($build)),
      'The empty QuickTabs block output is render cached.'
    );

    $this->createNode([
      'type' => 'article',
      'title' => 'Fresh article',
      'status' => 1,
    ])->save();

    $this->assertFalse(
      $render_cache->get($cache_keys, CacheableMetadata::createFromRenderArray($build)),
      'Creating matching content invalidates the cached QuickTabs block output.'
    );

    $build = $this->buildBlock($block);
    $second_output = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertStringContainsString('Fresh article', $second_output);

    $request->setMethod($request_method);
  }

  /**
   * Creates a QuickTabs instance with a Views block tab.
   */
  protected function createQuickTabsInstance(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'test_views_block_cache',
      'label' => 'Test Views block cache',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => FALSE,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [
        0 => [
          'title' => 'Articles',
          'type' => 'block_content',
          'content' => [
            'block_content' => [
              'options' => [
                'bid' => 'views_block:quicktabs_articles-block_1',
              ],
            ],
          ],
        ],
      ],
    ]);
    $instance->save();
  }

  /**
   * Creates a placed QuickTabs block config entity.
   */
  protected function createQuickTabsBlock(): Block {
    $block = Block::create([
      'id' => 'quicktabs_articles',
      'theme' => 'stark',
      'plugin' => 'quicktabs_block:test_views_block_cache',
    ]);
    $block->save();

    return $block;
  }

  /**
   * Builds a placed QuickTabs block.
   */
  protected function buildBlock(Block $block): array {
    return $this->container->get('entity_type.manager')
      ->getViewBuilder('block')
      ->view($block, 'block');
  }

}
