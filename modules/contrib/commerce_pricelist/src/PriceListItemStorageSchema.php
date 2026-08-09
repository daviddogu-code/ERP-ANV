<?php

namespace Drupal\commerce_pricelist;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines a storage schema handler for price list items.
 */
class PriceListItemStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    // Disallow having multiple prices for the same product / quantity tier
    // within the same price list.
    $schema[$this->storage->getBaseTable()]['unique keys'] += [
      'price_list_purchasable_currency' => [
        'type',
        'price_list_id',
        'purchasable_entity',
        'quantity',
        'price__currency_code',
      ],
    ];
    return $schema;
  }

}
