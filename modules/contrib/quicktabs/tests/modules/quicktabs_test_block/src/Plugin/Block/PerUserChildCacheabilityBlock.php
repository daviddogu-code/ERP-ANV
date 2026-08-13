<?php

namespace Drupal\quicktabs_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders per-user content with cacheability only on a child element.
 */
#[Block(
  id: 'quicktabs_test_per_user_child_cacheability',
  admin_label: new TranslatableMarkup('Quick Tabs per-user child cacheability test block'),
)]
class PerUserChildCacheabilityBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a per-user child cacheability test block.
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
      'child' => [
        '#markup' => 'Child secret for ' . $this->currentUser->getAccountName(),
        '#cache' => [
          'contexts' => ['user'],
          'tags' => ['quicktabs_test_per_user_child'],
        ],
      ],
    ];
  }

}
