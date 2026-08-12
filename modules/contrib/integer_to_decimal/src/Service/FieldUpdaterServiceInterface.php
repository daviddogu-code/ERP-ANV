<?php

namespace Drupal\integer_to_decimal\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * provides an interface for Field Updater services.
 *
 */
interface FieldUpdaterServiceInterface
{

  /**
   *
   * @param string $field
   * Machine name of the field
   *
   * @param string $type
   * Field type such as integer, decimal
   *
   * @param $entity_type
   * The entity machine name
   *
   * @param string $bundle
   * The bundle to which the converted field is associated with
   *
   * @param integer $precision
   * precision associated with decimal field type
   *
   * @param integer $scale
   * scale associated with decimal field type
   *
   *
   * @throws InvalidPluginDefinitionException
   */
    public function fieldUpdater($field, $type, $entity_type, $bundle, $precision, $scale);
}
