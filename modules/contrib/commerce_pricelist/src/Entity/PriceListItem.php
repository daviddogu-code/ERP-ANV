<?php

namespace Drupal\commerce_pricelist\Entity;

use Drupal\commerce\Entity\CommerceContentEntityBase;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the price list item entity.
 *
 * Called a "price" in the UI for UX reasons.
 *
 * @ContentEntityType(
 *   id = "commerce_pricelist_item",
 *   label = @Translation("Price list item"),
 *   label_collection = @Translation("Prices"),
 *   label_singular = @Translation("price"),
 *   label_plural = @Translation("prices"),
 *   label_count = @PluralTranslation(
 *     singular = "@count price",
 *     plural = "@count prices",
 *   ),
 *   handlers = {
 *     "event" = "Drupal\commerce_pricelist\Event\PriceListItemEvent",
 *     "storage" = "Drupal\commerce\CommerceContentEntityStorage",
 *     "storage_schema" = "Drupal\commerce_pricelist\PriceListItemStorageSchema",
 *     "list_builder" = "Drupal\commerce_pricelist\PriceListItemListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\commerce\CommerceEntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\commerce_pricelist\Form\PriceListItemForm",
 *       "enable" = "Drupal\commerce_pricelist\Form\PriceListItemEnableForm",
 *       "disable" = "Drupal\commerce_pricelist\Form\PriceListItemDisableForm",
 *       "edit" = "Drupal\commerce_pricelist\Form\PriceListItemForm",
 *       "duplicate" = "Drupal\commerce_pricelist\Form\PriceListItemForm",
 *       "delete" = "Drupal\commerce_pricelist\Form\PriceListItemDeleteForm",
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\commerce_pricelist\PriceListItemRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer commerce_pricelist",
 *   base_table = "commerce_pricelist_item",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "owner" = "uid",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "add-form" = "/price-list/{commerce_pricelist}/prices/add",
 *     "edit-form" = "/price-list/{commerce_pricelist}/prices/{commerce_pricelist_item}/edit",
 *     "enable-form" = "/price-list/{commerce_pricelist}/prices/{commerce_pricelist_item}/enable",
 *     "disable-form" = "/price-list/{commerce_pricelist}/prices/{commerce_pricelist_item}/disable",
 *     "duplicate-form" = "/price-list/{commerce_pricelist}/prices/{commerce_pricelist_item}/duplicate",
 *     "delete-form" = "/price-list/{commerce_pricelist}/prices/{commerce_pricelist_item}/delete",
 *     "export-form" = "/price-list/{commerce_pricelist}/prices/export",
 *     "import-form" = "/price-list/{commerce_pricelist}/prices/import",
 *     "collection" = "/price-list/{commerce_pricelist}/prices",
 *   },
 *   field_ui_base_route = "entity.commerce_pricelist_item.bundle_page"
 * )
 */
class PriceListItem extends CommerceContentEntityBase implements PriceListItemInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['commerce_pricelist'] = $this->getPriceListId();
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    if ($this->isNew()) {
      return '';
    }
    $purchasable_entity = $this->getPurchasableEntity();
    $purchasable_entity_label = $purchasable_entity ? $purchasable_entity->label() : '';
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    $price = $this->getPrice();
    $formatted_price = $currency_formatter->format($price->getNumber(), $price->getCurrencyCode());

    return sprintf('%s: %s', $purchasable_entity_label, $formatted_price);
  }

  /**
   * {@inheritdoc}
   */
  public function getPriceList() {
    return $this->get('price_list_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriceListId() {
    return $this->get('price_list_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasableEntity() {
    return $this->getTranslatedReferencedEntity('purchasable_entity');
  }

  /**
   * {@inheritdoc}
   */
  public function setPurchasableEntity(PurchasableEntityInterface $purchasable_entity) {
    return $this->set('purchasable_entity', $purchasable_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasableEntityId() {
    return $this->get('purchasable_entity')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setPurchasableEntityId($purchasable_entity_id) {
    return $this->set('purchasable_entity', $purchasable_entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantity() {
    return $this->get('quantity')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuantity($quantity) {
    $this->set('quantity', (string) $quantity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getListPrice(): ?Price {
    if (!$this->get('list_price')->isEmpty()) {
      return $this->get('list_price')->first()->toPrice();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setListPrice(Price $list_price) {
    return $this->set('list_price', $list_price);
  }

  /**
   * {@inheritdoc}
   */
  public function getPrice(): ?Price {
    if (!$this->get('price')->isEmpty()) {
      return $this->get('price')->first()->toPrice();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrice(Price $price) {
    return $this->set('price', $price);
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled) {
    $this->set('status', (bool) $enabled);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $price_list_id = $this->getPriceListId();
    if (empty($price_list_id)) {
      throw new EntityMalformedException(sprintf('Required price list item field "price_list_id" is empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($purchasable_entity = $this->getPurchasableEntity()) {
      // Invalidate caches to allow the new pricing to come into effect.
      Cache::invalidateTags($purchasable_entity->getCacheTagsToInvalidate());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user that owns this price list item.'));

    $fields['price_list_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Price list'))
      ->setDescription(t('The parent price list.'))
      ->setSetting('target_type', 'commerce_pricelist')
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['purchasable_entity'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Purchasable entity'))
      ->setDescription(t('The purchasable entity.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -1,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ]);
    // Provide a default target_type for Views, which uses
    // base field definitions without bundle overrides.
    if (\Drupal::moduleHandler()->moduleExists('commerce_product')) {
      $fields['purchasable_entity']->setSetting('target_type', 'commerce_product_variation');
    }

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('The quantity tier.'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('min', 0)
      ->setDefaultValue(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_quantity',
      ]);

    $fields['list_price'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('List price'))
      ->setDescription(t('The list price.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'commerce_price_default',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_list_price',
      ]);

    $fields['price'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Price'))
      ->setDescription(t('The price.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'commerce_price_default',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_price_default',
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether the price list item is enabled.'))
      ->setDefaultValue(TRUE)
      ->setRequired(TRUE)
      ->setSettings([
        'on_label' => t('Enabled'),
        'off_label' => t('Disabled'),
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the price list item was last edited.'))
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $purchasable_entity_type = \Drupal::entityTypeManager()->getDefinition($bundle);
    $fields = [];
    $fields['purchasable_entity'] = clone $base_field_definitions['purchasable_entity'];
    $fields['purchasable_entity']->setSetting('target_type', $purchasable_entity_type->id());
    $fields['purchasable_entity']->setLabel($purchasable_entity_type->getLabel());

    return $fields;
  }

}
