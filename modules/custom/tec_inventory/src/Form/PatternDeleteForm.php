<?php

namespace Drupal\tec_inventory\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Confirm deletion, then back to the pattern list.
 */
class PatternDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    /** @var \Drupal\tec_inventory\Entity\Pattern $pattern */
    $pattern = $this->getEntity();
    $code = $pattern->code();
    if ($code !== '') {
      return Url::fromRoute('entity.tec_pattern.canonical', ['tec_pattern' => $code]);
    }
    return Url::fromRoute('entity.tec_pattern.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return Url::fromRoute('entity.tec_pattern.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('Pattern @label has been deleted.', [
      '@label' => $this->getEntity()->label(),
    ]);
  }

}
