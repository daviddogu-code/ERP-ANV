<?php

namespace Drupal\eca_commerce\Plugin\Action;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Describes the eca_commerce change_price_in_cart action.
 *
 * This allows users to set the final price when added to cart based on ECA.
 *
 * @Action(
 *   id = "eca_commerce_change_price_in_cart",
 *   label = @Translation("Change Price in Cart"),
 *   type = "commerce_order_item"
 * )
 */
class ChangePriceInCartAction extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = AccessResult::AllowedIf($object instanceof OrderItemInterface);

    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (class_exists(Price::class)) {
      $newPrice = $this->tokenServices->replace($this->configuration['final_price']);
      $newPrice = str_replace('$', '', $newPrice);

      $newPrice = new Price($newPrice, 'USD');
      $entity->setUnitPrice($newPrice, TRUE);
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'final_price' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['final_price'] = [
      '#type' => 'textfield',
      '#description' => $this->t('This should either be a number or a token that outputs a number or price field. This only supports USD.'),
      '#title' => $this->t('Final Price'),
      '#default_value' => $this->configuration['final_price'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['final_price'] = $form_state->getValue('final_price');
    parent::submitConfigurationForm($form, $form_state);
  }

}
