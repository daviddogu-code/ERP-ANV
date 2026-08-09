<?php

namespace Drupal\eca_vbo\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\eca\Event\ConditionalApplianceInterface;
use Drupal\views\ViewExecutable;

/**
 * Base class for VBO events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class VboEventBase extends Event implements ConditionalApplianceInterface {

  /**
   * The executable view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected ViewExecutable $view;

  /**
   * The operation name, or NULL if not defined.
   *
   * @var string|null
   */
  protected ?string $operationName = NULL;

  /**
   * {@inheritdoc}
   */
  public function appliesForLazyLoadingWildcard(string $wildcard): bool {
    [$w_operation_names, $w_view_ids, $w_display_ids] = explode('::', $wildcard);

    if (($w_operation_names !== '*') && (!isset($this->operationName) || !in_array($this->operationName, explode(',', $w_operation_names)))) {
      return FALSE;
    }

    if (($w_view_ids !== '*') && !in_array($this->view->id(), explode(',', $w_view_ids))) {
      return FALSE;
    }

    if (($w_display_ids !== '*') && !in_array($this->view->current_display, explode(',', $w_display_ids))) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(string $id, array $arguments): bool {
    if (!empty($arguments['operation_name']) && ($arguments['operation_name'] !== '*')) {
      if (!isset($this->operationName)) {
        return FALSE;
      }
      $is_contained = FALSE;
      foreach (explode(',', $arguments['operation_name']) as $value) {
        if ($this->operationName === trim($value)) {
          $is_contained = TRUE;
        }
      }
      if (!$is_contained) {
        return FALSE;
      }
    }

    if (!empty($arguments['view_id']) && ($arguments['view_id'] !== '*')) {
      $is_contained = FALSE;
      foreach (explode(',', $arguments['view_id']) as $value) {
        if ($this->view->id() === trim($value)) {
          $is_contained = TRUE;
        }
      }
      if (!$is_contained) {
        return FALSE;
      }
    }

    if (!empty($arguments['display_id']) && ($arguments['display_id'] !== '*')) {
      $is_contained = FALSE;
      foreach (explode(',', $arguments['display_id']) as $value) {
        if ($this->view->current_display === trim($value)) {
          $is_contained = TRUE;
        }
      }
      if (!$is_contained) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
