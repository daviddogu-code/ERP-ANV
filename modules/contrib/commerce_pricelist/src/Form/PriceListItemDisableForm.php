<?php

namespace Drupal\commerce_pricelist\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the price list item disable form.
 */
class PriceListItemDisableForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disable the price list item %label?', [
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_pricelist.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_pricelist\Entity\PriceListItemInterface $price_list_item */
    $price_list_item = $this->getEntity();
    $price_list_item->setEnabled(FALSE);
    $price_list_item->save();
    $this->messenger()->addStatus($this->t('Successfully disabled the price list item %label.', ['%label' => $price_list_item->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
