<?php

namespace Drupal\eca_vbo\Plugin\ECA\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_vbo\Event\EcaVboEvents;
use Drupal\eca_vbo\Event\VboConfirmFormBuildEvent;
use Drupal\eca_vbo\Event\VboConfirmFormSubmitEvent;
use Drupal\eca_vbo\Event\VboConfirmFormValidateEvent;
use Drupal\eca_vbo\Event\VboCustomAccessEvent;
use Drupal\eca_vbo\Event\VboExecuteEvent;
use Drupal\eca_vbo\Event\VboExecuteMultipleEvent;
use Drupal\eca_vbo\Event\VboExecutionEventBase;
use Drupal\eca_vbo\Event\VboFormBuildEvent;
use Drupal\eca_vbo\Event\VboFormSubmitEvent;
use Drupal\eca_vbo\Event\VboFormValidateEvent;

/**
 * Plugin implementation of the ECA Events regarding VBO.
 *
 * @EcaEvent(
 *   id = "vbo",
 *   deriver = "Drupal\eca_vbo\Plugin\ECA\Event\VboEventDeriver"
 * )
 */
class VboEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $actions = [];
    $actions['execute'] = [
      'label' => 'VBO: Execute Views bulk operation (one by one)',
      'event_name' => EcaVboEvents::EXECUTE,
      'event_class' => VboExecuteEvent::class,
      'tags' => Tag::CONTENT,
    ];
    $actions['execute_multiple'] = [
      'label' => 'VBO: Execute Views bulk operation (multiple at once)',
      'event_name' => EcaVboEvents::EXECUTE_MULTIPLE,
      'event_class' => VboExecuteMultipleEvent::class,
      'tags' => Tag::CONTENT,
    ];
    $actions['custom_access'] = [
      'label' => 'VBO: Custom access for Views bulk operation',
      'event_name' => EcaVboEvents::CUSTOM_ACCESS,
      'event_class' => VboCustomAccessEvent::class,
      'tags' => Tag::BEFORE,
    ];
    $actions['form_build'] = [
      'label' => 'VBO: Form build of Views bulk operation',
      'event_name' => EcaVboEvents::FORM_BUILD,
      'event_class' => VboFormBuildEvent::class,
      'tags' => Tag::VIEW | Tag::RUNTIME | Tag::BEFORE,
    ];
    $actions['form_validate'] = [
      'label' => 'VBO: Form validate of Views bulk operation',
      'event_name' => EcaVboEvents::FORM_VALIDATE,
      'event_class' => VboFormValidateEvent::class,
      'tags' => Tag::READ | Tag::RUNTIME | Tag::AFTER,
    ];
    $actions['form_submit'] = [
      'label' => 'VBO: Form submit of Views bulk operation',
      'event_name' => EcaVboEvents::FORM_SUBMIT,
      'event_class' => VboFormSubmitEvent::class,
      'tags' => Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
    ];
    $actions['confirm_form_build'] = [
      'label' => 'VBO: Confirm form build of Views bulk operation',
      'event_name' => EcaVboEvents::CONFIRM_FORM_BUILD,
      'event_class' => VboConfirmFormBuildEvent::class,
      'tags' => Tag::VIEW | Tag::RUNTIME | Tag::BEFORE,
    ];
    $actions['confirm_form_validate'] = [
      'label' => 'VBO: Confirm form validate of Views bulk operation',
      'event_name' => EcaVboEvents::CONFIRM_FORM_VALIDATE,
      'event_class' => VboConfirmFormValidateEvent::class,
      'tags' => Tag::READ | Tag::RUNTIME | Tag::AFTER,
    ];
    $actions['confirm_form_submit'] = [
      'label' => 'VBO: Confirm form submit of Views bulk operation',
      'event_name' => EcaVboEvents::CONFIRM_FORM_SUBMIT,
      'event_class' => VboConfirmFormSubmitEvent::class,
      'tags' => Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'operation_name' => '',
      'view_id' => '',
      'display_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['operation_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Operation name'),
      '#default_value' => $this->configuration['operation_name'],
      '#required' => TRUE,
      '#weight' => 10,
    ];
    if (is_a($this->eventClass(), VboExecutionEventBase::class, TRUE)) {
      $form['operation_name']['#description'] = $this->t('The operation name identifies this process and will show up in the bulk operations configuration form as selectable action. If you need custom access handling using the operation name, then make sure that the Views configuration is using the <em>ECA bulk operations</em> Views field plugin.');
      if (is_a($this->eventClass(), VboExecuteEvent::class, TRUE)) {
        $form['operation_name']['#description'] .= '<br/>' . $this->t('By using one-by-one execution (for each single entity), you have following tokens available:<ul><li><em>[event:view]</em> containing info about the used view</li><li><em>[event:action]</em> containing info about the executed action</li><li><em>[event:entity]</em> containing info about the entity in scope</li></ul>');
      }
      elseif (is_a($this->eventClass(), VboExecuteMultipleEvent::class, TRUE)) {
        $form['operation_name']['#description'] .= '<br/>' . $this->t('By using multiple execution (selected entities at once), you have following tokens available:<ul><li><em>[event:view]</em> containing info about the used view</li><li><em>[event:action]</em> containing info about the executed action</li><li><em>[event:queue]</em> containing info about the queued entities selected for processing</li></ul>');
      }
    }
    elseif ($this->eventClass() === VboCustomAccessEvent::class) {
      $form['operation_name']['#description'] = $this->t('Important note: The operation name is only available, when the operation got executed from the <em>ECA bulk operations</em> Views field plugin.');
    }

    $form['view_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Optionally restrict by view ID'),
      '#default_value' => $this->configuration['view_id'],
      '#required' => FALSE,
      '#weight' => 20,
    ];

    $form['display_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Optionally restrict by view display ID'),
      '#default_value' => $this->configuration['display_id'],
      '#required' => FALSE,
      '#weight' => 30,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['operation_name'] = $form_state->getValue('operation_name');
    $this->configuration['view_id'] = $form_state->getValue('view_id');
    $this->configuration['display_id'] = $form_state->getValue('display_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function lazyLoadingWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $configuration = $ecaEvent->getConfiguration();

    $wildcard = '';
    $operation_names = [];
    if (!empty($configuration['operation_name'])) {
      foreach (explode(',', $configuration['operation_name']) as $operation_name) {
        $operation_name = trim($operation_name);
        if ($operation_name !== '') {
          $operation_names[] = $operation_name;
        }
      }
    }
    if ($operation_names) {
      $wildcard .= implode(',', $operation_names);
    }
    else {
      $wildcard .= '*';
    }

    $wildcard .= '::';
    $view_ids = [];
    if (!empty($configuration['view_id'])) {
      foreach (explode(',', $configuration['view_id']) as $view_id) {
        $view_id = trim($view_id);
        if ($view_id !== '') {
          $view_ids[] = $view_id;
        }
      }
    }
    if ($view_ids) {
      $wildcard .= implode(',', $view_ids);
    }
    else {
      $wildcard .= '*';
    }

    $wildcard .= '::';
    $display_ids = [];
    if (!empty($configuration['display_id'])) {
      foreach (explode(',', $configuration['display_id']) as $display_id) {
        $display_id = trim($display_id);
        if ($display_id !== '') {
          $display_ids[] = $display_id;
        }
      }
    }
    if ($display_ids) {
      $wildcard .= implode(',', $display_ids);
    }
    else {
      $wildcard .= '*';
    }

    return $wildcard;
  }

}
