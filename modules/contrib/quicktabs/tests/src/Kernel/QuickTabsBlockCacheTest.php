<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\quicktabs\Plugin\Block\QuickTabsBlock;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests QuickTabs block cache metadata.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QuickTabsBlockCacheTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'quicktabs',
  ];

  /**
   * Tests that rendered QuickTabs blocks depend on their config entity.
   */
  public function testBlockBuildContainsQuickTabsInstanceCacheTags(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'test_cache_tags',
      'label' => 'Test cache tags',
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
      'configuration_data' => [],
    ]);
    $instance->save();

    $block = QuickTabsBlock::create(
      $this->container,
      [],
      'quicktabs_block:test_cache_tags',
      ['provider' => 'quicktabs']
    );

    $build = $block->build();

    $this->assertEqualsCanonicalizing(
      Cache::mergeTags(['config:quicktabs.quicktabs_instance.test_cache_tags'], $instance->getCacheTags()),
      $build['#cache']['tags']
    );
  }

}
