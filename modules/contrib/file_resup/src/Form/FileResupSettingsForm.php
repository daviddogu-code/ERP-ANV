<?php declare(strict_types = 1);

namespace Drupal\file_resup\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure File Resumable Upload settings for this site.
 */
final class FileResupSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'file_resup_file_resup_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['file_resup.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['prevent_duplicates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prevent Duplicates'),
      '#description' => $this->t('Keep track of users uploading the same file name over and over. Point to the already existing file name instead of uploading it again and automatically renaming the file.'),
      '#default_value' => $this->config('file_resup.settings')->get('prevent_duplicates'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('file_resup.settings')
      ->set('prevent_duplicates', $form_state->getValue('prevent_duplicates'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
