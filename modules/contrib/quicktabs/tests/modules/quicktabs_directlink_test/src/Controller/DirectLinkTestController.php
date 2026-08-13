<?php

namespace Drupal\quicktabs_directlink_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\quicktabs\Entity\QuickTabsInstance;

/**
 * Renders Quick Tabs instances for direct-linking Nightwatch tests.
 */
class DirectLinkTestController extends ControllerBase {

  /**
   * Builds a single Quick Tabs instance with direct linking enabled.
   *
   * @return array
   *   The render array for the Quick Tabs instance.
   */
  public function page(): array {
    return $this->buildInstance('qtdl_test', 'Quick Tabs direct link test');
  }

  /**
   * Builds two Quick Tabs instances on a single page.
   *
   * Both instances use identical tab titles (and therefore identical slugs) so
   * the test can verify that direct-link fragments are namespaced per instance
   * and do not collide.
   *
   * @return array
   *   The render array for both Quick Tabs instances.
   */
  public function multiple(): array {
    return [
      'one' => $this->buildInstance('qtdl_a', 'Quick Tabs A'),
      'two' => $this->buildInstance('qtdl_b', 'Quick Tabs B'),
    ];
  }

  /**
   * Builds a two-tab Quick Tabs instance with direct linking enabled.
   *
   * @param string $id
   *   The instance ID.
   * @param string $label
   *   The instance label.
   *
   * @return array
   *   The render array for the Quick Tabs instance.
   */
  protected function buildInstance(string $id, string $label): array {
    $instance = QuickTabsInstance::create([
      'id' => $id,
      'label' => $label,
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
          'title' => 'First Tab',
          'type' => 'block_content',
          'content' => [
            'block_content' => ['options' => ['bid' => 'system_powered_by_block']],
          ],
        ],
        [
          'title' => 'Second Tab',
          'type' => 'block_content',
          'content' => [
            'block_content' => ['options' => ['bid' => 'system_powered_by_block']],
          ],
        ],
      ],
    ]);

    return $instance->getRenderArray();
  }

}
