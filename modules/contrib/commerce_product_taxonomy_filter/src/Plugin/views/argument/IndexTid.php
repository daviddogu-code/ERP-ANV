<?php

namespace Drupal\commerce_product_taxonomy_filter\Plugin\views\argument;

use Drupal\taxonomy\Entity\Term;
use Drupal\views\Plugin\views\argument\ManyToOne;

/**
 * Allow taxonomy term ID(s) as argument.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("commerce_product_taxonomy_index_tid")
 */
class IndexTid extends ManyToOne {

  /**
   * {@inheritdoc}
   */
  public function titleQuery() {
    $titles = [];
    $terms = Term::loadMultiple($this->value);
    foreach ($terms as $term) {
      $titles[] = \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
    }
    return $titles;
  }

}
