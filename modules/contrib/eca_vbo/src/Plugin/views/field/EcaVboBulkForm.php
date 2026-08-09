<?php

namespace Drupal\eca_vbo\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm;

/**
 * Defines an ECA-only VBO actions field plugin.
 *
 * Extends the access behavior in the way that it additionally passes along
 * the action definition and preconfigured values.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("eca_vbo_bulk_form")
 */
class EcaVboBulkForm extends ViewsBulkOperationsBulkForm {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->actions = array_filter($this->actions, function ($definition) {
      // This plugin form is meant for ECA-related actions only.
      return is_a($definition['class'], 'Drupal\eca_vbo\Plugin\Action\VboExecute', TRUE);
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function getBulkOptions(): array {
    if (!isset($this->bulkOptions)) {
      $this->bulkOptions = [];
      foreach ($this->options['selected_actions'] as $key => $selected_action_data) {
        if (!isset($this->actions[$selected_action_data['action_id']])) {
          continue;
        }

        $definition = $this->actions[$selected_action_data['action_id']];

        // This plugin form is meant for ECA-related actions only.
        if (!is_a($definition['class'], 'Drupal\eca_vbo\Plugin\Action\VboExecute', TRUE)) {
          continue;
        }

        // Check access permission, if defined.
        if (!empty($definition['requirements']['_permission']) && !$this->currentUser->hasPermission($definition['requirements']['_permission'])) {
          continue;
        }

        // Check custom access, if defined.
        // This is the only difference of the ECA bulk form to the regular VBO,
        // passing along the action plugin definition plus selected action data.
        if (!empty($definition['requirements']['_custom_access']) && !$definition['class']::customAccess($this->currentUser, $this->view, $definition, $selected_action_data)) {
          continue;
        }

        // Override label if applicable.
        if (!empty($selected_action_data['preconfiguration']['label_override'])) {
          $this->bulkOptions[$key] = $selected_action_data['preconfiguration']['label_override'];
        }
        else {
          $this->bulkOptions[$key] = $definition['label'];
        }
      }
    }

    return $this->bulkOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // If the view type is not supported, suppress form display.
    // Also display information note to the user.
    if (empty($this->actions)) {
      $form = [
        '#type' => 'item',
        '#title' => $this->t('NOTE'),
        '#markup' => $this->t('You need to create at least one ECA configuration that reacts upon the event <em>VBO: Execute Views bulk operation</em>. That event will provide the configuration for defining an operation name, which will then show up here as selectable action.'),
        '#prefix' => '<div class="scroll">',
        '#suffix' => '</div>',
      ];
      return;
    }
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);
    if (!empty($this->options['element_class'])) {
      $this->options['element_class'] .= ' views-field-views-bulk-operations-bulk-form';
    }
    else {
      $this->options['element_class'] = 'views-field-views-bulk-operations-bulk-form';
    }
    if (!empty($this->options['element_label_class'])) {
      $this->options['element_label_class'] .= ' views-field-views-bulk-operations-bulk-form';
    }
    else {
      $this->options['element_label_class'] = 'views-field-views-bulk-operations-bulk-form';
    }
  }

}
