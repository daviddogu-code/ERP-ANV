<?php

namespace Drupal\quicktabs\Hook;

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for quicktabs.
 */
class QuicktabsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name == 'quicktabs.admin') {
      return '<p>' . $this->t('Each QuickTabs instance has a corresponding block that is managed on the <a href=":link">blocks administration page</a>.', [
        ':link' => Url::fromRoute('block.admin_display')->setAbsolute()->toString(),
      ]) . '</p>';
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme($existing, $type, $theme, $path): array {
    return [
      'quicktabs_loading' => [
        'variables' => [
          'message' => NULL,
          'tabid' => NULL,
          'instance' => NULL,
          'attributes' => [],
        ],
        'template' => 'quicktabs-loading',
      ],
      'quicktabs_block_content' => [
        'variables' => [
          'title' => NULL,
          'block' => NULL,
          'classes' => NULL,
          'id' => NULL,
          'tabid' => NULL,
        ],
        'template' => 'quicktabs-block-content',
      ],
    ];
  }

}
