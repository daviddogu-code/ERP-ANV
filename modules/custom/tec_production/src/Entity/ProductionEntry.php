<?php

namespace Drupal\tec_production\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Daily production log entry: date + order + line item + quantity.
 *
 * One immutable record per registration. The order's field_tec_produced (X on
 * the queue) is recomputed as the SUM of its entries on insert/update/delete
 * (see tec_production.module hooks).
 *
 * @ContentEntityType(
 *   id = "tec_production_entry",
 *   label = @Translation("Production log entry"),
 *   label_collection = @Translation("Production log entries"),
 *   base_table = "tec_production_entry",
 *   admin_permission = "register tec production",
 *   handlers = {
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "delete-form" = "/production/log/{tec_production_entry}/delete",
 *   },
 * )
 */
class ProductionEntry extends ContentEntityBase {

  public function label() {
    $date = $this->get('entry_date')->value ?: '';
    $qty = $this->get('quantity')->value ?: 0;
    return $date . ' · ' . $qty . ' pcs (#' . $this->id() . ')';
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['entry_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Production Date'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date');

    $fields['order_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'tec_order');

    $fields['line_item_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Line item'))
      ->setSetting('target_type', 'tec_line_item');

    $fields['quantity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Quantity'))
      ->setRequired(TRUE)
      ->setSetting('min', 0);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Registered by'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultUserId');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Logged'));

    return $fields;
  }

  /**
   * Default value callback for uid.
   */
  public static function getDefaultUserId(): array {
    return [\Drupal::currentUser()->id()];
  }

}
