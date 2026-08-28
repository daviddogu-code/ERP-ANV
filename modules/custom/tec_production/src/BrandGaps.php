<?php

namespace Drupal\tec_production;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\views\ViewExecutable;

/**
 * Names the products a brand's catalogue cannot show, and says what they lack.
 *
 * The catalogue on a brand page is a list of sizes: one row per size variation,
 * reaching up to its colour and to its product. That is what makes it the screen
 * where prices are typed, and it is also a trapdoor. A product with no colour
 * has no size, so it has no row, so the brand page cannot show it -- and the
 * + Product button is on that very page, which means the first thing you see
 * after creating a product is a list it is not in. The owner walked into it on
 * 19 August 2026 and had no way of telling whether the product had been saved.
 *
 * So the list says what it cannot show. Underneath the table, the products of
 * the brand that have no colour, or that have a colour with no size, with a link
 * to each and the reason. Newest first, because the one being looked for is the
 * one just made.
 *
 * The rule for what counts as missing lives in of() and nowhere else: the
 * Organize products screen marks the same rows with the same question, and the
 * two must not be able to disagree about which products are half made.
 */
final class BrandGaps implements TrustedCallbackInterface {

  /**
   * The screens that get the notice, and nothing else does.
   *
   * The same view draws the product blocks on the front page and the customer's
   * Products tab, and those list products directly -- a half-made product is
   * already visible there, so there is nothing to explain.
   */
  public const SCREENS = [
    'tec_products' => ['brand_catalogue'],
  ];

  /**
   * The colours of a product.
   */
  public const COLOURS = 'field_tec_color_variations';

  /**
   * The sizes of a colour, which is what the catalogue draws a row from.
   */
  public const SIZES = 'field_tec_size_variations';

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Hangs a late painter on the catalogue, after Views has built its footer.
   *
   * The catalogue is also a form — the sales price is typed in the table — and
   * Views' own pre-render empties the footer when it wraps the rows. Writing
   * the notice in preprocess_views_view was too early: that footer was still
   * about to be thrown away. This callback runs after that wrap.
   */
  public static function attach(ViewExecutable $view, array &$element): void {
    if (!in_array($view->current_display, self::SCREENS[$view->id()] ?? [], TRUE)) {
      return;
    }
    $element['#pre_render'][] = [static::class, 'preRender'];
  }

  /**
   * Puts the notice under the catalogue of the brand being looked at.
   */
  public static function preRender(array $element): array {
    $view = $element['#view'] ?? NULL;
    if (!$view instanceof ViewExecutable) {
      return $element;
    }

    $brand = (int) ($view->args[0] ?? 0);
    if ($brand < 1) {
      return $element;
    }

    $gaps = self::inBrand($brand);
    if (!$gaps) {
      return $element;
    }

    $element['#footer'][] = self::notice($gaps);
    return $element;
  }

  /**
   * What keeps one product out of its brand's catalogue.
   *
   * A colour with no sizes is a gap of its own even when the product has other
   * colours that do have them: that colour is as invisible in the catalogue as
   * the whole product would be, and its prices are nowhere to be typed.
   *
   * @return array
   *   Empty when the product is whole. Otherwise 'blank' says it has no colour
   *   at all, and 'colours' holds the colours that have no size.
   */
  public static function of(EntityInterface $product): array {
    $colours = $product->hasField(self::COLOURS)
      ? $product->get(self::COLOURS)->referencedEntities()
      : [];

    if (!$colours) {
      return ['blank' => TRUE, 'colours' => []];
    }

    $bare = array_filter($colours, static fn(EntityInterface $colour): bool
      => !$colour->hasField(self::SIZES) || $colour->get(self::SIZES)->isEmpty());

    return $bare ? ['blank' => FALSE, 'colours' => array_values($bare)] : [];
  }

  /**
   * The half-made products of a brand, newest first.
   *
   * Newest first and not in the brand's own order because this list is read
   * looking for one particular product: the one saved a moment ago, which is
   * the highest number there is. The brand's order is what the catalogue above
   * is for.
   *
   * @return array
   *   Keyed by product id. Each entry is what of() returned plus 'product'.
   */
  public static function inBrand(int $brand_tid): array {
    $storage = \Drupal::entityTypeManager()->getStorage('tec_product');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_product')
      ->condition(CataloguePosition::BRAND_FIELD, $brand_tid)
      ->sort('id', 'DESC')
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return [];
    }

