<?php

namespace Drupal\tec_inventory\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Factory pattern identified by its code (055, 099).
 *
 * The recipe lives here. A product that points at this pattern reads greens
 * live and fills yellow holes on the colour. Catalogue sizes without a
 * pattern still explode their own BoM.
 *
 * @ContentEntityType(
 *   id = "tec_pattern",
 *   label = @Translation("Pattern"),
 *   label_collection = @Translation("Patterns"),
 *   label_singular = @Translation("pattern"),
 *   label_plural = @Translation("patterns"),
 *   base_table = "tec_pattern",
 *   admin_permission = "edit tec patterns",
 *   handlers = {
 *     "access" = "Drupal\tec_inventory\PatternAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\tec_inventory\Form\PatternDeleteForm",
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "code",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/pattern/{tec_pattern}",
 *     "add-form" = "/pattern/new",
 *     "edit-form" = "/pattern/{tec_pattern}/edit",
 *     "delete-form" = "/pattern/{tec_pattern}/delete",
 *     "collection" = "/pattern",
 *     "organize-form" = "/pattern/organize",
 *   },
 * )
 */
class Pattern extends ContentEntityBase {

  /**
   * Codes that would clash with /pattern/new and similar paths.
   */
  public const RESERVED = ['new', 'edit', 'delete', 'add', 'organize'];

  /**
   * Load by factory code (the slug in /pattern/055).
   */
  public static function loadByCode(string $code): ?self {
    $code = trim($code);
    if ($code === '') {
      return NULL;
    }
    $ids = \Drupal::entityTypeManager()->getStorage('tec_pattern')->getQuery()
      ->accessCheck(FALSE)
      ->condition('code', $code)
      ->range(0, 1)
      ->execute();
    return $ids ? static::load(reset($ids)) : NULL;
  }

  public function displayName(): string {
    $name = trim((string) $this->get('name')->value);
    return $name !== '' ? $name : $this->code();
  }

  /**
   * Allowed values for Product material, same list as the catalogue product.
   *
   * @return array<string, string>
   */
  public static function productMaterialAllowedValues(): array {
    return [
      'semi' => 'Semi',
      'leather' => 'Leather',
      'microfiber' => 'Microfiber',
      'pu_leather' => 'PU Leather',
      'semi_micro' => 'Micro + Semi',
    ];
  }

  /**
   * @return array<string, string>
   */
  public static function productMaterialOptions(): array {
    $storage = \Drupal::service('entity_field.manager')
      ->getFieldStorageDefinitions('tec_product')['field_tec_product_material'] ?? NULL;
    if ($storage && function_exists('options_allowed_values')) {
      $options = options_allowed_values($storage);
      if (is_array($options) && $options) {
        return $options;
      }
    }
    return self::productMaterialAllowedValues();
  }

  public function productMaterialLabel(): string {
    $value = trim((string) $this->get('product_material')->value);
    if ($value === '') {
      return '';
    }
    $options = static::productMaterialOptions();
    return (string) ($options[$value] ?? $value);
  }

