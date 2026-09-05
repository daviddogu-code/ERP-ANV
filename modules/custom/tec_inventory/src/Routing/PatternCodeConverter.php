<?php

namespace Drupal\tec_inventory\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\tec_inventory\Entity\Pattern;
use Symfony\Component\Routing\Route;

/**
 * /pattern/055 loads the pattern whose code is 055, not entity id 55.
 */
class PatternCodeConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!is_string($value) || $value === '') {
      return NULL;
    }
    $code = trim($value);
    if (in_array(strtolower($code), Pattern::RESERVED, TRUE)) {
      return NULL;
    }
    return Pattern::loadByCode($code);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return ($definition['type'] ?? '') === 'tec_pattern_code';
  }

}
