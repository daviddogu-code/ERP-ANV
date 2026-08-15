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
 *
 * Since receiving exists (15 August 2026) "coming in" means what is still
 * outstanding, not what was ordered, and it is derived from the lines every
 * time it is asked for. There is deliberately no field anywhere holding it.
 */
final class Purchasing {

  /**
   * The purchase order status that still brings stock in.
   *
   * A draft that nobody has sent to the supplier should not count as incoming
   * stock either, which is why this is a whitelist of one and not "anything
   * that is not closed".
   */
  public const INCOMING_STATUS = 'open';

  /**
   * The purchase order status that brings nothing else in.
   *
   * Set when no line has anything outstanding, whether because everything
   * arrived or because what was missing got written off. Which of the two it
   * was is read off the lines; see receiptState().
   */
  public const CLOSED_STATUS = 'closed';

  /**
   * What one purchase order line still has to bring, in purchase units.
   *
   * This is the whole "on order" idea in one place, and it is deliberately a
   * subtraction rather than a stored number: an ERP that lets a human type how
   * much is still coming ends up with a figure nobody can reconcile against the
   * orders. Odoo and SAP both derive it the same way.
   *
   * Zero when the line was closed short. Never negative: an over-delivery means
   * the supplier sent more than was ordered, not that the warehouse owes them
   * the difference back.
   */
  public static function outstanding($line): float {
    if ($line->hasField('field_tec_no_more_expected') && !empty($line->get('field_tec_no_more_expected')->value)) {
      return 0.0;
    }
    $ordered = $line->hasField('field_tec_quantity') && !$line->get('field_tec_quantity')->isEmpty()
      ? (float) $line->get('field_tec_quantity')->value
      : 0.0;
    $received = $line->hasField('field_tec_quantity_received') && !$line->get('field_tec_quantity_received')->isEmpty()
      ? (float) $line->get('field_tec_quantity_received')->value
      : 0.0;

    return max(0.0, $ordered - $received);
  }

  /**
   * How far along a purchase order is, worked out from its lines.
   *
   * Not stored anywhere on purpose. Two statuses live in the database, open and
   * closed, and this is the nuance on top: storing it as a third status would
   * be a second version of the truth, and the two would drift the first time
   * someone fixed a quantity by hand.
   *
   * @return string
   *   One of 'none' (open, nothing has arrived), 'partial' (open, something
   *   has), 'full' (nothing outstanding and something arrived) or 'cancelled'
   *   (nothing outstanding and nothing ever arrived).
   */
  public static function receiptState($order): string {
    $outstanding = 0.0;
    $received = 0.0;
    foreach ($order->get('field_tec_line_items')->referencedEntities() as $line) {
      $outstanding += self::outstanding($line);
      $received += $line->hasField('field_tec_quantity_received') && !$line->get('field_tec_quantity_received')->isEmpty()
        ? (float) $line->get('field_tec_quantity_received')->value
        : 0.0;
    }

    if ($outstanding > 0.0) {
      return $received > 0.0 ? 'partial' : 'none';
    }
    return $received > 0.0 ? 'full' : 'cancelled';
  }

  /**
   * Quantity still to come per material, in purchase units.
   *
   * Until 15 August 2026 this returned the quantity ordered, which was the same
   * number until the day a delivery arrived -- and there was no way to record
   * that a delivery had arrived, so it stayed the same number forever.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param int[] $tids
   *   Material term ids to look up.
   *
   * @return float[]
   *   Quantity outstanding keyed by material term id. Materials with nothing
   *   coming are absent, not zero.
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
      $pending = self::outstanding($line);
      // Nothing outstanding must leave the material out of the map rather than
      // put a zero in it, because callers read a present key as "something is
      // coming" and would show a 0 where a dash belongs.
      if ($pending <= 0.0) {
        continue;
      }
      $tid = (int) $line->get('field_tec_inventory')->target_id;
      $map[$tid] = ($map[$tid] ?? 0.0) + $pending;
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
