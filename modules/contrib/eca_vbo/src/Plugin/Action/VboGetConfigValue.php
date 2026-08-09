<?php

namespace Drupal\eca_vbo\Plugin\Action;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca_vbo\Event\VboExecutionEventBase;
use Drupal\eca_vbo\Event\VboFormEventBase;

/**
 * Get a configuration value from the action of the bulk operation.
 *
 * Type annotation is set to "system" so that it does not appear within VBO.
 *
 * @Action(
 *   id = "eca_vbo_get_config_value",
 *   label = @Translation("VBO: Get configuration value"),
 *   description = @Translation("Get a configuration value from the action of the bulk operation and store it as a token."),
 *   type = "system"
 * )
 */
class VboGetConfigValue extends ConfigurableActionBase {

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
      'config_key' => '',
      'token_name' => '',
      'replace_tokens' => FALSE,
      'default_value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['config_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Config key'),
      '#description' => $this->t('The config key, for example <em>message_text</em>. This may also be the machine name of a custom form field. Leave empty to use the whole config. <strong>Please note:</strong> This action only works upon the event <em>VBO: Execute Views bulk operation</em>.'),
      '#required' => FALSE,
      '#default_value' => $this->configuration['config_key'],
      '#weight' => 0,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The targeted configuration value will be loaded into this specified token.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['token_name'],
      '#weight' => 10,
    ];
    $form['replace_tokens'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace tokens'),
      '#description' => $this->t('When checked, existing Tokens within the user-provided configuration input will be replaced.'),
      '#default_value' => $this->configuration['replace_tokens'],
      '#weight' => 20,
    ];
    $form['default_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default value'),
      '#description' => $this->t('Optionally specify a default value when no value is given for the specified config key. Supports tokens.'),
      '#default_value' => $this->configuration['default_value'],
      '#weight' => 30,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['config_key'] = $form_state->getValue('config_key');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['replace_tokens'] = !empty($form_state->getValue('replace_tokens'));
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
    $config_key = $this->configuration['config_key'] !== '' ? trim((string) $token->replace($this->configuration['config_key'])) : '';
    if ((mb_substr($config_key, 0, 1) === '[') && (mb_substr($config_key, -1, 1) === ']')) {
      $config_key = mb_substr($config_key, 1, -1);
    }
    if (mb_strpos($config_key, '.')) {
      // User input may use "." instead of ":".
      $config_key = str_replace('.', ':', $config_key);
    }
    if ($config_key !== '') {
      $config_key = explode(':', $config_key);
      if (isset($event->actionConfiguration['_entities_to_load']) && NestedArray::keyExists($event->actionConfiguration, array_merge(['_entities_to_load'], $config_key))) {
        // Directly load entities from storage. Each array item consists of
        // an array with the following sequence of values:
        // - Entity type ID
        // - Entity ID
        // - Language ID
        // - Revision ID, or NULL if the entity has no revision.
        $config_value = [];
        foreach (NestedArray::getValue($event->actionConfiguration, array_merge(['_entities_to_load'], $config_key)) as $item) {
          [$entity_type_id, $entity_id, $langcode, $revision_id] = $item;
          if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
            continue;
          }
          $storage = $this->entityTypeManager->getStorage($entity_type_id);
          if (!($entity = $storage->load($entity_id))) {
            continue;
          }
          if (($entity->language()->getId() !== $langcode) && $entity instanceof TranslatableInterface) {
            if (!$entity->hasTranslation($langcode)) {
              continue;
            }
            $entity = $entity->getTranslation($langcode);
          }
          if (isset($revision_id) && $entity instanceof RevisionableInterface && ($revision_id !== $entity->getLoadedRevisionId())) {
            if (!($entity = $storage->loadRevision($revision_id))) {
              continue;
            }
          }
          $config_value[] = $entity;
        }
      }
      else {
        $config_value = NestedArray::getValue($event->actionConfiguration, $config_key);
      }
    }
    else {
      $config_value = $event->actionConfiguration;
    }
    if ($config_value !== NULL) {
      $replace_tokens = !empty($this->configuration['replace_tokens']);
      if (is_array($config_value) || ($config_value instanceof \ArrayAccess)) {
        array_walk_recursive($config_value, function (&$v) use ($replace_tokens) {
          if (is_object($v) && !(($v instanceof EntityInterface) || ($v instanceof DataTransferObject))) {
            $v = method_exists($v, '__toString') ? (string) $v : NULL;
          }
          if (is_string($v) && $replace_tokens) {
            $v = $this->tokenServices->getOrReplace($v);
          }
        });
        $copied = [];
        foreach ($config_value as $k => $v) {
          $copied[$k] = $v;
        }
        $config_value = $copied;
      }
      elseif (is_string($config_value) || (is_object($config_value) && method_exists($config_value, '__toString'))) {
        $config_value = $replace_tokens ? $token->getOrReplace((string) $config_value) : (string) $config_value;
      }
      else {
        $config_value = NULL;
      }
    }
    if ((($config_value === NULL) || ((is_string($config_value) || (is_object($config_value) && method_exists($config_value, '__toString'))) && trim((string) $config_value) === '')) && ($this->configuration['default_value'] !== '')) {
      $config_value = $token->getOrReplace((string) $this->configuration['default_value']);
    }

    $token_name = $this->configuration['token_name'];
    if ($token_name !== '') {
      $token->addTokenData($token_name, $config_value);
    }
  }

}
