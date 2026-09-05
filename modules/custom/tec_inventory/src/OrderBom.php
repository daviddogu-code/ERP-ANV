<?php

namespace Drupal\tec_inventory;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * The sales-line BoM snapshot: a photo of the size BoM when the line is born.
 *
 * Catalogue and /purchase keep today's figures. A tec_order does not. Quantity,
 * per-piece required and consumption cost are written here once (or again only
 * when the size itself changes) and the order screens read this copy.
 */
final class OrderBom {

  public const LINE_BOM_FIELD = 'field_tec_line_item_bom';

  public const SIZE_FIELD = 'field_tec_size_variation';

  public const QTY_FIELD = 'field_tec_quantity';

  public const SIZE_BOM_FIELD = 'field_tec_bom';

  public const MATERIAL_FIELD = 'field_tec_inventory';

  public const PER_FIELD = 'field_tec_quantity_per';

  public const INPUT_FIELD = 'field_tec_quantity_input';

  public const COST_FIELD = 'field_tec_consumption_cost';

  public const CATALOGUE_BOM_FIELD = 'field_tec_bom_item';

  /**
   * Line ids whose snapshot is being written, to skip nested saves.
   *
   * @var array<int, true>
   */
  private static array $busy = [];

  /**
   * What a sales-line save should do to its snapshot, decided in presave.
   *
   * ECA still saves the line again to write the sales total. That nested save
   * clears getOriginal() on the same object, so hook_entity_update can no
   * longer tell a quantity change from a size change. The plan is taken here
   * once, while the previous size and quantity are still known.
   *
   * @var array<int, 'explode'|'rescale'|null>
   */
  private static array $pending = [];

  /**
   * Remember explode vs rescale before nested saves wipe the previous entity.
   */
  public static function remember(EntityInterface $line): void {
    if (!self::isSalesLine($line) || $line->isNew()) {
      return;
    }
    $id = (int) $line->id();
    if ($id <= 0 || isset(self::$busy[$id]) || array_key_exists($id, self::$pending)) {
      return;
    }
    $original = $line->getOriginal();
    if (!$original instanceof EntityInterface) {
      self::$pending[$id] = NULL;
      return;
    }
    $had = self::snapshotIds($line) || self::snapshotIds($original);
    $size_changed = self::targetId($original, self::SIZE_FIELD) !== self::targetId($line, self::SIZE_FIELD);
    $qty_changed = self::qty($original) !== self::qty($line);
    if (!$had || $size_changed) {
      self::$pending[$id] = 'explode';
    }
    elseif ($qty_changed) {
      self::$pending[$id] = 'rescale';
    }
    else {
      self::$pending[$id] = NULL;
    }
  }

  /**
   * Keep the line's snapshot in step with size and quantity.
   */
  public static function sync(EntityInterface $line): void {
    if (!self::isSalesLine($line)) {
      return;
    }
    $id = (int) $line->id();
    if ($id <= 0 || isset(self::$busy[$id])) {
      return;
    }

    $plan = NULL;
    if (array_key_exists($id, self::$pending)) {
      $plan = self::$pending[$id];
      unset(self::$pending[$id]);
    }

    $size_id = self::targetId($line, self::SIZE_FIELD);
    if ($size_id <= 0) {
      return;
    }

    $had = self::snapshotIds($line);
    self::$busy[$id] = TRUE;
    try {
      // A new line has no presave plan. An update whose previous size and
      // quantity were already read uses that plan, even if a nested save has
      // since cleared getOriginal().
      if ($plan === 'explode' || ($plan === NULL && !$had)) {
        self::explode($line);
      }
      elseif ($plan === 'rescale') {
        self::rescale($line);
      }
    }
    finally {
      unset(self::$busy[$id]);
    }
  }

