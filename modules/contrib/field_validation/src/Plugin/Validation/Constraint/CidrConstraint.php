<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Cidr;
use Symfony\Component\Validator\Constraints\CidrValidator;

/**
 * Cidr constraint.
 *
 * @Constraint(
 *   id = "Cidr",
 *   label = @Translation("Cidr", context = "Validation"),
 * )
 */
class CidrConstraint extends Cidr {

  public $netmaskRangeViolationMessage = 'The value of the netmask should be between %min and %max.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return CidrValidator::class;
  }

}
