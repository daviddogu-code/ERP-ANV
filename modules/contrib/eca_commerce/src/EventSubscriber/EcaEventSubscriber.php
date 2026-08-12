<?php

namespace Drupal\eca_commerce\EventSubscriber;

use Drupal\commerce_cart\Event\CartEmptyEvent;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_cart\Event\OrderItemComparisonFieldsEvent;
use Drupal\commerce_checkout\Event\CheckoutCompletionRegisterEvent;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\commerce_order\Event\OrderLabelEvent;
use Drupal\commerce_order\Event\OrderProfilesEvent;
use Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent;
use Drupal\commerce_payment\Event\PaymentEvent;
use Drupal\commerce_price\Event\NumberFormatDefinitionEvent;
use Drupal\commerce_product\Event\FilterVariationsEvent;
use Drupal\commerce_product\Event\ProductAttributeValueEvent;
use Drupal\commerce_product\Event\ProductDefaultVariationEvent;
use Drupal\commerce_product\Event\ProductEvent;
use Drupal\commerce_product\Event\ProductVariationAjaxChangeEvent;
use Drupal\commerce_product\Event\ProductVariationEvent;
use Drupal\commerce_promotion\Event\CouponEvent;
use Drupal\commerce_promotion\Event\FilterPromotionsEvent;
use Drupal\commerce_promotion\Event\PromotionEvent;
use Drupal\commerce_store\Event\StoreEvent;
use Drupal\commerce_tax\Event\BuildZonesEvent;
use Drupal\commerce_tax\Event\CustomerProfileEvent;
use Drupal\eca\EcaEvents;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\EventSubscriber\EcaBase;
use Drupal\eca_commerce\Plugin\ECA\Event\EcaEvent;

/**
 * Event Subscriber for eca_commerce.
 */
class EcaEventSubscriber extends EcaBase {

