<?php

namespace Drupal\file_resup\Form;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file_resup\Entity\FileResup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;

abstract class FileFormAlterBase {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  public function formAlter(&$field_widget_complete_form, FormStateInterface $form_state, $field_definition, &$element, $delta, string $entity_type_id, string $bundle) {
    $third_party_settings = $field_definition->getThirdPartySettings('file_resup');
    // Add our resup element via a process callback.
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    $element['#file_resup_max_files'] = $cardinality != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED ? $cardinality - $delta : -1;
    $element['#file_resup_auto_upload'] = $third_party_settings['auto_upload'];
    $element['#file_resup_entity_type_id'] = $entity_type_id;
    $element['#file_resup_bundle'] = $bundle;
    $element['#process'][] = static::class . '::managedFileProcess';
    $element['#file_value_callbacks'][] = static::class . '::fileValue';

    $file_size = $element['#upload_validators']['file_validate_size'][0] ?? $element['#upload_validators']['FileSizeLimit']['fileLimit'];
    if (!empty($third_party_settings['max_upload_size']) && $third_party_settings['max_upload_size'] > $file_size) {
      $element['#upload_validators']['file_validate_size'] = [$third_party_settings['max_upload_size']];
    }
  }

  /**
   * Managed file process callback.
   */
  public static function managedFileProcess($element, FormStateInterface $form_state, $form) {
    // Add our resup element.
    $form_object = $form_state->getFormObject();
    $max_files = $element['#file_resup_max_files'];
    $upload_validators = $element['#upload_validators'];
    $description = [
      '#theme' => 'file_upload_help',
      '#description' => '',
      '#upload_validators' => $element['#upload_validators'],
      '#cardinality' => $element['#cardinality'],
    ];
    $element['resup'] = [
      '#type' => 'hidden',
      '#value_callback' => static::class . '::fileResupValue',
      '#field_name' => $element['#field_name'],
      '#upload_location' => $element['#upload_location'],
      '#file_resup_upload_validators' => $upload_validators,
      '#attributes' => [
        'class' => ['file-resup'],
        'data-entity-type-id' => $element['#file_resup_entity_type_id'],
        'data-bundle' => $element['#file_resup_bundle'],
        'data-form-type' => $element['#form_type'],
        'data-operation' => method_exists($form_object, 'getOperation') ? $form_object->getOperation() : 'default',
        'data-upload-name' => $element['upload']['#multiple'] ? $element['upload']['#name'] . '[]' : $element['upload']['#name'],
        'data-upload-button-name' => $element['upload_button']['#name'],
        'data-max-filesize' => $upload_validators['file_validate_size'][0] ?? $upload_validators['FileSizeLimit']['fileLimit'] ?? NULL,
        'data-description' => \Drupal::service('renderer')->renderRoot($description)->__toString(),
        'data-parents' => Json::encode($element['#array_parents']),
        'data-url' => Url::fromRoute('file_resup.upload')->toString(),
        'data-drop-message' => $max_files > -1 ? (new PluralTranslatableMarkup($max_files, 'Drop a file here or click <em>Browse</em> below.', 'Drop up to @count files here or click <em>Browse</em> below.')) : t('Drop files here or click <em>Browse</em> below.'),
      ],
      '#prefix' => '<div class="file-resup-wrapper">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => ['file_resup/file_resup'],
        'drupalSettings' => [
          'file_resup' => [
            'chunk_size' => file_resup_chunksize(),
          ],
        ],
      ],
    ];
    // Add the extension list as a data attribute.
    if (isset($upload_validators['file_validate_extensions'][0]) || !empty($upload_validators['FileExtension']['extensions'])) {
      $element['resup']['#attributes']['data-extensions'] = isset($upload_validators['file_validate_extensions'][0]) ? str_replace(' ' , ',', $upload_validators['file_validate_extensions'][0]) : str_replace(' ' , ',', $upload_validators['FileExtension']['extensions']);
    }

    // Add the maximum number of files as a data attribute.
    if ($max_files > -1) {
      $element['resup']['#attributes']['data-max-files'] = $max_files;
    }


    // Add autostart as a data attribute.
    if ($element['#file_resup_auto_upload'] === 1) {
      $element['resup']['#attributes']['data-autostart'] = 'on';
    }

