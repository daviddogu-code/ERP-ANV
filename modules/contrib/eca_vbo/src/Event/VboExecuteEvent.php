<?php

namespace Drupal\eca_vbo\Event;

use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Event\EntityEventInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\views\ViewExecutable;

/**
 * Dispatches when executing a "eca_vbo_execute" action on each single entity.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class VboExecuteEvent extends VboExecutionEventBase implements EntityEventInterface {

  /**
   * The main entity of the Views row.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * Constructs a new VboExecuteEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The main entity of the Views row.
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
  public function __construct(EntityInterface $entity, ViewExecutable $view, array &$action_context, array &$action_configuration, ActionInterface $action, $result = NULL) {
    $this->entity = $entity;
    parent::__construct($view, $action_context, $action_configuration, $action, $result);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
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
    $values = [
      'machine-name' => EcaVboEvents::EXECUTE,
      'view' => [
        'id' => $this->view->id(),
        'display-id' => $this->view->current_display,
      ],
      'action' => [
        'plugin' => $this->action->getPluginId(),
        'config' => $action_config,
      ],
      'entity' => [
        'id' => $this->entity->id(),
        'label' => $this->entity->label(),
        'type' => $this->entity->getEntityTypeId(),
        'bundle' => $this->entity->bundle(),
        'langcode' => $this->entity->language()->getId(),
      ],
    ];
    return DataTransferObject::create($values);
  }

}
