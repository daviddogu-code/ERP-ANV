<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\ChoiceValidator;

/**
 * Choice constraint.
 *
 * @Constraint(
 *   id = "Choice",
 *   label = @Translation("Choice", context = "Validation"),
 * )
 */
class ChoiceConstraint extends Choice {

  public $minMessage = 'You must select at least %limit choice.|You must select at least %limit choices.';
  public $maxMessage = 'You must select at most %limit choice.|You must select at most %limit choices.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return ChoiceValidator::class;
  }

}