    return $element;
  }

  /**
   * Upload ID from file ID.
   *
   * @param $resup_file_id
   *   The resup file ID.
   *
   * @return false|string
   *   The parsed upload ID or False if not found.
   */
  public static function uploadIdFromFileId($resup_file_id) {
    $user = \Drupal::currentUser();
    if (preg_match('`^[1-9]\d*-\d+-[\w%]+$`', $resup_file_id)) {
      $prefix = $user->id() ? $user->id() : str_replace('.', '_', \Drupal::request()->getClientIp());
      return substr($prefix . '-' . $resup_file_id, 0, 240);
    }
    return FALSE;
  }

  public static function getUploadUri(FileResup $upload) {
    return $upload->scheme->value . '://' . FILE_RESUP_TEMPORARY . '/' . $upload->upload_id->value;
  }

  /**
   * Is enabled.
   *
   * @param $field_definition
   *   The managed file field definition.
   *
   * @return bool
   *   Whether File Resumable Upload is enabled for this widget.
   */
  public function isEnabled($field_definition) {
    return method_exists($field_definition, 'getThirdPartySettings') && ($third_party_settings = $field_definition->getThirdPartySettings('file_resup')) && $third_party_settings['enabled'] === 1;
  }

  /**
   * File value callback for the managed file widget.
   */
  public static function fileValue(&$element, array &$input, FormStateInterface $form_state) {
    if (!empty($input['resup'])) {
      $resup_file_ids = explode(',', $input['resup']);
      foreach ($resup_file_ids as $resup_file_id) {
        if ($file = self::saveUpload($element, $resup_file_id, $form_state)) {
          $input['fids'][] = $file->fid->value;
          // When anonymous, file_managed_file_value() does not allow previously
          // uploaded temporary files to be reused, so we also need to pass fid
          // through element's default value.
          if ($file->isPermanent() && !\Drupal::currentUser()->isAnonymous()) {
            if ($element['#extended']) {
              $element['#default_value'][] = [
                'fid' => $file->fid->value,
                // 'display' must be passed as well, as an integer.
                'display' => $input['display'],
              ];
            }
            else {
              $element['#default_value'][] = $file->fid->value;
            }
          }
        }
      }
    }
  }

  /**
   * Value callback for the resup element.
   */
  public static function fileResupValue(&$element, $input, FormStateInterface $form_state) {
    $fids = [];

    if ($input) {
      $resup_file_ids = explode(',', $input);
      if (isset($element['#attributes']['data-max-files'])) {
        $resup_file_ids = array_slice($resup_file_ids, 0, max(0, $element['#attributes']['data-max-files'] - 1));
      }
      foreach ($resup_file_ids as $resup_file_id) {
        if ($file = FileWidgetFormAlter::saveUpload($element, $resup_file_id, $form_state)) {
          $fids[] = $file->fid;
        }
      }
    }

    return $fids;
  }

  /**
   * Save upload.
   *
   * @param array $element
   *   The file element.
   * @param string $resup_file_id
   *   The resup file ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\file\Entity\File|false|null
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected static function saveUpload(array $element, string $resup_file_id, FormStateInterface $form_state) {
    // Get a valid upload ID.
    $upload_id = static::uploadIdFromFileId($resup_file_id);
    if (!$upload_id) {
      return FALSE;
    }
    $upload_id = urldecode($upload_id);

    // Get the upload record.
    $upload = FileResup::load($upload_id);
    if (!$upload) {
      return FALSE;
    }

    // The file may have already been uploaded before.
    if ($upload->fid->target_id) {
      return File::load($upload->fid->target_id);
    }

    // Ensure the upload is complete.
    if ($upload->uploaded_chunks->value != ceil($upload->filesize->value / file_resup_chunksize())) {
      return FALSE;
    }

    // Ensure the destination is still valid.
    $destination = $element['#upload_location'];
    $destination_scheme = StreamWrapperManager::getScheme($destination);
    if (!$destination_scheme || $destination_scheme != $upload->scheme->value) {
      return FALSE;
    }

    // Ensure the following file_exists call succeeds on containerized deployments. Quick changes between webnodes causes
    // file_exists to not detect a new file because of the metadata cache. Let's open the directory to invalidate the cache.
    //
    // @see https://stackoverflow.com/questions/41723458/php-file-exists-or-is-file-does-not-answer-correctly-for-10-20s-on-nfs-files-ec
    $handle = opendir($upload->scheme->value . '://' . FILE_RESUP_TEMPORARY);
    closedir($handle);

    // Ensure the uploaded file is present.
    $upload_uri = FileWidgetFormAlter::getUploadUri($upload);
    if (!file_exists($upload_uri)) {
      return FALSE;
    }

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file = File::create([
      'uid' => \Drupal::currentUser()->id(),
      'filename' => trim($file_system->basename($upload->filename->value), '.'),
      'uri' => $upload_uri,
      'filemime' => \Drupal::service('file.mime_type.guesser')->guessMimeType($upload->filename->value),
      'filesize' => $upload->filesize->value,
      'status' => 1,
      'created' => \Drupal::time()->getRequestTime(),
      'changed' => \Drupal::time()->getRequestTime(),
    ]);

    // Munge the filename.
    $validators = $element['#upload_validators'];
    $extensions = '';
    if (isset($validators['file_validate_extensions'])) {
      if (isset($validators['file_validate_extensions'][0])) {
        $extensions = $validators['file_validate_extensions'][0];
      }
      else {
        unset($validators['file_validate_extensions']);
      }
    }
    elseif (!empty($validators['FileExtension']['extensions'])) {
      $extensions = $validators['FileExtension']['extensions'];
    }
    else {
      $extensions = 'jpg jpeg gif png txt doc xls pdf ppt pps odt ods odp';
      $validators['file_validate_extensions'][] = $extensions;
    }
    if (!empty($extensions)) {
      $event = new FileUploadSanitizeNameEvent($file->getFilename(), $extensions);
      \Drupal::service('event_dispatcher')->dispatch($event);
      $file->setFilename($event->getFilename());
    }

    // Rename potentially executable files.
    $insecure_uploads = \Drupal::config('system.file')->get('allow_insecure_uploads') ?? 0;
    if (!$insecure_uploads && preg_match('/\.(php|pl|py|cgi|asp|js)(\.|$)/i', $file->getFilename()) && (substr($file->getFilename(), -4) != '.txt')) {
      $file->setMimeType('text/plain');
      $file->setFileUri($file->getFileUri() . '.txt');
      $file->setFilename($file->getFilename() . '.txt');
      if (!empty($extensions)) {
        $validators['file_validate_extensions'][0] .= ' txt';
        \Drupal::messenger()->addMessage(t('For security reasons, your upload has been renamed to %filename.', ['%filename' => $file->getFilename()]));
      }
    }

    // Get the upload element name.
    $element_parents = $element['#parents'];
    if (end($element_parents) == 'resup') {
      unset($element_parents[key($element_parents)]);
    }
    $form_field_name = implode('_', $element_parents);

    // Run validators.
    $validators['file_validate_name_length'] = [];
    $errors = file_validate($file, $validators);
    if ($errors) {
      $message = t('The specified file %name could not be uploaded.', ['%name' => $file->getFilename()]);
      if (count($errors) > 1) {
        $message = [
          '#theme' => 'item_list',
          '#title' => $message,
          '#items' => $errors,
        ];
      }
      else {
        $message .= ' ' . array_pop($errors);
      }
      $form_state->setErrorByName($form_field_name, $message);
      return FALSE;
    }

    // Prepare the destination directory.
    $logger = \Drupal::logger('file_resup');
    if (!$file_system->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      $logger->error(t('The upload directory %directory for the file field !name could not be created or is not accessible. A newly uploaded file could not be saved in this directory as a consequence, and the upload was canceled.', ['%directory' => $destination, '!name' => $element['#field_name']]));
      $form_state->setErrorByName($form_field_name, t('The file could not be uploaded.'));
      return FALSE;
    }

    // Complete the destination.
    if (substr($destination, -1) != '/') {
      $destination .= '/';
    }
    $destination = $file_system->getDestinationFilename($destination . $file->filename->value, FileSystemInterface::EXISTS_RENAME);

    // Move the uploaded file.
    $file->setFileUri($destination);
    if (!rename($upload_uri, $file->uri->value)) {
      $form_state->setErrorByName($form_field_name, t('File upload error. Could not move uploaded file.'));
      $logger->error(t('Upload error. Could not move uploaded file %file to destination %destination.', array('%file' => $file->filename->value, '%destination' => $file->uri->value)));
      return FALSE;
    }

    // Set the permissions on the new file.
    $file_system->chmod($file->uri->value);

    // Save the file object to the database.
    $saved = $file->save();
    if (!$saved) {
      return FALSE;
    }

    $should_prevent_duplicates = \Drupal::config('file_resup.settings')->get('prevent_duplicates');
    if ($should_prevent_duplicates) {
      // Update the upload record.
      $upload->set('fid', $file->fid->value);
      $upload->save();
    }
    else {
      // Remove the upload in progress record.
      $upload->delete();
    }


    return $file;
  }


}
