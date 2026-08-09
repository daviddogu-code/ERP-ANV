<?php

namespace Drupal\file_uploader\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;

/**
 * Provides file uploader element.
 *
 * @FormElement("file_uploader")
 */
class FileUploader extends ManagedFile {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#extended' => TRUE,
      '#upload_provider' => NULL,
      '#upload_options' => [],
      '#upload_validators' => [],
      '#upload_location' => NULL,
      '#process' => [
        [$class, 'processFileUploader'],
      ],
      '#element_validate' => [
        [$class, 'validateManagedFile'],
      ],
      '#value_callback' => [
        [$class, 'valueCallback'],
      ],
      '#theme' => 'file_uploader',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Process file uploader element.
   */
  public static function processFileUploader($element, FormStateInterface $form_state, &$complete_form): array {
    $element['#attached']['library'][] = "{$element['#upload_provider']}/widget";
    $element['#attributes']['id'] = $element['#id'];
    $element['#attributes']['class'][] = Html::cleanCssIdentifier($element['#upload_provider']);
    $element['#attributes']['class'][] = $element['#cardinality'] > 1 ? 'file-uploader-multiple' : 'file-uploader-single';

    // Generate upload URL with token and options key.
    $options = serialize([
      '#name' => $element['#name'],
      '#upload_location' => $element['#upload_location'],
      '#upload_validators' => $element['#upload_validators'],
    ]);
    $key = Crypt::hmacBase64($options, \Drupal::csrfToken()->get());
    $url = Url::fromRoute('file_uploader.xhr');
    $url->setOptions([
      'query' => [
        'token' => \Drupal::csrfToken()->get($url->getInternalPath()),
        'key' => $key,
      ],
    ]);
    \Drupal::keyValue('file_uploader')->set($key, $options);

    // Prepare default values as existing files.
    $values = [];
    if ($fids = $element['#value']['fids']) {
      /** @var \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator */
      $fileUrlGenerator = \Drupal::service('file_url_generator');
      $files = File::loadMultiple($fids);
      foreach ($fids as $fid) {
        $values[] = [
          'fid' => $fid,
          'name' => $files[$fid]->getFilename(),
          'url' => $fileUrlGenerator->generate($files[$fid]->getFileUri())->toString(),
          'size' => $files[$fid]->getSize(),
          'type' => $files[$fid]->getMimeType(),
        ];
      }
    }
    $element['fids'] = [
      '#type' => 'hidden',
      '#value' => implode(' ', $fids),
    ];

    // Prepare the attachment settings.
    // Note: the file_validate_* functions are for backwards compatibility, they were deprecated in Drupal 10.2
    $upload_validators = $element['#upload_validators'];
    $extensions = $upload_validators['FileExtension']['extensions'] ?? ($upload_validators['file_validate_extensions'][0] ?? '');
    $file_size = $upload_validators['FileSizeLimit']['fileLimit'] ?? ($upload_validators['file_validate_size'][0] ?? 0);

    $settings = [
      'provider' => $element['#upload_provider'],
      'name' => $element['#name'],
      'options' => $element['#upload_options'] + [
        'xhr' => $url->toString(),
        'validators' => [
          'limit' => $element['#cardinality'],
          'extensions' => preg_filter('/^/', '.', explode(' ', $extensions)),
          'filesize' => $file_size,
        ],
      ],
      'values' => $values,
    ];
    $element['#attached']['drupalSettings']['file_uploader'][$element['#id']] = &$settings;

    // Invoke hook_file_uploader_element(). Allows modules and themes
    // to easily alter the file uploader element and attachment settings.
    \Drupal::moduleHandler()->alter('file_uploader_element', $element, $settings, $form_state);
    \Drupal::theme()->alter('file_uploader_element', $element, $settings, $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateManagedFile(&$element, FormStateInterface $form_state, &$complete_form): void {
    if ($element['fids']['#value'] && !is_array($element['fids']['#value'])) {
      $element['fids']['#value'] = explode(' ', $element['fids']['#value']);
    }
    parent::validateManagedFile($element, $form_state, $complete_form);
  }

}