  /**
   * Replace the snapshot with the size BoM as it is right now.
   *
   * A size wired to a pattern explodes greens + filled yellows + extras. A size
   * with a pattern but holes still empty gets an empty photo, not the leftover
   * size BoM. Legacy catalogue sizes keep exploding field_tec_bom.
   */
  public static function explode(EntityInterface $line): void {
    if (!self::isSalesLine($line) || !self::fieldsReady()) {
      return;
    }
    $size = $line->get(self::SIZE_FIELD)->entity;
    if (!$size) {
      self::replaceSnapshots($line, []);
      return;
    }
    if (PatternRecipe::wired($size)) {
      self::explodePattern($line, $size);
      return;
    }
    if (PatternRecipe::hasPattern($size) || !$size->hasField(self::SIZE_BOM_FIELD)) {
      self::replaceSnapshots($line, []);
      return;
    }

    $line_qty = self::qty($line);
    $created = [];
    $storage = \Drupal::entityTypeManager()->getStorage('tec_inventory');
    foreach ($size->get(self::SIZE_BOM_FIELD)->referencedEntities() as $catalogue) {
      $material = $catalogue->hasField(self::MATERIAL_FIELD)
        ? $catalogue->get(self::MATERIAL_FIELD)->entity
        : NULL;
      if (!$material) {
        continue;
      }
      $per = self::perPiece($catalogue);
      $input = self::inputOf($catalogue, $per);
      $cost = self::materialCost($material);
      $item = $storage->create([
        'type' => 'tec_line_item_bom_item',
        'title' => $material->label(),
        'status' => 1,
        self::MATERIAL_FIELD => ['target_id' => $material->id()],
        self::CATALOGUE_BOM_FIELD => ['target_id' => $catalogue->id()],
        self::PER_FIELD => self::dec($per, 4),
        self::INPUT_FIELD => $input,
        self::QTY_FIELD => self::dec($per * $line_qty, 4),
        self::COST_FIELD => $cost,
      ]);
      $item->save();
      $created[] = $item;
    }
    self::replaceSnapshots($line, $created);
  }

  /**
   * Snapshot from the live pattern recipe. No pointer back to a size BoM row.
   */
  private static function explodePattern(EntityInterface $line, EntityInterface $size): void {
    $line_qty = self::qty($line);
    $created = [];
    $storage = \Drupal::entityTypeManager()->getStorage('tec_inventory');
    foreach (PatternRecipe::explodeLines($size) as $row) {
      $material = $row['material'];
      $per = $row['parsed'];
      $item = $storage->create([
        'type' => 'tec_line_item_bom_item',
        'title' => $material->label(),
        'status' => 1,
        self::MATERIAL_FIELD => ['target_id' => $material->id()],
        self::PER_FIELD => self::dec($per, 4),
        self::INPUT_FIELD => $row['qty'],
        self::QTY_FIELD => self::dec($per * $line_qty, 4),
        self::COST_FIELD => self::materialCost($material),
      ]);
      $item->save();
      $created[] = $item;
    }
    self::replaceSnapshots($line, $created);
  }

  /**
   * Multiply frozen per-piece required by the line quantity. Do not re-read the size.
   */
  public static function rescale(EntityInterface $line): void {
    if (!self::isSalesLine($line) || !self::fieldsReady()) {
      return;
    }
    $line_qty = self::qty($line);
    foreach ($line->get(self::LINE_BOM_FIELD)->referencedEntities() as $item) {
      if (!$item->hasField(self::PER_FIELD) || $item->get(self::PER_FIELD)->isEmpty()) {
        continue;
      }
      $per = (float) $item->get(self::PER_FIELD)->value;
      $item->set(self::QTY_FIELD, self::dec($per * $line_qty, 4));
      $item->save();
    }
  }

