<?php

namespace Drupal\eca_flag\EventSubscriber;

use Drupal\eca\EcaEvents;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\EventSubscriber\EcaBase;
use Drupal\eca_content\Event\ContentEntityDelete;
use Drupal\eca_content\Event\ContentEntityInsert;
use Drupal\eca_flag\Plugin\ECA\Event\FlagEvent;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\flag\FlaggingInterface;

/**
 * ECA event subscriber.
 */
class EcaFlag extends EcaBase {

  /**
   * Subscriber method before initial execution.
   *
   * Adds the event to the stack, and the form entity to the Token service.
   *
   * @param \Drupal\eca\Event\BeforeInitialExecutionEvent $before_event
   *   The according event.
   */
  public function onBeforeInitialExecution(BeforeInitialExecutionEvent $before_event): void {
    $event = $before_event->getEvent();
    $flagging = NULL;
    $flaggings = NULL;
    if ($event instanceof FlaggingEvent) {
      $flagging = $event->getFlagging();
    }
    elseif ($event instanceof UnflaggingEvent) {
      $flaggings = $event->getFlaggings();
    }
    elseif ($event instanceof ContentEntityInsert && $event->getEntity() instanceof FlaggingInterface) {
      $flagging = $event->getEntity();
    }
    elseif ($event instanceof ContentEntityDelete && $event->getEntity() instanceof FlaggingInterface) {
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
      $this->tokenService->addTokenData('flaggings', $flaggingTokens);
    }
    if ($flagging !== NULL) {
      $this->tokenService->addTokenData('flag', $flagging->getFlag());
      $this->tokenService->addTokenData('flagging', $flagging);
      $this->tokenService->addTokenData('entity', $flagging->getFlaggable());
      // Review the tokens when the issue #2500091 got completed in the flag
      // module.
      // @todo Also add the token for "flag-action"
      // @see https://www.drupal.org/project/flag/issues/2500091
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    foreach (FlagEvent::definitions() as $definition) {
      $events[$definition['event_name']][] = ['onEvent'];
    }
    $events[EcaEvents::BEFORE_INITIAL_EXECUTION][] = [
      'onBeforeInitialExecution',
      -100,
    ];
    return $events;
  }

}
