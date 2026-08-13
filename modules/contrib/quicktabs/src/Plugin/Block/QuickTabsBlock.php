<?php

namespace Drupal\quicktabs\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'QuickTabs' block.
 */
#[Block(
  id: "quicktabs_block",
  admin_label: new TranslatableMarkup("QuickTabs Block"),
  category: new TranslatableMarkup("QuickTabs"),
  deriver: \Drupal\quicktabs\Plugin\Derivative\QuickTabsBlock::class
)]
class QuickTabsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * Constructs a BlockComponentRenderArray object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $qt_id = $this->getDerivativeId();
    $qt = $this->entityTypeManager->getStorage('quicktabs_instance')->load($qt_id);

    if (!isset($qt)) {
      $this->getLogger('quicktabs')->warning('Broken QuickTabs block. QuickTabs block does not exist.');
      return [];
    }

    $build = $qt->getRenderArray();
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($qt)
      ->applyTo($build);

    return $build;
  }

}
