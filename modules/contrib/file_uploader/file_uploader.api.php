<?php

/**
 * @file
 * Hooks provided by the module.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;

/**
 * Alter the file uploader element.
 *
 * @param array $element
 *   The form element.
 * @param array $settings
 *   The attachment settings.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state.
 */
function hook_file_uploader_element_alter(array &$element, array &$settings, FormStateInterface $form_state): void {
  $settings['options']['instance']['autoProceed'] = TRUE;

  // Use image style on existing image previews.
  if ($element['#name'] === 'field_image') {
    foreach ($settings['values'] as &$value) {
      $value['url'] = ImageStyle::load('large')->buildUrl(File::load($value['fid'])->getFileUri());
    }
  }
}

/**
 * @} End of "addtogroup hooks".
 */
