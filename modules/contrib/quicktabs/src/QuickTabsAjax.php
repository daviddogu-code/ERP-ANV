<?php

namespace Drupal\quicktabs;

/**
 * Defines shared Quick Tabs AJAX request parameter names.
 */
final class QuickTabsAjax {

  /**
   * Query parameter containing the source page path for AJAX tab callbacks.
   */
  public const SOURCE_PATH_QUERY = 'quicktabs_path';

  /**
   * Query parameters that must not be carried onto AJAX tab requests.
   *
   * These are Quick Tabs and Drupal internal parameters; genuine query
   * parameters such as a View's exposed filters are intentionally preserved.
   *
   * @return string[]
   *   The query parameter names to strip from AJAX tab links and the
   *   reconstructed source request.
   */
  public static function filteredQueryParameters(): array {
    return [
      self::SOURCE_PATH_QUERY,
      'ajax_page_state',
      'ajax_form',
      '_drupal_ajax',
      '_wrapper_format',
      '_format',
      'destination',
      'token',
    ];
  }

}
