<?php

namespace Drupal\Tests\quicktabs\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;

/**
 * Tests the Quick Tabs Views style grouping tab titles.
 *
 * @group quicktabs
 */
class QuickTabsViewsGroupingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'views',
    'quicktabs',
    'quicktabs_test_views',
  ];

  /**
   * Tests that a grouped tab title with "&" is not double-encoded.
   */
  public function testGroupingTitleEncoding() {
    // Import the grouped view fixture shipped with the test module.
    $path = \Drupal::service('extension.list.module')->getPath('quicktabs_test_views');
    $values = Yaml::decode(file_get_contents($path . '/test_views/views.view.quicktabs_grouped.yml'));
    View::create($values)->save();

    $this->drupalCreateContentType(['type' => 'article']);
    $this->drupalCreateNode([
      'title' => 'Meetings & Events',
      'type' => 'article',
    ]);

    $view = Views::getView('quicktabs_grouped');
    $this->assertNotNull($view, 'The grouped quicktabs view loaded.');
    $build = $view->preview('default');
    $html = (string) \Drupal::service('renderer')->renderRoot($build);

    // The group value becomes the tab title; "&" must be encoded exactly once.
    $this->assertStringContainsString('Meetings &amp; Events', $html);
    $this->assertStringNotContainsString('&amp;amp;', $html);
  }

}
