<?php

namespace Drupal\eck;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\eck\Entity\EckEntityBundle;

/**
 * Defines the translation handler for eck entities.
 */
class EckTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   */
  protected function entityFormTitle(EntityInterface $entity) {
    $type_label = NULL;
    $type = EckEntityBundle::load($entity->bundle());

    if ($type !== NULL) {
      $type_label = $type->label();
    }

    return t('<em>Edit @type</em> @title', ['@type' => $type_label, '@title' => $entity->label()]);
  }

}
