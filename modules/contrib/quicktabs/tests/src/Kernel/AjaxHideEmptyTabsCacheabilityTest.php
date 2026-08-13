<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests cacheability when AJAX mode is combined with hide_empty_tabs.
 *
 * A non-default AJAX tab renders as a loading placeholder on initial load —
 * its real content is fetched later, by a separate request. But
 * hide_empty_tabs has to know whether that tab is empty *now*, so it builds
 * the real content once, purely to decide. Whatever that decision depended
 * on — a block's access check, a view's results — is exactly the kind of
 * thing that must vary the tabset's own cacheability, whether the tab ends
 * up shown or hidden. The placeholder itself carries no such information.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class AjaxHideEmptyTabsCacheabilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'quicktabs',
    'quicktabs_test_block',
  ];

  /**
   * Builds an ajax + hide_empty_tabs instance with the given tabs.
   */
  protected function buildInstance(string $id, array $tabs): QuickTabsInstance {
    $instance = QuickTabsInstance::create([
      'id' => $id,
      'label' => $id,
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => TRUE,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => TRUE,
      'remember_last_clicked_tab' => FALSE,
      // Tab 0 is the default (rendered eagerly); tab 1 is not (placeholder).
      'default_tab' => 0,
      'configuration_data' => $tabs,
    ]);
    $instance->save();
    return $instance;
  }

  /**
   * A block tab for the given plugin ID.
   */
  protected function blockTab(string $title, string $bid): array {
    return [
      'title' => $title,
      'type' => 'block_content',
      'weight' => 0,
      'content' => [
        'block_content' => [
          'options' => [
            'bid' => $bid,
            'display_title' => 0,
            'block_title' => '',
          ],
        ],
      ],
    ];
  }

  /**
   * A non-default block tab that is not empty still bubbles its cacheability.
   */
  public function testShownNonDefaultAjaxTabBubblesCacheability(): void {
    $instance = $this->buildInstance('ajax_shown', [
      $this->blockTab('First', 'system_powered_by_block'),
      $this->blockTab('Second', 'quicktabs_test_nonempty_with_cacheability'),
    ]);

    $build = $instance->getRenderArray();

    // The second tab was not hidden: its block always renders content.
    $this->assertArrayHasKey(1, $build['pages']);

    $metadata = CacheableMetadata::createFromRenderArray($build);
    $this->assertContains(
      'quicktabs_test_nonempty_with_cacheability',
      $metadata->getCacheTags(),
      'A shown non-default AJAX tab must still bubble the cache tags of the '
      . 'real content that was built to check whether it was empty.'
    );
    $this->assertContains(
      'url.query_args:reveal',
      $metadata->getCacheContexts(),
      'A shown non-default AJAX tab must still bubble the cache contexts of '
      . 'the real content that was built to check whether it was empty.'
    );
  }

  /**
   * A non-default block tab that is empty still bubbles its cacheability.
   */
  public function testHiddenNonDefaultAjaxTabBubblesCacheability(): void {
    $instance = $this->buildInstance('ajax_hidden', [
      $this->blockTab('First', 'system_powered_by_block'),
      $this->blockTab('Empty', 'quicktabs_test_empty_with_cacheability'),
    ]);

    $build = $instance->getRenderArray();

    // The empty tab was dropped entirely.
    $this->assertArrayNotHasKey(1, $build['pages']);

    $metadata = CacheableMetadata::createFromRenderArray($build);
    $this->assertContains(
      'quicktabs_test_empty_with_cacheability',
      $metadata->getCacheTags(),
      'A hidden non-default AJAX tab must still bubble the cache tags of the '
      . 'real content that was built to determine it was empty.'
    );
    $this->assertContains(
      'url.query_args:reveal',
      $metadata->getCacheContexts(),
      'A hidden non-default AJAX tab must still bubble the cache contexts of '
      . 'the real content that was built to determine it was empty.'
    );
  }

}
