<?php

namespace Drupal\quicktabs_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders per-user content with cacheability only on the plugin object.
 */
#[Block(
  id: 'quicktabs_test_per_user_cache_context',
  admin_label: new TranslatableMarkup('Quick Tabs per-user cache context test block'),
)]
class PerUserCacheContextBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a per-user cache context test block.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => 'Plugin secret for ' . $this->currentUser->getAccountName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['quicktabs_test_per_user_plugin']);
  }

}
