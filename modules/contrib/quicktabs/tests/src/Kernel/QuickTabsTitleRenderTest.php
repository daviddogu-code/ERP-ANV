<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\quicktabs\Plugin\TabRenderer\QuickTabs;
use Drupal\quicktabs\QuickTabsAjax;
use Drupal\quicktabs\TabTypeInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests QuickTabs title rendering.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QuickTabsTitleRenderTest extends KernelTestBase {

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
   * Tests that tab titles are not double-escaped when rendered as links.
   */
  public function testTabTitleIsEscapedOnlyOnce(): void {
    $renderer = $this->createRenderer();
    $instance = QuickTabsInstance::create([
      'id' => 'test_title_rendering',
      'label' => 'Test title rendering',
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
        [
          'title' => 'Fish & Chips <em>special</em>',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [],
            ],
          ],
        ],
      ],
    ]);

    $build = $renderer->render($instance);

    $title = $build[0]['#items'][0][0]['#title'];
    $this->assertInstanceOf(MarkupInterface::class, $title);
    $this->assertSame('Fish &amp; Chips <em>special</em>', (string) $title);

    $output = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertStringContainsString('Fish &amp; Chips <em>special</em>', $output);
    $this->assertStringNotContainsString('&amp;amp;', $output);
  }

  /**
   * Tests that direct linking metadata is attached to rendered tabs.
   */
  public function testDirectLinkingMetadataIsRendered(): void {
    $renderer = $this->createRenderer();
    $instance = QuickTabsInstance::create([
      'id' => 'test_direct_linking',
      'label' => 'Test direct linking',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => FALSE,
          'style' => '',
          'class' => '',
          'direct_linking' => TRUE,
        ],
      ],
      'hide_empty_tabs' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [
        [
          'title' => 'Fish & Chips',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [],
            ],
          ],
        ],
        [
          'title' => 'Fish Chips',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [],
            ],
          ],
        ],
      ],
    ]);

    $build = $renderer->render($instance);

    $this->assertTrue($build['#attached']['drupalSettings']['quicktabs']['qt_test_direct_linking']['directLinking']);
    $this->assertSame('fish-chips', $build[0]['#items'][0][0]['#url']->getOption('attributes')['data-quicktabs-tab-name']);
    $this->assertSame('fish-chips-1', $build[0]['#items'][1][0]['#url']->getOption('attributes')['data-quicktabs-tab-name']);
  }

  /**
   * Tests that direct linking can be disabled in attached settings.
   */
  public function testDirectLinkingCanBeDisabled(): void {
    $renderer = $this->createRenderer();
    $instance = QuickTabsInstance::create([
      'id' => 'test_direct_linking_disabled',
      'label' => 'Test direct linking disabled',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => FALSE,
          'style' => '',
          'class' => '',
          'direct_linking' => FALSE,
        ],
      ],
      'hide_empty_tabs' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [
        [
          'title' => 'First tab',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [],
            ],
          ],
        ],
      ],
    ]);

    $build = $renderer->render($instance);

    $this->assertFalse($build['#attached']['drupalSettings']['quicktabs']['qt_test_direct_linking_disabled']['directLinking']);
  }

  /**
   * Tests AJAX tab links include the source path.
   */
  public function testAjaxTabLinksIncludeSourcePath(): void {
    $request = Request::create('/source-page', 'GET', [
      'filter' => 'active',
      'destination' => '/somewhere',
      '_format' => 'json',
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $renderer = $this->createRenderer();
    $instance = QuickTabsInstance::create([
      'id' => 'test_ajax_source_path',
      'label' => 'Test AJAX source path',
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
        [
          'title' => 'First tab',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [],
            ],
          ],
        ],
        [
          'title' => 'Second tab',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [],
            ],
          ],
        ],
      ],
    ]);

    $build = $renderer->render($instance);
    $query = $build[0]['#items'][1][0]['#url']->getOption('query');

    $this->assertSame('/source-page', $query[QuickTabsAjax::SOURCE_PATH_QUERY]);
    // Genuine query parameters (e.g. a View's exposed filters) are preserved.
    $this->assertSame('active', $query['filter']);
    // Drupal/redirect internal parameters are not carried onto the tab link.
    $this->assertArrayNotHasKey('destination', $query);
    $this->assertArrayNotHasKey('_format', $query);
  }

  /**
   * Tests the block title sits outside the AJAX content replacement target.
   *
   * The AJAX controller replaces the inner HTML of
   * '#quicktabs-tabpage-{id}-{tab} .quicktabs-tabpage-content'. The block
   * title rendered on initial page load must live outside that wrapper, or
   * it is wiped when the lazily loaded tab content arrives.
   *
   * @see https://www.drupal.org/project/quicktabs/issues/3545758
   */
  public function testAjaxTabTitleIsOutsideContentReplacementTarget(): void {
    $request = Request::create('/source-page');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $renderer = $this->createRenderer();
    $instance = QuickTabsInstance::create([
      'id' => 'test_ajax_title',
      'label' => 'Test AJAX title',
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
        [
          'title' => 'First tab',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [
                'display_title' => TRUE,
                'block_title' => 'First block title',
              ],
            ],
          ],
        ],
        [
          'title' => 'Second tab',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [
                'display_title' => TRUE,
                'block_title' => 'Second block title',
              ],
            ],
          ],
        ],
      ],
    ]);

    $build = $renderer->render($instance);
    $output = (string) $this->container->get('renderer')->renderRoot($build);
    $xpath = new \DOMXPath(Html::load($output));
    $tabpage = '//div[@id="quicktabs-tabpage-test_ajax_title-1"]';

    // The lazily loaded tab renders its block title on initial page load.
    $title = $xpath->query($tabpage . '/div[@class="quicktabs-block-title"]');
    $this->assertCount(1, $title);
    $this->assertSame('Second block title', trim($title->item(0)->textContent));

    // The replacement target wraps the loading placeholder but not the title,
    // so an AJAX load replaces only the placeholder.
    $content = $tabpage . '/div[@class="quicktabs-tabpage-content"]';
    $this->assertCount(1, $xpath->query($content));
    $this->assertCount(1, $xpath->query($content . '//*[contains(@class, "quicktabs-loading")]'));
    $this->assertCount(0, $xpath->query($content . '//div[@class="quicktabs-block-title"]'));
  }

  /**
   * Tests tab type plugins are not required to implement empty detection.
   */
  public function testTabTypeInterfaceRemainsBackwardsCompatible(): void {
    $renderer = $this->createRenderer();
    $instance = QuickTabsInstance::create([
      'id' => 'test_tab_type_interface_bc',
      'label' => 'Test tab type interface BC',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => FALSE,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => TRUE,
      'default_tab' => 0,
      'configuration_data' => [
        [
          'title' => 'First tab',
          'type' => 'test',
          'content' => [
            'test' => [
              'options' => [],
            ],
          ],
        ],
      ],
    ]);

    $build = $renderer->render($instance);

    $this->assertArrayHasKey(0, $build['pages']);
  }

  /**
   * Creates a QuickTabs renderer with a mocked tab type.
   */
  protected function createRenderer(): QuickTabs {
    $tab_type = $this->createMock(TabTypeInterface::class);
    $tab_type->method('render')
      ->willReturn([
        '#markup' => 'Tab body',
      ]);

    $tab_type_manager = $this->createMock('Drupal\quicktabs\TabTypeManager');
    $tab_type_manager->method('createInstance')
      ->willReturn($tab_type);

    return new QuickTabs(
      [],
      'quick_tabs',
      [],
      $tab_type_manager,
      $this->container->get('transliteration'),
      $this->container->get('language_manager'),
      $this->container->get('path.current'),
      $this->container->get('request_stack')
    );
  }

}
