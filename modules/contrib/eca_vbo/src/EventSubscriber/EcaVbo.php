<?php

namespace Drupal\eca_vbo\EventSubscriber;

use Drupal\eca\EventSubscriber\EcaBase;
use Drupal\eca_vbo\Plugin\ECA\Event\VboEvent;

/**
 * ECA event subscriber regarding VBO events.
 */
class EcaVbo extends EcaBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    foreach (VboEvent::definitions() as $definition) {
      $events[$definition['event_name']][] = ['onEvent'];
    }
    return $events;
  }

}
