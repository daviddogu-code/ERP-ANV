<?php

namespace Drupal\eca_flag\Event;

use Drupal\flag\FlaggingInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for flagging entity related events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class FlaggingBase extends Event {

  /**
   * The entity.
   *
   * @var \Drupal\flag\FlaggingInterface
   */
  protected FlaggingInterface $entity;

  /**
   * FlaggingBase constructor.
   *
   * @param \Drupal\flag\FlaggingInterface $entity
   *   The entity.
   */
  public function __construct(FlaggingInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): FlaggingInterface {
    return $this->entity;
  }

}
