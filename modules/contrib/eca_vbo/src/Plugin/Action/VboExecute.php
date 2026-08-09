<?php

namespace Drupal\eca_vbo\Plugin\Action;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca_vbo\Event\EcaVboEvents;
use Drupal\eca_vbo\Event\VboCustomAccessEvent;
use Drupal\eca_vbo\Event\VboExecuteEvent;
use Drupal\eca_vbo\Event\VboExecuteMultipleEvent;
use Drupal\eca_vbo\Event\VboFormBuildEvent;
use Drupal\eca_vbo\Event\VboFormSubmitEvent;
use Drupal\eca_vbo\Event\VboFormValidateEvent;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Triggers a VBO event for ECA using a defined operation name.
 *
 * The annotation sets confirm_form_route_name to the default VBO route, so that
 * this action won't show up within the ECA UI.
 *
 * @Action(
 *   id = "eca_vbo_execute",
 *   label = @Translation("VBO: Execute Views bulk operation"),
 *   description = @Translation("Dispatches an event with the selected entity from a Views row, allowing components such as ECA to react upon it."),
 *   type = "",
 *   confirm = TRUE,
 *   confirm_form_route_name = "eca_vbo.confirm",
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 *   deriver = "Drupal\eca_vbo\Plugin\Action\Derivative\VboExecuteDeriver",
 * )
 */
class VboExecute extends ViewsBulkOperationsActionBase implements DependentPluginInterface, ContainerFactoryPluginInterface, PluginFormInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'));
  }

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventDispatcher = $event_dispatcher;
    if (!isset($configuration['operation_name']) && isset($plugin_definition['operation_name'])) {
      $configuration['operation_name'] = &$plugin_definition['operation_name'];
    }
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = (($object instanceof EntityInterface) && (trim($this->configuration['operation_name']) !== '')) ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects) {
    $results = [];

    $event = new VboExecuteMultipleEvent($objects, $this->view, $this->context, $this->configuration, $this);
    $this->eventDispatcher->dispatch($event, EcaVboEvents::EXECUTE_MULTIPLE);
    $result = $event->result;
    if (!(($result === NULL) || ($result === ''))) {
      if (is_scalar($result)) {
        $result = Markup::create($result);
      }
      $results[] = $result;
    }

    foreach ($event->getQueue() as $entity) {
      $result = $this->execute($entity);
      if (($result === NULL) || ($result === '')) {
        continue;
      }
      if (is_scalar($result)) {
        $result = Markup::create($result);
      }
      $results[] = $result;
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!($entity instanceof EntityInterface)) {
      return NULL;
    }
    $event = new VboExecuteEvent($entity, $this->view, $this->context, $this->configuration, $this);
    $this->eventDispatcher->dispatch($event, EcaVboEvents::EXECUTE);
    return $event->result;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = ['module' => ['eca', 'views_bulk_operations']];
    $definition = $this->getPluginDefinition();
    if (!empty($definition['eca_config'])) {
      foreach ($definition['eca_config'] as $eca_id) {
        $dependencies['config'][] = 'eca.eca.' . $eca_id;
      }
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account, ViewExecutable $view, ?array $definition = NULL, ?array $selected_data = NULL): bool {
    $event = new VboCustomAccessEvent($account, $view, FALSE, $definition, $selected_data);
    \Drupal::service('event_dispatcher')->dispatch($event, EcaVboEvents::CUSTOM_ACCESS);
    return $event->accessGranted;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->eventDispatcher->dispatch(new VboFormBuildEvent($form, $form_state, $this->getView(), $this->context, $this->configuration, $this), EcaVboEvents::FORM_BUILD);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (empty($this->context)) {
      $this->context = $form_state->get('views_bulk_operations');
    }
    $this->eventDispatcher->dispatch(new VboFormValidateEvent($form, $form_state, $this->getView(), $this->context, $this->configuration, $this), EcaVboEvents::FORM_VALIDATE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (empty($this->context)) {
      $this->context = $form_state->get('views_bulk_operations');
    }
    $this->eventDispatcher->dispatch(new VboFormSubmitEvent($form, $form_state, $this->getView(), $this->context, $this->configuration, $this), EcaVboEvents::FORM_SUBMIT);
    $this->configuration += $form_state->getValues();
  }

  /**
   * Get the executable view.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The executable view, or NULL if not available.
   */
  protected function getView(): ?ViewExecutable {
    if (!isset($this->view)) {
      if ($view = Views::getView($this->context['view_id'])) {
        $view->setDisplay($this->context['display_id']);
        $this->view = $view;
      }
    }
    return $this->view;
  }

}
