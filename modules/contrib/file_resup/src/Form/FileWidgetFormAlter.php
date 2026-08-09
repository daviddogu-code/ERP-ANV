<?php

namespace Drupal\file_resup\Form;

use Drupal\Core\Field\WidgetBase;
use Drupal\file\Element\ManagedFile;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Implements hook_field_widget_complete_form_alter().
 */
class FileWidgetFormAlter extends FileFormAlterBase implements TrustedCallbackInterface {

  public function formAlter(&$field_widget_complete_form, FormStateInterface $form_state, $field_definition, &$element, $delta, string $entity_type_id, string $bundle) {
    parent::formAlter($field_widget_complete_form, $form_state, $field_definition, $element, $delta, $entity_type_id, $bundle);
    $element['#form_type'] = 'file_widget';
    $element['#pre_render'][] = static::class . '::preRender';
  }

  public static function managedFileProcess($element, FormStateInterface $form_state, $form) {
    $element =  parent::managedFileProcess($element, $form_state, $form);
    $element['upload_button']['#submit'][] = [static::class, 'fieldWidgetSubmit'];
    return $element;
  }

  /**
   * Field widget submit handler.
   */
  public static function fieldWidgetSubmit($form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $field_name = $element['#field_name'];
    $parents = $element['#field_parents'];
    $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);
    $items = $field_state['items'];

    // Append our items.
    if (!empty($element['resup']['#value'])) {
      foreach ($element['resup']['#value'] as $fid) {
        $items[]['fids'][] = $fid->value;
      }
    }

    // Remove possible duplicate items.
    $fids = [];
    foreach ($items as $delta => $item) {
      foreach ($item['fids'] as $item_fid) {
        if (in_array($item_fid, $fids)) {
          unset($items[$delta]);
        }
        else {
          $fids[] = $item_fid;
        }
      }
    }
    $items = array_values($items);

    // Update form state.
    NestedArray::setValue($form_state->getValues(), array_slice($button['#parents'], 0, -2), $items);
    $field_state['items'] = $items;
    WidgetBase::setWidgetState($parents, $field_name, $form_state, $field_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Render API callback: Hides display of the upload controls.
   */
  public static function preRender($element) {
    $element = ManagedFile::preRenderManagedFile($element);
    // If we already have a file, we don't want to show the upload controls.
    if (!empty($element['#value']['fids'])) {
      if (!$element['#multiple']) {
        $element['resup']['#access'] = FALSE;
      }
    }
    return $element;
  }

}
