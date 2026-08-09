<?php

namespace Drupal\base_field_override_ui\Form;

use Drupal\base_field_override_ui\BaseFieldOverrideUI;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for removing a base field override from a bundle.
 */
class BaseFieldOverrideDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return BaseFieldOverrideUI::getOverviewRouteInfo($this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();

    $this->messenger()->addStatus($this->t('The base field override has been deleted.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
