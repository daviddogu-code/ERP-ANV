<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\quicktabs\Plugin\TabType\NodeContent as NodeContentPlugin;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the NodeContent TabType's access-result cacheability.
 *
 * Same shape as the BlockContent tab type: the access check is a separate
 * cacheable object from the render array ::render() returns, so the caller
 * has to merge it in explicitly.
 *
 * @see \Drupal\Tests\quicktabs\Kernel\BlockContentCacheabilityTest
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class NodeContentCacheabilityTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'quicktabs',
  ];

  /**
   * The NodeContent plugin under test.
   */
  protected NodeContentPlugin $tabType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'field', 'filter', 'user']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // The current user throughout is the default anonymous user; 'access
    // content' is granted so a published node is viewable, but 'view own
    // unpublished content' is deliberately withheld, so an unpublished node
    // is denied.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);
    \Drupal::service(NodeAccessRebuild::class)->rebuild();

    $this->tabType = new NodeContentPlugin(
      [],
      'node_content',
      [],
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_display.repository'),
      $this->container->get('current_user')
    );
  }

  /**
   * Renders the given node as a tab.
   */
  protected function renderNodeTab(int $nid): array {
    $tab = [
      'type' => 'node_content',
      'content' => [
        'node_content' => [
          'options' => [
            'nid' => $nid,
            'view_mode' => 'full',
            'hide_title' => 0,
          ],
        ],
      ],
    ];
    return $this->tabType->render($tab);
  }

  /**
   * A viewable node's tab carries the access result's cacheability.
   */
  public function testAllowedNodeCarriesAccessCacheability(): void {
    $node = $this->createNode(['type' => 'page', 'status' => 1]);

    $render = $this->renderNodeTab((int) $node->id());

    $this->assertNotEmpty($render);
    $this->assertContains(
      'node:' . $node->id(),
      CacheableMetadata::createFromRenderArray($render)->getCacheTags()
    );
  }

  /**
   * An unpublished node is denied and still carries the access cacheability.
   *
   * Node access is checked separately from the view builder's own cache
   * metadata; without merging it, an unpublished node's tab could stay
   * cached as visible once it's later published for a user with 'access
   * content' but no 'bypass node access'.
   */
  public function testForbiddenNodeCarriesAccessCacheability(): void {
    $node = $this->createNode(['type' => 'page', 'status' => 0]);

    $render = $this->renderNodeTab((int) $node->id());

    $this->assertEmpty(
      array_diff(array_keys($render), ['#cache']),
      'Access denied node returned no renderable content.'
    );
    $this->assertTrue($this->tabType->isEmpty([], $render, FALSE));

    $metadata = CacheableMetadata::createFromRenderArray($render);
    $this->assertContains('node:' . $node->id(), $metadata->getCacheTags());
  }

}