  /**
   * Fill per-piece, typed required and consumption cost on snapshots that lack them.
   *
   * Existing orders have no 1 March history. Today's catalogue figures are what
   * can be written; after this they stop following the master.
   */
  public static function backfillExisting(): int {
    if (!self::fieldsReady()) {
      return 0;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('tec_inventory');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_line_item_bom_item')
      ->execute();
    if (!$ids) {
      return 0;
    }
    $n = 0;
    foreach ($storage->loadMultiple($ids) as $item) {
      if (self::backfillOne($item)) {
        $item->save();
        $n++;
      }
    }
    return $n;
  }

  private static function backfillOne(EntityInterface $item): bool {
    $dirty = FALSE;
    $per = $item->hasField(self::PER_FIELD) && !$item->get(self::PER_FIELD)->isEmpty()
      ? (float) $item->get(self::PER_FIELD)->value
      : NULL;
    $total = $item->hasField(self::QTY_FIELD) && !$item->get(self::QTY_FIELD)->isEmpty()
      ? (float) $item->get(self::QTY_FIELD)->value
      : NULL;
    $line_qty = self::parentLineQty($item);

    if ($per === NULL) {
      $catalogue = $item->hasField(self::CATALOGUE_BOM_FIELD)
        ? $item->get(self::CATALOGUE_BOM_FIELD)->entity
        : NULL;
      if ($catalogue) {
        $per = self::perPiece($catalogue);
      }
      elseif ($total !== NULL && $line_qty > 0) {
        $per = $total / $line_qty;
      }
      if ($per !== NULL && $item->hasField(self::PER_FIELD)) {
        $item->set(self::PER_FIELD, self::dec($per, 4));
        $dirty = TRUE;
      }
    }

    if ($item->hasField(self::INPUT_FIELD) && $item->get(self::INPUT_FIELD)->isEmpty()) {
      $catalogue = $item->hasField(self::CATALOGUE_BOM_FIELD)
        ? $item->get(self::CATALOGUE_BOM_FIELD)->entity
        : NULL;
      $input = $catalogue ? self::inputOf($catalogue, $per ?? 0.0) : ($per !== NULL ? self::dec($per, 4) : '');
      if ($input !== '') {
        $item->set(self::INPUT_FIELD, $input);
        $dirty = TRUE;
      }
    }

    if ($item->hasField(self::COST_FIELD) && $item->get(self::COST_FIELD)->isEmpty()) {
      $material = $item->hasField(self::MATERIAL_FIELD)
        ? $item->get(self::MATERIAL_FIELD)->entity
        : NULL;
      $cost = self::materialCost($material);
      if ($cost !== NULL) {
        $item->set(self::COST_FIELD, $cost);
        $dirty = TRUE;
      }
    }

    return $dirty;
  }

  private static function parentLineQty(EntityInterface $item): float {
    $ids = \Drupal::entityTypeManager()->getStorage('tec_line_item')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'tec_sales_order_line_item')
      ->condition(self::LINE_BOM_FIELD, $item->id())
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return 0.0;
    }
    $line = \Drupal::entityTypeManager()->getStorage('tec_line_item')->load(reset($ids));
    return $line ? self::qty($line) : 0.0;
  }

  private static function replaceSnapshots(EntityInterface $line, array $items): void {
    $old_ids = self::snapshotIds($line);
    $new_ids = [];
    foreach ($items as $item) {
      $new_ids[] = ['target_id' => $item->id()];
    }
    $line->set(self::LINE_BOM_FIELD, $new_ids);
    $line->save();
    $keep = array_map(static fn($item) => (int) $item->id(), $items);
    $drop = array_diff($old_ids, $keep);
    if ($drop) {
      $storage = \Drupal::entityTypeManager()->getStorage('tec_inventory');
      foreach ($storage->loadMultiple($drop) as $old) {
        $old->delete();
      }
    }
  }

  private static function snapshotIds(EntityInterface $line): array {
    if (!$line->hasField(self::LINE_BOM_FIELD)) {
      return [];
    }
    $ids = [];
    foreach ($line->get(self::LINE_BOM_FIELD)->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    return $ids;
  }

  private static function isSalesLine(EntityInterface $line): bool {
    return $line instanceof FieldableEntityInterface
      && $line->getEntityTypeId() === 'tec_line_item'
      && $line->bundle() === 'tec_sales_order_line_item'
      && $line->hasField(self::LINE_BOM_FIELD)
      && $line->hasField(self::SIZE_FIELD)
      && $line->hasField(self::QTY_FIELD);
  }

  private static function fieldsReady(): bool {
    $storage = \Drupal::entityTypeManager()->getStorage('tec_inventory');
    try {
      $probe = $storage->create(['type' => 'tec_line_item_bom_item']);
    }
    catch (\Throwable $e) {
      return FALSE;
    }
    return $probe->hasField(self::PER_FIELD)
      && $probe->hasField(self::INPUT_FIELD)
      && $probe->hasField(self::COST_FIELD);
  }

  private static function qty(EntityInterface $line): float {
    if (!$line->hasField(self::QTY_FIELD) || $line->get(self::QTY_FIELD)->isEmpty()) {
      return 0.0;
    }
    return (float) $line->get(self::QTY_FIELD)->value;
  }

  private static function targetId(EntityInterface $entity, string $field): int {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return 0;
    }
    return (int) $entity->get($field)->target_id;
  }

  private static function perPiece(EntityInterface $catalogue): float {
    if ($catalogue->hasField(self::QTY_FIELD) && !$catalogue->get(self::QTY_FIELD)->isEmpty()) {
      return (float) $catalogue->get(self::QTY_FIELD)->value;
    }
    if ($catalogue->hasField(self::INPUT_FIELD) && !$catalogue->get(self::INPUT_FIELD)->isEmpty()) {
      $parsed = _tec_inventory_parse_quantity_formula(trim((string) $catalogue->get(self::INPUT_FIELD)->value));
      if ($parsed !== NULL) {
        return $parsed;
      }
    }
    return 0.0;
  }

  private static function inputOf(EntityInterface $catalogue, float $per): string {
    if ($catalogue->hasField(self::INPUT_FIELD) && !$catalogue->get(self::INPUT_FIELD)->isEmpty()) {
      $raw = trim((string) $catalogue->get(self::INPUT_FIELD)->value);
      if ($raw !== '') {
        return $raw;
      }
    }
    return self::dec($per, 4);
  }

  private static function materialCost($material): ?string {
    if (!$material || !$material->hasField('field_tec_price') || $material->get('field_tec_price')->isEmpty()) {
      return NULL;
    }
    return self::dec((float) $material->get('field_tec_price')->value, 6);
  }

  private static function dec(float $n, int $scale): string {
    return number_format($n, $scale, '.', '');
  }

}
