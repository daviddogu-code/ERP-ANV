<?php

namespace Drupal\quicktabs_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A test block whose cacheability is only knowable after rendering.
 *
 * The metadata sits on a child element and behind a #pre_render callback,
 * neither of which is visible in the array ::build() returns. This is the
 * normal shape of any entity or field render array: the view builder declares
 * the entity's own tags up front, while formatters contribute theirs as they
 * are rendered.
 */
#[Block(
  id: "quicktabs_test_bubbling_block",
  admin_label: new TranslatableMarkup("Quick Tabs bubbling cacheability test block"),
)]
class BubblingCacheabilityBlock extends BlockBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderDeferred'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      'child' => [
        '#markup' => 'Bubbling test block body',
        '#cache' => [
          'tags' => ['quicktabs_test_bubbled_child'],
          'contexts' => ['user'],
        ],
      ],
      'deferred' => [
        '#pre_render' => [[static::class, 'preRenderDeferred']],
      ],
    ];
  }

  /**
   * Adds cacheability that only exists once the element is rendered.
   */
  public static function preRenderDeferred(array $element): array {
    $element['#markup'] = 'Deferred body';
    $element['#cache']['tags'][] = 'quicktabs_test_bubbled_pre_render';
    return $element;
  }

}
