<?php

namespace Drupal\eca_vbo\Event;

use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\views\ViewExecutable;

/**
 * Dispatches when executing a "eca_vbo_execute" action on multiple entities.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class VboExecuteMultipleEvent extends VboExecutionEventBase {

  /**
   * The queued entities to process.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $queue;

  /**
   * Constructs a new VboExecuteMultipleEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $queue
   *   The queued entities to process.
   * @param \Drupal\views\ViewExecutable $view
   *   The executable view.
   * @param array &$action_context
   *   The context array passed from VBO to the action.
   * @param array &$action_configuration
   *   The action configuration.
   * @param \Drupal\Core\Action\ActionInterface $action
   *   The action plugin instance.
   * @param mixed $result
   *   (optional) The output of the processed result.
   */
  public function __construct(array $queue, ViewExecutable $view, array &$action_context, array &$action_configuration, ActionInterface $action, $result = NULL) {
    $this->queue = $queue;
    parent::__construct($view, $action_context, $action_configuration, $action, $result);
  }

  /**
   * Get the queued entities to process.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The queued entities to process.
   */
  public function getQueue(): array {
    return $this->queue;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEventDto(): DataTransferObject {
    $action_config = $this->actionConfiguration;
    array_walk_recursive($action_config, function (&$value) {
      if (is_object($value) && method_exists($value, '__toString')) {
        $value = (string) $value;
      }
      elseif (!is_scalar($value) && !($value instanceof EntityInterface)) {
        $value = '(object)';
      }
    });
    $num_items = count($this->queue);
    $ids = [];
    $vids = [];
    foreach ($this->queue as $entity) {
      if (($entity instanceof RevisionableInterface) && ($entity->getLoadedRevisionId() !== NULL)) {
        $vids[] = $entity->getLoadedRevisionId();
      }
      $ids[] = $entity->id();
    }
    $values = [
      'machine-name' => EcaVboEvents::EXECUTE_MULTIPLE,
      'view' => [
        'id' => $this->view->id(),
        'display-id' => $this->view->current_display,
      ],
      'action' => [
        'plugin' => $this->action->getPluginId(),
        'config' => $action_config,
      ],
      'queue' => [
        'count' => $num_items,
        'ids' => $ids,
        'revisions' => $vids,
        'items' => $this->queue,
      ],
    ];
    return DataTransferObject::create($values);
  }

}
