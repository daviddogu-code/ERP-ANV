<?php

namespace Drupal\tec_production\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_production\BrandGaps;
use Drupal\tec_production\CataloguePosition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Puts the products of one brand in the order the brand wants them.
 *
 * The order used to belong to the customer, one per customer, and the pro forma
 * could not read any of them. It belongs to the brand now: the same list of
 * products, in the same order, for every customer who buys it, and the pro
 * forma is created in that order. See CataloguePosition for the four levels
 * and for why the number is written straight into its table.
 *
 * Reached from the Organize products tab on the brand page, which shows only
 * for people who can edit brands.
 */
class BrandProductOrderForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId() {
    return 'tec_production_brand_product_order_form';
  }

  /**
   * Only brands, and only for whoever can edit the brand.
   *
   * Being able to change what every customer sees is the same job as editing
   * the brand itself, so it asks for the same permission instead of inventing
   * another one. It is also what keeps the tab off the other vocabularies:
   * Drupal drops local tasks whose route the user cannot reach.
   */
  public static function access(AccountInterface $account, ?EntityInterface $taxonomy_term = NULL) {
    if (!$taxonomy_term || $taxonomy_term->bundle() !== 'tec_brands') {
      return AccessResult::forbidden()->addCacheableDependency($taxonomy_term);
    }

    return $taxonomy_term->access('update', $account, TRUE)
      ->addCacheableDependency($taxonomy_term);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $taxonomy_term = NULL) {
    if (!$taxonomy_term || $taxonomy_term->bundle() !== 'tec_brands') {
      throw new NotFoundHttpException();
    }
    $brand_tid = (int) $taxonomy_term->id();
    $form_state->set('brand_tid', $brand_tid);

    $form['#attached']['library'][] = 'tec_production/brand_products';

    $products = $this->loadProducts($brand_tid);
    $buyers = $this->buyersOf($brand_tid);

    $form['head'] = [
      '#markup' => '<div class="tec-brand-order__head">'
        . '<span class="tec-brand-order__brand">' . Html::escape((string) $taxonomy_term->label()) . '</span>'
        . ' <span class="tec-brand-order__count">'
        . $this->formatPlural(count($products), '1 product', '@count products')
        . '</span></div>',
    ];

    $form['help'] = [
      '#markup' => '<div class="tec-brand-order__help">'
        . $this->t('Drag the products into the order this brand should be shown in. It is the brand\'s own order, the same one for every customer who buys it, and it is the order the catalogue, the customer tab and a new pro forma follow. Inside each product, colours keep the order they were dragged into on the product form, and sizes follow the size list.')
        . ($buyers ? '<div class="tec-brand-order__buyers">'
          . $this->t('Seen by: @buyers', ['@buyers' => implode(', ', $buyers)])
          . '</div>' : '')
        . '</div>',
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        ['data' => $this->t('No'), 'class' => ['tec-brand-order__no-col']],
        ['data' => '', 'class' => ['tec-brand-order__img-col']],
        ['data' => $this->t('Product')],
        ['data' => $this->t('Type')],
        ['data' => $this->t('Colours'), 'class' => ['tec-brand-order__num']],
        ['data' => $this->t('Sizes'), 'class' => ['tec-brand-order__num']],
        ['data' => $this->t('Weight')],
      ],
      '#empty' => $this->t('This brand has no products yet.'),
      '#attributes' => ['id' => 'tec-brand-products'],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'brand-product-position',
        ],
      ],
    ];

    $delta = max(count($products), 1);
    $place = 0;
    foreach ($products as $pid => $product) {
      $place++;
      $colours = $product->hasField(BrandGaps::COLOURS)
        ? $product->get(BrandGaps::COLOURS)->referencedEntities()
        : [];
      $colour_names = array_map(static fn($colour): string => (string) $colour->label(), $colours);
      $sizes = BrandGaps::sizeCount($product);
      $gap = BrandGaps::of($product);
      $row_class = ['draggable', 'tec-brand-order__row'];
      if ($gap) {
        $row_class[] = 'tec-brand-order__row--gap';
      }

      $form['table'][$pid] = [
        '#attributes' => ['class' => $row_class],
        'no' => [
          '#markup' => '<span class="tec-brand-order__no">' . $place . '</span>',
          '#wrapper_attributes' => ['class' => ['tec-brand-order__no-cell']],
        ],
        'image' => $this->imageCell($colours[0] ?? NULL) + [
          '#wrapper_attributes' => ['class' => ['tec-brand-order__img-cell']],
        ],
        'product' => [
          '#type' => 'link',
          '#title' => $this->nameOf($product),
          '#url' => $product->toUrl(),
          '#wrapper_attributes' => ['class' => ['tec-brand-order__name-cell']],
        ],
        'type' => [
          '#markup' => '<span class="tec-brand-order__type">' . Html::escape($this->typeOf($product)) . '</span>',
        ],
        'colours' => [
          '#markup' => '<span class="tec-brand-order__colours" title="'
            . Html::escape(implode(', ', $colour_names)) . '">' . count($colours) . '</span>',
          '#wrapper_attributes' => ['class' => ['tec-brand-order__num']],
        ],
        'sizes' => [
          '#markup' => '<span class="tec-brand-order__sizes'
            . ($gap ? ' tec-brand-order__sizes--gap' : '') . '"'
            . ($gap ? ' title="' . Html::escape((string) BrandGaps::reason($gap)) . '"' : '')
            . '>' . $sizes . '</span>',
          '#wrapper_attributes' => ['class' => ['tec-brand-order__num']],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $place,
          '#delta' => $delta,
          '#attributes' => ['class' => ['brand-product-position']],
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save order'),
      '#button_type' => 'primary',
      '#access' => (bool) $products,
    ];
    $form['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to the brand'),
      '#url' => $taxonomy_term->toUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rows = $form_state->getValue('table') ?: [];

    // The top row is the first product of the brand. Places are renumbered
    // from 1 on every save, so gaps left by deleted products close themselves.
    uasort($rows, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    $positions = [];
    $place = 0;
    foreach (array_keys($rows) as $pid) {
      $positions[(int) $pid] = ++$place;
    }

    $moved = CataloguePosition::write($positions);
    if ($moved) {
      $this->messenger()->addStatus($this->formatPlural(
        $moved,
        'Order saved. 1 product moved.',
        'Order saved. @count products moved.'
      ));
    }
    else {
      $this->messenger()->addStatus($this->t('Nothing moved: the order was already this one.'));
    }
  }

  /**
   * The products of a brand, in the order they are shown in.
   *
   * Sorted here and not in the query because the tie-break is a natural sort
   * on the name: a brand seeded alphabetically must not put "Glove 10" before
   * "Glove 2", and a brand nobody has dragged yet is exactly that case.
   */
  protected function loadProducts(int $brand_tid): array {
    $storage = $this->entityTypeManager->getStorage('tec_product');
    $ids = $storage->getQuery()
      ->condition('type', 'tec_product')
      ->condition(CataloguePosition::BRAND_FIELD, $brand_tid)
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return [];
    }

    $products = $storage->loadMultiple($ids);
    uasort($products, function (EntityInterface $a, EntityInterface $b): int {
      $place_a = CataloguePosition::of($a) ?: PHP_INT_MAX;
      $place_b = CataloguePosition::of($b) ?: PHP_INT_MAX;
      return $place_a <=> $place_b ?: strnatcasecmp($this->nameOf($a), $this->nameOf($b));
    });

    return $products;
  }

  /**
   * The customers who bought the right to see this order.
   */
  protected function buyersOf(int $brand_tid): array {
    $storage = $this->entityTypeManager->getStorage('tec_crm');
    $ids = $storage->getQuery()
      ->condition('field_tec_brands', $brand_tid)
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return [];
    }

    $names = [];
    foreach ($storage->loadMultiple($ids) as $contact) {
      $names[] = (string) $contact->label();
    }
    natcasesort($names);

    return $names;
  }

  /**
   * The name a product is known by.
   */
  protected function nameOf(EntityInterface $product): string {
    if ($product->hasField('field_product_name')) {
      $name = trim((string) $product->get('field_product_name')->value);
      if ($name !== '') {
        return $name;
      }
    }
    return (string) $product->label();
  }

  /**
   * What kind of product it is, for the eye only.
   */
  protected function typeOf(EntityInterface $product): string {
    if (!$product->hasField('field_tec_product_type') || $product->get('field_tec_product_type')->isEmpty()) {
      return '';
    }
    $type = $product->get('field_tec_product_type')->entity;

    return $type ? (string) $type->label() : '';
  }

  /**
   * 40x40 thumbnail of the first colour, which is how a product is recognised.
   */
  protected function imageCell(?EntityInterface $colour): array {
    if (!$colour || !$colour->hasField('field_tec_images') || $colour->get('field_tec_images')->isEmpty()) {
      return ['#markup' => ''];
    }
    $file = $colour->get('field_tec_images')->entity;
    if (!$file) {
      return ['#markup' => ''];
    }

    return [
      '#theme' => 'image_style',
      '#style_name' => 'small_40x40',
      '#uri' => $file->getFileUri(),
      '#attributes' => ['loading' => 'lazy', 'class' => ['tec-brand-order__img']],
    ];
  }

}
