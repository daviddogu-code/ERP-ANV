<?php

namespace Drupal\eca_vbo\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA VBO event plugins.
 */
class VboEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return VboEvent::definitions();
  }

}
