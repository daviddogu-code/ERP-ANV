<?php

namespace Drupal\quicktabs_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A test block that always renders content but declares cacheability.
 *
 * The non-empty counterpart to EmptyWithCacheabilityBlock: proves that the
 * bubbling fix applies whether the tab ends up shown or hidden, since the
 * decision to show it is exactly as cacheable as the decision to hide it.
 *
 * @see \Drupal\quicktabs_test_block\Plugin\Block\EmptyWithCacheabilityBlock
 */
#[Block(
  id: "quicktabs_test_nonempty_with_cacheability",
  admin_label: new TranslatableMarkup("Quick Tabs non-empty-with-cacheability test block"),
)]
class NonEmptyWithCacheabilityBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return ['#markup' => 'Content'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.query_args:reveal']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['quicktabs_test_nonempty_with_cacheability']);
  }

}
