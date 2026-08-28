<?php

namespace Drupal\tec_production;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;

/**
 * The place a product holds inside its brand.
 *
 * Four things decide the order of a pro forma, and this is the second:
 *
 *   brand    - the delta of field_tec_brands on the customer
 *   product  - this, dragged on the brand's Organize products screen
 *   colour   - the delta of field_tec_color_variations on the product
 *   size     - the weight of the term in the tec_sizes vocabulary
 *
 * Until 19 August 2026 the product order belonged to the customer: it was
 * dragged on /tec_crm/N/reorder and kept in the draggableviews table, one row
 * per customer and product. Eight customers meant eight orders of the same
 * catalogue and none of them was any use to the pro forma, because the view
 * that creates the lines runs for one customer and cannot read another one's
 * order. The number now lives on the product, where every screen can read it.
 *
 * ## Why the number is written straight into its table
 *
 * Saving a product wakes up "TEC Product: Set references on sub-entities"
 * (process_dxgyftk), which walks every colour variation and every size
 * variation of the product, saves each one, and writes two lines in the log.
 * Measured on a product with two colours: 0.2 - 1.1 s per save. Dragging a
 * brand with twenty products would then take twenty seconds and leave forty
 * log lines behind, all for a number that no automatism reads.
 *
 * So this class writes the field table directly - 0.005 s per product - and
 * invalidates the cache tags by hand, which is the part an entity save would
 * have done. It is safe here and nowhere else: the position is not business
 * data, nothing computes anything from it, and no ECA model listens to it.
 * Anything with meaning goes through the entity, always.
 */
final class CataloguePosition {

  /**
   * The field, its table and its column.
   */
  public const FIELD = 'field_tec_catalogue_position';
  public const TABLE = 'tec_product__field_tec_catalogue_position';
  public const COLUMN = 'field_tec_catalogue_position_value';

  /**
   * The brand a product hangs from.
   */
  public const BRAND_FIELD = 'field_tec_brand';
  public const BRAND_TABLE = 'tec_product__field_tec_brand';
  public const BRAND_COLUMN = 'field_tec_brand_target_id';

  /**
   * The place a product holds, or 0 when it has none yet.
   */
  public static function of(EntityInterface $product): int {
    if (!$product->hasField(self::FIELD)) {
      return 0;
    }
    return (int) $product->get(self::FIELD)->value;
  }

  /**
   * The first free place in a brand.
   *
   * New products go to the end of the list: arriving in the middle of an order
   * somebody dragged on purpose would be worse than arriving last.
   */
  public static function nextFor(int $brand_tid): int {
    if ($brand_tid < 1) {
      return 1;
    }
    $query = \Drupal::database()->select(self::BRAND_TABLE, 'b');
    $query->leftJoin(self::TABLE, 'p', 'p.entity_id = b.entity_id AND p.deleted = 0');
    $query->condition('b.deleted', 0);
    $query->condition('b.' . self::BRAND_COLUMN, $brand_tid);
    $query->addExpression('MAX(p.' . self::COLUMN . ')', 'top');
    $top = (int) $query->execute()->fetchField();

    return $top + 1;
  }

  /**
   * Writes places, and says how many products actually moved.
   *
   * @param array $positions
   *   Product id => place, 1 upwards. Whatever already sits in its place is
   *   left alone.
   */
  public static function write(array $positions): int {
    if (!$positions) {
      return 0;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('tec_product');
    $products = $storage->loadMultiple(array_keys($positions));
    $db = \Drupal::database();
    $moved = [];

    foreach ($products as $pid => $product) {
      if ($product->bundle() !== 'tec_product' || !$product->hasField(self::FIELD)) {
        continue;
      }
      $place = (int) $positions[$pid];
      if ($place < 1 || self::of($product) === $place) {
        continue;
      }
      $db->merge(self::TABLE)
        ->keys([
          'entity_id' => (int) $pid,
          'deleted' => 0,
          'delta' => 0,
          'langcode' => $product->language()->getId(),
        ])
        ->fields([
          'bundle' => $product->bundle(),
          // The product is not revisionable, so its revision is itself. The
          // existing rows say the same.
          'revision_id' => (int) $pid,
          self::COLUMN => $place,
        ])
        ->execute();
      $moved[] = (int) $pid;
    }

    if ($moved) {
      $tags = array_map(static fn(int $id): string => 'tec_product:' . $id, $moved);
      // Every list of products is tagged with this one, and the order is
      // exactly what changed.
      $tags[] = 'tec_product_list';
      Cache::invalidateTags($tags);
      $storage->resetCache($moved);
    }

    return count($moved);
  }

}
