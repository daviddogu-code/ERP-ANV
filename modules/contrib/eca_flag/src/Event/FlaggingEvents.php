<?php

namespace Drupal\eca_flag\Event;

/**
 * Flagging events.
 */
final class FlaggingEvents {

  /**
   * Identifies \Drupal\eca_flag\Event\FlaggingInsert event.
   *
   * @Event
   *
   * @var string
   */
  public const INSERT = 'eca_flag.insert';

  /**
   * Identifies \Drupal\eca_flag\Event\FlaggingDelete event.
   *
   * @Event
   *
   * @var string
   */
  public const DELETE = 'eca_flag.delete';

}
