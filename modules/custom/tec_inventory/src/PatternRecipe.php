<?php

namespace Drupal\tec_inventory;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\tec_inventory\Entity\Pattern;

/**
 * Live recipe of a catalogue size that hangs from a factory pattern.
 *
 * Greens and yellow quantities live on the pattern. Which leather SKU fills a
 * yellow hole lives on the colour. Stickers live on each size. A size
 * without a pattern, or with holes still empty, keeps exploding its own BoM.
 */
final class PatternRecipe {

  public const PATTERN_FIELD = 'field_tec_factory_pattern';

  public const HOLES_FIELD = 'field_tec_pattern_holes';

  public const EXTRAS_FIELD = 'field_tec_pattern_extras';

  public const PRODUCTS_LIST_TAG = 'tec_pattern_products';

  /**
   * The product a colour or size hangs from, or the product itself.
   */
  public static function productOf(EntityInterface $entity): ?FieldableEntityInterface {
    if ($entity->getEntityTypeId() !== 'tec_product' || !$entity instanceof FieldableEntityInterface) {
      return NULL;
    }
    if ($entity->bundle() === 'tec_product') {
      return $entity;
    }
    if ($entity->hasField('field_tec_product') && !$entity->get('field_tec_product')->isEmpty()) {
      $product = $entity->get('field_tec_product')->entity;
      if ($product instanceof FieldableEntityInterface && $product->bundle() === 'tec_product') {
        return $product;
      }
    }
    $id = _tec_inventory_product_above($entity);
    if (!$id) {
      return NULL;
    }
    $product = \Drupal::entityTypeManager()->getStorage('tec_product')->load($id);
    return $product instanceof FieldableEntityInterface ? $product : NULL;
  }

  /**
   * The colour a size hangs from.
   */
  public static function colorOf(EntityInterface $size): ?FieldableEntityInterface {
    if ($size->getEntityTypeId() !== 'tec_product' || $size->bundle() !== 'tec_size_variation') {
      return NULL;
    }
    if (!$size instanceof FieldableEntityInterface) {
      return NULL;
    }
    $color_id = 0;
    if ($size->hasField('field_tec_color_variation') && !$size->get('field_tec_color_variation')->isEmpty()) {
      $color_id = (int) $size->get('field_tec_color_variation')->target_id;
    }
    elseif ($size->id()) {
      $color_id = (int) _tec_inventory_who_lists((int) $size->id(), 'field_tec_size_variations');
    }
    if ($color_id <= 0) {
      return NULL;
    }
    $color = \Drupal::entityTypeManager()->getStorage('tec_product')->load($color_id);
    return $color instanceof FieldableEntityInterface ? $color : NULL;
  }

  public static function patternOf(EntityInterface $entity): ?Pattern {
    $product = self::productOf($entity);
    if (!$product || !$product->hasField(self::PATTERN_FIELD) || $product->get(self::PATTERN_FIELD)->isEmpty()) {
      return NULL;
    }
    $pattern = $product->get(self::PATTERN_FIELD)->entity;
    return $pattern instanceof Pattern ? $pattern : NULL;
  }

  /**
   * The product chose a pattern. Orders still wait for the yellow holes.
   */
  public static function hasPattern(EntityInterface $entity): bool {
    return self::patternOf($entity) instanceof Pattern;
  }

  /**
   * Catalogue products that hang from this pattern.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface[]
   */
  public static function productsOf(Pattern $pattern): array {
    $ids = self::productIdsOf([$pattern->id() ? (int) $pattern->id() : 0]);
    $pattern_id = (int) $pattern->id();
    $found = $ids[$pattern_id] ?? [];
    if (!$found) {
      return [];
    }
    $storage = \Drupal::entityTypeManager()->getStorage('tec_product');
    $products = [];
    foreach ($storage->loadMultiple($found) as $product) {
      if ($product instanceof FieldableEntityInterface && $product->bundle() === 'tec_product') {
        $products[] = $product;
      }
    }
    usort($products, static function (FieldableEntityInterface $a, FieldableEntityInterface $b): int {
      $brand = strnatcasecmp(self::productBrand($a), self::productBrand($b));
      if ($brand !== 0) {
        return $brand;
      }
      $name = strnatcasecmp(self::productTitle($a), self::productTitle($b));
      return $name !== 0 ? $name : ((int) $a->id() <=> (int) $b->id());
    });
    return $products;
  }

  /**
   * How many catalogue products hang from each pattern.
   *
   * @param int[] $pattern_ids
   *
   * @return array<int, int>
   */
  public static function productCounts(array $pattern_ids): array {
    $counts = [];
    foreach ($pattern_ids as $id) {
      $counts[(int) $id] = 0;
    }
    foreach (self::productIdsOf($pattern_ids) as $pattern_id => $ids) {
      $counts[$pattern_id] = count($ids);
    }
    return $counts;
  }

