<?php

namespace Drupal\tec_inventory;

use Drupal\Core\Entity\EntityInterface;

/**
 * What the materials of a size add up to.
 *
 * The two numbers this multiplies live apart on purpose. How much a size
 * consumes belongs to the size, because two sizes of the same glove eat
 * different amounts of the same sheet. What that consumption is worth belongs to
 * the material, because the day a supplier raises the price of foam it is raised
 * once and not on every size that uses it.
 *
 * The quantity read here is the parsed one and not what was typed. In a BoM
 * people write `1/12` of a sheet, and tec_inventory_bom_item_entity_builder()
 * turns that into 0.0833 when the line is saved. Charging what was typed would
 * bill a twelfth of a sheet as a whole one, which is the difference between a
 * glove that costs sixty-six baht in foam and one that costs eight hundred.
 *
 * It is called material cost and not manufacturing cost because that is all it
 * is: the BoM, at today's consumption costs. No labour, no waste, no freight
 * spread over the run. Somebody reading a screen has to be able to tell what the
 * figure covers from what it is called.
 *
 * Nothing is stored. A figure on the size would have to be recalculated whenever
 * any of its BoM lines changed, and also whenever any material it uses changed
 * price -- and a material sits in many BoMs, so that cascade is wider than it
 * looks and gets one entity wrong quietly. Reading it live is a handful of
 * loads, and the cache tags this returns are what keeps a rendered figure honest
 * when a material's cost moves underneath it.
 */
final class MaterialCost {

  /**
   * The BoM on a size variation.
   */
  public const BOM_FIELD = 'field_tec_bom';

  /**
   * The material a BoM line points at. It is a taxonomy term.
   */
  public const MATERIAL_FIELD = 'field_tec_inventory';

  /**
   * The parsed consumption quantity on a BoM line.
   */
  public const QUANTITY_FIELD = 'field_tec_quantity';

  /**
   * The consumption cost on a material: what one unit of use is worth.
   */
  public const CONSUMPTION_COST_FIELD = 'field_tec_price';

  /**
   * The material cost of one size, or NULL if the question does not apply.
   *
   * Returns the total, how many lines went into it, and the materials that
   * could not be priced. Those are reported rather than counted as zero: a
   * total that quietly leaves a material out reads as a cheap product, and
   * nobody goes looking for a number that looks fine.
   *
   * Rounded once, here, for the same reason. Adding up lines that were each
   * already rounded to the satang and rounding the sum are both defensible and
   * they disagree, so one place decides and every screen repeats it.
   */
  public static function ofSize(EntityInterface $size): ?array {
    if ($size->getEntityTypeId() !== 'tec_product' || $size->bundle() !== 'tec_size_variation') {
      return NULL;
    }
    if (!$size->hasField(self::BOM_FIELD)) {
      return NULL;
    }

    $total = 0.0;
    $lines = 0;
    $priced = 0;
    $unpriced = [];
    $tags = $size->getCacheTags();

    foreach ($size->get(self::BOM_FIELD)->referencedEntities() as $line) {
      $lines++;
      $tags = array_merge($tags, $line->getCacheTags());

      $material = $line->hasField(self::MATERIAL_FIELD)
        ? $line->get(self::MATERIAL_FIELD)->entity
        : NULL;
      if ($material) {
        // A material getting dearer has to be able to throw this figure out of
        // the render cache, and neither the size nor the line has changed.
        $tags = array_merge($tags, $material->getCacheTags());
      }

      $quantity = $line->hasField(self::QUANTITY_FIELD)
        ? $line->get(self::QUANTITY_FIELD)->value
        : NULL;
      $cost = $material && $material->hasField(self::CONSUMPTION_COST_FIELD)
        ? $material->get(self::CONSUMPTION_COST_FIELD)->value
        : NULL;

      if ($quantity === NULL || $quantity === '' || $cost === NULL || $cost === '') {
        $unpriced[] = $material ? $material->label() : (string) $line->id();
        continue;
      }

      $total += (float) $quantity * (float) $cost;
      $priced++;
    }

    return [
      'total' => round($total, 2),
      'lines' => $lines,
      'priced' => $priced,
      'unpriced' => $unpriced,
      'tags' => array_values(array_unique($tags)),
    ];
  }

}
