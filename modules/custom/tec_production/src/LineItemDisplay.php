<?php

namespace Drupal\tec_production;

/**
 * Shared display helpers for sales order line items.
 *
 * Single source of truth for how a line item resolves its color variation,
 * color / size / material labels. Used by the Daily Production Log form and
 * the Production Report so both always agree with the order page views.
 */
final class LineItemDisplay {

  /**
   * Resolve the color variation of a line item.
   *
   * field_tec_color_variation on the line item is never populated in practice
   * (0 of ~2000 rows), so follow the canonical chain used by the order-page
   * view: line item -> size variation -> field_tec_color_variation on the
   * size variation. The direct field is still checked first in case it is
   * ever filled.
   */
  public static function colorVariation($line) {
    if ($line->hasField('field_tec_color_variation') && !$line->get('field_tec_color_variation')->isEmpty()) {
      return $line->get('field_tec_color_variation')->entity;
    }
    $size = $line->hasField('field_tec_size_variation') ? $line->get('field_tec_size_variation')->entity : NULL;
    if ($size && $size->hasField('field_tec_color_variation') && !$size->get('field_tec_color_variation')->isEmpty()) {
      return $size->get('field_tec_color_variation')->entity;
    }
    return NULL;
  }

  /**
   * Prefer taxonomy color name over variation entity title.
   */
  public static function colorLabel($color): string {
    if (!$color) {
      return '—';
    }
    if ($color->hasField('field_tec_colors') && !$color->get('field_tec_colors')->isEmpty()) {
      $names = [];
      foreach ($color->get('field_tec_colors')->referencedEntities() as $term) {
        $names[] = $term->label();
      }
      if ($names) {
        return implode(' / ', $names);
      }
    }
    return $color->label();
  }

  /**
   * Prefer size taxonomy term over size-variation entity title.
   */
  public static function sizeLabel($size): string {
    if (!$size) {
      return '—';
    }
    if ($size->hasField('field_tec_size') && !$size->get('field_tec_size')->isEmpty()) {
      $term = $size->get('field_tec_size')->entity;
      if ($term) {
        return $term->label();
      }
    }
    return $size->label();
  }

  /**
   * Material label from the main product.
   *
   * field_tec_product_material is a list_string (options) field, so map the
   * stored key to its human-readable label (leather -> Leather, etc.).
   */
  public static function materialLabel($product): string {
    if (!$product || !$product->hasField('field_tec_product_material') || $product->get('field_tec_product_material')->isEmpty()) {
      return '—';
    }
    $items = $product->get('field_tec_product_material');
    $allowed = $items->getFieldDefinition()->getFieldStorageDefinition()->getSetting('allowed_values');
    $labels = [];
    foreach ($items as $item) {
      $labels[] = (string) ($allowed[$item->value] ?? $item->value);
    }
    return $labels ? implode(' / ', $labels) : '—';
  }

}
