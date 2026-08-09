<?php

namespace Drupal\eca_vbo\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_vbo\Event\VboCustomAccessEvent;

/**
 * Set custom access to a bulk operation.
 *
 * Type annotation is set to "system" so that it does not appear within VBO.
 *
 * @Action(
 *   id = "eca_vbo_set_custom_access",
 *   label = @Translation("VBO: Set custom access on Views Bulk Operation"),
 *   description = @Translation("This action only works upon the event <em>VBO: Custom access for Views bulk operation</em>."),
 *   type = "system"
 * )
 */
class VboSetCustomAccess extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = ($this->event instanceof VboCustomAccessEvent) && is_bool($this->configuration['access_granted']) ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if (!($this->event instanceof VboCustomAccessEvent)) {
      return;
    }
    $this->event->accessGranted = $this->configuration['access_granted'];
  }

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
    return ['access_granted' => TRUE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['access_granted'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Access granted'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['access_granted'],
      '#weight' => 0,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['access_granted'] = !empty($form_state->getValue('access_granted'));
    parent::submitConfigurationForm($form, $form_state);
  }

}
