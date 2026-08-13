<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that an embedded quicktabs_instance carries its own cache tag.
 *
 * @see \Drupal\quicktabs\Plugin\TabType\QtabsContent
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QtabsContentCacheabilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'quicktabs',
  ];

  /**
   * A nested instance's own config cache tag must be present.
   *
   * Without it, editing the nested instance (adding a tab, changing its
   * options) does not invalidate a parent tabset that embeds it via a
   * qtabs_content tab.
   */
  public function testNestedInstanceCarriesOwnCacheTag(): void {
    $inner = QuickTabsInstance::create([
      'id' => 'inner',
      'label' => 'Inner',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => ['ajax' => FALSE, 'style' => '', 'class' => ''],
      ],
      'hide_empty_tabs' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [],
    ]);
    $inner->save();

    $tab = [
      'type' => 'qtabs_content',
      'content' => [
        'qtabs_content' => [
          'options' => ['machine_name' => 'inner'],
        ],
      ],
    ];

    $object = $this->container->get('plugin.manager.tab_type')->createInstance('qtabs_content');
    $render = $object->render($tab);

    $this->assertContains(
      'config:quicktabs.quicktabs_instance.inner',
      CacheableMetadata::createFromRenderArray($render)->getCacheTags()
    );
  }

  /**
   * A reference to a deleted instance renders as empty, not a fatal error.
   */
  public function testMissingNestedInstanceRendersEmpty(): void {
    $tab = [
      'type' => 'qtabs_content',
      'content' => [
        'qtabs_content' => [
          'options' => ['machine_name' => 'does_not_exist'],
        ],
      ],
    ];

    $object = $this->container->get('plugin.manager.tab_type')->createInstance('qtabs_content');
    $this->assertSame([], $object->render($tab));
  }

}
