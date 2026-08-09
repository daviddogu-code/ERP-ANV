<?php

namespace Drupal\eca_flag\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;

/**
 * Plugin implementation of the ECA Events for flag events.
 *
 * @EcaEvent(
 *   id = "flag",
 *   deriver = "Drupal\eca_flag\Plugin\ECA\Event\FlagEventDeriver"
 * )
 */
class FlagEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'flag' => [
        'label' => 'Flag',
        'event_name' => FlagEvents::ENTITY_FLAGGED,
        'event_class' => FlaggingEvent::class,
      ],
      'unflag' => [
        'label' => 'Unflag',
        'event_name' => FlagEvents::ENTITY_UNFLAGGED,
        'event_class' => UnflaggingEvent::class,
      ],
    ];
  }

}
