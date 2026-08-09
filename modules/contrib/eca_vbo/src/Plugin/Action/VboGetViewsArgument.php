<?php

namespace Drupal\eca_vbo\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_vbo\Event\VboExecutionEventBase;
use Drupal\eca_vbo\Event\VboFormEventBase;

/**
 * Get an argument passed to the according view of the bulk operation.
 *
 * Type annotation is set to "system" so that it does not appear within VBO.
 *
 * @Action(
 *   id = "eca_vbo_get_views_argument",
 *   label = @Translation("VBO: Get Views argument"),
 *   description = @Translation("Get an argument passed to the according view of the bulk operation and store it as a token."),
 *   type = "system"
 * )
 */
class VboGetViewsArgument extends ConfigurableActionBase {

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
      'index' => '',
      'token_name' => '',
      'default_value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['index'] = [
      '#type' => 'number',
      '#title' => $this->t('Argument index key'),
      '#description' => $this->t('Index counting starts at 0. Leave empty to get all available arguments.'),
      '#required' => FALSE,
      '#default_value' => $this->configuration['index'],
      '#weight' => 0,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The argument value will be loaded into this specified token.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['token_name'],
      '#weight' => 10,
    ];
    $form['default_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default value'),
      '#description' => $this->t('Optionally specify a default value when no value is given for the specified index. Supports tokens.'),
      '#default_value' => $this->configuration['default_value'],
      '#weight' => 30,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['index'] = $form_state->getValue('index');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['default_value'] = $form_state->getValue('default_value');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = ($this->event instanceof VboExecutionEventBase || $this->event instanceof VboFormEventBase) ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $event = $this->event;
    if (!($event instanceof VboExecutionEventBase || $event instanceof VboFormEventBase)) {
      return;
    }

    $token = $this->tokenServices;
    $index = $this->configuration['index'] !== '' ? trim((string) $token->replaceClear($this->configuration['index'])) : '';
    if (ctype_digit(strval($index))) {
      $index = (int) $index;
    }

    if ($index !== '') {
      $args = $this->event->actionContext['arguments'] ?? [];
      $arg_value = $args[$index] ?? NULL;
    }
    else {
      $arg_value = $this->event->actionContext['arguments'] ?? [];
    }

    if ((($arg_value === NULL) || ((is_string($arg_value) || (is_object($arg_value) && method_exists($arg_value, '__toString'))) && trim((string) $arg_value) === '')) && ($this->configuration['default_value'] !== '')) {
      $arg_value = $token->getOrReplace((string) $this->configuration['default_value']);
    }

    $token_name = $this->configuration['token_name'];
    if ($token_name !== '') {
      $token->addTokenData($token_name, $arg_value);
    }
  }

}
