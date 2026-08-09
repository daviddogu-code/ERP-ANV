<?php

namespace Drupal\symfony_mailer_log\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates that the value is a valid duration string for {@see \DateInterval}.
 */
class DateIntervalConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$constraint instanceof DateIntervalConstraint) {
      throw new UnexpectedValueException($constraint, DateIntervalConstraint::class);
    }

    if (
      !$value
      || $value instanceof \DateInterval
    ) {
      return;
    }

    try {
      new \DateInterval($value);
    }
    catch (\Exception $e) {
      $this->context->addViolation($constraint->message);
    }

  }

}