  /**
   * Before the execution find the events to intercept.
   *
   * @param Drupal\eca\Event\BeforeInitialExecutionEvent $before_event
   *   Before event check.
   */
  public function onBeforeInitialExecution(BeforeInitialExecutionEvent $before_event): void {
    $event = $before_event->getEvent();

    // We check if the event exists before adding tokens.
    if ($event instanceof CartEmptyEvent) {
      $this->tokenService->addTokenData('cart', $event->getCart());
      $this->tokenService->addTokenData('commerce_order_items', $event->getOrderItems());
    }
    if ($event instanceof CartEntityAddEvent) {
      $this->tokenService->addTokenData('entity', $event->getEntity());
      $this->tokenService->addTokenData('cart', $event->getCart());
      $this->tokenService->addTokenData('quantity', $event->getQuantity());
      $this->tokenService->addTokenData('commerce_order_item', $event->getOrderItem());
    }
    if ($event instanceof CartOrderItemAddEvent) {
      $this->tokenService->addTokenData('cart', $event->getCart());
      $this->tokenService->addTokenData('quantity', $event->getQuantity());
      $this->tokenService->addTokenData('commerce_order_item', $event->getOrderItem());
    }
    if ($event instanceof CartOrderItemRemoveEvent) {
      $this->tokenService->addTokenData('cart', $event->getCart());
      $this->tokenService->addTokenData('commerce_order_item', $event->getOrderItem());
    }
    if ($event instanceof CartOrderItemUpdateEvent) {
      $this->tokenService->addTokenData('cart', $event->getCart());
      $this->tokenService->addTokenData('commerce_order_item', $event->getOrderItem());
      $this->tokenService->addTokenData('commerce_order_item_original', $event->getOriginalOrderItem());
    }
    if ($event instanceof OrderItemComparisonFieldsEvent) {
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('commerce_order_item_comparison_fields', $event->getComparisonFields());
      $this->tokenService->addTokenData('commerce_order_item', $event->getOrderItem());
    }
    if ($event instanceof CheckoutCompletionRegisterEvent) {
      $this->tokenService->addTokenData('user', $event->getAccount());
      $this->tokenService->addTokenData('commerce_order', $event->getOrder());
    }
    if ($event instanceof OrderAssignEvent) {
      $this->tokenService->addTokenData('commerce_customer', $event->getCustomer());
      $this->tokenService->addTokenData('commerce_order', $event->getOrder());
    }
    if ($event instanceof OrderEvent) {
      $this->tokenService->addTokenData('commerce_order', $event->getOrder());
    }
    if ($event instanceof OrderItemEvent) {
      $this->tokenService->addTokenData('commerce_order_item', $event->getOrderItem());
    }
    if ($event instanceof OrderLabelEvent) {
      $this->tokenService->addTokenData('commerce_order', $event->getOrder());
      $this->tokenService->addTokenData('commerce_order_label', $event->getLabel());
    }
    if ($event instanceof OrderProfilesEvent) {
      $this->tokenService->addTokenData('commerce_order', $event->getOrder());
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('profile', $event->getProfiles());
    }
    if ($event instanceof FilterPaymentGatewaysEvent) {
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('commerce_order_payment_gateways', $event->getPaymentGateways());
      $this->tokenService->addTokenData('commerce_order', $event->getOrder());
    }
    if ($event instanceof PaymentEvent) {
      $this->tokenService->addTokenData('commerce_payment', $event->getPayment());
    }
    if ($event instanceof NumberFormatDefinitionEvent) {
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('commerce_definition', $event->getDefinition());
    }
    if ($event instanceof FilterVariationsEvent) {
      $this->tokenService->addTokenData('commerce_product', $event->getProduct());
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('commerce_product_variation', $event->getVariations());
    }
    if ($event instanceof ProductAttributeValueEvent) {
      $this->tokenService->addTokenData('commerce_product_attribute_value', $event->getAttributeValue());
    }
    if ($event instanceof ProductDefaultVariationEvent) {
      $this->tokenService->addTokenData('commerce_product', $event->getProduct());
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('commerce_product_variation_default', $event->getDefaultVariation());
    }
    if ($event instanceof ProductEvent) {
      $this->tokenService->addTokenData('commerce_product', $event->getProduct());
    }
    if ($event instanceof ProductVariationAjaxChangeEvent) {
      $this->tokenService->addTokenData('commerce_product_variation', $event->getProductVariation());
      $this->tokenService->addTokenData('commerce_product_variation_response', $event->getResponse());
      $this->tokenService->addTokenData('commerce_product_variation_view_mode', $event->getViewMode());
    }
    if ($event instanceof ProductVariationEvent) {
      $this->tokenService->addTokenData('commerce_product_variation', $event->getProductVariation());
    }
    if ($event instanceof CouponEvent) {
      $this->tokenService->addTokenData('commerce_coupon', $event->getCoupon());
    }
    if ($event instanceof FilterPromotionsEvent) {
      $this->tokenService->addTokenData('commerce_order', $event->getOrder());
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('commerce_promotions', $event->getPromotions());
    }
    if ($event instanceof PromotionEvent) {
      $this->tokenService->addTokenData('commerce_promotion', $event->getPromotion());
    }
    if ($event instanceof StoreEvent) {
      $this->tokenService->addTokenData('commerce_store', $event->getStore());
    }
    if ($event instanceof BuildZonesEvent) {
      $this->tokenService->addTokenData('commerce_store_plugin', $event->getPlugin());
      $this->tokenService->addTokenData('commerce_store_zones', $event->getZones());
    }
    if ($event instanceof CustomerProfileEvent) {
      $this->tokenService->addTokenData('commerce_order_item', $event->getOrderItem());
      // This likely needs some additional work to handle additional methods.
      $this->tokenService->addTokenData('profile', $event->getCustomerProfile());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    foreach (EcaEvent::definitions() as $definition) {
      $events[$definition['event_name']][] = ['onEvent'];
    }
    $events[EcaEvents::BEFORE_INITIAL_EXECUTION][] = [
      'onBeforeInitialExecution',
      -100,
    ];
    return $events;
  }

}
