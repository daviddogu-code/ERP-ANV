<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\quicktabs\QuickTabsAjax;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests AJAX Views tabs with contextual filters.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QuickTabsAjaxViewsContextualFilterTest extends QuickTabsViewsKernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'quicktabs_node_argument',
    'test_simple_argument',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $testViewModules = [
    'quicktabs_test_views',
    'views_test_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'filter',
    'node',
    'text',
    'user',
    'path_alias',
    'views',
    'quicktabs_test_views',
    'views_test_config',
    'views_test_data',
    'quicktabs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'filter', 'node']);

    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // The source route is now resolved with access checks, so the acting user
    // must be allowed to view the source node for its route context to apply.
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Tests AJAX Views tabs resolve contextual filters from the source path.
   */
  public function testAjaxViewTabResolvesContextualFilterFromSourcePath(): void {
    $this->configureSimpleArgumentViewToUseRawUrlDefault();

    $request = Request::create('/quicktabs/ajax/nojs/test_raw_url_argument/1', 'GET', [
      QuickTabsAjax::SOURCE_PATH_QUERY => '/group/27',
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $instance = QuickTabsInstance::create([
      'id' => 'test_raw_url_argument',
      'label' => 'Test raw URL argument',
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
        0 => $this->viewTab('Default populated view tab', ''),
        1 => $this->viewTab('AJAX populated view tab', ''),
      ],
    ]);
    $instance->save();

    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\quicktabs\Controller\QuickTabsController');
    $response = $controller->ajaxContent('ajax', $instance->id(), 1);

    $commands = $response->getCommands();
    $this->assertSame('#quicktabs-tabpage-test_raw_url_argument-1 .quicktabs-tabpage-content', $commands[0]['selector']);

    $output = (string) $commands[0]['data'];
    $this->assertStringContainsString('George', $output);
    $this->assertStringNotContainsString('John', $output);
    $this->assertStringNotContainsString('Ringo', $output);
  }

  /**
   * Tests AJAX Views tabs resolve contextual filters from the source node.
   */
  public function testAjaxViewTabResolvesContextualFilterFromSourceNodeRoute(): void {
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
        'title' => 'Quick Tabs source node',
        'body' => [
          [
            'value' => 'Quick Tabs source node body',
            'format' => 'plain_text',
          ],
        ],
        'status' => 1,
      ]);
    $node->save();

    $request = Request::create('/quicktabs/ajax/nojs/test_node_argument/1', 'GET', [
      QuickTabsAjax::SOURCE_PATH_QUERY => '/node/' . $node->id(),
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $instance = QuickTabsInstance::create([
      'id' => 'test_node_argument',
      'label' => 'Test node argument',
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
        0 => $this->nodeViewTab('Node title tab', 'title_block'),
        1 => $this->nodeViewTab('Node body tab', 'body_block'),
      ],
    ]);
    $instance->save();

    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\quicktabs\Controller\QuickTabsController');
    $response = $controller->ajaxContent('ajax', $instance->id(), 1);

    $commands = $response->getCommands();
    $this->assertSame('#quicktabs-tabpage-test_node_argument-1 .quicktabs-tabpage-content', $commands[0]['selector']);

    $output = (string) $commands[0]['data'];
    $this->assertStringContainsString('Quick Tabs source node body', $output);
    $this->assertStringNotContainsString('Quick Tabs source node</a>', $output);
  }

  /**
   * Tests AJAX block tabs resolve contextual filters from the source node.
   */
  public function testAjaxViewsBlockTabResolvesContextualFilterFromSourceNodeRoute(): void {
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
        'title' => 'Quick Tabs block source node',
        'body' => [
          [
            'value' => 'Quick Tabs block source node body',
            'format' => 'plain_text',
          ],
        ],
        'status' => 1,
      ]);
    $node->save();

    $request = Request::create('/quicktabs/ajax/nojs/test_node_block_argument/1', 'GET', [
      QuickTabsAjax::SOURCE_PATH_QUERY => '/node/' . $node->id(),
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $instance = QuickTabsInstance::create([
      'id' => 'test_node_block_argument',
      'label' => 'Test node block argument',
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
        0 => $this->blockTab('Node title block tab', 'views_block:quicktabs_node_argument-title_block'),
        1 => $this->blockTab('Node body block tab', 'views_block:quicktabs_node_argument-body_block'),
      ],
    ]);
    $instance->save();

    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\quicktabs\Controller\QuickTabsController');
    $response = $controller->ajaxContent('ajax', $instance->id(), 1);

    $commands = $response->getCommands();
    $this->assertSame('#quicktabs-tabpage-test_node_block_argument-1 .quicktabs-tabpage-content', $commands[0]['selector']);

    $output = (string) $commands[0]['data'];
    $this->assertStringContainsString('Quick Tabs block source node body', $output);
    $this->assertStringNotContainsString('Quick Tabs block source node</a>', $output);
  }

  /**
   * Configures the simple argument View to read its age from the source URL.
   */
  protected function configureSimpleArgumentViewToUseRawUrlDefault(): void {
    $view_storage = $this->container->get('entity_type.manager')->getStorage('view');
    $view = $view_storage->load('test_simple_argument');
    $display = $view->get('display');
    $display['default']['display_options']['arguments']['age']['default_action'] = 'default';
    $display['default']['display_options']['arguments']['age']['default_argument_type'] = 'raw';
    $display['default']['display_options']['arguments']['age']['default_argument_options'] = [
      'index' => 1,
      'use_alias' => FALSE,
    ];
    $view->set('display', $display);
    $view->save();
  }

  /**
   * Builds a Quicktabs Views tab for a node argument View display.
   */
  protected function nodeViewTab(string $title, string $display): array {
    return [
      'title' => $title,
      'type' => 'view_content',
      'content' => [
        'view_content' => [
          'options' => [
            'vid' => 'quicktabs_node_argument',
            'display' => $display,
            'args' => '',
          ],
        ],
      ],
    ];
  }

  /**
   * Builds a Quicktabs Block tab for a block plugin.
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
