<?php

namespace Drupal\symfony_mailer_log\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for symfony_mailer_log module.
 */
class SymfonyMailerLogSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'symfony_mailer_log.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'symfony_mailer_log_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::SETTINGS);

    $log_expiry_max_age_datalist_id = Html::getUniqueId('log_expiry_max_age_datalist');
    $form['log_expiry_max_age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum age of log entries'),
      '#description' => $this->t('The maximum age of a log entry after which it will be deleted. Must be a valid <a href="https://en.wikipedia.org/wiki/ISO_8601#Durations">ISO 8601 duration string</a>.<br/>
Some common values are:<br/>
1 day = P1D<br/>
1 week = P1W<br/>
1 month = P1M<br/>
1 year = P1Y<br/>
'),
      '#config_target' => static::SETTINGS . ':log_expiry.max_age',
      // @todo #default_value is not needed on Drupal 10.2 with #config_target:
      // https://www.drupal.org/node/3373502
      '#default_value' => $config->get('log_expiry.max_age'),
      '#attributes' => [
        'list' => $log_expiry_max_age_datalist_id,
        'pattern' => 'P(\d+Y)?(\d+M)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?',
      ],
    ];
    $form['log_expiry_max_age_datalist'] = [
      '#type' => 'html_tag',
      '#tag' => 'datalist',
      '#attributes' => [
        'id' => $log_expiry_max_age_datalist_id,
      ],
      'log_expiry_max_age_datalist_P1D' => [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#attributes' => [
          'value' => 'P1D',
        ],
      ],
      'log_expiry_max_age_datalist_P1W' => [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#attributes' => [
          'value' => 'P1W',
        ],
      ],
      'log_expiry_max_age_datalist_P1M' => [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#attributes' => [
          'value' => 'P1M',
        ],
      ],
      'log_expiry_max_age_datalist_P1Y' => [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#attributes' => [
          'value' => 'P1Y',
        ],
      ],
    ];
    $form['log_expiry_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of entries to delete in one cron run'),
      '#description' => $this->t('The number of expired log entries which will be deleted in a single cron run. All expired entries will be deleted at once if not given.'),
      '#config_target' => static::SETTINGS . ':log_expiry.batch_size',
      // @todo #default_value is not needed on Drupal 10.2 with #config_target:
      // https://www.drupal.org/node/3373502
      '#default_value' => $config->get('log_expiry.batch_size'),
      '#min' => 1,
      '#step' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Deprecated for D10.2+ This should not be necessary anymore with
   * {@link https://www.drupal.org/node/3373502}.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::SETTINGS)
      ->set('log_expiry.max_age', ((string) $form_state->getValue('log_expiry_max_age')) ?: NULL)
      ->set('log_expiry.batch_size', ((int) $form_state->getValue('log_expiry_batch_size')) ?: NULL)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
