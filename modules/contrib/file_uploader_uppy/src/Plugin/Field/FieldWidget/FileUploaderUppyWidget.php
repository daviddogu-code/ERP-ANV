<?php

namespace Drupal\file_uploader_uppy\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file_uploader\Plugin\Field\FieldWidget\FileUploaderWidgetBase;
use Drupal\file_uploader_uppy\UppyLocaleMapper;

/**
 * Plugin implementation of the 'file_uploader_uppy' widget.
 *
 * @FieldWidget(
 *   id = "file_uploader_uppy",
 *   label = @Translation("File uploader by Uppy"),
 *   field_types = {
 *     "file",
 *     "image",
 *   },
 *   multiple_values = TRUE
 * )
 */
class FileUploaderUppyWidget extends FileUploaderWidgetBase {

  /**
   * The documentation URL.
   */
  const DOCUMENTATION_URL = 'https://uppy.io/docs/uppy/#options';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();
    $settings['options']['instance'] += [
      'autoProceed' => FALSE,
    ];
    $settings['options']['plugin'] += [
      'showProgressDetails' => FALSE,
      'hideCancelButton' => FALSE,
      'hideProgressAfterFinish' => FALSE,
      'singleFileFullScreen' => TRUE,
      'disableStatusBar' => FALSE,
      'disableInformer' => FALSE,
      'disableThumbnailGenerator' => FALSE,
      'waitForThumbnailsBeforeUpload' => FALSE,
      'thumbnailWidth' => 200,
      'thumbnailHeight' => 200,
      'enableImageEditor' => FALSE,
      'autoOpenFileEditor' => FALSE,
      'imageEditor' => [
        'actions' => [],
        'cropperOptions' => [],
      ],
      'theme' => 'light',
    ];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $name = $this->fieldDefinition->getName();

    $options = $this->getSetting('options');
    $form['options']['instance']['autoProceed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto proceed'),
      '#description' => $this->t('Upload as soon as files are added.'),
      '#default_value' => $options['instance']['autoProceed'],
    ];
    $form['options']['plugin']['showProgressDetails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show progress details'),
      '#description' => $this->t('Show or hide progress details in the status bar.'),
      '#default_value' => $options['plugin']['showProgressDetails'],
    ];
    $form['options']['plugin']['hideCancelButton'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide cancel button'),
      '#description' => $this->t('Hide the cancel button in status bar and on each individual file.'),
      '#default_value' => $options['plugin']['hideCancelButton'],
    ];
    $form['options']['plugin']['hideProgressAfterFinish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide progress after finish'),
      '#description' => $this->t('Hide the status bar after the upload has finished.'),
      '#default_value' => $options['plugin']['hideProgressAfterFinish'],
    ];
    $form['options']['plugin']['singleFileFullScreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Single file fullscreen'),
      '#description' => $this->t('When only one file is selected, its preview and meta information will be centered and enlarged.'),
      '#default_value' => $options['plugin']['singleFileFullScreen'],
    ];
    $form['options']['plugin']['disableStatusBar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable status bar'),
      '#description' => $this->t('Disable the status bar completely.'),
      '#default_value' => $options['plugin']['disableStatusBar'],
    ];
    $form['options']['plugin']['disableInformer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable informer'),
      '#description' => $this->t('Disable informer (shows notifications in the form of toasts) completely.'),
      '#default_value' => $options['plugin']['disableInformer'],
    ];
    $form['options']['plugin']['disableThumbnailGenerator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable thumbnail generator'),
      '#description' => $this->t('Disable the thumbnail generator completely.'),
      '#default_value' => $options['plugin']['disableThumbnailGenerator'],
    ];
    $form['options']['plugin']['waitForThumbnailsBeforeUpload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wait for thumbnails before upload'),
      '#description' => $this->t('Whether to wait for all thumbnails to be ready before starting the upload.'),
      '#default_value' => $options['plugin']['waitForThumbnailsBeforeUpload'],
    ];
    $form['options']['plugin']['thumbnailWidth'] = [
      '#type' => 'number',
      '#title' => $this->t('Thumbnail width'),
      '#description' => $this->t('Width of the resulting thumbnail.'),
      '#default_value' => $options['plugin']['thumbnailWidth'],
    ];
    $form['options']['plugin']['thumbnailHeight'] = [
      '#type' => 'number',
      '#title' => $this->t('Thumbnail height'),
      '#description' => $this->t('Height of the resulting thumbnail.'),
      '#default_value' => $options['plugin']['thumbnailHeight'],
    ];
    $form['options']['plugin']['enableImageEditor'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable image editor'),
      '#description' => $this->t('When you want to allow users to crop, rotate, zoom and flip images that are added to Uppy.<br /><strong>Only available on new image uploads.</strong>'),
      '#default_value' => $options['plugin']['enableImageEditor'],
    ];
    $form['options']['plugin']['autoOpenFileEditor'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto open image editor'),
      '#description' => $this->t('Automatically open image editor for the image file in a upload batch.'),
      '#default_value' => $options['plugin']['autoOpenFileEditor'],
      '#states' => [
        'visible' => [
          ":input[name='fields[$name][settings_edit_form][settings][options][plugin][enableImageEditor]']" => ['checked' => TRUE],
        ],
      ],
    ];
    $form['options']['plugin']['imageEditor'] = [];
    $form['options']['plugin']['imageEditor']['actions'] = [
      '#type' => 'select',
      '#title' => $this->t('Image editor actions'),
      '#description' => $this->t('Leave blank to enable all actions.'),
      '#options' => [
        'revert' => $this->t('Revert'),
        'rotate' => $this->t('Rotate'),
        'granularRotate' => $this->t('Granular rotate'),
        'flip' => $this->t('Flip'),
        'zoomIn' => $this->t('Zoom in'),
        'zoomOut' => $this->t('Zoom out'),
        'cropSquare' => $this->t('Crop square'),
        'cropWidescreen' => $this->t('Crop widescreen'),
        'cropWidescreenVertical' => $this->t('Crop widescreen vertical'),
      ],
      '#multiple' => TRUE,
      '#default_value' => $options['plugin']['imageEditor']['actions'],
      '#states' => $form['options']['plugin']['autoOpenFileEditor']['#states'],
    ];
    $form['options']['plugin']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#description' => $this->t('Light or dark theme for the Dashboard.'),
      '#options' => [
        'light' => $this->t('Light'),
        'dark' => $this->t('Dark'),
        'auto' => $this->t('Auto'),
      ],
      '#default_value' => $options['plugin']['theme'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formSingleElement($items, $delta, $element, $form, $form_state);
    $element['#upload_provider'] = 'file_uploader_uppy';

    // Attach the locale library for the current language.
    if ($language = $this->languageManager->getCurrentLanguage()->getId()) {
      $element['#attached']['library'][] = "file_uploader_uppy/locale.$language";
      $element['#cache']['contexts'][] = 'languages:language_interface';
      $element['#upload_options']['instance']['locale'] = UppyLocaleMapper::getLocale();
    }

    return $element;
  }

  /**
   * Prerender the element.
   */
  public static function preRender($element): array {
    if ($element['#disabled'] ?? FALSE) {
      $element['#attached']['drupalSettings']['file_uploader'][$element['#id']]['options']['plugin']['disabled'] = TRUE;
    }
    return $element;
  }

}
