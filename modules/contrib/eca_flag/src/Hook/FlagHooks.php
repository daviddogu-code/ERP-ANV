<?php

namespace Drupal\eca_flag\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\Event\TriggerEvent;
use Drupal\flag\FlaggingInterface;

/**
 * Implements flag hooks for the eca_flag module.
 */
class FlagHooks {

  /**
   * Constructs a new BaseHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('flagging_insert')]
  public function flaggingInsert(FlaggingInterface $entity): void {
    $this->triggerEvent->dispatchFromPlugin('flag:insert', $entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('flagging_delete')]
  public function flaggingDelete(FlaggingInterface $entity): void {
    $this->triggerEvent->dispatchFromPlugin('flag:delete', $entity);
  }

}
