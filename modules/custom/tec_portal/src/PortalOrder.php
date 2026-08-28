<?php

namespace Drupal\tec_portal;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;

/**
 * Whether a sales order is still the customer's to change.
 *
 * Open (draft) is a shopping list. Confirming it does not mean the factory
 * has the payment: that is Accounting Verified, a human step. Confirming
 * only moves the order to Pending payment and takes the pen away from the
 * customer. The proforma email is a later step.
 */
final class PortalOrder {

  public const OPEN = 'draft';

  public const PENDING_DEPOSIT = 'pending_deposit';

  /**
   * Unix time when the factory first marked the deposit as received.
   */
  public const PAID_ON_FIELD = 'field_tec_accounting_verified_on';

  /**
   * What the customer sees, not the ten factory statuses.
   *
   * From the moment Accounting Verified until Ready to ship, one word:
   * Confirmed. Production stages stay inside the factory.
   */
  public const PORTAL_LABELS = [
    self::OPEN => 'Open',
    self::PENDING_DEPOSIT => 'Pending payment',
    'accounting_verified' => 'Confirmed',
    'in_progress_processing' => 'Confirmed',
    'ready_for_production' => 'Confirmed',
    'production_started' => 'Confirmed',
    'quality_control_inspection' => 'Confirmed',
    'completed' => 'Confirmed',
    'ready_for_delivery' => 'Ready to ship',
    'shipped_delivered' => 'Shipped',
    'cancelled' => 'Cancelled',
  ];

  /**
   * The stored status, or Open when the field is empty.
   */
  public static function statusOf(EntityInterface $order): string {
    if (!$order->hasField('field_tec_order_status') || $order->get('field_tec_order_status')->isEmpty()) {
      return self::OPEN;
    }
    return (string) $order->get('field_tec_order_status')->value;
  }

  /**
   * Whether the customer may still change quantities.
   */
  public static function isOpen(EntityInterface $order): bool {
    return self::statusOf($order) === self::OPEN;
  }

  /**
   * Whether this status is still unpaid, for the factory queue's grey rows.
   */
  public static function isUnpaid(string $status): bool {
    return in_array($status, [self::OPEN, self::PENDING_DEPOSIT], TRUE);
  }

  /**
   * Payment seen: Accounting Verified and every production step after it.
   */
  public static function isPaid(string $status): bool {
    return !self::isUnpaid($status) && $status !== 'cancelled';
  }

  /**
   * Stamp the portal Date the first time the factory records the payment.
   *
   * Does not overwrite. Cancelled and still-unpaid orders stay empty.
   */
  public static function stampPaidOn(EntityInterface $order): void {
    if ($order->getEntityTypeId() !== 'tec_order' || $order->bundle() !== 'tec_sales_order') {
      return;
    }
    if (!$order->hasField(self::PAID_ON_FIELD) || !$order->get(self::PAID_ON_FIELD)->isEmpty()) {
      return;
    }
    if (!self::isPaid(self::statusOf($order))) {
      return;
    }
    $was = $order->getOriginal();
    if ($was && self::isPaid(self::statusOf($was))) {
      return;
    }
    $order->set(self::PAID_ON_FIELD, \Drupal::time()->getRequestTime());
  }

  /**
   * When the factory recorded the payment, or NULL if it has not yet.
   */
  public static function paidOn(EntityInterface $order): ?int {
    if ($order->hasField(self::PAID_ON_FIELD) && !$order->get(self::PAID_ON_FIELD)->isEmpty()) {
      return (int) $order->get(self::PAID_ON_FIELD)->value;
    }
    if (!self::isPaid(self::statusOf($order))) {
      return NULL;
    }
    if (!\Drupal::hasService('flag')) {
      return NULL;
    }
    try {
      $flags = \Drupal::service('flag');
      $flag = $flags->getFlagById('tec_order_accounting_verified');
      if (!$flag) {
        return NULL;
      }
      $flagging = $flags->getFlagging($flag, $order);
      return $flagging ? (int) $flagging->getCreatedTime() : NULL;
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * The word the customer sees for this order.
   */
  public static function portalLabel(EntityInterface $order): string {
    $status = self::statusOf($order);
    return self::PORTAL_LABELS[$status] ?? 'Confirmed';
  }

  /**
   * One of the six customer tones, for the coloured pill.
   */
  public static function portalTone(EntityInterface $order): string {
    return match (self::statusOf($order)) {
      self::OPEN => 'open',
      self::PENDING_DEPOSIT => 'pending-payment',
      'ready_for_delivery' => 'ready-to-ship',
      'shipped_delivered' => 'shipped',
      'cancelled' => 'cancelled',
      default => 'confirmed',
    };
  }

  /**
   * Coloured status pill markup.
   */
  public static function statusMarkup(EntityInterface $order): string {
    $tone = self::portalTone($order);
    return '<span class="tec-portal__status tec-portal__status--' . Html::escape($tone) . '">'
      . Html::escape(self::portalLabel($order))
      . '</span>';
  }

}
