<?php

namespace Drupal\quicktabs_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A test block that renders no content but still declares cacheability.
 *
 * Stands in for any block whose presence is conditional — an access check,
 * a query condition, anything context-dependent — but whose ::build() array
 * itself carries no #cache. That is exactly the shape that goes missing when
 * a hidden or shown AJAX tab's emptiness is decided from real content but the
 * tabset bubbles a loading placeholder's (contentless) cacheability instead.
 *
 * @see \Drupal\quicktabs\Plugin\TabRenderer\QuickTabs::render()
 */
#[Block(
  id: "quicktabs_test_empty_with_cacheability",
  admin_label: new TranslatableMarkup("Quick Tabs empty-with-cacheability test block"),
)]
class EmptyWithCacheabilityBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
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
    return Cache::mergeTags(parent::getCacheTags(), ['quicktabs_test_empty_with_cacheability']);
  }

}