  /**
   * @return array<int, string>
   */
  public static function productTypeOptions(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_product_types')
      ->sort('name')
      ->sort('weight')
      ->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $term) {
      $options[(int) $term->id()] = (string) $term->label();
    }
    return $options;
  }

  public function productTypeLabel(): string {
    if (!$this->hasField('product_type') || $this->get('product_type')->isEmpty()) {
      return '';
    }
    $term = $this->get('product_type')->entity;
    return $term ? (string) $term->label() : '';
  }

  public function code(): string {
    return trim((string) $this->get('code')->value);
  }

  /**
   * Photo of this pattern, if one was uploaded.
   */
  public function imageFile(): ?FileInterface {
    if (!$this->hasField('image') || $this->get('image')->isEmpty()) {
      return NULL;
    }
    $file = $this->get('image')->entity;
    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * Next factory position: one after the current last row.
   */
  public static function nextWeight(): int {
    try {
      $query = \Drupal::database()->select('tec_pattern', 'p');
      $query->addExpression('MAX(p.weight)', 'top');
      $top = $query->execute()->fetchField();
    }
    catch (\Exception $e) {
      return 1;
    }
    return ((int) $top) + 1;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $code = $this->code();
    $name = trim((string) $this->get('name')->value);
    if ($code === '') {
      return $name !== '' ? $name : new TranslatableMarkup('Pattern');
    }
    return $name === '' ? $code : $code . ' ' . $name;
  }

  /**
   * Size term ids, in the order stored on the pattern.
   *
   * @return int[]
   */
  public function sizeIds(): array {
    $ids = [];
    foreach ($this->get('sizes')->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    return $ids;
  }

  /**
   * Recipe rows. Each size cell has its own kind, item and quantity.
   *
   * @return array<int, array{label: string, cells: array<int, array{kind: string, target_id: int, qty: string}>}>
   */
  public function lines(): array {
    $raw = trim((string) $this->get('bom')->value);
    if ($raw === '') {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    if (!is_array($decoded)) {
      return [];
    }
    $lines = [];
    foreach ($decoded as $row) {
      if (!is_array($row)) {
        continue;
      }
      $label = trim((string) ($row['label'] ?? ''));
      $cells = $this->normalizeCells($row);
      $lines[] = [
        'label' => $label,
        'cells' => $cells,
      ];
    }
    return $lines;
  }

  /**
   * @param array<string, mixed> $row
   *
   * @return array<int, array{kind: string, target_id: int, qty: string}>
   */
  private function normalizeCells(array $row): array {
    $cells = [];
    if (isset($row['cells']) && is_array($row['cells'])) {
      foreach ($row['cells'] as $size_id => $cell) {
        if (!is_array($cell)) {
          continue;
        }
        $target = (int) ($cell['target_id'] ?? 0);
        if ($target <= 0) {
          continue;
        }
        $cells[(int) $size_id] = [
          'kind' => ($cell['kind'] ?? '') === 'type' ? 'type' : 'material',
          'target_id' => $target,
          'qty' => trim((string) ($cell['qty'] ?? '')),
        ];
      }
      return $cells;
    }

    // First-trozo rows: one kind/item for every size, only qty changed.
    $kind = ($row['kind'] ?? '') === 'type' ? 'type' : 'material';
    $target = (int) ($row['target_id'] ?? 0);
    if ($target <= 0) {
      return [];
    }
    foreach ($row['qty'] ?? [] as $size_id => $value) {
      $value = trim((string) $value);
      $cells[(int) $size_id] = [
        'kind' => $kind,
        'target_id' => $target,
        'qty' => $value,
      ];
    }
    return $cells;
  }

  /**
   * @param array<int, array{label?: string, cells: array<int, array{kind: string, target_id: int, qty: string}>}> $lines
   */
  public function setLines(array $lines): self {
    $this->set('bom', json_encode(array_values($lines), JSON_UNESCAPED_UNICODE));
    return $this;
  }

  /**
   * Material cost of one size on this pattern, SKU lines only.
   *
   * Type cells are not inventory and do not belong in the total. A SKU with
   * no consumption cost is reported rather than counted as zero.
   *
   * @return array{total: float, lines: int, priced: int, unpriced: string[], tags: string[]}|null
   */
  public function costOfSize(int $size_id): ?array {
    $total = 0.0;
    $lines = 0;
    $priced = 0;
    $unpriced = [];
    $tags = $this->getCacheTags();

    foreach ($this->lines() as $line) {
      $cell = $line['cells'][$size_id] ?? NULL;
      if (!$cell || ($cell['kind'] ?? '') !== 'material') {
        continue;
      }
      $lines++;
      $term = Term::load($cell['target_id']);
      if ($term) {
        $tags = array_merge($tags, $term->getCacheTags());
      }
      $quantity = _tec_inventory_parse_quantity_formula(trim((string) ($cell['qty'] ?? '')));
      $cost = $term && $term->hasField('field_tec_price')
        ? $term->get('field_tec_price')->value
        : NULL;
      if ($quantity === NULL || $cost === NULL || $cost === '') {
        $unpriced[] = $term ? (string) $term->label() : '#' . $cell['target_id'];
        continue;
      }
      $total += $quantity * (float) $cost;
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
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $code = $this->code();
    if ($code === '' || in_array(strtolower($code), self::RESERVED, TRUE)) {
      throw new \LogicException('Invalid pattern code.');
    }
    $existing = static::loadByCode($code);
    if ($existing && (int) $existing->id() !== (int) $this->id()) {
      throw new \LogicException('Pattern code already exists.');
    }
    if ($this->isNew() && $this->hasField('weight') && (int) $this->get('weight')->value <= 0) {
      $this->set('weight', static::nextWeight());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $parameters = parent::urlRouteParameters($rel);
    $code = $this->code();
    if ($code !== '' && isset($parameters['tec_pattern'])) {
      $parameters['tec_pattern'] = $code;
    }
    return $parameters;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Code'))
      ->setDescription(t('Factory code. This is the URL: /pattern/055.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->addConstraint('UniqueField');

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('Optional: Gloves, Shin guard. The code already identifies it.'))
      ->setSetting('max_length', 128);

    $fields['product_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Product type'))
      ->setDescription(t('Same catalogue as on a product: Gloves, Shin guard.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => ['tec_product_types' => 'tec_product_types'],
        'sort' => ['field' => 'name', 'direction' => 'asc'],
        'auto_create' => FALSE,
      ]);

    $fields['product_material'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Product material'))
      ->setDescription(t('Same list as on a catalogue product: Semi, Leather, Microfiber…'))
      ->setSetting('allowed_values', self::productMaterialAllowedValues());

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDescription(t('A photo of this pattern.'))
      ->setSettings([
        'uri_scheme' => 'public',
        'file_directory' => 'pattern/[date:custom:Y]-[date:custom:m]',
        'file_extensions' => 'png gif jpg jpeg webp',
        'alt_field' => FALSE,
        'alt_field_required' => FALSE,
        'title_field' => FALSE,
        'title_field_required' => FALSE,
      ]);

    $fields['sizes'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sizes'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => ['tec_sizes' => 'tec_sizes'],
        'sort' => ['field' => 'weight', 'direction' => 'asc'],
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['bom'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Recipe'))
      ->setDescription(t('Pattern BoM. Each size cell points at an inventory SKU or a material type. Products wired to this pattern read it live.'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultUserId');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('Factory order on /pattern. Lower comes first. New patterns go last.'))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    return $fields;
  }

  public static function getDefaultUserId(): array {
    return [\Drupal::currentUser()->id()];
  }

}
