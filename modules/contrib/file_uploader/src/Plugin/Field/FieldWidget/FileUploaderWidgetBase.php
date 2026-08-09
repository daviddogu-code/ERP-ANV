<?php

namespace Drupal\file_uploader\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\file_uploader\Element\FileUploader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides file uploader widget base.
 */
class FileUploaderWidgetBase extends FileWidget implements TrustedCallbackInterface {

  /**
   * The documentation URL.
   */
  const DOCUMENTATION_URL = NULL;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * The available upload methods.
   *
   * @return array
   *   Returns an array of allowed upload methods.
   */
  public static function uploadMethods(): array {
    return [
      'xhr' => t('Drupal'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'options' => [
        'instance' => [],
        'plugin' => [],
        'uploader' => [
          'method' => 'xhr',
        ],
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key) {
    $setting = parent::getSetting($key);
    if (is_array($setting)) {
      $defaultOptions = static::defaultSettings()['options'];
      return NestedArray::mergeDeep($defaultOptions, $setting);
    }
    return $setting;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['note'] = [
      '#markup' => $this->t('See @link for more information.', [
        '@link' => Link::fromTextAndUrl(static::DOCUMENTATION_URL, Url::fromUri(static::DOCUMENTATION_URL))->toString(),
      ]),
      '#theme_wrappers' => ['container'],
    ];

    $options = $this->getSetting('options');
    $form['options'] = ['#tree' => TRUE];
    $form['options']['uploader']['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Upload method'),
      '#options' => static::uploadMethods(),
      '#default_value' => $options['uploader']['method'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $options = $this->getSetting('options');
    return [
      $this->t('Upload method: @method', [
        '@method' => static::uploadMethods()[$options['uploader']['method']],
      ]),
      $this->t('Progress indicator: File uploader'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    if (!isset($items[0])) {
      $items->appendItem();
    }
    $element = parent::formSingleElement($items, $delta, $element, $form, $form_state);

    $options = $this->getSetting('options');
    foreach ($options as &$set) {
      array_walk($set, function (&$value): void {
        // Treat checkbox options as boolean values.
        $value = is_numeric($value) && is_string($value) && $value <= 1 ? (bool) $value : $value;
      });
    }

    // Massage the default values.
    $values = [
      'fids' => array_column($items->getValue(), 'target_id'),
    ];

    return [
      '#type' => 'file_uploader',
      '#title' => $this->fieldDefinition->getLabel(),
      '#description' => [
        '#theme' => 'file_upload_help',
        '#description' => $this->fieldDefinition->getDescription(),
        '#upload_validators' => $element['#upload_validators'],
        '#cardinality' => $element['#cardinality'],
      ],
      '#process' => [
        [static::class, 'process'],
        [FileUploader::class, 'processFileUploader'],
      ],
      '#pre_render' => [[static::class, 'preRender']],
      '#upload_options' => $options,
      '#upload_location' => $element['#upload_location'],
      '#upload_validators' => $element['#upload_validators'],
      '#cardinality' => $element['#cardinality'],
      '#multiple' => $element['#cardinality'] > 1,
      '#default_value' => $values,
      '#required' => $element['#required'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    return $element;
  }

  /**
   * Prerender the element.
   */
  public static function preRender($element): array {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $newValues = [];
    foreach ($values['fids'] as $fid) {
      $newValues[] = ['target_id' => $fid];
    }
    return $newValues;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

}
