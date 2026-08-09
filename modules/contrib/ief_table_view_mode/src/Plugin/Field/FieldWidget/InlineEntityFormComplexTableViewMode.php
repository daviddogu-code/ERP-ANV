<?php

namespace Drupal\ief_table_view_mode\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;

/**
 * Complex inline widget.
 *
 * @FieldWidget(
 *   id = "inline_entity_form_complex_table_view_mode",
 *   label = @Translation("Inline entity form - Complex - Table View Mode"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions"
 *   },
 *   multiple_values = true
 * )
 */
class InlineEntityFormComplexTableViewMode extends InlineEntityFormComplex {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $target_type = $field_definition->getSetting('target_type');
    return \Drupal::entityTypeManager()->hasHandler($target_type, 'inline_form_table_view_mode');
  }

  /**
   * {@inheritdoc}
   */
  protected function createInlineFormHandler() {
    if (!isset($this->inlineFormHandler)) {
      $target_type = $this->getFieldSetting('target_type');
      $this->inlineFormHandler = $this->entityTypeManager->getHandler($target_type, 'inline_form_table_view_mode');
    }
  }

}
