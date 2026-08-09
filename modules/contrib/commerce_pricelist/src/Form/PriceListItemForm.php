<?php

namespace Drupal\commerce_pricelist\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity\Form\EntityDuplicateFormTrait;

class PriceListItemForm extends ContentEntityForm {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // The default edit title is wrong because EntityController::doGetEntity()
    // takes the price list entity instead of the price list item entity.
    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit %label', ['%label' => $this->entity->label()]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter($entity_type_id) !== NULL) {
      $entity = $route_match->getParameter($entity_type_id);
    }
    elseif ($product_variation = $route_match->getParameter('commerce_product_variation')) {
      $values = [
        'type' => 'commerce_product_variation',
        'purchasable_entity' => $product_variation->id(),
      ];
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    }
    else {
      // Price lists and price list items share the same bundle.
      $price_list = $route_match->getParameter('commerce_pricelist');
      $values = [
        'type' => $price_list->bundle(),
        'price_list_id' => $price_list->id(),
      ];
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    try {
      $save = $this->entity->save();
      $this->postSave($this->entity, $this->operation);
      $this->messenger()->addMessage($this->t('Saved the %label price.', ['%label' => $this->entity->label()]));
      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
      return $save;
    }
    catch (EntityStorageException $exception) {
      $this->getLogger('commerce_pricelist')->error('<pre>%exception</pre>', [
        '%exception' => $exception->__toString(),
      ]);
      $this->messenger()->addError($this->t('Cannot save the price. Check if a price already exists for the same product / quantity tier?'), [
        '%label' => $this->entity->label(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setFormDisplay(EntityFormDisplayInterface $form_display, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_pricelist\Entity\PriceListItemInterface $price_list_item */
    $price_list_item = $this->entity;
    // When adding a price from the variation add price route, the price list
    // item is new, the product variation is already known but the price list
    // isn't, so we need to expose the price list field but remove the
    // "purchasable_entity" one.
    if ($price_list_item->isNew() &&
      is_null($price_list_item->getPriceListId()) &&
      $price_list_item->getPurchasableEntityId()) {
      $form_display->setComponent('price_list_id', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ]);
      $form_display->removeComponent('purchasable_entity');
    }
    $form_state->set('form_display', $form_display);
    return $this;
  }

}
