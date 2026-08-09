<?php

namespace Drupal\file_resup_media_library\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file_resup\Form\FileFormAlterBase;

/**
 * Implements hook_field_widget_complete_form_alter().
 */
class MediaLibraryAddFormUploadAlter extends FileFormAlterBase {

  public function formAlter(&$field_widget_complete_form, FormStateInterface $form_state, $field_definition, &$element, $delta, string $entity_type_id, string $bundle) {
    parent::formAlter($field_widget_complete_form, $form_state, $field_definition, $element, $delta, $entity_type_id, $bundle);
    $element['#form_type'] = 'media_library';
    $uri_scheme = $field_definition->getFieldStorageDefinition()->getSetting('uri_scheme');
    $date = date('Y-m');
    $element['#upload_location'] = "$uri_scheme://$date";

    // Update the max files to match the number of remaining slots in the media
    // field. We mimic what Media Library does in its file upload form.
    $state = $form_state->get('media_library_state');
    if ($state) {
      $slots = $state->getAvailableSlots();
      $element['#file_resup_max_files'] = $slots != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED ? $slots : -1;
    }
  }

  public static function managedFileProcess($element, FormStateInterface $form_state, $form) {
    if (empty($element['#field_name'])) {
      $element['#field_name'] = $element['#name'];
    }
    $element =  parent::managedFileProcess($element, $form_state, $form);
    $media_library_state = $form_state->get('media_library_state')->all();
    $init_state_values = array_intersect_key($media_library_state, [
      'media_library_opener_id' => 0,
      'media_library_allowed_types' => 0,
      'media_library_selected_type' => 0,
      'media_library_remaining' => 0,
      'media_library_opener_parameters' => 0,
    ]);
    $element['resup']['#attributes']['data-media-library'] = Json::encode($init_state_values);
    return $element;
  }

}