  /**
   * @param int[] $pattern_ids
   *
   * @return array<int, int[]>
   */
  public static function productIdsOf(array $pattern_ids): array {
    $pattern_ids = array_values(array_filter(array_map('intval', $pattern_ids)));
    $grouped = [];
    foreach ($pattern_ids as $id) {
      $grouped[$id] = [];
    }
    if (!$pattern_ids) {
      return $grouped;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('tec_product');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_product')
      ->condition(self::PATTERN_FIELD, $pattern_ids, 'IN')
      ->execute();
    if (!$ids) {
      return $grouped;
    }
    foreach ($storage->loadMultiple($ids) as $product) {
      if (!$product instanceof FieldableEntityInterface || !$product->hasField(self::PATTERN_FIELD)) {
        continue;
      }
      $pid = (int) $product->get(self::PATTERN_FIELD)->target_id;
      if (isset($grouped[$pid])) {
        $grouped[$pid][] = (int) $product->id();
      }
    }
    return $grouped;
  }

  public static function productTitle(FieldableEntityInterface $product): string {
    if ($product->hasField('field_product_name')) {
      $name = trim((string) $product->get('field_product_name')->value);
      if ($name !== '') {
        return $name;
      }
    }
    return trim((string) $product->label());
  }

  public static function productBrand(FieldableEntityInterface $product): string {
    if (!$product->hasField('field_tec_brand') || $product->get('field_tec_brand')->isEmpty()) {
      return '';
    }
    $brand = $product->get('field_tec_brand')->entity;
    return $brand instanceof TermInterface ? (string) $brand->label() : '';
  }

  public static function productsTag(int $pattern_id): string {
    return 'tec_pattern:' . $pattern_id . ':products';
  }

  /**
   * @param int[] $pattern_ids
   */
  public static function invalidateProductLists(array $pattern_ids): void {
    $tags = [self::PRODUCTS_LIST_TAG];
    foreach (array_unique(array_filter(array_map('intval', $pattern_ids))) as $id) {
      $tags[] = self::productsTag($id);
    }
    Cache::invalidateTags($tags);
  }

  /**
   * Size term id stored on a size variation.
   */
  public static function sizeTermId(EntityInterface $size): int {
    if (!$size instanceof FieldableEntityInterface || !$size->hasField('field_tec_size') || $size->get('field_tec_size')->isEmpty()) {
      return 0;
    }
    return (int) $size->get('field_tec_size')->target_id;
  }

  /**
   * This size may explode from the pattern: greens plus every yellow on this size filled.
   */
  public static function wired(EntityInterface $size): bool {
    if ($size->getEntityTypeId() !== 'tec_product' || $size->bundle() !== 'tec_size_variation') {
      return FALSE;
    }
    $pattern = self::patternOf($size);
    if (!$pattern) {
      return FALSE;
    }
    $sid = self::sizeTermId($size);
    if ($sid <= 0 || !in_array($sid, $pattern->sizeIds(), TRUE)) {
      return FALSE;
    }
    $color = self::colorOf($size);
    if (!$color) {
      return FALSE;
    }
    $holes = self::holesOf($color);
    foreach (self::yellowTypesOnSize($pattern, $sid) as $type_id) {
      if (empty($holes[$type_id])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Type term ids used as yellow cells on one size of the pattern.
   *
   * @return int[]
   */
  public static function yellowTypesOnSize(Pattern $pattern, int $size_id): array {
    $ids = [];
    foreach ($pattern->lines() as $line) {
      $cell = $line['cells'][$size_id] ?? NULL;
      if (($cell['kind'] ?? '') === 'type' && (int) ($cell['target_id'] ?? 0) > 0) {
        $ids[(int) $cell['target_id']] = (int) $cell['target_id'];
      }
    }
    return array_values($ids);
  }

  /**
   * Unique yellow types on the whole pattern, for the colour mapping table.
   *
   * @return int[]
   */
  public static function yellowTypes(Pattern $pattern): array {
    $ids = [];
    foreach ($pattern->sizeIds() as $sid) {
      foreach (self::yellowTypesOnSize($pattern, $sid) as $tid) {
        $ids[$tid] = $tid;
      }
    }
    return array_values($ids);
  }

  /**
   * @return array<int, int> type tid => inventory SKU tid
   */
  public static function holesOf(EntityInterface $color): array {
    if (!$color instanceof FieldableEntityInterface || !$color->hasField(self::HOLES_FIELD)) {
      return [];
    }
    $raw = trim((string) $color->get(self::HOLES_FIELD)->value);
    if ($raw === '') {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    if (!is_array($decoded)) {
      return [];
    }
    $holes = [];
    foreach ($decoded as $type_id => $sku_id) {
      $type_id = (int) $type_id;
      $sku_id = (int) $sku_id;
      if ($type_id > 0 && $sku_id > 0) {
        $holes[$type_id] = $sku_id;
      }
    }
    return $holes;
  }

  /**
   * @param array<int, int> $holes
   */
  public static function setHoles(FieldableEntityInterface $color, array $holes): void {
    if (!$color->hasField(self::HOLES_FIELD)) {
      return;
    }
    $clean = [];
    foreach ($holes as $type_id => $sku_id) {
      $type_id = (int) $type_id;
      $sku_id = (int) $sku_id;
      if ($type_id > 0 && $sku_id > 0) {
        $clean[(string) $type_id] = $sku_id;
      }
    }
    $color->set(self::HOLES_FIELD, $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : '');
  }

  /**
   * Stickers on a size: colour extra lines, the size's own list, or leftover.
   *
   * @return array<int, array{target_id: int, qty: string}>
   */
  public static function extrasOfSize(EntityInterface $size): array {
    $color = self::colorOf($size);
    $term = self::sizeTermId($size);
    if ($color && self::hasExtraLines($color) && $term > 0) {
      $out = [];
      foreach (self::extraLinesOf($color) as $line) {
        $cell = $line['cells'][$term] ?? NULL;
        $tid = (int) ($cell['target_id'] ?? 0);
        if ($tid > 0) {
          $out[] = [
            'target_id' => $tid,
            'qty' => trim((string) ($cell['qty'] ?? '')),
          ];
        }
      }
      return $out;
    }
    if ($size instanceof FieldableEntityInterface && $size->hasField(self::EXTRAS_FIELD) && !$size->get(self::EXTRAS_FIELD)->isEmpty()) {
      return self::extrasOf($size);
    }
    return $color ? self::extrasOf($color) : [];
  }

  /**
   * Colour JSON is the aligned extra lines (one row across sizes).
   */
  public static function hasExtraLines(EntityInterface $color): bool {
    if (!$color instanceof FieldableEntityInterface || !$color->hasField(self::EXTRAS_FIELD) || $color->get(self::EXTRAS_FIELD)->isEmpty()) {
      return FALSE;
    }
    $decoded = json_decode(trim((string) $color->get(self::EXTRAS_FIELD)->value), TRUE);
    return self::isExtraLinesShape($decoded);
  }

  /**
   * Extra rows for the ficha: stored colour lines, or lifted from each size.
   *
   * @return array<int, array{cells: array<int, array{target_id: int, qty: string}>}>
   */
  public static function extraLinesOf(FieldableEntityInterface $color): array {
    if ($color->hasField(self::EXTRAS_FIELD) && !$color->get(self::EXTRAS_FIELD)->isEmpty()) {
      $decoded = json_decode(trim((string) $color->get(self::EXTRAS_FIELD)->value), TRUE);
      if (self::isExtraLinesShape($decoded)) {
        return self::pruneEmptyExtraLines(self::normalizeExtraLines($decoded));
      }
    }
    $sizes = self::sizesInPatternOrder($color);
    $per_term = [];
    $max = 0;
    foreach ($sizes as $size) {
      $term = self::sizeTermId($size);
      if ($term <= 0) {
        continue;
      }
      $list = self::extrasOf($size);
      $per_term[$term] = $list;
      $max = max($max, count($list));
    }
    if ($max === 0) {
      $legacy = self::extrasOf($color);
      if (!$legacy) {
        return [];
      }
      $lines = [];
      foreach ($legacy as $extra) {
        $cells = [];
        foreach (array_keys($per_term) ?: array_map([self::class, 'sizeTermId'], $sizes) as $term) {
          $term = (int) $term;
          if ($term > 0) {
            $cells[$term] = $extra;
          }
        }
        $lines[] = ['cells' => $cells];
      }
      return self::pruneEmptyExtraLines($lines);
    }
    $lines = [];
    for ($i = 0; $i < $max; $i++) {
      $cells = [];
      foreach ($per_term as $term => $list) {
        if (isset($list[$i])) {
          $cells[(int) $term] = $list[$i];
        }
      }
      $lines[] = ['cells' => $cells];
    }
    return self::pruneEmptyExtraLines($lines);
  }

  /**
   * @param array<int, array{cells?: array<int|string, array{target_id?: int, qty?: string}>}> $lines
   */
  public static function setExtraLines(FieldableEntityInterface $color, array $lines): void {
    if (!$color->hasField(self::EXTRAS_FIELD)) {
      return;
    }
    $clean = self::normalizeExtraLines($lines);
    $color->set(self::EXTRAS_FIELD, $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : '');
  }

  /**
   * Write colour lines and explode filled cells onto each size.
   *
   * @param array<int, array{cells?: array<int|string, array{target_id?: int, qty?: string}>}> $lines
   */
  public static function applyExtraLines(FieldableEntityInterface $color, array $lines): void {
    $clean = self::pruneEmptyExtraLines(self::normalizeExtraLines($lines));
    self::setExtraLines($color, $clean);
    $color->save();
    $fresh = \Drupal::entityTypeManager()->getStorage('tec_product')->loadUnchanged($color->id());
    if ($fresh instanceof FieldableEntityInterface) {
      $color = $fresh;
    }
    foreach (self::sizesInPatternOrder($color) as $size) {
      $term = self::sizeTermId($size);
      $flat = [];
      if ($term > 0) {
        foreach ($clean as $line) {
          $cell = $line['cells'][$term] ?? NULL;
          $tid = (int) ($cell['target_id'] ?? 0);
          if ($tid > 0) {
            $flat[] = [
              'target_id' => $tid,
              'qty' => trim((string) ($cell['qty'] ?? '')),
            ];
          }
        }
      }
      self::setExtras($size, $flat);
      $size->save();
    }
  }

  /**
   * @param mixed $decoded
   */
  private static function isExtraLinesShape($decoded): bool {
    if (!is_array($decoded) || $decoded === []) {
      return FALSE;
    }
    $first = reset($decoded);
    return is_array($first) && array_key_exists('cells', $first);
  }

  /**
   * @param mixed $lines
   *
   * @return array<int, array{cells: array<int, array{target_id: int, qty: string}>}>
   */
  private static function normalizeExtraLines($lines): array {
    if (!is_array($lines)) {
      return [];
    }
    $clean = [];
    foreach ($lines as $line) {
      if (!is_array($line)) {
        continue;
      }
      $cells = [];
      foreach ($line['cells'] ?? [] as $term => $cell) {
        if (!is_numeric((string) $term) || !is_array($cell)) {
          continue;
        }
        $cells[(int) $term] = [
          'target_id' => (int) ($cell['target_id'] ?? 0),
          'qty' => trim((string) ($cell['qty'] ?? '')),
        ];
      }
      $clean[] = ['cells' => $cells];
    }
    return $clean;
  }

  /**
   * Drop extra lines that have no SKU on any size.
   *
   * @param array<int, array{cells: array<int, array{target_id: int, qty: string}>}> $lines
   *
   * @return array<int, array{cells: array<int, array{target_id: int, qty: string}>}>
   */
  private static function pruneEmptyExtraLines(array $lines): array {
    $kept = [];
    foreach ($lines as $line) {
      $has = FALSE;
      foreach ($line['cells'] ?? [] as $cell) {
        if ((int) ($cell['target_id'] ?? 0) > 0) {
          $has = TRUE;
          break;
        }
      }
      if ($has) {
        $kept[] = $line;
      }
    }
    return $kept;
  }

  /**
   * Create every pattern size still missing on this colour.
   */
  public static function ensureSizes(FieldableEntityInterface $color): void {
    static $busy = FALSE;
    if ($busy) {
      return;
    }
    if ($color->getEntityTypeId() !== 'tec_product' || $color->bundle() !== 'tec_color_variation' || !$color->id()) {
      return;
    }
    $pattern = self::patternOf($color);
    $product = self::productOf($color);
    if (!$pattern || !$product || !$color->hasField('field_tec_size_variations')) {
      return;
    }
    $used = self::usedSizeTermIds($color);
    $missing = [];
    foreach ($pattern->sizeIds() as $sid) {
      if (!in_array($sid, $used, TRUE)) {
        $missing[] = $sid;
      }
    }
    if (!$missing) {
      return;
    }
    $busy = TRUE;
    try {
      $storage = \Drupal::entityTypeManager()->getStorage('tec_product');
      $created = [];
      foreach ($missing as $sid) {
        $term = Term::load($sid);
        $size = $storage->create([
          'type' => 'tec_size_variation',
          'title' => $term ? $term->label() : (string) $sid,
          'status' => 1,
          'field_tec_size' => ['target_id' => $sid],
          'field_tec_color_variation' => ['target_id' => $color->id()],
          'field_tec_product' => ['target_id' => $product->id()],
        ]);
        $size->save();
        $created[] = (int) $size->id();
      }
      $fresh = $storage->loadUnchanged($color->id());
      $refs = $fresh instanceof FieldableEntityInterface
        ? $fresh->get('field_tec_size_variations')->getValue()
        : [];
      $have = [];
      foreach ($refs as $item) {
        $id = (int) ($item['target_id'] ?? 0);
        if ($id > 0) {
          $have[$id] = TRUE;
        }
      }
      $changed = FALSE;
      foreach ($created as $id) {
        if ($id > 0 && !isset($have[$id])) {
          $refs[] = ['target_id' => $id];
          $have[$id] = TRUE;
          $changed = TRUE;
        }
      }
      $color->set('field_tec_size_variations', $refs);
      if ($changed) {
        $color->save();
      }
    }
    finally {
      $busy = FALSE;
    }
  }

  /**
   * Sizes on a colour, in the pattern's size order.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface[]
   */
  public static function sizesInPatternOrder(FieldableEntityInterface $color): array {
    if (!$color->hasField('field_tec_size_variations')) {
      return [];
    }
    $by_term = [];
    foreach ($color->get('field_tec_size_variations')->referencedEntities() as $size) {
      $tid = self::sizeTermId($size);
      if ($tid > 0) {
        $by_term[$tid] = $size;
      }
    }
    $pattern = self::patternOf($color);
    $ids = $pattern ? $pattern->sizeIds() : array_keys($by_term);
    $out = [];
    foreach ($ids as $tid) {
      if (isset($by_term[$tid])) {
        $out[] = $by_term[$tid];
      }
    }
    return $out;
  }

  /**
   * @return array<int, array{target_id: int, qty: string}>
   */
  public static function extrasOf(EntityInterface $color): array {
    if (!$color instanceof FieldableEntityInterface || !$color->hasField(self::EXTRAS_FIELD)) {
      return [];
    }
    $raw = trim((string) $color->get(self::EXTRAS_FIELD)->value);
    if ($raw === '') {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    if (!is_array($decoded)) {
      return [];
    }
    $extras = [];
    foreach ($decoded as $row) {
      if (!is_array($row)) {
        continue;
      }
      $tid = (int) ($row['target_id'] ?? 0);
      $qty = trim((string) ($row['qty'] ?? ''));
      if ($tid > 0) {
        $extras[] = ['target_id' => $tid, 'qty' => $qty];
      }
    }
    return $extras;
  }

  /**
   * @param array<int, array{target_id?: int, qty?: string}> $extras
   */
  public static function setExtras(FieldableEntityInterface $color, array $extras): void {
    if (!$color->hasField(self::EXTRAS_FIELD)) {
      return;
    }
    $clean = [];
    foreach ($extras as $row) {
      $tid = (int) ($row['target_id'] ?? 0);
      $qty = trim((string) ($row['qty'] ?? ''));
      if ($tid > 0) {
        $clean[] = ['target_id' => $tid, 'qty' => $qty];
      }
    }
    $color->set(self::EXTRAS_FIELD, $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : '');
  }

  /**
   * Inventory SKUs whose Material Type is this yellow type.
   *
   * @return array<int, string> tid => label
   */
  public static function skusOfType(int $type_id): array {
    if ($type_id <= 0) {
      return [];
    }
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_inventory')
      ->condition('field_tec_material_type', $type_id)
      ->sort('name')
      ->execute();
    if (!$ids) {
      return [];
    }
    $options = [];
    foreach ($storage->loadMultiple($ids) as $term) {
      $options[(int) $term->id()] = (string) $term->label();
    }
    return $options;
  }

  /**
   * Assembled rows for one size: greens, filled yellows, extras.
   *
   * Unfilled yellows are included with material NULL so the ficha can still
   * show the type. Explode and cost skip those.
   *
   * @return array<int, array{source: string, material: ?TermInterface, type: ?TermInterface, qty: string, parsed: ?float, uou: string, cost: ?float}>
   */
  public static function linesOfSize(EntityInterface $size): array {
    $pattern = self::patternOf($size);
    if (!$pattern) {
      return [];
    }
    $sid = self::sizeTermId($size);
    if ($sid <= 0) {
      return [];
    }
    $color = self::colorOf($size);
    $holes = $color ? self::holesOf($color) : [];
    $rows = [];
    foreach ($pattern->lines() as $line) {
      $cell = $line['cells'][$sid] ?? NULL;
      if (!$cell || (int) ($cell['target_id'] ?? 0) <= 0) {
        continue;
      }
      $qty = trim((string) ($cell['qty'] ?? ''));
      if (($cell['kind'] ?? '') === 'type') {
        $type = Term::load((int) $cell['target_id']);
        $sku_id = $holes[(int) $cell['target_id']] ?? 0;
        $sku = $sku_id ? Term::load($sku_id) : NULL;
        $rows[] = self::row('yellow', $sku instanceof TermInterface ? $sku : NULL, $type instanceof TermInterface ? $type : NULL, $qty);
      }
      else {
        $sku = Term::load((int) $cell['target_id']);
        $rows[] = self::row('green', $sku instanceof TermInterface ? $sku : NULL, NULL, $qty);
      }
    }
    foreach (self::extrasOfSize($size) as $extra) {
      $sku = Term::load($extra['target_id']);
      $rows[] = self::row('blue', $sku instanceof TermInterface ? $sku : NULL, NULL, $extra['qty']);
    }
    return $rows;
  }

  /**
   * Same shape as MaterialCost::ofSize, from the assembled recipe.
   *
   * @return array{total: float, lines: int, priced: int, unpriced: string[], tags: string[]}|null
   */
  public static function costOfSize(EntityInterface $size): ?array {
    $pattern = self::patternOf($size);
    if (!$pattern) {
      return NULL;
    }
    $total = 0.0;
    $lines = 0;
    $priced = 0;
    $unpriced = [];
    $tags = $size->getCacheTags();
    $tags = array_merge($tags, $pattern->getCacheTags());
    $color = self::colorOf($size);
    if ($color) {
      $tags = array_merge($tags, $color->getCacheTags());
    }

    foreach (self::linesOfSize($size) as $row) {
      if ($row['source'] === 'yellow' && !$row['material']) {
        $unpriced[] = $row['type'] ? (string) $row['type']->label() : 'type';
        $lines++;
        continue;
      }
      if (!$row['material']) {
        continue;
      }
      $lines++;
      $tags = array_merge($tags, $row['material']->getCacheTags());
      if ($row['parsed'] === NULL || $row['cost'] === NULL) {
        $unpriced[] = (string) $row['material']->label();
        continue;
      }
      $total += $row['parsed'] * $row['cost'];
      $priced++;
    }

    if ($lines === 0) {
      return NULL;
    }

    return [
      'total' => round($total, 2),
      'lines' => $lines,
      'priced' => $priced,
      'unpriced' => $unpriced,
      'tags' => array_values(array_unique($tags)),
    ];
  }

  /**
   * Rows that explode onto an order: real SKUs only.
   *
   * @return array<int, array{material: TermInterface, qty: string, parsed: float}>
   */
  public static function explodeLines(EntityInterface $size): array {
    $out = [];
    foreach (self::linesOfSize($size) as $row) {
      if (!$row['material'] || $row['parsed'] === NULL) {
        continue;
      }
      $out[] = [
        'material' => $row['material'],
        'qty' => $row['qty'] !== '' ? $row['qty'] : (string) $row['parsed'],
        'parsed' => $row['parsed'],
      ];
    }
    return $out;
  }

  /**
   * Size term ids already used on this colour, optionally skipping one size entity.
   *
   * @return int[]
   */
  public static function usedSizeTermIds(EntityInterface $color, int $except_size_id = 0): array {
    $ids = [];
    if (!$color instanceof FieldableEntityInterface || !$color->hasField('field_tec_size_variations')) {
      return $ids;
    }
    foreach ($color->get('field_tec_size_variations')->referencedEntities() as $size) {
      if ($except_size_id && (int) $size->id() === $except_size_id) {
        continue;
      }
      $sid = self::sizeTermId($size);
      if ($sid > 0) {
        $ids[$sid] = $sid;
      }
    }
    return array_values($ids);
  }

  /**
   * Bill of Materials table that matches the product size card.
   *
   * Same columns, Bootstrap styles, view wrapper (subtle small) and cost foot
   * as embed_7. Row colours and the pattern line are extra and stay.
   *
   * @param bool $with_label
   *   FALSE when this sits inside the existing viewfield (label already above).
   * @param bool $with_extras
   *   FALSE on the ficha when extras are editable inputs in these tables.
   */
  public static function tableBuild(EntityInterface $size, bool $with_label = TRUE, bool $with_extras = TRUE): array {
    $blank = "\u{00A0}";
    $left = ['views-align-left'];
    $right = ['views-align-right', 'text-end'];
    $rows = [];
    foreach (self::linesOfSize($size) as $row) {
      if (!$with_extras && ($row['source'] ?? '') === 'blue') {
        continue;
      }
      $item = $row['material']
        ? (string) $row['material']->label()
        : ($row['type'] ? (string) $row['type']->label() : '');
      $cost = self::formatLineCost($row['parsed'], $row['cost']);
      $class = 'tec-recipe--' . $row['source'];
      $alias = $row['source'] === 'yellow' ? 'tec-recipe--type' : ($row['source'] === 'green' ? 'tec-recipe--material' : 'tec-recipe--extra');
      $line = [
        'no_striping' => TRUE,
        'class' => [$class, $alias],
        'data' => [
          ['data' => $item !== '' ? $item : $blank, 'class' => $left],
          ['data' => $row['qty'] !== '' ? $row['qty'] : $blank, 'class' => $right],
          ['data' => $row['uou'] !== '' ? $row['uou'] : $blank, 'class' => $left],
          ['data' => $cost !== '' ? $cost : $blank, 'class' => $right],
        ],
      ];
      if ($row['material'] && $row['parsed'] !== NULL && $row['cost'] !== NULL) {
        $line['data-tec-line-cost'] = (string) round($row['parsed'] * $row['cost'], 2);
      }
      $rows[] = $line;
    }

    $color = self::colorOf($size);
    $editable = !$with_extras
      && $color instanceof FieldableEntityInterface
      && $color->id()
      && $size->access('update');
    if ($editable) {
      $term_id = self::sizeTermId($size);
      foreach (self::extraLinesOf($color) as $i => $line) {
        $cell = $term_id > 0 ? ($line['cells'][$term_id] ?? ['target_id' => 0, 'qty' => '']) : ['target_id' => 0, 'qty' => ''];
        $tid = (int) ($cell['target_id'] ?? 0);
        $sku = $tid > 0 ? Term::load($tid) : NULL;
        $assembled = self::row('blue', $sku instanceof TermInterface ? $sku : NULL, NULL, (string) ($cell['qty'] ?? ''));
        $extra_cost = self::formatLineCost($assembled['parsed'], $assembled['cost']);
        $extra = [
          'no_striping' => TRUE,
          'class' => ['tec-recipe--blue', 'tec-recipe--extra', 'tec-ficha-extra'],
          'data-tec-extra-line' => (string) $i,
          'data' => [
            ['data' => self::extraSkuWidget($i, $term_id, $sku instanceof TermInterface ? $sku : NULL, (int) $size->id()), 'class' => array_merge($left, ['tec-ficha-extra-sku-cell'])],
            ['data' => self::extraQtyInput($i, $term_id, (string) ($cell['qty'] ?? '')), 'class' => $right],
            ['data' => $assembled['uou'] !== '' ? $assembled['uou'] : $blank, 'class' => array_merge($left, ['tec-ficha-extra-uou'])],
            ['data' => $extra_cost !== '' ? $extra_cost : $blank, 'class' => array_merge($right, ['tec-ficha-extra-cost'])],
          ],
        ];
        if ($assembled['uou'] !== '') {
          $extra['data-tec-uou'] = $assembled['uou'];
        }
        if ($assembled['cost'] !== NULL) {
          $extra['data-tec-unit-price'] = (string) $assembled['cost'];
        }
        if ($assembled['material'] && $assembled['parsed'] !== NULL && $assembled['cost'] !== NULL) {
          $extra['data-tec-line-cost'] = (string) round($assembled['parsed'] * $assembled['cost'], 2);
        }
        $rows[] = $extra;
      }
      $ordered = self::sizesInPatternOrder($color);
      $first = $ordered && (int) reset($ordered)->id() === (int) $size->id();
      if ($first) {
        $rows[] = [
          'no_striping' => TRUE,
          'class' => ['tec-ficha-extra-add-row'],
          'data' => [
            [
              'data' => [
                '#type' => 'container',
                '#attributes' => ['class' => ['tec-ficha-extra-add-wrap']],
                'add' => [
                  '#type' => 'html_tag',
                  '#tag' => 'button',
                  '#value' => t('Add extra'),
                  '#attributes' => [
                    'type' => 'button',
                    'class' => ['button', 'button--small', 'tec-ficha-extra-add'],
                  ],
                ],
                'status' => [
                  '#type' => 'html_tag',
                  '#tag' => 'span',
                  '#value' => '',
                  '#attributes' => [
                    'class' => ['tec-ficha-extra-status'],
                    'aria-live' => 'polite',
                    'aria-atomic' => 'true',
                  ],
                ],
              ],
              'class' => $left,
            ],
            ['data' => $blank, 'class' => $right],
            ['data' => $blank, 'class' => $left],
            ['data' => $blank, 'class' => $right],
          ],
        ];
      }
    }

    $wrap = ['tec-size-card__recipe', 'subtle', 'small'];
    if (!$with_label) {
      $wrap[] = 'views-element-container';
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => $wrap],
    ];
    if ($with_label) {
      $build['label'] = [
        '#markup' => '<div class="field__label">' . t('Bill of Materials') . '</div>',
      ];
    }
    $term = self::sizeTermId($size);
    $table_attrs = [
      'class' => [
        'table',
        'tec-size-card__bom',
        'table-sm',
        'table-bordered',
        'table-hover',
        'cols-4',
      ],
    ];
    if ($term > 0) {
      $table_attrs['data-tec-size-term'] = (string) $term;
    }
    if ($size->id()) {
      $table_attrs['data-tec-size-id'] = (string) $size->id();
    }
    if ($editable && $color) {
      $table_attrs['data-tec-color-id'] = (string) $color->id();
      $table_attrs['data-tec-extras-save'] = Url::fromRoute('tec_inventory.color_extras', [
        'tec_product' => $color->id(),
      ])->toString();
      $table_attrs['data-autocomplete-path'] = Url::fromRoute('tec_inventory.sku_autocomplete')->toString();
    }
    $build['bom'] = [
      '#theme' => 'table',
      '#header' => [
        ['data' => t('Material'), 'class' => $left],
        ['data' => t('Requires'), 'class' => $right],
        ['data' => t('UoU'), 'class' => $left],
        ['data' => t('Cost'), 'class' => $right],
      ],
      '#rows' => $rows,
      '#empty' => t('This pattern has no recipe for this size yet.'),
      '#responsive' => FALSE,
      '#attributes' => $table_attrs,
    ];

    $sums = self::costOfSize($size);
    if ($sums && $sums['lines'] > 0) {
      $build['cost'] = self::costFoot($sums);
    }

    $pattern = self::patternOf($size);
    if ($pattern) {
      $url = Url::fromRoute('entity.tec_pattern.canonical', ['tec_pattern' => $pattern->code()]);
      $build['pattern'] = [
        '#markup' => '<p class="tec-recipe-pattern">'
          . t('Pattern @code. Greens come from the pattern.', ['@code' => $pattern->code()])
          . ' ' . Link::fromTextAndUrl(t('Open pattern'), $url)->toString()
          . '</p>',
      ];
    }

    $tags = $size->getCacheTags();
    if ($pattern) {
      $tags = array_merge($tags, $pattern->getCacheTags());
    }
    $color = self::colorOf($size);
    if ($color) {
      $tags = array_merge($tags, $color->getCacheTags());
    }
    $build['#cache']['tags'] = array_values(array_unique($tags));
    $build['#attached']['library'][] = 'tec_inventory/pattern';
    if ($editable) {
      $build['#cache']['max-age'] = 0;
      $build['#attached']['library'][] = 'tec_inventory/ficha_extras';
      $build['#attached']['drupalSettings']['tecInventory']['csrfToken'] = \Drupal::csrfToken()->get(\Drupal\Core\Access\CsrfRequestHeaderAccessCheck::TOKEN_KEY);
      $build['#attached']['drupalSettings']['tecInventory']['skuMetaPath'] = Url::fromRoute('tec_inventory.sku_meta')->toString();
    }
    return $build;
  }

  /**
   * Unit of use and consumption price of an inventory SKU.
   *
   * @return array{uou: string, price: float|null}
   */
  public static function skuMeta(?TermInterface $sku): array {
    if (!$sku instanceof TermInterface) {
      return ['uou' => '', 'price' => NULL];
    }
    $row = self::row('blue', $sku, NULL, '1');
    return [
      'uou' => $row['uou'],
      'price' => $row['cost'],
    ];
  }

  /**
   * SKU on the ficha: wrapping name when picked, autocomplete while editing.
   */
  private static function extraSkuWidget(int $line, int $term, ?TermInterface $sku, int $size_id = 0): array {
    $value = '';
    $label = '';
    if ($sku) {
      $label = (string) $sku->label();
      $value = $label . ' (' . $sku->id() . ')';
    }
    $picked = $sku instanceof TermInterface;
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'tec-ficha-extra-sku',
          $picked ? 'is-picked' : 'is-editing',
        ],
      ],
      'name' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $label !== '' ? $label : '',
        '#attributes' => [
          'type' => 'button',
          'class' => ['tec-ficha-extra-sku-name'],
          'aria-label' => (string) t('Change SKU'),
        ],
      ],
      'input' => [
        '#type' => 'html_tag',
        '#tag' => 'input',
        '#attributes' => [
          'type' => 'text',
          'id' => 'tec-ficha-extra-sku-' . $size_id . '-' . $line,
          'class' => ['form-text', 'form-autocomplete', 'tec-ficha-extra-sku-input'],
          'value' => $value,
          'size' => 16,
          'autocomplete' => 'off',
          'aria-label' => (string) t('SKU'),
          'tabindex' => $picked ? '-1' : '0',
          'data-tec-extra-line' => (string) $line,
          'data-tec-size-term' => (string) $term,
          'data-autocomplete-path' => Url::fromRoute('tec_inventory.sku_autocomplete')->toString(),
        ],
      ],
    ];
  }

  /**
   * Requires field for one extra cell on the ficha.
   */
  private static function extraQtyInput(int $line, int $term, string $qty): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'input',
      '#attributes' => [
        'type' => 'text',
        'class' => ['form-text', 'tec-ficha-extra-qty-input'],
        'value' => $qty,
        'size' => 6,
        'autocomplete' => 'off',
        'aria-label' => (string) t('Requires'),
        'data-tec-extra-line' => (string) $line,
        'data-tec-size-term' => (string) $term,
      ],
    ];
  }

  /**
   * Same foot as MaterialCostTotal on a size that has no pattern.
   *
   * @param array{total: float, lines: int, priced: int, unpriced: string[], tags: string[]} $sums
   */
  private static function costFoot(array $sums): array {
    $rows = [[
      'class' => ['tec-material-cost__row--total'],
      'data' => [
        [
          'data' => t('Material cost'),
          'class' => ['tec-material-cost__label'],
        ],
        [
          'data' => "\u{0E3F} " . number_format($sums['total'], 2),
          'class' => ['tec-material-cost__amount'],
        ],
      ],
    ]];
    if ($sums['unpriced']) {
      $rows[] = [
        'class' => ['tec-material-cost__row--warning'],
        'data' => [
          [
            'data' => \Drupal::translation()->formatPlural(
              count($sums['unpriced']),
              'One material has no cost yet and is not in this total: @which',
              '@count materials have no cost yet and are not in this total: @which',
              ['@which' => implode(', ', $sums['unpriced'])]
            ),
            'class' => ['tec-material-cost__note'],
            'colspan' => 2,
          ],
        ],
      ];
    }
    return [
      '#theme' => 'table',
      '#rows' => $rows,
      '#attributes' => ['class' => ['tec-material-cost']],
      '#attached' => ['library' => ['tec_inventory/material_cost']],
      '#cache' => ['tags' => $sums['tags']],
    ];
  }

  private static function formatLineCost(?float $qty, ?float $price): string {
    if ($qty === NULL || $price === NULL) {
      return '';
    }
    return "\u{0E3F} " . number_format($qty * $price, 2);
  }

  /**
   * @return array{source: string, material: ?TermInterface, type: ?TermInterface, qty: string, parsed: ?float, uou: string, cost: ?float}
   */
  private static function row(string $source, ?TermInterface $material, ?TermInterface $type, string $qty): array {
    $parsed = $qty === '' ? NULL : _tec_inventory_parse_quantity_formula($qty);
    $uou = '';
    $cost = NULL;
    if ($material) {
      if ($material->hasField('field_tec_unit_use') && !$material->get('field_tec_unit_use')->isEmpty()) {
        $unit = $material->get('field_tec_unit_use')->entity;
        $uou = $unit instanceof TermInterface ? (string) $unit->label() : '';
      }
      if ($material->hasField('field_tec_price') && !$material->get('field_tec_price')->isEmpty()) {
        $cost = (float) $material->get('field_tec_price')->value;
      }
    }
    return [
      'source' => $source,
      'material' => $material,
      'type' => $type,
      'qty' => $qty,
      'parsed' => $parsed,
      'uou' => $uou,
      'cost' => $cost,
    ];
  }

}
