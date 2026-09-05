<?php

namespace Drupal\tec_inventory\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\tec_inventory\Entity\Pattern;
use Drupal\tec_inventory\PatternRecipe;

/**
 * Factory doors for patterns: list and recipe. URLs use the code, not the id.
 */
class PatternController extends ControllerBase {

  /**
   * /pattern/055
   */
  public function view(Pattern $tec_pattern): array {
    $can_edit = $this->currentUser()->hasPermission('edit tec patterns');
    $code = $tec_pattern->code();

    $build = [
      '#title' => $tec_pattern->displayName(),
      '#attached' => ['library' => ['tec_inventory/pattern']],
      '#cache' => [
        'tags' => $tec_pattern->getCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];

    $material = $tec_pattern->hasField('product_material') ? $tec_pattern->productMaterialLabel() : '';
    $type = $tec_pattern->hasField('product_type') ? $tec_pattern->productTypeLabel() : '';
    $type_term = ($tec_pattern->hasField('product_type') && !$tec_pattern->get('product_type')->isEmpty())
      ? $tec_pattern->get('product_type')->entity
      : NULL;
    if ($type_term) {
      $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], $type_term->getCacheTags());
    }
    $build['heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-pattern-heading']],
    ];
    $build['heading']['name'] = [
      '#markup' => '<h1 class="tec-pattern-heading__name">' . $this->escape($tec_pattern->displayName()) . '</h1>',
    ];
    if ($type !== '' || $material !== '') {
      $meta = '<div class="tec-pattern-heading__meta">';
      if ($type !== '') {
        $meta .= '<div class="tec-pattern-heading__type">' . $this->escape($type) . '</div>';
      }
      if ($material !== '') {
        $meta .= '<div class="tec-pattern-heading__material">' . $this->escape($material) . '</div>';
      }
      $meta .= '</div>';
      $build['heading']['meta'] = ['#markup' => $meta];
    }

    $build['help'] = [
      '#markup' => '<p class="tec-pattern-help">'
        . $this->t('Each size has its own item. Green is a real SKU. Yellow is a type (Leather, Thread): the amount lives here; which SKU is chosen later on the product.')
        . '</p>',
    ];

    if ($can_edit) {
      $edit = Url::fromRoute('entity.tec_pattern.edit_form', ['tec_pattern' => $code])->toString();
      $delete = Url::fromRoute('entity.tec_pattern.delete_form', ['tec_pattern' => $code])->toString();
      $build['actions'] = [
        '#markup' => '<div class="text-align-right tec-pattern-actions"><span class="btn-default"><strong><a href="'
          . $edit . '">' . $this->t('Edit') . '</a></strong></span> <span class="btn-default"><a href="'
          . $delete . '">' . $this->t('Delete') . '</a></span></div>',
      ];
    }

    $build['products'] = $this->productsBuild($tec_pattern);
    $build['#cache']['tags'] = Cache::mergeTags(
      $build['#cache']['tags'],
      $build['products']['#cache']['tags'] ?? []
    );

    $size_ids = $tec_pattern->sizeIds();
    $size_labels = $this->sizeLabels($size_ids);
    $terms = $this->recipeTerms($tec_pattern);

    $build['ficha'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-pattern-ficha']],
    ];
    $photo = $this->photo($tec_pattern);
    if ($photo) {
      $build['ficha']['photo'] = $photo;
    }
    $build['ficha']['main'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-pattern-ficha__main']],
    ];

    $build['ficha']['main']['sizes'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-pattern-sizes']],
    ];
    $build['ficha']['main']['sizes']['label'] = [
      '#markup' => '<div class="field__label">' . $this->t('Sizes') . '</div>',
    ];

    if (!$size_ids) {
      $build['ficha']['main']['sizes']['empty'] = [
        '#markup' => '<p class="tec-pattern-help">' . $this->t('No sizes selected. Edit the pattern and tick the sizes this pattern is cut in.') . '</p>',
      ];
      return $build;
    }

    $build['ficha']['main']['sizes']['cards'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-size-cards']],
    ];

    foreach ($size_ids as $sid) {
      $build['ficha']['main']['sizes']['cards']['s' . $sid] = $this->sizeCard($tec_pattern, $sid, $size_labels[$sid] ?? (string) $sid, $terms);
    }

    return $build;
  }

  public function title(Pattern $tec_pattern): string {
    return $tec_pattern->displayName();
  }

  /**
   * Catalogue products that came from this pattern.
   */
  private function productsBuild(Pattern $pattern): array {
    $products = PatternRecipe::productsOf($pattern);
    $tags = [
      PatternRecipe::PRODUCTS_LIST_TAG,
      PatternRecipe::productsTag((int) $pattern->id()),
    ];
    foreach ($products as $product) {
      $tags = Cache::mergeTags($tags, $product->getCacheTags());
    }
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'tec-pattern-products',
        'class' => ['tec-pattern-products'],
      ],
      '#cache' => ['tags' => $tags],
    ];
    $build['label'] = [
      '#markup' => '<div class="field__label">' . $this->t('Products') . '</div>',
    ];
    if (!$products) {
      $build['empty'] = [
        '#markup' => '<p class="tec-pattern-help">' . $this->t('No products yet.') . '</p>',
      ];
      return $build;
    }
    $items = [];
    foreach ($products as $product) {
      $name = PatternRecipe::productTitle($product);
      if ($name === '') {
        $name = '#' . $product->id();
      }
      $link = Link::fromTextAndUrl($name, Url::fromRoute('entity.tec_product.canonical', [
        'tec_product' => $product->id(),
      ]))->toString();
      $brand = PatternRecipe::productBrand($product);
      $items[] = $brand !== ''
        ? ['#markup' => '<span class="tec-pattern-products__brand">' . $this->escape($brand) . '</span> ' . $link]
        : ['#markup' => $link];
    }
    $build['list'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['tec-pattern-products__list']],
    ];
    return $build;
  }

  private function photo(Pattern $pattern): ?array {
    $file = $pattern->imageFile();
    if (!$file) {
      return NULL;
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-pattern-ficha__photo']],
      'img' => [
        '#theme' => 'image_style',
        '#style_name' => 'medium',
        '#uri' => $file->getFileUri(),
        '#alt' => $pattern->displayName(),
      ],
    ];
  }

  /**
   * @param array<int, \Drupal\taxonomy\TermInterface> $terms
   */
  private function sizeCard(Pattern $pattern, int $size_id, string $title, array $terms): array {
    $rows = [];
    $blank = "\u{00A0}";
    foreach ($pattern->lines() as $line) {
      $cell = $line['cells'][$size_id] ?? NULL;
      if (!$cell || (int) ($cell['target_id'] ?? 0) <= 0) {
        $rows[] = [
          'class' => ['tec-size-card__bom-row--empty'],
          'data' => [
            ['data' => $blank],
            ['data' => $blank, 'class' => ['text-end']],
            ['data' => $blank],
            ['data' => $blank, 'class' => ['text-end']],
          ],
        ];
        continue;
      }
      $term = $terms[$cell['target_id']] ?? NULL;
      $item = $term ? (string) $term->label() : '#' . $cell['target_id'];
      $kind = ($cell['kind'] ?? '') === 'type' ? 'type' : 'material';
      $qty = trim((string) ($cell['qty'] ?? ''));
      $uou = '';
      $cost = '';
      if ($kind === 'material' && $term) {
        $uou = $this->unitOfUse($term);
        $cost = $this->lineCost($term, $qty);
      }
      $rows[] = [
        'no_striping' => TRUE,
        'class' => $kind === 'type'
          ? ['tec-recipe--yellow', 'tec-recipe--type']
          : ['tec-recipe--green', 'tec-recipe--material'],
        'data' => [
          ['data' => $this->escape($item)],
          ['data' => $this->escape($qty), 'class' => ['text-end']],
          ['data' => $this->escape($uou)],
          ['data' => $cost === '' ? '' : $this->escape($cost), 'class' => ['text-end']],
        ],
      ];
    }

    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-size-card']],
    ];
    $card['title'] = [
      '#markup' => '<h5 class="tec-size-card__title strong">' . $this->escape($title) . '</h5>',
    ];
    $card['bom_label'] = [
      '#markup' => '<div class="field__label">' . $this->t('Bill of Materials') . '</div>',
    ];
    $card['bom'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Material'),
        ['data' => $this->t('Requires'), 'class' => ['text-end']],
        $this->t('UoU'),
        ['data' => $this->t('Cost'), 'class' => ['text-end']],
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No recipe for this size.'),
      '#attributes' => [
        'class' => [
          'table',
          'tec-size-card__bom',
          'table-sm',
          'table-bordered',
          'table-hover',
        ],
      ],
    ];
    $foot = $this->costFoot($pattern, $size_id);
    if ($foot) {
      $card['cost'] = $foot;
    }
    return $card;
  }

  /**
   * @return array<int, \Drupal\taxonomy\TermInterface>
   */
  private function recipeTerms(Pattern $pattern): array {
    $ids = [];
    foreach ($pattern->lines() as $line) {
      foreach ($line['cells'] ?? [] as $cell) {
        if (!empty($cell['target_id'])) {
          $ids[(int) $cell['target_id']] = (int) $cell['target_id'];
        }
      }
    }
    return $ids ? Term::loadMultiple($ids) : [];
  }

  private function unitOfUse(TermInterface $term): string {
    if (!$term->hasField('field_tec_unit_use') || $term->get('field_tec_unit_use')->isEmpty()) {
      return '';
    }
    $unit = $term->get('field_tec_unit_use')->entity;
    return $unit instanceof TermInterface ? (string) $unit->label() : '';
  }

  private function lineCost(TermInterface $term, string $qty): string {
    $quantity = _tec_inventory_parse_quantity_formula($qty);
    $cost = $term->hasField('field_tec_price') ? $term->get('field_tec_price')->value : NULL;
    if ($quantity === NULL || $cost === NULL || $cost === '') {
      return '';
    }
    return "\u{0E3F} " . number_format($quantity * (float) $cost, 2);
  }

  private function costFoot(Pattern $pattern, int $size_id): ?array {
    $sums = $pattern->costOfSize($size_id);
    if (!$sums) {
      return NULL;
    }
    $rows = [[
      'class' => ['tec-material-cost__row--total'],
      'data' => [
        [
          'data' => $this->t('Material cost'),
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
            'data' => $this->formatPlural(
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
      '#cache' => ['tags' => $sums['tags']],
    ];
  }

  /**
   * @param int[] $ids
   *
   * @return array<int, string>
   */
  private function sizeLabels(array $ids): array {
    $labels = [];
    if (!$ids) {
      return $labels;
    }
    $terms = Term::loadMultiple($ids);
    foreach ($ids as $id) {
      $term = $terms[$id] ?? NULL;
      $labels[$id] = $term instanceof TermInterface ? (string) $term->label() : (string) $id;
    }
    return $labels;
  }

  private function escape(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

}
