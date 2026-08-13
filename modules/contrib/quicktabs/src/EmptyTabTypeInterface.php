<?php

namespace Drupal\quicktabs;

/**
 * Defines an optional interface for tab types with custom empty detection.
 */
interface EmptyTabTypeInterface extends TabTypeInterface {

  /**
   * Determines whether the rendered tab has no content.
   */
  public function isEmpty(array $tab, array $render, bool $is_ajax): bool;

}
