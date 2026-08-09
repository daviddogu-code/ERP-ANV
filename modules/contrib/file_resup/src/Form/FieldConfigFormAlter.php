<?php

namespace Drupal\file_resup\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
class FieldConfigFormAlter implements ContainerInjectionInterface {

  use StringTranslationTrait;

  private EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  public function formAlter(&$form, FormStateInterface $form_state) {
    $field_config = $form_state->getFormObject()->getEntity();
    $settings = $field_config->getFieldStorageDefinition()->getSettings();
    $third_party_settings = $field_config->getThirdPartySettings('file_resup') ?? [];
    // Is this a file field?
    if (!empty($settings['target_type']) && $settings['target_type'] === 'file') {
      $form['resumable_upload'] = [
        '#type' => 'details',
        '#title' => $this->t('Resumable Upload Settings'),
        '#open' => $third_party_settings['enabled'] ?? FALSE,
        '#tree' => TRUE,
        'settings' => [
          'enabled' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable resumable upload'),
            '#default_value' => $third_party_settings['enabled'] ?? FALSE,
          ],
          'max_upload_size' => [
            '#type' => 'textfield',
            '#title' => $this->t('Maximum upload size'),
            '#default_value' => $third_party_settings['max_upload_size'] ?? NULL,
          ],
          'auto_upload' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Start upload on files added'),
            '#description' => $this->t('When checked, upload will start as soon as files are added without requiring to click Upload, unless some of the added files did not pass validation.'),
            '#default_value' => $third_party_settings['auto_upload'] ?? NULL,
          ],
        ],
      ];
      $form['actions']['submit']['#submit'][] = [$this, 'submit'];
    }
  }

  public function submit(&$form, FormStateInterface $form_state) {
    $field_config = $form_state->getFormObject()->getEntity();
    $settings = $form_state->getValue('resumable_upload')['settings'];
    $field_config->setThirdPartySetting('file_resup', 'enabled', $settings['enabled']);
    $field_config->setThirdPartySetting('file_resup', 'max_upload_size', $settings['max_upload_size']);
    $field_config->setThirdPartySetting('file_resup', 'auto_upload', $settings['auto_upload']);
    $field_config->save();
  }

}
