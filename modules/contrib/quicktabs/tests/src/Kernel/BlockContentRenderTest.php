<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\quicktabs\Plugin\TabType\BlockContent as BlockContentPlugin;
use Drupal\user\Entity\User;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the BlockContent TabType rendering.
 *
 * @group quicktabs
 */
#[RunTestsInSeparateProcesses]
class BlockContentRenderTest extends KernelTestBase {

  use BlockCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'block_content',
    'quicktabs',
  ];

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected UserInterface $user;

  /**
   * The BlockContent plugin instance.
   *
   * @var \Drupal\quicktabs\Plugin\TabType\BlockContent
   */
  protected BlockContentPlugin $blockContentPlugin;

  /**
   * Setup before each test.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    // Install necessary schema.
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('user');

    // Create a test user.
    $this->user = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $this->user->save();

    $container = $this->container;
    // Create a BlockContent plugin instance.
    $this->blockContentPlugin = new BlockContentPlugin(
      [],
      'block_content',
      [],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.block'),
      $container->get('context.repository'),
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('router')
    );
  }

  /**
   * Tests rendering a custom block content.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRenderBlockContent() {
    // Create a block content entity.
    $blockContent = BlockContent::create([
      'info' => 'Test Block',
      'type' => 'basic',
      'uuid' => 'test-uuid-123',
    ]);
    $blockContent->save();

    $tab = [
      'type' => 'block_content',
      'content' => [
        'block_content' => [
          'options' => [
            'bid' => 'block_content:test-uuid-123',
          ],
        ],
      ],
    ];

    $output = $this->blockContentPlugin->render($tab);

    // Ensure output is not empty.
    $this->assertNotEmpty($output, 'Block content was rendered correctly.');

    // Ensure cache tags are merged properly.
    $expected_cache_tags = Cache::mergeTags([], $blockContent->getCacheTags());
    $this->assertEqualsCanonicalizing(
      array_merge($expected_cache_tags, ['block_content_view']),
      $output['#cache']['tags'],
      'Cache tags are correctly merged.'
    );
  }

  /**
   * Tests rendering a plugin block.
   */
  public function testRenderPluginBlock() {
    $tab = [
      'type' => 'plugin',
      'content' => [
        'plugin' => [
          'options' => [
            'bid' => 'system_powered_by_block',
          ],
        ],
      ],
    ];

    $output = $this->blockContentPlugin->render($tab);

    // Check that output is not empty.
    $this->assertNotEmpty($output, 'Plugin block was rendered correctly.');
  }

  /**
   * Tests plugin and access-result cacheability is merged into the build.
   */
  public function testRenderPluginBlockMergesAllCacheability(): void {
    $mock_block = $this->getMockBuilder('Drupal\Core\Block\BlockBase')
      ->disableOriginalConstructor()
      ->getMock();
    $mock_block->method('access')
      ->willReturn(AccessResult::allowed()
        ->addCacheContexts(['user.roles'])
        ->addCacheTags(['quicktabs_test_access']));
    $mock_block->method('build')
      ->willReturn(['#markup' => 'Cacheable block']);
    $mock_block->method('getCacheContexts')
      ->willReturn(['user']);
    $mock_block->method('getCacheTags')
      ->willReturn(['quicktabs_test_plugin']);
    $mock_block->method('getCacheMaxAge')
      ->willReturn(73);

    $block_manager = $this->createMock(BlockManagerInterface::class);
    $block_manager->method('createInstance')->willReturn($mock_block);
    $this->blockContentPlugin = $this->createPlugin($block_manager);

    $output = $this->blockContentPlugin->render($this->pluginTab());
    $metadata = CacheableMetadata::createFromRenderArray($output);

    $this->assertEqualsCanonicalizing(
      ['user', 'user.roles'],
      $metadata->getCacheContexts()
    );
    $this->assertEqualsCanonicalizing(
      ['quicktabs_test_access', 'quicktabs_test_plugin'],
      $metadata->getCacheTags()
    );
    $this->assertSame(73, $metadata->getCacheMaxAge());
  }

  /**
   * Tests a neutral block access result is denied and remains cacheable.
   */
  public function testRenderPluginBlockWithNeutralAccessDenied(): void {
    $mock_block = $this->getMockBuilder('Drupal\Core\Block\BlockBase')
      ->disableOriginalConstructor()
      ->getMock();
    $mock_block->method('access')
      ->willReturn(AccessResult::neutral()
        ->addCacheContexts(['user.roles'])
        ->addCacheTags(['quicktabs_test_neutral_access']));
    $mock_block->expects($this->never())->method('build');

    $block_manager = $this->createMock(BlockManagerInterface::class);
    $block_manager->method('createInstance')->willReturn($mock_block);
    $this->blockContentPlugin = $this->createPlugin($block_manager);

    $output = $this->blockContentPlugin->render($this->pluginTab());
    $metadata = CacheableMetadata::createFromRenderArray($output);

    $this->assertEmpty(array_diff(array_keys($output), ['#cache']));
    $this->assertContains('user.roles', $metadata->getCacheContexts());
    $this->assertContains('quicktabs_test_neutral_access', $metadata->getCacheTags());
  }

  /**
   * Tests an unpublished reusable content block is not rendered.
   */
  public function testRenderUnpublishedBlockContentWithAccessDenied(): void {
    $block_content = BlockContent::create([
      'info' => 'Unpublished test block',
      'type' => 'basic',
      'uuid' => 'unpublished-test-uuid',
      'status' => FALSE,
      'reusable' => TRUE,
    ]);
    $block_content->save();

    $access = $block_content->access(
      'view',
      $this->container->get('current_user'),
      TRUE
    );
    $this->assertFalse($access->isAllowed(), 'The test account must not have access to the unpublished block.');

    $tab = [
      'type' => 'block_content',
      'content' => [
        'block_content' => [
          'options' => [
            'bid' => 'block_content:unpublished-test-uuid',
          ],
        ],
      ],
    ];
    $output = $this->blockContentPlugin->render($tab);

    $this->assertEmpty(
      array_diff(array_keys($output), ['#cache']),
      'An access-denied reusable content block returned no renderable content.'
    );
    $this->assertContains(
      'block_content:' . $block_content->id(),
      CacheableMetadata::createFromRenderArray($output)->getCacheTags()
    );
  }

  /**
   * Tests that access restrictions work.
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   * @throws \Exception
   */
  public function testRenderPluginBlockWithAccessDenied() {
    // Mock a block plugin.
    $mock_block = $this->getMockBuilder('Drupal\Core\Block\BlockBase')
      ->disableOriginalConstructor()
      ->getMock();

    // Force access check to return forbidden.
    $mock_block->method('access')
      ->willReturn(AccessResult::forbidden());

    // Mock the block manager to return the mocked block.
    $block_manager_mock = $this->createMock(BlockManagerInterface::class);
    $block_manager_mock->method('createInstance')->willReturn($mock_block);
    $container = $this->container;
    // Inject the mock block manager into the plugin.
    $this->blockContentPlugin = new BlockContentPlugin(
      [],
      'block_content',
      [],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $block_manager_mock,
      $container->get('context.repository'),
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('router')
    );

    // Define a tab array for the test.
    $tab = [
      'type' => 'plugin',
      'content' => [
        'plugin' => [
          'options' => [
            'bid' => 'some_protected_block',
          ],
        ],
      ],
    ];

    // Call render() and check if access is denied.
    $output = $this->blockContentPlugin->render($tab);

    // The output carries no content when access is denied, only the access
    // result's cacheability, so the tab is not cached as empty for users who
    // are allowed to see the block.
    // @see \Drupal\quicktabs\Plugin\TabType\BlockContent::isEmpty()
    $this->assertEmpty(
      array_diff(array_keys($output), ['#cache']),
      'Access denied block returned no renderable content.'
    );
    $this->assertTrue($this->blockContentPlugin->isEmpty($tab, $output, FALSE));
  }

  /**
   * Creates the tab type plugin with a specific block manager.
   */
  protected function createPlugin(BlockManagerInterface $block_manager): BlockContentPlugin {
    $container = $this->container;
    return new BlockContentPlugin(
      [],
      'block_content',
      [],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $block_manager,
      $container->get('context.repository'),
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('router')
    );
  }

  /**
   * Returns a plugin-block tab configuration.
   */
  protected function pluginTab(): array {
    return [
      'type' => 'plugin',
      'content' => [
        'plugin' => [
          'options' => [
            'bid' => 'some_protected_block',
          ],
        ],
      ],
    ];
  }

}
