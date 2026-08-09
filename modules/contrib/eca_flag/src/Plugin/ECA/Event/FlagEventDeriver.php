<?php

namespace Drupal\eca_flag\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Flag event plugins.
 */
class FlagEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return FlagEvent::definitions();
  }

}
