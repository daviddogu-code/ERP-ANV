<?php

namespace Drupal\select2_multicheck\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\select2\Plugin\Field\FieldWidget\Select2Widget;

/**
 * Select2 Multi Checkboxes widget.
 *
 * @FieldWidget(
 *   id = "select2_multicheck",
 *   label = @Translation("Select2 Multi Checkboxes"),
 *   field_types = {
 *     "entity_reference",
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class Select2MultiCheckoxes extends Select2Widget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // Add js files and classes to the form element.
    $element['#type'] = 'select2';
    if ($element['#multiple']) {
      $element['#attributes']['class'][] = 'select2-multiple';
    }
    else {
      $element['#attributes']['class'][] = 'select2-single';
    }
    $element['#attached']['library'][] = 'select2_multicheck/select2-multi-check';
    $element['#attached']['library'][] = 'select2_multicheck/font-awesome';

    return $element;
  }

}
