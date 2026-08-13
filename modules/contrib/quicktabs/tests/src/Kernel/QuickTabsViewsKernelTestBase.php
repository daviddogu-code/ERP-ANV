<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base class for Quick Tabs kernel tests using Views fixtures.
 */
abstract class QuickTabsViewsKernelTestBase extends ViewsKernelTestBase {

  /**
   * Test fixture modules that provide Views config.
   *
   * @var array
   */
  protected static $testViewModules = [
    'views_test_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path_alias',
    'views',
    'views_test_config',
    'views_test_data',
    'quicktabs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);
    $this->installEntitySchema('path_alias');
    ViewTestData::createTestViews(static::class, static::$testViewModules);
  }

  /**
   * Builds a Quicktabs Views tab for a specific age argument.
   */
  protected function viewTab(string $title, string $argument): array {
    return [
      'title' => $title,
      'type' => 'view_content',
      'content' => [
        'view_content' => [
          'options' => [
            'vid' => 'test_simple_argument',
            'display' => 'default',
            'args' => $argument,
          ],
        ],
      ],
    ];
  }

}
