<?php

namespace Drupal\quicktabs_accordion\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for quicktabs_accordion.
 */
class QuicktabsAccordionHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.quicktabs_accordion':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Makes accordion rendering from jQuery UI available to the Quicktabs module.') . '</p>';
        return $output;
    }
  }

}
