<?php

namespace Drupal\eca_vbo\Event;

use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\FormEventInterface;
use Drupal\views\ViewExecutable;

/**
 * Base class for form events that belong to a VBO form.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class VboFormEventBase extends VboEventBase implements FormEventInterface {

  /**
   * The form array.
   *
   * This may be the complete form, or a sub-form, or a specific form element.
   *
   * @var array
   */
  protected array $form;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected FormStateInterface $formState;

  /**
   * Contains view data and optionally batch operation context.
   *
   * @var array
   */
  public array $actionContext;

  /**
   * The action plugin configuration.
   *
   * @var array
   */
  public array $actionConfiguration;

  /**
   * The action plugin instance.
   *
   * @var mixed
   */
  protected ActionInterface $action;

  /**
   * Constructs a new VboFormEventBase object.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\views\ViewExecutable $view
   *   The executable view.
   * @param array &$action_context
   *   The context array passed from VBO to the action.
   * @param array &$action_configuration
   *   The action configuration.
   * @param \Drupal\Core\Action\ActionInterface $action
   *   The action plugin instance.
   */
  public function __construct(array &$form, FormStateInterface $form_state, ViewExecutable $view, array &$action_context, array &$action_configuration, ActionInterface $action) {
    $this->form = &$form;
    $this->formState = $form_state;
    $this->view = $view;
    $this->actionContext = &$action_context;
    if (!isset($action_configuration['operation_name'])) {
      $action_configuration['operation_name'] = $action->getPluginDefinition()['operation_name'] ?? '';
    }
    $this->actionConfiguration = &$action_configuration;
    $this->operationName = &$action_configuration['operation_name'];
    $this->action = $action;
  }

  /**
   * {@inheritdoc}
   */
  public function &getForm(): array {
    return $this->form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormState(): FormStateInterface {
    return $this->formState;
  }

  /**
   * Get the exeuctable view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executable view.
   */
  public function getView(): ViewExecutable {
    return $this->view;
  }

  /**
   * Get the action plugin instance.
   *
   * @return \Drupal\Core\Action\ActionInterface
   *   The action plugin instance.
   */
  public function getAction(): ActionInterface {
    return $this->action;
  }

}
