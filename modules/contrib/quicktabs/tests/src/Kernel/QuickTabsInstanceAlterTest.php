<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\quicktabs\Controller\QuickTabsController;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests hook_quicktabs_instance_alter() on both render paths.
 *
 * Confirms the alter fires on the server-rendered path and the AJAX controller,
 * and that the changes it makes (a View argument, a reorder) apply to a tab
 * loaded through the AJAX controller.
 *
 * views_test_data dataset: age 25 = John, 27 = George, 28 = Ringo.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class QuickTabsInstanceAlterTest extends QuickTabsViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_simple_argument',
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
    'quicktabs_alter_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->container->get('state')->delete('quicktabs_alter_test.alter_calls');
  }

  /**
   * Tests the alter fires on the server-rendered path.
   */
  public function testAlterFiresOnServerRender(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'test_alter',
      'label' => 'Test alter',
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
        0 => $this->viewTab('Tab One', '25'),
      ],
    ]);

    $instance->getRenderArray();

    $calls = $this->container->get('state')->get('quicktabs_alter_test.alter_calls', []);
    $this->assertContains('test_alter', $calls);
  }

  /**
   * Tests the alter fires on the AJAX controller path.
   */
  public function testAlterFiresOnAjaxController(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'test_alter_ajax',
      'label' => 'Test alter ajax',
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
        0 => $this->viewTab('Tab One', '25'),
      ],
    ]);
    $instance->save();

    $request = Request::create('/quicktabs/ajax/nojs/test_alter_ajax/0');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\quicktabs\Controller\QuickTabsController');
    $controller->ajaxContent('ajax', $instance->id(), 0);

    $calls = $this->container->get('state')->get('quicktabs_alter_test.alter_calls', []);
    $this->assertContains('test_alter_ajax', $calls);
  }

  /**
   * A View argument injected by the alter applies on the AJAX-loaded tab.
   */
  public function testInjectedArgumentAppliesOnAjax(): void {
    $this->saveInstance('argument', [
      0 => $this->viewTab('John', '25'),
      1 => $this->viewTab('All', ''),
    ]);
    // The alter filters tab 1 to age 27, so only George renders.
    $output = $this->renderAjaxTab('argument', 1);
    $this->assertStringContainsString('George', $output);
    $this->assertStringNotContainsString('John', $output);
  }

  /**
   * A reorder applied by the alter resolves the AJAX index to the new tab.
   */
  public function testReorderAppliesOnAjax(): void {
    $this->saveInstance('reorder', [
      0 => $this->viewTab('John', '25'),
      1 => $this->viewTab('Ringo', '28'),
      2 => $this->viewTab('George', '27'),
    ]);
    // The alter reverses the tabs, so index 2 is now the age-25 tab (John).
    $output = $this->renderAjaxTab('reorder', 2);
    $this->assertStringContainsString('John', $output);
    $this->assertStringNotContainsString('George', $output);
  }

  /**
   * Cache metadata an alter adds to the instance reaches the AJAX response.
   */
  public function testAlterCacheMetadataPropagatesOnAjax(): void {
    $this->saveInstance('cache', [
      0 => $this->viewTab('John', '25'),
    ]);

    $metadata = $this->ajaxResponse('cache', 0)->getCacheableMetadata();
    $this->assertContains('user.roles', $metadata->getCacheContexts());
    $this->assertContains('quicktabs_alter_test:cache', $metadata->getCacheTags());
  }

  /**
   * A request for a non-existent instance returns a 404 instead of a fatal.
   */
  public function testMissingInstanceIsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->ajaxResponse('does_not_exist', 0);
  }

  /**
   * An out-of-range tab index renders empty instead of a fatal.
   */
  public function testUnknownTabIndexRendersEmpty(): void {
    $instance = QuickTabsInstance::create([
      'id' => 'one_tab',
      'label' => 'one_tab',
      'renderer' => 'quick_tabs',
      'options' => ['quick_tabs' => ['ajax' => TRUE, 'style' => '', 'class' => '']],
      'hide_empty_tabs' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [0 => $this->viewTab('John', '25')],
    ]);

    $this->assertSame([], $instance->renderTab(5));
  }

  /**
   * Creates and saves an AJAX-enabled instance.
   */
  protected function saveInstance(string $id, array $tabs): void {
    QuickTabsInstance::create([
      'id' => $id,
      'label' => $id,
      'renderer' => 'quick_tabs',
      'options' => ['quick_tabs' => ['ajax' => TRUE, 'style' => '', 'class' => '']],
      'hide_empty_tabs' => FALSE,
      'default_tab' => 0,
      'configuration_data' => $tabs,
    ])->save();
  }

  /**
   * Renders one tab through the AJAX controller (where the alter runs).
   */
  protected function renderAjaxTab(string $id, int $index): string {
    return (string) $this->ajaxResponse($id, $index)->getCommands()[0]['data'];
  }

  /**
   * Invokes the AJAX controller for one tab and returns the response.
   *
   * Owns the request_stack push/pop so callers and test methods do not have to.
   */
  protected function ajaxResponse(string $id, int $index): CacheableAjaxResponse {
    $request = Request::create('/quicktabs/ajax/' . $id . '/' . $index);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);
    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(QuickTabsController::class);
    $response = $controller->ajaxContent('ajax', $id, $index);
    $request_stack->pop();
    return $response;
  }

}
