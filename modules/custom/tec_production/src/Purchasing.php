<?php

namespace Drupal\tec_production;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Purchasing arithmetic shared by the Stock Control board and the Purchase List.
 *
 * Both screens have to answer the same two questions -- how much of a material
 * is already coming in, and how many inventory units fit in a purchase unit --
 * and they have to answer them the same way. When /stock and /purchase disagree
 * about what is on order, one of them tells you to buy something twice.
 */
final class Purchasing {

  /**
   * The purchase order status that counts as "coming in".
   *
   * field_tec_po_status has exactly one allowed value today. A draft that
   * nobody has sent to the supplier should not count as incoming stock, so the
   * day a second status appears this constant is the place to decide it.
   */
  public const INCOMING_STATUS = 'open';

  /**
   * Quantity already on order per material, in purchase units.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param int[] $tids
   *   Material term ids to look up.
   *
   * @return float[]
   *   Quantity on order keyed by material term id. Materials with nothing on
   *   order are absent, not zero.
   */
  public static function onOrder(EntityTypeManagerInterface $entityTypeManager, array $tids): array {
    $map = [];
    if (!$tids) {
      return $map;
    }

    $storage = $entityTypeManager->getStorage('tec_line_item');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_po_line_item')
      ->condition('field_tec_inventory', $tids, 'IN')
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return $map;
    }

    foreach ($storage->loadMultiple($ids) as $line) {
      $order = $line->hasField('field_tec_order') ? $line->get('field_tec_order')->entity : NULL;
      // A line with no order behind it is an orphan, not an order in flight.
      if (!$order || $order->bundle() !== 'tec_purchase_order') {
        continue;
      }
      $status = $order->hasField('field_tec_po_status') ? $order->get('field_tec_po_status')->value : NULL;
      if ($status !== self::INCOMING_STATUS) {
        continue;
      }
      $tid = (int) $line->get('field_tec_inventory')->target_id;
      $quantity = $line->hasField('field_tec_quantity') && !$line->get('field_tec_quantity')->isEmpty()
        ? (float) $line->get('field_tec_quantity')->value
        : 0.0;
      $map[$tid] = ($map[$tid] ?? 0.0) + $quantity;
    }

    return $map;
  }

  /**
   * A conversion factor, falling back to 1.
   *
   * Every material is supposed to carry both factors since Oscar's optional
   * unit system was removed, and the factor is 1 when the unit does not change.
   * An empty or zero factor would divide by zero, so it is read as that 1.
   */
  public static function factor($entity, string $field): float {
    if ($entity->hasField($field) && !$entity->get($field)->isEmpty()) {
      $value = (float) $entity->get($field)->value;
      if ($value > 0.0) {
        return $value;
      }
    }
    return 1.0;
  }

}
