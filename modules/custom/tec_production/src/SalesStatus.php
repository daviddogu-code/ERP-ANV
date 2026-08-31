<?php

namespace Drupal\tec_production;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Factory sales-order statuses: names, colours, and the forward-only click.
 *
 * Open is sealed with Confirm, not with this map. The rest of the plant
 * clicks one step at a time on the queue or the order card. Accounting
 * Verified and Quality Control are not statuses; leftover values are
 * rewritten to Processing and On production.
 */
final class SalesStatus {

  public const OPEN = 'draft';

  public const PENDING_PAYMENT = 'pending_deposit';

  public const PROCESSING = 'in_progress_processing';

  public const READY_FOR_PRODUCTION = 'ready_for_production';

  public const ON_PRODUCTION = 'production_started';

  public const COMPLETED = 'completed';

  public const READY_FOR_COLLECTION = 'ready_for_delivery';

  public const SHIPPED = 'shipped_delivered';

  public const CANCELLED = 'cancelled';

  /**
   * Stored value => factory label.
   */
  public const LABELS = [
    self::OPEN => 'Open',
    self::PENDING_PAYMENT => 'Pending payment',
    self::PROCESSING => 'Processing',
    self::READY_FOR_PRODUCTION => 'Ready for production',
    self::ON_PRODUCTION => 'On production',
    self::COMPLETED => 'Completed',
    self::READY_FOR_COLLECTION => 'Ready for collection',
    self::SHIPPED => 'Shipped',
    self::CANCELLED => 'Cancelled',
  ];

  /**
   * Values still allowed on the field, in display order.
   */
  public const ALLOWED = [
    self::OPEN,
    self::PENDING_PAYMENT,
    self::PROCESSING,
    self::READY_FOR_PRODUCTION,
    self::ON_PRODUCTION,
    self::COMPLETED,
    self::READY_FOR_COLLECTION,
    self::SHIPPED,
    self::CANCELLED,
  ];

  /**
   * Still on /o/queue (not Shipped, not Cancelled).
   */
  public const ACTIVE = [
    self::OPEN,
    self::PENDING_PAYMENT,
    self::PROCESSING,
    self::READY_FOR_PRODUCTION,
    self::ON_PRODUCTION,
    self::COMPLETED,
    self::READY_FOR_COLLECTION,
  ];

  /**
   * On the queue but not eating manufacturing capacity.
   */
  public const POST_MFG = [
    self::COMPLETED,
    self::READY_FOR_COLLECTION,
  ];

  /**
   * Forward-only. Open is sealed with Confirm, not from here.
   */
  public const NEXT = [
    self::PENDING_PAYMENT => self::PROCESSING,
    self::PROCESSING => self::READY_FOR_PRODUCTION,
    self::READY_FOR_PRODUCTION => self::ON_PRODUCTION,
    self::ON_PRODUCTION => self::COMPLETED,
    self::COMPLETED => self::READY_FOR_COLLECTION,
    self::READY_FOR_COLLECTION => self::SHIPPED,
  ];

  /**
   * Old stored values rewritten onto the map above.
   */
  public const MIGRATE = [
    'accounting_verified' => self::PROCESSING,
    'quality_control_inspection' => self::ON_PRODUCTION,
  ];

  /**
   * The stored status, Open when empty, migrated when the old names linger.
   */
  public static function of(EntityInterface $order): string {
    if (!$order->hasField('field_tec_order_status') || $order->get('field_tec_order_status')->isEmpty()) {
      return self::OPEN;
    }
    $status = (string) $order->get('field_tec_order_status')->value;
    return self::MIGRATE[$status] ?? $status;
  }

  /**
   * Factory label for a stored (or migrated) value.
   */
  public static function label(string $status): string {
    $status = self::MIGRATE[$status] ?? $status;
    return self::LABELS[$status] ?? $status;
  }

  /**
   * Coloured factory pill.
   */
  public static function pill(string $status): array {
    $status = self::MIGRATE[$status] ?? $status;
    $safe = preg_replace('/[^a-z0-9_]/', '', $status) ?: 'draft';
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => self::label($status),
      '#attributes' => [
        'class' => ['tec-so-status', 'tec-so-status--' . $safe],
      ],
      '#attached' => ['library' => ['tec_production/sales_status']],
    ];
  }

  /**
   * The next factory status, or NULL at the end (and from Open).
   */
  public static function next(string $status): ?string {
    $status = self::MIGRATE[$status] ?? $status;
    return self::NEXT[$status] ?? NULL;
  }

  /**
   * Advance one step. Returns the new status, or NULL if there is no next.
   */
  public static function applyNext(EntityInterface $order): ?string {
    $next = self::next(self::of($order));
    if ($next === NULL) {
      return NULL;
    }
    $order->set('field_tec_order_status', $next);
    $order->save();
    return $next;
  }

  /**
   * Card control that used to be the Accounting Verified flag.
   */
  public static function advanceBuild(EntityInterface $order): array {
    $next = self::next(self::of($order));
    if ($next === NULL) {
      return ['#access' => FALSE];
    }
    $label = self::label($next);
    return [
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Update to @status', ['@status' => $label]),
      '#url' => Url::fromRoute('tec_production.sales_advance', [
        'tec_order' => $order->id(),
      ]),
      '#attributes' => [
        'class' => ['tec-so-advance'],
        'title' => (string) new TranslatableMarkup('Advance to @status (forward only)', [
          '@status' => $label,
        ]),
      ],
      '#attached' => ['library' => ['tec_production/sales_status']],
      '#cache' => [
        'tags' => $order->getCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Allowed values in the shape FieldStorageConfig expects.
   *
   * @return array<string, string>
   */
  public static function allowedValues(): array {
    return self::LABELS;
  }

}
