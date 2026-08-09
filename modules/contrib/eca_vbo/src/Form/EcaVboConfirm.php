<?php

namespace Drupal\eca_vbo\Form;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca_vbo\Event\EcaVboEvents;
use Drupal\eca_vbo\Event\VboConfirmFormBuildEvent;
use Drupal\eca_vbo\Event\VboConfirmFormSubmitEvent;
use Drupal\eca_vbo\Event\VboConfirmFormValidateEvent;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views_bulk_operations\Form\ConfirmAction;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Confirm form of a ECA VBO action.
 */
class EcaVboConfirm extends ConfirmAction {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\eca_vbo\Form\EcaVboConfirm $instance */
    $instance = parent::create($container);
    $instance->setEventDispatcher($container->get('event_dispatcher'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eca_vbo_confirm_action';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $view_id = NULL, $display_id = NULL) {
    $form = parent::buildForm($form, $form_state, $view_id, $display_id);
    $form_data = $this->getFormData($view_id, $display_id);
    $action = $this->getConfiguredAction($form_data);
    $config = $action instanceof ConfigurableInterface ? $action->getConfiguration() : ($form_data['configuration'] ?? []);
    $event = new VboConfirmFormBuildEvent($form, $form_state, $this->getView($form_data), $form_data, $config, $action);
    $this->eventDispatcher->dispatch($event, EcaVboEvents::CONFIRM_FORM_BUILD);
    if ($action instanceof ConfigurableInterface) {
      $action->setConfiguration($config);
      $form_data['configuration'] = $action->getConfiguration();
    }
    else {
      $form_data['configuration'] = $config;
    }
    $form_state->set('views_bulk_operations', $form_data);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_data = $form_state->get('views_bulk_operations');
    $action = $this->getConfiguredAction($form_data);
    $config = $action instanceof ConfigurableInterface ? $action->getConfiguration() : [];
    $event = new VboConfirmFormValidateEvent($form, $form_state, $this->getView($form_data), $form_data, $config, $action);
    $this->eventDispatcher->dispatch($event, EcaVboEvents::CONFIRM_FORM_VALIDATE);
    if ($action instanceof ConfigurableInterface) {
      $action->setConfiguration($config);
      $form_data['configuration'] = $action->getConfiguration();
    }
    else {
      $form_data['configuration'] = $config;
    }
    $form_state->set('views_bulk_operations', $form_data);
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_data = $form_state->get('views_bulk_operations');
    $action = $this->getConfiguredAction($form_data);
    $config = $action instanceof ConfigurableInterface ? $action->getConfiguration() : [];
    $event = new VboConfirmFormSubmitEvent($form, $form_state, $this->getView($form_data), $form_data, $config, $action);
    $this->eventDispatcher->dispatch($event, EcaVboEvents::CONFIRM_FORM_SUBMIT);
    if ($action instanceof ConfigurableInterface) {
      $action->setConfiguration($config);
      $form_data['configuration'] = $action->getConfiguration();
    }
    else {
      $form_data['configuration'] = $config;
    }
    $form_state->set('views_bulk_operations', $form_data);
    parent::submitForm($form, $form_state);
  }

  /**
   * Set the event dispatcher.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function setEventDispatcher(EventDispatcherInterface $event_dispatcher): void {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Get the configured action.
   *
   * @param mixed $form_data
   *   The form data.
   *
   * @return \Drupal\Core\Action\ActionInterface
   *   The configured action.
   */
  protected function getConfiguredAction($form_data): ActionInterface {
    $action = $this->actionManager->createInstance($form_data['action_id'], ($form_data['configuration'] ?? []));
    return $action;
  }

  /**
   * Get the view.
   *
   * @param mixed $form_data
   *   The form data.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view.
   */
  protected function getView($form_data): ViewExecutable {
    $view = Views::getView($form_data['view_id']);
    $view->setDisplay($form_data['display_id']);
    return $view;
  }

}
