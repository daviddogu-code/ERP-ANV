<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\quicktabs\Controller\QuickTabsController;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the cacheability of the AJAX tab content response.
 *
 * The response is stored by Dynamic Page Cache under the cache contexts it
 * declares, so anything the tab content varies by has to reach the response.
 * Cacheability contributed by child elements and #pre_render callbacks only
 * exists once the content is rendered, which happens when the AJAX command is
 * turned into markup — after the response's metadata would otherwise have been
 * finalised.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class AjaxResponseCacheabilityTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');

    QuickTabsInstance::create([
      'id' => 'ajax_cacheability',
      'label' => 'Ajax cacheability',
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => TRUE,
          'style' => '',
          'class' => '',
        ],
      ],
      'hide_empty_tabs' => FALSE,
      'remember_last_clicked_tab' => FALSE,
      'default_tab' => 0,
      'configuration_data' => [
        [
          'title' => 'Bubbling',
          'type' => 'block_content',
          'weight' => 0,
          'content' => [
            'block_content' => [
              'options' => [
                'bid' => 'quicktabs_test_bubbling_block',
                'display_title' => 0,
                'block_title' => '',
              ],
            ],
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Returns the cacheable metadata of the AJAX response for tab 0.
   */
  protected function responseMetadata(): CacheableMetadata {
    $response = QuickTabsController::create($this->container)
      ->ajaxContent('ajax', 'ajax_cacheability', 0);
    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    return $response->getCacheableMetadata();
  }

  /**
   * Cacheability declared by a child element reaches the response.
   */
  public function testChildElementCacheabilityReachesResponse(): void {
    $metadata = $this->responseMetadata();

    $this->assertContains(
      'quicktabs_test_bubbled_child',
      $metadata->getCacheTags(),
      'A cache tag declared on a child element must invalidate the AJAX response.'
    );
    $this->assertContains(
      'user',
      $metadata->getCacheContexts(),
      "A child element's cache context must vary the AJAX response, or Dynamic "
      . "Page Cache serves one user's tab content to another."
    );
  }

  /**
   * Cacheability added by a #pre_render callback reaches the response.
   */
  public function testPreRenderCacheabilityReachesResponse(): void {
    $this->assertContains(
      'quicktabs_test_bubbled_pre_render',
      $this->responseMetadata()->getCacheTags(),
      'Cacheability added during rendering must reach the AJAX response.'
    );
  }

}
