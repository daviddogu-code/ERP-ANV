<?php

namespace Drupal\eca_flag\Plugin\ECA\Event;

use Drupal\eca\Attribute\EcaEvent;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_flag\Event\FlaggingDelete;
use Drupal\eca_flag\Event\FlaggingEvents;
use Drupal\eca_flag\Event\FlaggingInsert;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;

/**
 * Plugin implementation of the ECA Events for flag events.
 */
#[EcaEvent(
  id: 'flag',
  deriver: 'Drupal\eca_flag\Plugin\ECA\Event\FlagEventDeriver',
  version_introduced: '1.0.0',
)]
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
      'insert' => [
        'label' => 'Insert flagging',
        'event_name' => FlaggingEvents::INSERT,
        'event_class' => FlaggingInsert::class,
      ],
      'delete' => [
        'label' => 'Delete flagging',
        'event_name' => FlaggingEvents::DELETE,
        'event_class' => FlaggingDelete::class,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'flagging',
    description: 'The flagging entity.',
  )]
  #[Token(
    name: 'flag',
    description: 'The flag entity.',
  )]
  #[Token(
    name: 'entity',
    description: 'The flagged entity',
  )]
  public function getData(string $key): mixed {
    $event = $this->getEvent();
    $flagging = NULL;
    $flaggings = NULL;
    if ($event instanceof FlaggingEvent) {
      $flagging = $event->getFlagging();
    }
    elseif ($event instanceof UnflaggingEvent) {
      $flaggings = $event->getFlaggings();
      $flagging = reset($flaggings);
    }
    if ($flagging) {
      switch ($key) {
        case 'flagging':
          return $flagging;

        case 'flag':
          return $flagging->getFlag();

        case 'entity':
          return $flagging->getFlaggable();
      }
    }
    if ($key == 'flaggings' && $flaggings) {
      return $flaggings;
    }

    return parent::getData($key);
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    properties: [
      new Token(
        name: 'flag',
        description: 'The flag entity.',
        classes: [
          FlaggingEvent::class,
          FlaggingInsert::class,
        ],
      ),
      new Token(
        name: 'flagging',
        description: 'The flagging entity.',
        classes: [
          FlaggingEvent::class,
          FlaggingInsert::class,
        ],
      ),
      new Token(
        name: 'entity',
        description: 'The flagged entity.',
        classes: [
          FlaggingEvent::class,
          FlaggingInsert::class,
        ],
      ),
      new Token(
        name: 'flag',
        description: 'The flag entity (if there is only 1 item).',
        classes: [
          UnflaggingEvent::class,
          FlaggingDelete::class,
        ],
      ),
      new Token(
        name: 'flagging',
        description: 'The flagging entity (if there is only 1 item).',
        classes: [
          UnflaggingEvent::class,
          FlaggingDelete::class,
        ],
      ),
      new Token(
        name: 'entity',
        description: 'The flagged entity (if there is only 1 item).',
        classes: [
          UnflaggingEvent::class,
          FlaggingDelete::class,
        ],
      ),
      new Token(
        name: 'flaggings',
        description: 'The flaggings.',
        classes: [
          UnflaggingEvent::class,
          FlaggingDelete::class,
        ],
        properties: [
          new Token(
            name: '#',
            description: 'The index in the list beginning with 0.',
            properties: [
              new Token(
                name: 'flag',
                description: 'The flag entity.',
              ),
              new Token(
                name: 'flagging',
                description: 'The flagging entity.',
              ),
              new Token(
                name: 'entity',
                description: 'The flagged entity.',
              ),
            ],
          ),
        ],
      ),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];
    $flagging = NULL;
    $flaggings = NULL;
    if ($event instanceof FlaggingEvent) {
      $flagging = $event->getFlagging();
    }
    elseif ($event instanceof UnflaggingEvent) {
      $flaggings = $event->getFlaggings();
    }
    elseif ($event instanceof FlaggingInsert) {
      $flagging = $event->getEntity();
    }
    elseif ($event instanceof FlaggingDelete) {
      $flaggings = [$event->getEntity()];
    }
    if ($flaggings !== NULL) {
      if (count($flaggings) === 1) {
        $flagging = reset($flaggings);
      }
      $flaggingTokens = [];
      foreach ($flaggings as $item) {
        $flaggingTokens[] = [
          'flag' => $item->getFlag(),
          'flagging' => $item,
          'entity' => $item->getFlaggable(),
        ];
      }
      $data['flaggings'] = $flaggingTokens;
    }
    if ($flagging !== NULL) {
      $data += [
        'flag' => $flagging->getFlag(),
        'flagging' => $flagging,
        'entity' => $flagging->getFlaggable(),
      ];
      // Review the tokens when the issue #2500091 got completed in the flag
      // module.
      // @todo Also add the token for "flag-action"
      // @see https://www.drupal.org/project/flag/issues/2500091
    }

    $data += parent::buildEventData();
    return $data;
  }

}
