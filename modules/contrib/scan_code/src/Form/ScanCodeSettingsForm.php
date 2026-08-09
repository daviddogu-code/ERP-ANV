<?php

namespace Drupal\scan_code\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\scan_code\Services\ScanCodeSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure example settings for this site.
 */
class ScanCodeSettingsForm extends ConfigFormBase {

  /**
   * Scan_code settings.
   *
   * @var \Drupal\scan_code\Services\ScanCodeSettings
   */
  protected $scanCodeSettings;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scan_code_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'scan_code.settings',
    ];
  }

  /**
   * Constructs a ScanCodeSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\scan_code\Services\ScanCodeSettings $scan_code_settings
   *   The scan code settings.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ScanCodeSettings $scan_code_settings,
  ) {
    parent::__construct($config_factory);
    $this->scanCodeSettings = $scan_code_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('scan_code.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('scan_code.settings');

    $form['close_after_scanning'] = [
      '#type' => 'radios',
      '#title' => $this->t('Close after successful scanning'),
      '#description' => $this->t('After a barcode is successfully scanned,
        should the scanning interface close or remain open for additional scans'),
      '#default_value' => $config->get('close_after_scanning') ?? 'closed',
      '#options' => [
        'open' => $this->t('Leave open'),
        'closed' => $this->t('Close'),
      ],
    ];

    $form['text_barcode_reading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text for barcode reading'),
      '#default_value' => $config->get('text_barcode_reading') ?? $this->t('Scanning for barcode'),
      '#required' => TRUE,
    ];

    $form['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay in milliseconds before next item'),
      '#description' => $this->t('After a barcode is successfully scanned,
        there is a delay before the next barcode will be read, so it is not scanned twice.'),
      '#default_value' => $config->get('delay') ?? 1000,
      '#min' => 0,
      '#max' => 10000,
      '#step' => 100,
      '#size' => 5,
    ];

    $form['patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Scan code patterns list for Quagga library'),
      '#description' => $this->t('Code scan patters list. Every row contains one pattern. Attention: one name per row.
      More information here https://github.com/ericblade/quagga2/blob/master/README.md#decoder'),
      '#default_value' => $config->get('patterns') ?? $this->scanCodeSettings->getDefaultPaternString(),
      '#cols' => '40',
      '#rows' => 10,
    ];

    $form['widgets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed form widgets.'),
      '#description' => $this->t('Allowed form widgets list. Every row contains one widget`s machine name. Attention: one name per row.
      Be careful with this parameter - need to check possibility of widget`s work with this update'),
      '#default_value' => $config->get('widgets') ?? $this->scanCodeSettings->getDefaultWidgetsString(),
      '#cols' => '40',
      '#rows' => 10,
    ];

    $form['status_on_load'] = [
      '#type' => 'radios',
      '#title' => $this->t('Scanning interface open by default'),
      '#description' => $this->t('When the POS interface is loaded, should
        the barcode scanning interface start open or closed.'),
      '#default_value' => $config->get('status_on_load') ?? 'closed',
      // @todo Will be using in a future.
      '#disabled' => TRUE,
      '#options' => [
        'open' => $this->t('Open'),
        'closed' => $this->t('Closed'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('scan_code.settings')
      ->set('status_on_load', $form_state->getValue('status_on_load'))
      ->set('close_after_scanning', $form_state->getValue('close_after_scanning'))
      ->set('text_barcode_reading', $form_state->getValue('text_barcode_reading'))
      ->set('delay', $form_state->getValue('delay'))
      ->set('patterns', trim($form_state->getValue('patterns'), $this->scanCodeSettings->getSeparator()))
      ->set('widgets', trim($form_state->getValue('widgets'), $this->scanCodeSettings->getSeparator()))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
