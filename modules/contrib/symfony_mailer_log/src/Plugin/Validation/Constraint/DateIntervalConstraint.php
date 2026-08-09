<?php

namespace Drupal\symfony_mailer_log\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the value is a valid duration string for {@see \DateInterval}.
 *
 * @Constraint(
 *   id = "SymfonyMailerLogDateInterval",
 *   label = @Translation("Valid DateInterval duration", context = "Validation"),
 * )
 */
class DateIntervalConstraint extends Constraint {

  /**
   * Message shown when entered date interval is incorrect.
   *
   * @var string
   */
  public $message = "This value is not a valid duration string.";

}
