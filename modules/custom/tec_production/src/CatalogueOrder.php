<?php

namespace Drupal\tec_production;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\views\ViewExecutable;

/**
 * Puts a list of size variations in catalogue order, all four levels of it.
 *
 *   brand    - the delta of field_tec_brands on the customer
 *   product  - field_tec_catalogue_position, dragged on the brand screen
 *   colour   - the delta of field_tec_color_variations on the product
 *   size     - the weight of the term in the tec_sizes vocabulary
 *
 * ## Why in PHP and not in the query
 *
 * Two of the four levels are deltas of a multi-value field, and Views can only
 * reach a delta by joining that field's table a second time. The second join is
 * not tied to the row, so every row comes back once per value in the list:
 * measured on the customer's Products tab, three products turned into six rows.
 * The same trap is written up in tec_crm_ux_views_pre_render(), which fixes the
 * brand level of that tab the same way.
 *
 * Two things make sorting after the query safe here. Neither screen has a pager
 * -- both show every row -- so there is no page being sorted in isolation, and
 * the sort is stable, so whatever the query already got right is kept.
 *
 * ## Why the pro forma needs it
 *
 * The + Order button runs tec_order_eca_create_draft_sales through ECA, which
 * walks the rows in order and creates one line item per row. The order the rows
 * come out in is therefore the order of the lines, and from then on it is
 * frozen: every screen that lists the lines of an order sorts by line id, which
 * is the order they were created in. Re-dragging a brand next month changes the
 * next pro forma, not the ones already raised -- the same rule the prices
 * follow.
 */
final class CatalogueOrder {

  /**
   * Which screens get sorted, and which displays of them.
   *
   * Only the displays whose argument is a customer: the other displays of the
   * draft view take a product id, and reading that as a customer would put the
   * brands in the order of somebody else's card.
   */
  public const SCREENS = [
    'tec_order_eca_create_draft_sales' => ['default', 'customer_size_variations'],
    'tec_products' => ['brand_catalogue'],
  ];

  /**
   * Sorts the result of one of our views, if it is one of ours.
   */
  public static function apply(ViewExecutable $view): void {
    $displays = self::SCREENS[$view->id()] ?? NULL;
    if (!$displays || !in_array($view->current_display, $displays, TRUE)) {
      return;
    }

    // Only the draft view knows a customer, and its argument is that customer.
    // The brand catalogue is one brand and has nobody to ask, so its rows are
    // ordered from the product down.
    $customer = $view->id() === 'tec_order_eca_create_draft_sales'
      ? (int) ($view->args[0] ?? 0)
      : 0;

    self::sort($view, $customer);
  }

  /**
   * Reorders the rows of an executed view.
   */
  public static function sort(ViewExecutable $view, int $customer_id = 0): void {
    if (count($view->result) < 2) {
      return;
    }

    $brands = $customer_id > 0 ? self::brandOrder($customer_id) : [];
    $colour_order = [];
    $keyed = [];

    foreach ($view->result as $index => $row) {
      $size = $row->_entity instanceof FieldableEntityInterface ? $row->_entity : NULL;
      $colour = self::referenced($size, 'field_tec_color_variation');
      $product = self::referenced($size, 'field_tec_product')
        ?: self::referenced($colour, 'field_tec_product');

      $brand = $product instanceof FieldableEntityInterface && $product->hasField(CataloguePosition::BRAND_FIELD)
        ? (int) ($product->get(CataloguePosition::BRAND_FIELD)->target_id ?? 0)
        : 0;

      $colour_place = PHP_INT_MAX;
      if ($product && $colour) {
        $pid = (int) $product->id();
        $colour_order[$pid] ??= self::colourOrder($product);
        $colour_place = $colour_order[$pid][(int) $colour->id()] ?? PHP_INT_MAX;
      }

      // Anything the ERP cannot place goes last instead of first: a product
      // nobody has dragged yet, a brand the customer does not buy, a size
      // variation whose references were never filled in.
      $keyed[] = [
        [
          $brands[$brand] ?? PHP_INT_MAX,
          $product ? (CataloguePosition::of($product) ?: PHP_INT_MAX) : PHP_INT_MAX,
          $colour_place,
          ...self::sizePlace($size),
          $index,
        ],
        $row,
      ];
    }

    usort($keyed, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

    $view->result = [];
    foreach ($keyed as $index => [, $row]) {
      $row->index = $index;
      $view->result[$index] = $row;
    }
  }

  /**
   * The customer's brand order: brand term id => place in their list.
   */
  public static function brandOrder(int $customer_id): array {
    $customer = \Drupal::entityTypeManager()->getStorage('tec_crm')->load($customer_id);
    if (!$customer instanceof FieldableEntityInterface
      || !$customer->hasField('field_tec_brands')) {
      return [];
    }

    $order = [];
    foreach ($customer->get('field_tec_brands') as $delta => $item) {
      $tid = (int) ($item->target_id ?? 0);
      // A brand listed twice keeps its first place, which is the one the
      // owner dragged it to.
      if ($tid && !isset($order[$tid])) {
        $order[$tid] = $delta;
      }
    }

    return $order;
  }

  /**
   * The product's colour order: colour variation id => place in the product.
   */
  public static function colourOrder(EntityInterface $product): array {
    if (!$product instanceof FieldableEntityInterface
      || !$product->hasField('field_tec_color_variations')) {
      return [];
    }

    $order = [];
    foreach ($product->get('field_tec_color_variations') as $delta => $item) {
      $id = (int) ($item->target_id ?? 0);
      if ($id && !isset($order[$id])) {
        $order[$id] = $delta;
      }
    }

    return $order;
  }

  /**
   * Where a size sits: the term's weight, and its id to break a tie.
   */
  protected static function sizePlace(?EntityInterface $size): array {
    $term = self::referenced($size, 'field_tec_size');
    if (!$term) {
      return [PHP_INT_MAX, PHP_INT_MAX];
    }

    return [(int) $term->get('weight')->value, (int) $term->id()];
  }

  /**
   * The entity a single-value reference field points at, or NULL.
   */
  protected static function referenced(?EntityInterface $entity, string $field): ?EntityInterface {
    if (!$entity instanceof FieldableEntityInterface
      || !$entity->hasField($field)
      || $entity->get($field)->isEmpty()) {
      return NULL;
    }

    return $entity->get($field)->entity;
  }

}
