<?php

namespace Drupal\tec_portal;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_production\BrandGaps;
use Drupal\tec_production\CatalogueOrder;
use Drupal\tec_production\CataloguePosition;
use Drupal\tec_production\LineItemDisplay;

/**
 * The sizes a portal customer may order, in catalogue order.
 *
 * Four levels, the same as a factory pro forma: the company's brand list,
 * the brand's product order, the product's colour list, the size list.
 * Incomplete products (no colour, or a colour with no size) are left out:
 * there is nothing to type a quantity against.
 */
final class Catalogue {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CustomerCompany $companies,
  ) {}

  /**
   * The order grid for this portal login, grouped by brand.
   *
   * @return array<int, array{id: int, name: string, rows: array<int, array>}>
   */
  public function grid(AccountInterface $account): array {
    $company = $this->companies->company($account);
    return $company ? $this->gridForCompany($company) : [];
  }

  /**
   * The order grid for a company card. Factory and portal share this list.
   *
   * @return array<int, array{id: int, name: string, rows: array<int, array>}>
   */
  public function gridForCompany(EntityInterface $company): array {
    $brands = [];
    foreach ($this->companies->brandIdsOf($company) as $tid) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      if (!$term || $term->bundle() !== 'tec_brands') {
        continue;
      }
      $rows = $this->rowsOfBrand($tid);
      if (!$rows) {
        continue;
      }
      $brands[$tid] = [
        'id' => $tid,
        'name' => (string) $term->label(),
        'rows' => $rows,
      ];
    }
    return $brands;
  }

  /**
   * Size ids that belong on this account's grid.
   *
   * @return int[]
   */
  public function sizeIds(AccountInterface $account): array {
    $ids = [];
    foreach ($this->grid($account) as $brand) {
      foreach ($brand['rows'] as $row) {
        $ids[] = $row['size_id'];
      }
    }
    return $ids;
  }

  /**
   * One size row, or NULL if that size is not on this account's grid.
   */
  public function rowForSize(AccountInterface $account, int $size_id): ?array {
    return $this->rowForSizeIn($this->grid($account), $size_id);
  }

  /**
   * One size row on a company's grid, or NULL.
   */
  public function rowForSizeOnCompany(EntityInterface $company, int $size_id): ?array {
    return $this->rowForSizeIn($this->gridForCompany($company), $size_id);
  }

  /**
   * @param array<int, array{rows: array<int, array>}> $grid
   */
  protected function rowForSizeIn(array $grid, int $size_id): ?array {
    foreach ($grid as $brand) {
      foreach ($brand['rows'] as $row) {
        if ($row['size_id'] === $size_id) {
          return $row;
        }
      }
    }
    return NULL;
  }

  /**
   * Rows of one brand, in catalogue order.
   */
  protected function rowsOfBrand(int $brand_tid): array {
    $storage = $this->entityTypeManager->getStorage('tec_product');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_product')
      ->condition(CataloguePosition::BRAND_FIELD, $brand_tid)
      ->execute();
    if (!$ids) {
      return [];
    }

    $products = $storage->loadMultiple($ids);
    uasort($products, static function (EntityInterface $a, EntityInterface $b): int {
      $place_a = CataloguePosition::of($a) ?: PHP_INT_MAX;
      $place_b = CataloguePosition::of($b) ?: PHP_INT_MAX;
      return $place_a <=> $place_b ?: strnatcasecmp(self::productName($a), self::productName($b));
    });

    $rows = [];
    foreach ($products as $product) {
      if (BrandGaps::of($product)) {
        continue;
      }
      $colour_order = CatalogueOrder::colourOrder($product);
      $colours = $product->hasField(BrandGaps::COLOURS)
        ? $product->get(BrandGaps::COLOURS)->referencedEntities()
        : [];
      usort($colours, static function (EntityInterface $a, EntityInterface $b) use ($colour_order): int {
        $pa = $colour_order[(int) $a->id()] ?? PHP_INT_MAX;
        $pb = $colour_order[(int) $b->id()] ?? PHP_INT_MAX;
        return $pa <=> $pb;
      });

      foreach ($colours as $colour) {
        $sizes = $colour->hasField(BrandGaps::SIZES)
          ? $colour->get(BrandGaps::SIZES)->referencedEntities()
          : [];
        usort($sizes, [self::class, 'sortSizes']);
        foreach ($sizes as $size) {
          if ($size->bundle() !== 'tec_size_variation') {
            continue;
          }
          $price = 0.0;
          if ($size->hasField('field_tec_price') && !$size->get('field_tec_price')->isEmpty()) {
            $price = (float) $size->get('field_tec_price')->value;
          }
          $rows[] = [
            'size_id' => (int) $size->id(),
            'product_id' => (int) $product->id(),
            'colour_id' => (int) $colour->id(),
            'product' => self::productName($product),
            'colour' => LineItemDisplay::colorLabel($colour),
            'size' => LineItemDisplay::sizeLabel($size),
            'price' => $price,
            'size_entity' => $size,
            'product_entity' => $product,
            'colour_entity' => $colour,
          ];
        }
      }
    }

    return $rows;
  }

  /**
   * A product's catalogue name.
   */
  public static function productName(EntityInterface $product): string {
    if ($product->hasField('field_product_name')) {
      $name = trim((string) $product->get('field_product_name')->value);
      if ($name !== '') {
        return $name;
      }
    }
    return (string) $product->label();
  }

  /**
   * Size term weight, then term id.
   */
  protected static function sortSizes(EntityInterface $a, EntityInterface $b): int {
    return self::sizePlace($a) <=> self::sizePlace($b);
  }

  /**
   * @return array{0: int, 1: int}
   */
  protected static function sizePlace(EntityInterface $size): array {
    if (!$size->hasField('field_tec_size') || $size->get('field_tec_size')->isEmpty()) {
      return [PHP_INT_MAX, (int) $size->id()];
    }
    $term = $size->get('field_tec_size')->entity;
    if (!$term) {
      return [PHP_INT_MAX, (int) $size->id()];
    }
    return [(int) $term->get('weight')->value, (int) $term->id()];
  }

}
