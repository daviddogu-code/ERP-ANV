<?php

namespace Drupal\eca_vbo\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_vbo\Event\VboExecutionEventBase;

/**
 * Set the result output of a performed views bulk operation.
 *
 * Type annotation is set to "system" so that it does not appear within VBO.
 *
 * @Action(
 *   id = "eca_vbo_set_result",
 *   label = @Translation("VBO: Set result"),
 *   description = @Translation("Set the result output of an executed views bulk operation. This action only works upon the event <em>VBO: Execute Views bulk operation</em>."),
 *   type = "system"
 * )
 */
class VboSetResult extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return ['module' => ['eca', 'views_bulk_operations']];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'result' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['result'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Result'),
      '#description' => $this->t('This field supports tokens.'),
      '#default_value' => $this->configuration['result'],
      '#weight' => 10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['result'] = $form_state->getValue('result');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = ($this->event instanceof VboExecutionEventBase) ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $event = $this->event;
    if (!($event instanceof VboExecutionEventBase)) {
      return;
    }
    $event->result = (string) $this->tokenServices->replaceClear($this->configuration['result']);
  }

}
