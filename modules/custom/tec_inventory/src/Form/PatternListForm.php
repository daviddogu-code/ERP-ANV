<?php

namespace Drupal\tec_inventory\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\tec_inventory\Entity\Pattern;
use Drupal\tec_inventory\PatternRecipe;

/**
 * /pattern is the catalogue. /pattern/organize is where rows are dragged.
 *
 * Same split as a brand: look at the list, press Organize Patterns, drag,
 * Save order, back to the list. The handle does not sit on the catalogue.
 */
class PatternListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tec_pattern_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->set('skip_eca', TRUE);

    $organize = $this->isOrganize();
    $can_edit = $this->currentUser()->hasPermission('edit tec patterns');
    $patterns = $this->loadPatterns();

    $form['#attributes']['class'][] = 'tec-pattern-list-form';
    if ($organize) {
      $form['#attributes']['class'][] = 'tec-pattern-list-form--organize';
    }
    $form['#attached']['library'][] = 'tec_inventory/pattern';
    $form['#attached']['library'][] = 'simple_popup_views/simple_popup_views';
    $form['#cache']['tags'][] = PatternRecipe::PRODUCTS_LIST_TAG;

    $form['help'] = [
      '#markup' => '<p class="tec-pattern-help">'
        . ($organize
          ? $this->t('Drag rows into the factory order, then save.')
          : $this->t('055 is the same cut for every customer. The commercial name lives on the product.'))
        . '</p>',
    ];

    if ($can_edit && !$organize) {
      $buttons = '';
      if ($patterns) {
        $buttons .= '<span class="btn-default"><a href="'
          . Url::fromRoute('entity.tec_pattern.organize')->toString()
          . '">' . $this->t('Organize Patterns') . '</a></span> ';
      }
      $buttons .= '<span class="btn-default"><strong><a href="'
        . Url::fromRoute('entity.tec_pattern.add_form')->toString()
        . '">+ Pattern</a></strong></span>';
      $form['add'] = [
        '#markup' => '<div class="text-align-right tec-pattern-actions">' . $buttons . '</div>',
      ];
    }

    $header = [
      ['data' => $this->t('Image'), 'class' => ['tec-pattern-list__img']],
      $this->t('Code'),
      $this->t('Name'),
      $this->t('Product type'),
      $this->t('Product material'),
      $this->t('Sizes'),
      $this->t('Rows'),
    ];
    if (!$organize) {
      $header[] = $this->t('Products');
    }
    if ($can_edit && !$organize) {
      $header[] = $this->t('Operations');
    }
    if ($organize) {
      $header[] = $this->t('Weight');
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No patterns yet.'),
      '#attributes' => [
        'id' => 'tec-pattern-list',
        'class' => ['tec-pattern-list'],
      ],
    ];
    if ($organize && $patterns) {
      $form['table']['#tabledrag'] = [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'pattern-order-weight',
        ],
      ];
    }

    $delta = max(count($patterns), 1);
    $place = 0;
    $counts = [];
    if (!$organize && $patterns) {
      $counts = PatternRecipe::productCounts(array_map(static fn(Pattern $pattern): int => (int) $pattern->id(), $patterns));
    }
    foreach ($patterns as $pattern) {
      $place++;
      $code = $pattern->code();
      $row = [
        '#attributes' => [
          'class' => $organize ? ['draggable'] : [],
        ],
        'image' => $this->imageCell($pattern),
        'code' => [
          '#type' => 'link',
          '#title' => $code,
          '#url' => Url::fromRoute('entity.tec_pattern.canonical', ['tec_pattern' => $code]),
        ],
        'name' => [
          '#markup' => Html::escape(trim((string) $pattern->get('name')->value)),
        ],
        'product_type' => [
          '#markup' => Html::escape($pattern->hasField('product_type') ? $pattern->productTypeLabel() : ''),
        ],
        'product_material' => [
          '#markup' => Html::escape($pattern->hasField('product_material') ? $pattern->productMaterialLabel() : ''),
        ],
        'sizes' => [
          '#markup' => Html::escape($this->sizeSummary($pattern)),
        ],
        'rows' => [
          '#markup' => (string) count($pattern->lines()),
        ],
      ];
      if (!$organize) {
        $count = $counts[(int) $pattern->id()] ?? 0;
        if ($count > 0) {
          $row['products'] = [
            '#type' => 'link',
            '#title' => $this->formatPlural($count, '1 product', '@count products'),
            '#url' => Url::fromRoute('entity.tec_pattern.canonical', [
              'tec_pattern' => $code,
            ], [
              'fragment' => 'tec-pattern-products',
            ]),
          ];
        }
        else {
          $row['products'] = ['#markup' => ''];
        }
      }
      if ($can_edit && !$organize) {
        $edit = Url::fromRoute('entity.tec_pattern.edit_form', ['tec_pattern' => $code])->toString();
        $delete = Url::fromRoute('entity.tec_pattern.delete_form', ['tec_pattern' => $code])->toString();
        $row['operations'] = [
          '#markup' => '<span class="btn-default"><a href="' . Html::escape($edit) . '">' . $this->t('Edit') . '</a></span> '
            . '<span class="btn-default"><a href="' . Html::escape($delete) . '">' . $this->t('Delete') . '</a></span>',
        ];
      }
      if ($organize) {
        $row['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $place,
          '#delta' => $delta,
          '#attributes' => ['class' => ['pattern-order-weight']],
        ];
      }
      $form['table'][$pattern->id()] = $row;
    }

    if ($organize && $patterns) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['save'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save order'),
        '#button_type' => 'primary',
      ];
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('entity.tec_pattern.collection'),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rows = $form_state->getValue('table') ?: [];
    uasort($rows, static fn($a, $b): int => ((int) ($a['weight'] ?? 0)) <=> ((int) ($b['weight'] ?? 0)));

    $storage = \Drupal::entityTypeManager()->getStorage('tec_pattern');
    $place = 0;
    $moved = 0;
    foreach (array_keys($rows) as $id) {
      $pattern = $storage->load($id);
      if (!$pattern instanceof Pattern || !$pattern->hasField('weight')) {
        continue;
      }
      $place++;
      if ((int) $pattern->get('weight')->value !== $place) {
        $pattern->set('weight', $place);
        $pattern->save();
        $moved++;
      }
    }

    if ($moved) {
      $this->messenger()->addStatus($this->formatPlural(
        $moved,
        'Order saved. 1 pattern moved.',
        'Order saved. @count patterns moved.'
      ));
    }
    else {
      $this->messenger()->addStatus($this->t('Nothing moved: the order was already this one.'));
    }
    $form_state->setRedirect('entity.tec_pattern.collection');
  }

  private function isOrganize(): bool {
    return $this->getRouteMatch()->getRouteName() === 'entity.tec_pattern.organize';
  }

  /**
   * @return \Drupal\tec_inventory\Entity\Pattern[]
   */
  private function loadPatterns(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('tec_pattern');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('weight')
      ->sort('code')
      ->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * 40x40 with a large hover popup, same markup as line items.
   */
  private function imageCell(Pattern $pattern): array {
    $cell = [
      '#wrapper_attributes' => ['class' => ['tec-pattern-list__img']],
    ];
    $file = $pattern->imageFile();
    if (!$file) {
      $cell['#markup'] = '<span class="tec-pattern-list__thumb tec-pattern-list__thumb--empty"></span>';
      return $cell;
    }
    $uri = $file->getFileUri();
    $alt = $pattern->displayName();
    $cell['#type'] = 'container';
    $cell['#attributes'] = ['class' => ['simple-popup-views-global']];
    $cell['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spv-popup-wrapper']],
      'link' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['spv-popup-link', 'spv_on_hover']],
        'img' => [
          '#theme' => 'image_style',
          '#style_name' => 'small_40x40',
          '#uri' => $uri,
          '#alt' => $alt,
          '#attributes' => ['loading' => 'lazy'],
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['spv-popup-content', 'spv-top-popup']],
        'inside' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['spv-inside-popup']],
          'img' => [
            '#theme' => 'image_style',
            '#style_name' => 'large',
            '#uri' => $uri,
            '#alt' => $alt,
          ],
        ],
      ],
    ];
    return $cell;
  }

  private function sizeSummary(Pattern $pattern): string {
    $ids = $pattern->sizeIds();
    if (!$ids) {
      return '';
    }
    $labels = [];
    $terms = Term::loadMultiple($ids);
    foreach ($ids as $id) {
      $term = $terms[$id] ?? NULL;
      $labels[] = $term instanceof TermInterface ? (string) $term->label() : (string) $id;
    }
    return implode(', ', $labels);
  }

}