    $products = $storage->loadMultiple($ids);
    $gaps = [];
    // Walked in the order the query gave, because loadMultiple() hands entities
    // back in whatever order the database found them.
    foreach (array_keys($ids) as $id) {
      $product = $products[$id] ?? NULL;
      if (!$product) {
        continue;
      }
      $gap = self::of($product);
      if ($gap) {
        $gaps[$id] = $gap + ['product' => $product];
      }
    }

    return $gaps;
  }

  /**
   * The notice itself.
   */
  private static function notice(array $gaps): array {
    $items = [];
    foreach ($gaps as $gap) {
      $items[] = self::item($gap);
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-brand-gaps']],
      '#attached' => ['library' => ['tec_production/brand_gaps']],
      // The notice is a reading of every product of the brand and of their
      // colours, so anything that touches one of those has to wipe it.
      '#cache' => ['tags' => ['tec_product_list']],
      'head' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['tec-brand-gaps__head']],
        '#value' => \Drupal::translation()->formatPlural(
          count($gaps),
          '1 product is not on the list above yet',
          '@count products are not on the list above yet'
        ),
      ],
      'why' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['tec-brand-gaps__help']],
        '#value' => \Drupal::translation()->translate('The list above is a list of sizes, so a product joins it once it has a colour and that colour has a size. Until then it exists, and it is here.'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['tec-brand-gaps__list']],
      ],
    ];
  }

  /**
   * One product of the notice: where to go, and what it is missing.
   *
   * The link lands on the colour that needs the size when there is one, because
   * the product page keeps each colour behind an anchor of its own and the
   * + Size button is inside it. With no colour at all there is nowhere to aim
   * but the top of the page, where + Variation is.
   */
  private static function item(array $gap): array {
    $product = $gap['product'];
    $url = $product->toUrl();
    if (!empty($gap['colours'])) {
      $url->setOption('fragment', (string) $gap['colours'][0]->id());
    }

    return [
      'product' => [
        '#type' => 'link',
        '#title' => self::nameOf($product),
        '#url' => $url,
        '#attributes' => ['class' => ['tec-brand-gaps__product']],
      ],
      'why' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['tec-brand-gaps__missing']],
        '#value' => self::missing($gap),
      ],
    ];
  }

  /**
   * How many sizes a product has, counting every colour.
   *
   * Organize products shows this number next to the colour count, and a zero
   * there is the same fact the notice names. Counted here so the two cannot
   * disagree about what a size is.
   */
  public static function sizeCount(EntityInterface $product): int {
    if (!$product->hasField(self::COLOURS)) {
      return 0;
    }

    $n = 0;
    foreach ($product->get(self::COLOURS)->referencedEntities() as $colour) {
      if ($colour->hasField(self::SIZES)) {
        $n += $colour->get(self::SIZES)->count();
      }
    }

    return $n;
  }

  /**
   * What this product is missing, in words.
   *
   * Public because Organize products writes the same sentence on the row, and
   * the two screens must not be able to name the same gap two different ways.
   */
  public static function reason(array $gap) {
    return self::missing($gap);
  }

  /**
   * What this product is missing, in words.
   */
  private static function missing(array $gap) {
    if (!empty($gap['blank'])) {
      return \Drupal::translation()->translate('no colour yet');
    }

    $names = array_map(static fn(EntityInterface $colour): string => (string) $colour->label(), $gap['colours']);

    return \Drupal::translation()->formatPlural(
      count($names),
      '@colours has no size yet',
      '@colours have no size yet',
      ['@colours' => implode(', ', $names)]
    );
  }

  /**
   * The name a product is known by on screen.
   */
  private static function nameOf(EntityInterface $product): string {
    if ($product->hasField('field_product_name')) {
      $name = trim((string) $product->get('field_product_name')->value);
      if ($name !== '') {
        return $name;
      }
    }

    return (string) $product->label();
  }

}
