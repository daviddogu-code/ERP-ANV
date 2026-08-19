<?php

namespace Drupal\tec_inventory\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Production documents panel for a sales order.
 *
 * @Block(
 *   id = "tec_order_production_documents",
 *   admin_label = @Translation("TEC Order: Production Documents"),
 *   category = @Translation("TEC")
 * )
 */
class TecOrderProductionDocumentsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Feature toggle: sheet cutting tables on per-product documents only.
   *
   * Set to FALSE (or remove buildCuttingTables usage) to fully revert.
   */
  const ENABLE_CUTTING_TABLES = TRUE;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->fileUrlGenerator = $file_url_generator;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $order = $this->resolveOrder();
    if (!$order) {
      return [
        '#markup' => '<p>' . $this->t('Open a sales order to view production documents.') . '</p>',
        '#cache' => ['contexts' => ['route']],
      ];
    }

    $build_data = $this->buildDocumentData($order);

    return [
      '#theme' => 'tec_order_production_documents',
      '#order_label' => $order->label(),
      '#order_id' => (int) $order->id(),
      '#grand_total' => $build_data['grand_total'],
      '#products' => $build_data['products'],
      '#attached' => [
        'library' => ['tec_inventory/production_documents'],
      ],
      '#cache' => [
        'contexts' => ['route'],
        'tags' => Cache::mergeTags($order->getCacheTags(), $build_data['cache_tags']),
        'max-age' => Cache::PERMANENT,
      ],
    ];
  }

  /**
   * Resolve the current sales order from the route.
   */
  protected function resolveOrder() {
    $order = $this->routeMatch->getParameter('tec_order');
    if ($order instanceof EntityInterface && $order->getEntityTypeId() === 'tec_order') {
      return $order;
    }

    // Fallback when quicktabs/layout loses the route entity parameter.
    $raw = $this->routeMatch->getRawParameter('tec_order');
    if (is_numeric($raw)) {
      $loaded = \Drupal::entityTypeManager()->getStorage('tec_order')->load($raw);
      if ($loaded) {
        return $loaded;
      }
    }

    $path = \Drupal::service('path.current')->getPath();
    if (preg_match('#^/tec_order/(\d+)#', $path, $matches)) {
      return \Drupal::entityTypeManager()->getStorage('tec_order')->load($matches[1]);
    }

    return NULL;
  }

  /**
   * Build printable document structure from order line items.
   */
  protected function buildDocumentData(EntityInterface $order) {
    $products = [];
    $grand_total = 0.0;
    $cache_tags = [];

    if (!$order->hasField('field_tec_line_items')) {
      return [
        'products' => [],
        'grand_total' => 0,
        'cache_tags' => [],
      ];
    }

    // First pass: collect qty by color+size and materials by color.
    $qty_map = [];
    $materials_by_color = [];
    $color_meta = [];

    foreach ($order->get('field_tec_line_items') as $item) {
      $line = $item->entity;
      if (!$line) {
        continue;
      }
      $cache_tags = Cache::mergeTags($cache_tags, $line->getCacheTags());

      $qty = $line->hasField('field_tec_quantity') && !$line->get('field_tec_quantity')->isEmpty()
        ? (float) $line->get('field_tec_quantity')->value
        : 0.0;
      $grand_total += $qty;

      $product = $line->hasField('field_tec_product') ? $line->get('field_tec_product')->entity : NULL;
      $color = $line->hasField('field_tec_color_variation') ? $line->get('field_tec_color_variation')->entity : NULL;
      $size_var = $line->hasField('field_tec_size_variation') ? $line->get('field_tec_size_variation')->entity : NULL;

      if (!$product) {
        continue;
      }
      if (!$color && $size_var && $size_var->hasField('field_tec_color_variation')) {
        $color = $size_var->get('field_tec_color_variation')->entity;
      }
      if (!$color) {
        continue;
      }

      $pid = (int) $product->id();
      $cid = (int) $color->id();
      $cache_tags = Cache::mergeTags($cache_tags, $product->getCacheTags(), $color->getCacheTags());

      if (!isset($color_meta[$cid])) {
        $color_meta[$cid] = [
          'product_id' => $pid,
          'product_label' => $product->label(),
          'color_label' => $this->colorVariationLabel($color),
          'color_entity' => $color,
          'product_entity' => $product,
          'first_line' => (int) $line->id(),
        ];
      }
      else {
        $color_meta[$cid]['first_line'] = min($color_meta[$cid]['first_line'], (int) $line->id());
      }

      $size_label = '';
      $size_weight = 0;
      $size_id = 0;
      if ($size_var) {
        $cache_tags = Cache::mergeTags($cache_tags, $size_var->getCacheTags());
        $size_id = (int) $size_var->id();
        if ($size_var->hasField('field_tec_size') && !$size_var->get('field_tec_size')->isEmpty()) {
          $size_term = $size_var->get('field_tec_size')->entity;
          if ($size_term) {
            $size_label = $size_term->label();
            $size_weight = (int) $size_term->getWeight();
            $cache_tags = Cache::mergeTags($cache_tags, $size_term->getCacheTags());
          }
        }
        if ($size_label === '') {
          $size_label = $size_var->label();
        }
      }
      else {
        $size_label = '—';
      }

      $key = $cid . ':' . $size_id;
      if (!isset($qty_map[$cid])) {
        $qty_map[$cid] = [];
      }
      if (!isset($qty_map[$cid][$key])) {
        $qty_map[$cid][$key] = [
          'size_id' => $size_id,
          'size_label' => $size_label,
          'size_weight' => $size_weight,
          'qty' => 0.0,
        ];
      }
      $qty_map[$cid][$key]['qty'] += $qty;

      // Production-doc materials from line BoM snapshot.
      if ($line->hasField('field_tec_line_item_bom')) {
        foreach ($line->get('field_tec_line_item_bom') as $bom_ref) {
          $bom = $bom_ref->entity;
          if (!$bom || !$bom->hasField('field_tec_inventory')) {
            continue;
          }
          $cache_tags = Cache::mergeTags($cache_tags, $bom->getCacheTags());
          $material = $bom->get('field_tec_inventory')->entity;
          if (!$material) {
            continue;
          }
          $cache_tags = Cache::mergeTags($cache_tags, $material->getCacheTags());
          if (!$this->materialShowsOnProductionDoc($material)) {
            continue;
          }
          $mid = (int) $material->id();
          $materials_by_color[$cid][$mid] = $material->label();
        }
      }
    }

    // Second pass: for each color, fill all catalog sizes (qty 0 if missing).
    foreach ($color_meta as $cid => $meta) {
      /** @var \Drupal\Core\Entity\EntityInterface $color */
      $color = $meta['color_entity'];
      $sizes = [];

      if ($color->hasField('field_tec_size_variations')) {
        foreach ($color->get('field_tec_size_variations') as $sv_ref) {
          $sv = $sv_ref->entity;
          if (!$sv) {
            continue;
          }
          $cache_tags = Cache::mergeTags($cache_tags, $sv->getCacheTags());
          $sid = (int) $sv->id();
          $size_label = $sv->label();
          $size_weight = 0;
          if ($sv->hasField('field_tec_size') && !$sv->get('field_tec_size')->isEmpty()) {
            $size_term = $sv->get('field_tec_size')->entity;
            if ($size_term) {
              $size_label = $size_term->label();
              $size_weight = (int) $size_term->getWeight();
              $cache_tags = Cache::mergeTags($cache_tags, $size_term->getCacheTags());
            }
          }
          $key = $cid . ':' . $sid;
          $qty = isset($qty_map[$cid][$key]) ? $qty_map[$cid][$key]['qty'] : 0.0;
          $sizes[] = [
            'size_id' => $sid,
            'size_label' => $size_label,
            'qty' => $qty,
            'weight' => $size_weight,
          ];
          unset($qty_map[$cid][$key]);
        }
      }

      // Any leftover sizes from line items not in color catalog.
      if (!empty($qty_map[$cid])) {
        foreach ($qty_map[$cid] as $row) {
          $sizes[] = [
            'size_id' => (int) $row['size_id'],
            'size_label' => $row['size_label'],
            'qty' => $row['qty'],
            'weight' => $row['size_weight'],
          ];
        }
      }

      usort($sizes, static function ($a, $b) {
        if ($a['weight'] === $b['weight']) {
          return strnatcasecmp($a['size_label'], $b['size_label']);
        }
        return $a['weight'] <=> $b['weight'];
      });

      $subtotal = 0.0;
      foreach ($sizes as $s) {
        $subtotal += (float) $s['qty'];
      }

      $pid = $meta['product_id'];
      if (!isset($products[$pid])) {
        $products[$pid] = [
          'id' => $pid,
          'label' => $meta['product_label'],
          'variations' => [],
          'product_total' => 0.0,
          'cutting_tables' => [],
          'first_line' => $meta['first_line'],
        ];
      }
      else {
        $products[$pid]['first_line'] = min($products[$pid]['first_line'], $meta['first_line']);
      }

      $products[$pid]['variations'][] = [
        'color_id' => $cid,
        'color_label' => $meta['color_label'],
        'image_url' => $this->colorImageUrl($color),
        'sizes' => $sizes,
        'subtotal' => $subtotal,
        'materials' => array_values($materials_by_color[$cid] ?? []),
        'first_line' => $meta['first_line'],
      ];
      $products[$pid]['product_total'] += $subtotal;
    }

    // The order of the line items, which is the order every screen of the order
    // shows them in, and the order the owner typed them. It used to be
    // alphabetical by product name, which is a fine order for a catalogue and
    // the wrong one for a document read next to the order it came from: a
    // product added last showed up first, and the sheet stopped matching the
    // screen it was printed from.
    //
    // A product is placed by the earliest line that mentions it, and a colour by
    // the earliest line of that colour, so a product ordered again further down
    // the order stays where it first appeared instead of moving to the bottom.
    // The key is the line item id -- the same key the line item views sort by
    // since 19 August 2026, when a leftover draggableviews sort was taken out of
    // them. If one side ever changes key, the two orders drift apart again.
    //
    // Sizes are not sorted here: they follow the size vocabulary, because the
    // document lists every size in the catalogue, including the ones with no
    // quantity, and those were never in a line item to have a position.
    uasort($products, static fn(array $a, array $b): int => $a['first_line'] <=> $b['first_line']);
    foreach ($products as &$by_first_line) {
      usort($by_first_line['variations'], static fn(array $a, array $b): int => $a['first_line'] <=> $b['first_line']);
    }
    unset($by_first_line);

    // Per-product sheet cutting tables (not shown on All Products).
    if (self::ENABLE_CUTTING_TABLES) {
      foreach ($products as &$product) {
        $cutting = $this->buildCuttingTables($product, $cache_tags);
        $product['cutting_tables'] = $cutting['tables'];
        $cache_tags = $cutting['cache_tags'];
      }
      unset($product);
    }

    return [
      'products' => array_values($products),
      'grand_total' => $grand_total,
      'cache_tags' => $cache_tags,
    ];
  }

  /**
   * Build foam/sponge sheet cutting tables for one product document.
   *
   * Target = 1 / field_tec_quantity_input
   * Total Pairs/Pieces = ordered qty for that size (all colors summed).
   *
   * Only BoM rows whose material UoU is Sheet are included.
   */
  protected function buildCuttingTables(array $product, array $cache_tags) {
    $by_size = [];

    foreach ($product['variations'] as $variation) {
      foreach ($variation['sizes'] as $size) {
        $label = (string) ($size['size_label'] ?? '');
        if ($label === '') {
          continue;
        }
        if (!isset($by_size[$label])) {
          $by_size[$label] = [
            'size_label' => $label,
            'qty' => 0.0,
            'weight' => (int) ($size['weight'] ?? 0),
            'size_ids' => [],
          ];
        }
        $by_size[$label]['qty'] += (float) ($size['qty'] ?? 0);
        if (!empty($size['size_id'])) {
          $by_size[$label]['size_ids'][(int) $size['size_id']] = (int) $size['size_id'];
        }
        if ((int) ($size['weight'] ?? 0) < $by_size[$label]['weight']) {
          $by_size[$label]['weight'] = (int) $size['weight'];
        }
      }
    }

    uasort($by_size, static function ($a, $b) {
      if ($a['weight'] === $b['weight']) {
        return strnatcasecmp($a['size_label'], $b['size_label']);
      }
      return $a['weight'] <=> $b['weight'];
    });

    $tables = [];
    $storage = \Drupal::entityTypeManager()->getStorage('tec_product');

    foreach ($by_size as $group) {
      $rows = [];
      $seen_materials = [];

      foreach ($group['size_ids'] as $size_id) {
        $size_var = $storage->load($size_id);
        if (!$size_var || !$size_var->hasField('field_tec_bom')) {
          continue;
        }
        $cache_tags = Cache::mergeTags($cache_tags, $size_var->getCacheTags());

        foreach ($size_var->get('field_tec_bom')->referencedEntities() as $bom) {
          $cache_tags = Cache::mergeTags($cache_tags, $bom->getCacheTags());
          $material = $bom->hasField('field_tec_inventory') ? $bom->get('field_tec_inventory')->entity : NULL;
          if (!$material || !$this->materialIsSheetUnit($material)) {
            continue;
          }
          $cache_tags = Cache::mergeTags($cache_tags, $material->getCacheTags());
          $mid = (int) $material->id();
          if (isset($seen_materials[$mid])) {
            continue;
          }

          $input = $bom->hasField('field_tec_quantity_input') && !$bom->get('field_tec_quantity_input')->isEmpty()
            ? (string) $bom->get('field_tec_quantity_input')->value
            : '';
          $requires = $this->parseRequiresInput($input);
          if ($requires === NULL && $bom->hasField('field_tec_quantity') && !$bom->get('field_tec_quantity')->isEmpty()) {
            $requires = (float) $bom->get('field_tec_quantity')->value;
          }
          if ($requires === NULL || $requires == 0.0) {
            continue;
          }

          $target = 1.0 / $requires;
          $seen_materials[$mid] = TRUE;
          $rows[] = [
            'material' => $material->label(),
            'target' => $target,
            'total' => (float) $group['qty'],
          ];
        }

        // One representative size variation is enough when BoMs match per size.
        if ($rows) {
          break;
        }
      }

      if ($rows) {
        $tables[] = [
          'size_label' => $group['size_label'],
          'rows' => $rows,
        ];
      }
    }

    return [
      'tables' => $tables,
      'cache_tags' => $cache_tags,
    ];
  }

  /**
   * Whether material Unit of Use is Sheet (foam/sponge cutting stock).
   */
  protected function materialIsSheetUnit(EntityInterface $material) {
    if (!$material->hasField('field_tec_unit_use') || $material->get('field_tec_unit_use')->isEmpty()) {
      return FALSE;
    }
    $unit = $material->get('field_tec_unit_use')->entity;
    if (!$unit) {
      return FALSE;
    }
    return strcasecmp((string) $unit->label(), 'Sheet') === 0;
  }

  /**
   * Parse BoM Requires input ("1/12", "0.1", "1") to a float divisor.
   */
  protected function parseRequiresInput($input) {
    $input = trim((string) $input);
    if ($input === '') {
      return NULL;
    }
    if (preg_match('#^(\d+(?:\.\d+)?)\s*/\s*(\d+(?:\.\d+)?)$#', $input, $matches)) {
      $denominator = (float) $matches[2];
      if ($denominator == 0.0) {
        return NULL;
      }
      return ((float) $matches[1]) / $denominator;
    }
    if (is_numeric($input)) {
      return (float) $input;
    }
    return NULL;
  }

  /**
   * Whether a material's type is flagged for production documents.
   */
  protected function materialShowsOnProductionDoc(EntityInterface $material) {
    if (!$material->hasField('field_tec_material_type') || $material->get('field_tec_material_type')->isEmpty()) {
      return FALSE;
    }
    $type = $material->get('field_tec_material_type')->entity;
    if (!$type || !$type->hasField('field_tec_show_on_prod_doc')) {
      return FALSE;
    }
    if ($type->get('field_tec_show_on_prod_doc')->isEmpty()) {
      return FALSE;
    }
    return (bool) $type->get('field_tec_show_on_prod_doc')->value;
  }

  /**
   * Display label for a color variation (taxonomy Color, not entity title).
   */
  protected function colorVariationLabel(EntityInterface $color) {
    $labels = [];
    if ($color->hasField('field_tec_colors') && !$color->get('field_tec_colors')->isEmpty()) {
      foreach ($color->get('field_tec_colors')->referencedEntities() as $term) {
        $labels[] = $term->label();
      }
    }
    if ($labels) {
      return implode(' / ', $labels);
    }
    return (string) $color->label();
  }

  /**
   * First color-variation image URL, if any.
   */
  protected function colorImageUrl(EntityInterface $color) {
    if (!$color->hasField('field_tec_images') || $color->get('field_tec_images')->isEmpty()) {
      return '';
    }
    $file = $color->get('field_tec_images')->entity;
    if (!$file instanceof FileInterface) {
      return '';
    }
    $uri = $file->getFileUri();
    $style = ImageStyle::load('medium');
    if ($style) {
      return $style->buildUrl($uri);
    }
    return $this->fileUrlGenerator->generateAbsoluteString($uri);
  }

}
