<?php

namespace Drupal\eca_commerce\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for eca_commerce event plugins.
 */
class EcaEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return EcaEvent::definitions();
  }

}
