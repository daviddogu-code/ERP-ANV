<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\quicktabs\Entity\QuickTabsInstance;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that empty Views output is hidden in AJAX mode.
 *
 * Regression coverage for issue #3238148: prior to MR42, the AJAX code
 * path rendered loading placeholders for non-default tabs, so the
 * existing "skip empty render" check never saw an empty value and the
 * empty-view tab continued to appear.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class HideEmptyViewsTabsTest extends QuickTabsViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_simple_argument',
    'test_view_block',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $testViewModules = [
    'views_test_config',
    'block_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'block',
    'user',
    'path_alias',
    'views',
    'block_test_views',
    'views_test_config',
    'views_test_data',
    'quicktabs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->prepareViewsBlockDisplays();
  }

  /**
   * Tests AJAX hides only empty Views tabs while keeping populated Views tabs.
   */
  public function testAjaxEmptyViewTabIsHiddenAndPopulatedViewTabRemains(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'test_hide_empty_views_ajax',
      'label' => 'Test hide empty views',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => TRUE,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => TRUE,
      'default_tab' => 0,
      'configuration_data' => [
        0 => $this->viewTab('Default populated view tab', '27'),
        1 => $this->viewTab('Empty view tab', '999'),
        2 => $this->viewTab('Populated view tab', '25'),
      ],
    ]);

    $renderer = $this->container->get('plugin.manager.tab_renderer')->createInstance('quick_tabs');
    $build = $renderer->render($instance);

    $this->assertArrayHasKey('pages', $build);
    $this->assertArrayHasKey(0, $build['pages'], 'The default populated Views tab is rendered.');
    $this->assertArrayNotHasKey(1, $build['pages'], 'The empty non-default Views tab is hidden.');
    $this->assertArrayHasKey(2, $build['pages'], 'The populated non-default Views tab is still rendered.');

    $rendered_titles = array_map(
      static fn(array $item): string => (string) $item[0]['#title'],
      $build[0]['#items']
    );
    $this->assertContains('Default populated view tab', $rendered_titles);
    $this->assertNotContains('Empty view tab', $rendered_titles);
    $this->assertContains('Populated view tab', $rendered_titles);

    $configured_tab_pages = $build['#attached']['drupalSettings']['quicktabs']['qt_test_hide_empty_views_ajax']['tabs'];
    $this->assertSame([0, 2], array_column($configured_tab_pages, 'tab_page'));
  }

  /**
   * Tests AJAX hides empty Views block tabs with block_hide_empty enabled.
   */
  #[IgnoreDeprecations]
  public function testAjaxEmptyViewsBlockTabIsHiddenAndPopulatedViewsBlockTabRemains(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'test_hide_empty_views_blocks_ajax',
      'label' => 'Test hide empty views blocks',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => TRUE,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => TRUE,
      'default_tab' => 0,
      'configuration_data' => [
        0 => $this->blockTab('Default populated Views block tab', 'views_block:test_view_block-block_1'),
        1 => $this->blockTab('Empty Views block tab', 'views_block:test_view_block_empty-block_1'),
        2 => $this->blockTab('Populated Views block tab', 'views_block:test_view_block-block_1'),
      ],
    ]);

    $renderer = $this->container->get('plugin.manager.tab_renderer')->createInstance('quick_tabs');
    $build = $renderer->render($instance);

    $this->assertArrayHasKey('pages', $build);
    $this->assertArrayHasKey(0, $build['pages'], 'The default populated Views block tab is rendered.');
    $this->assertArrayNotHasKey(1, $build['pages'], 'The empty non-default Views block tab is hidden.');
    $this->assertArrayHasKey(2, $build['pages'], 'The populated non-default Views block tab is still rendered.');

    $rendered_titles = array_map(
      static fn(array $item): string => (string) $item[0]['#title'],
      $build[0]['#items']
    );
    $this->assertContains('Default populated Views block tab', $rendered_titles);
    $this->assertNotContains('Empty Views block tab', $rendered_titles);
    $this->assertContains('Populated Views block tab', $rendered_titles);

    $configured_tab_pages = $build['#attached']['drupalSettings']['quicktabs']['qt_test_hide_empty_views_blocks_ajax']['tabs'];
    $this->assertSame([0, 2], array_column($configured_tab_pages, 'tab_page'));
  }

  /**
   * Enables block_hide_empty and creates an empty Views block fixture.
   */
  protected function prepareViewsBlockDisplays(): void {
    $view_storage = $this->container->get('entity_type.manager')->getStorage('view');

    $populated_view = $view_storage->load('test_view_block');
    $display = $populated_view->get('display');
    $display['block_1']['display_options']['block_hide_empty'] = TRUE;
    $populated_view->set('display', $display);
    $populated_view->save();

    $empty_view_data = $populated_view->toArray();
    unset($empty_view_data['uuid']);
    $empty_view_data['id'] = 'test_view_block_empty';
    $empty_view_data['label'] = 'test_view_block_empty';
    $empty_view_data['display']['default']['display_options']['filters']['age'] = [
      'id' => 'age',
      'table' => 'views_test_data',
      'field' => 'age',
      'relationship' => 'none',
      'operator' => '=',
      'value' => [
        'value' => '999',
        'min' => '',
        'max' => '',
      ],
      'group' => 1,
      'exposed' => FALSE,
      'plugin_id' => 'numeric',
    ];
    $view_storage->create($empty_view_data)->save();
    $this->container->get('plugin.manager.block')->clearCachedDefinitions();
  }

  /**
   * Builds a Quicktabs Block tab for a specific block plugin.
   */
  protected function blockTab(string $title, string $block_id): array {
    return [
      'title' => $title,
      'type' => 'block_content',
      'content' => [
        'block_content' => [
          'options' => [
            'bid' => $block_id,
          ],
        ],
      ],
    ];
  }

}
