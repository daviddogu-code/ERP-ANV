<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that empty and access-denied view tabs still carry cacheability.
 *
 * A tab that renders as empty is not free of cacheability: whatever made it
 * empty — the display's access plugin, or a view with no matching rows — has
 * to be able to un-empty it for the next visitor.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class ViewContentCacheabilityTest extends QuickTabsViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_simple_argument'];

  /**
   * Renders a view tab for the given argument.
   */
  protected function renderViewTab(string $argument): array {
    $tab = $this->viewTab('Ages', $argument);
    return $this->container->get('plugin.manager.tab_type')
      ->createInstance('view_content')
      ->render($tab);
  }

  /**
   * A view with no matching rows still reports the view's cache tags.
   */
  public function testEmptyResultCarriesViewCacheTags(): void {
    // No row in views_test_data has this age, so the view returns nothing.
    $render = $this->renderViewTab('9999');

    $this->assertEmpty(
      array_diff(array_keys($render), ['#cache']),
      'The tab renders no content.'
    );
    $this->assertContains(
      'config:views.view.test_simple_argument',
      CacheableMetadata::createFromRenderArray($render)->getCacheTags(),
      'Without the view cache tags the tab stays cached as empty even after '
      . 'content is added that would populate it.'
    );
  }

  /**
   * A populated view is unaffected and still carries its own cacheability.
   */
  public function testPopulatedResultStillCarriesCacheTags(): void {
    $render = $this->renderViewTab('25');
    $this->assertContains(
      'config:views.view.test_simple_argument',
      CacheableMetadata::createFromRenderArray($render)->getCacheTags()
    );
  }

  /**
   * An access-denied view still reports the access plugin's cache context.
   */
  public function testAccessDeniedViewCarriesAccessCacheability(): void {
    $view = $this->container->get('entity_type.manager')
      ->getStorage('view')
      ->load('test_simple_argument');
    $display = &$view->getDisplay('default');
    $display['display_options']['access'] = [
      'type' => 'role',
      'options' => [
        'role' => [
          'authenticated' => 'authenticated',
        ],
      ],
    ];
    $view->save();

    $this->assertTrue($this->container->get('current_user')->isAnonymous());
    $render = $this->renderViewTab('25');
    $metadata = CacheableMetadata::createFromRenderArray($render);

    $this->assertEmpty(
      array_diff(array_keys($render), ['#cache']),
      'The role-restricted view returned no content to an anonymous user.'
    );
    $this->assertContains(
      'user.roles',
      $metadata->getCacheContexts(),
      'The denied result must vary by the role access plugin.'
    );
  }

  /**
   * A tab hidden by hide_empty_tabs bubbles its cacheability to the tabset.
   */
  public function testHiddenTabBubblesCacheabilityToTabset(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'hidden_cacheability',
      'label' => 'Hidden cacheability',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => FALSE,
          'style' => '',
          'class' => '',
        ],
      ],
      // The empty tab is dropped from the tabset entirely.
      'hide_empty_tabs' => TRUE,
      'remember_last_clicked_tab' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [
        $this->viewTab('Present', '25'),
        $this->viewTab('Absent', '9999'),
      ],
    ]);
    $instance->save();

    $build = $instance->getRenderArray();

    // The hidden tab produced no tab page.
    $this->assertArrayNotHasKey(1, $build['pages']);
    // But the tabset still depends on the view that came up empty, so adding
    // content re-renders the tabset with the tab restored.
    $this->assertContains(
      'config:views.view.test_simple_argument',
      CacheableMetadata::createFromRenderArray($build)->getCacheTags(),
      'A hidden tab must not silently drop its cacheability.'
    );
  }

}
