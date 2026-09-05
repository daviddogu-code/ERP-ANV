<?php

namespace Drupal\tec_inventory\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\ScrollTopCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\tec_inventory\Entity\Pattern;

/**
 * Create / edit a factory pattern: code, sizes, and a recipe cell per size.
 */
class PatternForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tec_pattern_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Pattern $tec_pattern = NULL) {
    if (!$form_state->has('pattern')) {
      $form_state->set('pattern', $tec_pattern ?? Pattern::create([]));
    }
    /** @var \Drupal\tec_inventory\Entity\Pattern $pattern */
    $pattern = $form_state->get('pattern');

    // ECA form subscribers can wrap every submit in AJAX. This screen owns
    // its own Ajax (sizes, rows, save) and must not inherit another layer.
    $form_state->set('skip_eca', TRUE);

    $form['#attached']['library'][] = 'tec_inventory/pattern';
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="tec-pattern-shell">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['novalidate'] = 'novalidate';
    $form['#attributes']['class'][] = 'tec-pattern-form';

    $form['messages'] = [
      '#type' => 'status_messages',
      '#weight' => -1001,
    ];

    $form['help'] = [
      '#markup' => '<p class="tec-pattern-help">'
        . $this->t('Each size has its own Kind, Item and amount. Material is a real SKU. Type is Leather or Thread: the amount lives here; which SKU is chosen later on the product. Leave a size blank if that line is not used.')
        . '</p>',
    ];

    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code'),
      '#required' => TRUE,
      '#maxlength' => 32,
      '#default_value' => $pattern->code(),
      '#description' => $this->t('This is the URL: /pattern/055. Letters, numbers, dot, underscore or hyphen.'),
      '#attributes' => $pattern->isNew() ? ['autofocus' => 'autofocus'] : [],
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 128,
      '#default_value' => trim((string) $pattern->get('name')->value),
      '#description' => $this->t('Gloves, Shin guard. Shown on the pattern page.'),
    ];

    $type_id = 0;
    if ($pattern->hasField('product_type') && !$pattern->get('product_type')->isEmpty()) {
      $type_id = (int) $pattern->get('product_type')->target_id;
    }
    $form['product_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Product type'),
      '#options' => Pattern::productTypeOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $type_id ?: NULL,
      '#required' => TRUE,
      '#suffix' => $this->catalogManageSuffix('tec_product_types', $this->t('Manage product types')),
    ];

    $material_value = $pattern->hasField('product_material')
      ? (string) $pattern->get('product_material')->value
      : '';
    $form['product_material'] = [
      '#type' => 'select',
      '#title' => $this->t('Product material'),
      '#options' => Pattern::productMaterialOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $material_value !== '' ? $material_value : NULL,
      '#required' => TRUE,
    ];

    $image_fid = 0;
    if ($pattern->hasField('image') && !$pattern->get('image')->isEmpty()) {
      $image_fid = (int) $pattern->get('image')->target_id;
    }
    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#default_value' => $image_fid ? [$image_fid] : [],
      '#upload_location' => 'public://pattern/' . date('Y') . '-' . date('m'),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png gif jpg jpeg webp'],
      ],
      '#description' => $this->t('PNG, JPG, GIF or WebP. A photo of this pattern.'),
    ];

    $size_options = $this->sizeOptions();
    $form['sizes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Sizes'),
      '#options' => $size_options,
      '#default_value' => $pattern->sizeIds(),
      '#description' => $this->t('Tick the sizes this pattern is cut in. Requires accepts a number or a fraction.'),
      '#ajax' => [
        'callback' => '::recipeAjax',
        'wrapper' => 'tec-pattern-recipe',
        'event' => 'change',
      ],
    ];

    if ($form_state->isRebuilding()) {
      $trigger_name = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
      if ($trigger_name !== 'add_line' && !str_starts_with($trigger_name, 'remove_line_')) {
        $this->storeWorkingLines($form_state);
      }
    }
    if (!$form_state->has('working_lines')) {
      $form_state->set('working_lines', $this->linesFromPattern($pattern));
    }

    $size_ids = $this->selectedSizeIds($form_state, $pattern, $size_options);
    $form['recipe'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'tec-pattern-recipe', 'class' => ['tec-pattern-recipe-wrap']],
    ];

    $form['recipe']['board'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'tec-pattern-board'],
    ];

    if (!$size_ids) {
      $form['recipe']['board']['empty'] = [
        '#markup' => '<p class="tec-pattern-help">' . $this->t('Tick sizes above. Each size then has its own Kind, Item and amount.') . '</p>',
      ];
    }
    else {
      $form['recipe']['board']['cards'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['tec-size-cards']],
      ];
      foreach ($size_ids as $sid) {
        $form['recipe']['board']['cards']['s' . $sid] = $this->sizeCardForm(
          $form_state,
          $sid,
          $size_options[$sid] ?? (string) $sid
        );
      }
    }

    $form['recipe']['add'] = [
      '#type' => 'submit',
      '#name' => 'add_line',
      '#value' => $this->t('Add row'),
      '#submit' => ['::addRow'],
      '#validate' => [],
      '#ajax' => [
        'callback' => '::recipeBoardAjax',
        'wrapper' => 'tec-pattern-board',
        'disable-refocus' => TRUE,
      ],
      '#limit_validation_errors' => [
        ['sizes'],
        ['recipe'],
      ],
      '#access' => (bool) $size_ids,
    ];

    // First submit in the DOM so Enter saves instead of hitting Remove/Add
    // (those are AJAX). CSS keeps this bar at the bottom of the screen.
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => -1000,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'save_pattern',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['tec-pattern-save'],
      ],
      '#ajax' => [
        'callback' => '::saveAjax',
        'wrapper' => 'tec-pattern-shell',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
        'disable-refocus' => TRUE,
      ],
    ];
    $cancel = $pattern->isNew() || $pattern->code() === ''
      ? Url::fromRoute('entity.tec_pattern.collection')
      : Url::fromRoute('entity.tec_pattern.canonical', ['tec_pattern' => $pattern->code()]);
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $cancel,
    ];

    return $form;
  }

  /**
   * Store the uploaded photo on the pattern and keep the file.
   */
  private function assignImage(Pattern $pattern, FormStateInterface $form_state): void {
    if (!$pattern->hasField('image')) {
      return;
    }
    $fids = $form_state->getValue('image');
    $fid = is_array($fids) ? (int) reset($fids) : 0;
    if ($fid <= 0) {
      $pattern->set('image', []);
      return;
    }
    $file = File::load($fid);
    if (!$file instanceof FileInterface) {
      $pattern->set('image', []);
      return;
    }
    $file->setPermanent();
    $file->save();
    $pattern->set('image', [
      'target_id' => $fid,
      'alt' => $pattern->displayName(),
    ]);
  }

  public function recipeAjax(array &$form, FormStateInterface $form_state) {
    return $form['recipe'];
  }

  public function recipeBoardAjax(array &$form, FormStateInterface $form_state) {
    return $form['recipe']['board'];
  }

  /**
   * Finish a Save click: show errors on the form, or go to the pattern.
   *
   * Without this callback Drupal still starts Ajax on Save (throbber + disable)
   * and then never completes when validation fails, so the button stays spinning.
   */
  public function saveAjax(array &$form, FormStateInterface $form_state) {
    if (!$form_state->hasAnyErrors()) {
      /** @var \Drupal\tec_inventory\Entity\Pattern $pattern */
      $pattern = $form_state->get('pattern');
      $response = new AjaxResponse();
      $response->addCommand(new RedirectCommand(
        Url::fromRoute('entity.tec_pattern.canonical', [
          'tec_pattern' => $pattern->code(),
        ])->toString()
      ));
      return $response;
    }
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#tec-pattern-shell', $form));
    $response->addCommand(new ScrollTopCommand('#tec-pattern-shell'));
    return $response;
  }

  public function addRow(array &$form, FormStateInterface $form_state): void {
    $this->storeWorkingLines($form_state);
    $lines = $form_state->get('working_lines') ?: [];
    $lines[] = $this->emptyLine();
    $form_state->set('working_lines', $lines);
    $form_state->setRebuild();
  }

  public function removeRow(array &$form, FormStateInterface $form_state): void {
    $this->storeWorkingLines($form_state);
    $lines = $form_state->get('working_lines') ?: [];
    $delta = (int) ($form_state->getTriggeringElement()['#row'] ?? -1);
    if (isset($lines[$delta])) {
      unset($lines[$delta]);
      $form_state->set('working_lines', array_values($lines));
    }
    if (!$form_state->get('working_lines')) {
      $form_state->set('working_lines', [$this->emptyLine()]);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $code = trim((string) $form_state->getValue('code'));
    if ($code === '') {
      $form_state->setErrorByName('code', $this->t('Code is required.'));
    }
    elseif (in_array(strtolower($code), Pattern::RESERVED, TRUE)) {
      $form_state->setErrorByName('code', $this->t('@code is reserved.', ['@code' => $code]));
    }
    elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $code)) {
      $form_state->setErrorByName('code', $this->t('Use letters, numbers, dot, underscore or hyphen. Start with a letter or number.'));
    }
    else {
      /** @var \Drupal\tec_inventory\Entity\Pattern $pattern */
      $pattern = $form_state->get('pattern');
      $existing = Pattern::loadByCode($code);
      if ($existing && (int) $existing->id() !== (int) $pattern->id()) {
        $form_state->setErrorByName('code', $this->t('That code is already a pattern.'));
      }
    }

    $type_id = (int) $form_state->getValue('product_type');
    if ($type_id < 1) {
      $form_state->setErrorByName('product_type', $this->t('Product type is required.'));
    }
    else {
      $term = Term::load($type_id);
      if (!$term || $term->bundle() !== 'tec_product_types') {
        $form_state->setErrorByName('product_type', $this->t('That product type is not valid.'));
      }
    }

    $size_ids = array_values(array_filter($form_state->getValue('sizes') ?: []));
    foreach ($form_state->getValue(['recipe', 'lines']) ?: [] as $i => $row) {
      if (!is_array($row)) {
        continue;
      }
      foreach ($size_ids as $sid) {
        $cell = $row['s' . $sid] ?? [];
        if (!is_array($cell)) {
          continue;
        }
        $kind = ($cell['kind'] ?? '') === 'type' ? 'type' : 'material';
        $target = $this->targetIdFromCell($cell, $kind);
        $qty = trim((string) ($cell['qty'] ?? ''));
        if ($target <= 0 && $qty === '') {
          continue;
        }
        if ($qty !== '' && _tec_inventory_parse_quantity_formula($qty) === NULL) {
          $form_state->setErrorByName("recipe][lines][$i][s$sid][qty", $this->t('Enter a number or a fraction such as 1/12.'));
        }
        if ($target <= 0) {
          $form_state->setErrorByName("recipe][lines][$i][s$sid][item][$kind", $this->t('Choose a @kind for this size, or leave the amount empty.', [
            '@kind' => $kind === 'type' ? $this->t('type') : $this->t('material'),
          ]));
          continue;
        }
        $term = Term::load($target);
        $vid = $kind === 'type' ? 'tec_materials' : 'tec_inventory';
        if (!$term || $term->bundle() !== $vid) {
          $form_state->setErrorByName("recipe][lines][$i][s$sid][item][$kind", $this->t('That @kind is not valid.', [
            '@kind' => $kind === 'type' ? $this->t('type') : $this->t('material'),
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\tec_inventory\Entity\Pattern $pattern */
    $pattern = $form_state->get('pattern');
    $code = trim((string) $form_state->getValue('code'));
    $pattern->set('code', $code);
    $pattern->set('name', trim((string) $form_state->getValue('name')));
    if ($pattern->hasField('product_type')) {
      $type_id = (int) $form_state->getValue('product_type');
      $pattern->set('product_type', $type_id > 0 ? ['target_id' => $type_id] : []);
    }
    if ($pattern->hasField('product_material')) {
      $pattern->set('product_material', (string) $form_state->getValue('product_material'));
    }
    $this->assignImage($pattern, $form_state);

    $size_ids = [];
    foreach ($this->sizeOptions() as $tid => $label) {
      if (!empty($form_state->getValue(['sizes', $tid]))) {
        $size_ids[] = (int) $tid;
      }
    }
    $pattern->set('sizes', array_map(static fn($id) => ['target_id' => $id], $size_ids));
    $pattern->setLines($this->linesFromValues($form_state->getValue(['recipe', 'lines']) ?: [], $size_ids));
    try {
      $pattern->save();
    }
    catch (\Throwable $e) {
      $this->logger('tec_inventory')->error('Pattern save failed: @m', ['@m' => $e->getMessage()]);
      $form_state->setErrorByName('code', $this->t('Could not save this pattern. Check the code.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Pattern @label saved.', ['@label' => $pattern->label()]));
    $form_state->setRedirect('entity.tec_pattern.canonical', ['tec_pattern' => $pattern->code()]);
  }

  /**
   * One size card: Kind, Item and amount for every recipe row.
   */
  private function sizeCardForm(FormStateInterface $form_state, int $sid, string $title): array {
    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-size-card', 'tec-size-card--form']],
    ];
    $card['title'] = [
      '#markup' => '<h5 class="tec-size-card__title strong">' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h5>',
    ];
    $card['bom_label'] = [
      '#markup' => '<div class="field__label">' . $this->t('Bill of Materials') . '</div>',
    ];
    $card['bom'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-size-card__form-table']],
    ];
    $card['bom']['head'] = [
      '#markup' => '<div class="tec-size-card__form-head">'
        . '<div>' . $this->t('Kind') . '</div>'
        . '<div>' . $this->t('Item') . '</div>'
        . '<div>' . $this->t('Requires') . '</div>'
        . '<div></div>'
        . '</div>',
    ];

    foreach ($form_state->get('working_lines') ?: [] as $i => $line) {
      $cell = $line['cell'][$sid] ?? [];
      $kind = ($cell['kind'] ?? '') === 'type' ? 'type' : 'material';
      $name_kind = 'recipe[lines][' . $i . '][s' . $sid . '][kind]';
      $row = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'tec-size-card__form-row',
            'tec-size-card__form-row--' . $kind,
          ],
        ],
      ];
      $row['kind'] = [
        '#type' => 'select',
        '#title' => $this->t('Kind'),
        '#title_display' => 'invisible',
        '#options' => [
          'material' => $this->t('Material'),
          'type' => $this->t('Type'),
        ],
        '#default_value' => $kind,
        '#attributes' => ['class' => ['tec-pattern-recipe__kind']],
        '#parents' => ['recipe', 'lines', $i, 's' . $sid, 'kind'],
      ];
      $row['item'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['tec-pattern-recipe__item']],
      ];
      $row['item']['material'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Material'),
        '#title_display' => 'invisible',
        '#target_type' => 'taxonomy_term',
        '#selection_settings' => ['target_bundles' => ['tec_inventory']],
        '#default_value' => $this->termFromMixed($cell['material'] ?? ($kind === 'material' ? ($cell['target_id'] ?? NULL) : NULL)),
        '#attributes' => ['placeholder' => $this->t('SKU')],
        '#parents' => ['recipe', 'lines', $i, 's' . $sid, 'item', 'material'],
        '#states' => [
          'visible' => [
            ':input[name="' . $name_kind . '"]' => ['value' => 'material'],
          ],
        ],
      ];
      $row['item']['type'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Type'),
        '#title_display' => 'invisible',
        '#target_type' => 'taxonomy_term',
        '#selection_settings' => ['target_bundles' => ['tec_materials']],
        '#default_value' => $this->termFromMixed($cell['type'] ?? ($kind === 'type' ? ($cell['target_id'] ?? NULL) : NULL)),
        '#attributes' => ['placeholder' => $this->t('Leather, Thread…')],
        '#parents' => ['recipe', 'lines', $i, 's' . $sid, 'item', 'type'],
        '#states' => [
          'visible' => [
            ':input[name="' . $name_kind . '"]' => ['value' => 'type'],
          ],
        ],
      ];
      $row['qty'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Requires'),
        '#title_display' => 'invisible',
        '#size' => 8,
        '#maxlength' => 32,
        '#default_value' => (string) ($cell['qty'] ?? ''),
        '#attributes' => [
          'class' => ['tec-pattern-recipe__qty'],
        ],
        '#parents' => ['recipe', 'lines', $i, 's' . $sid, 'qty'],
      ];
      $row['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_line_' . $i . '_s' . $sid,
        '#submit' => ['::removeRow'],
        '#validate' => [],
        '#ajax' => [
          'callback' => '::recipeBoardAjax',
          'wrapper' => 'tec-pattern-board',
          'disable-refocus' => TRUE,
        ],
        '#limit_validation_errors' => [
          ['sizes'],
          ['recipe'],
        ],
        '#row' => $i,
        '#attributes' => ['class' => ['button--small']],
      ];
      $card['bom'][$i] = $row;
    }

    return $card;
  }

  /**
   * @return array<int, string>
   */
  private function sizeOptions(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'tec_sizes')
      ->sort('weight')
      ->sort('name')
      ->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $term) {
      $options[(int) $term->id()] = (string) $term->label();
    }
    return $options;
  }

  /**
   * Catalog door under a select, same markup as product types on a product.
   */
  private function catalogManageSuffix(string $vocabulary, $link_text): string {
    $account = $this->currentUser();
    if (
      !$account->hasPermission('administer taxonomy')
      && !$account->hasPermission("create terms in $vocabulary")
      && !$account->hasPermission("edit terms in $vocabulary")
    ) {
      return '';
    }
    try {
      $url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', [
        'taxonomy_vocabulary' => $vocabulary,
      ]);
    }
    catch (\Exception $e) {
      return '';
    }
    if (!$url->access()) {
      return '';
    }
    $link = Link::fromTextAndUrl($link_text, $url)->toString();
    return '<div class="description admin-form-styles-catalog-link">' . $link . '</div>';
  }

  /**
   * @param array<int, string> $size_options
   *
   * @return int[]
   */
  private function selectedSizeIds(FormStateInterface $form_state, Pattern $pattern, array $size_options): array {
    $raw = $form_state->getValue('sizes');
    // Add/Remove skip full validation, so 'sizes' is often missing from values
    // even though the boxes are still ticked in the POST.
    if (!is_array($raw) || $raw === []) {
      $from_input = $form_state->getUserInput()['sizes'] ?? NULL;
      if (is_array($from_input) && $from_input !== []) {
        $raw = $from_input;
      }
    }
    if ($raw === NULL || $raw === []) {
      $chosen = array_flip($pattern->sizeIds());
    }
    else {
      $chosen = array_flip(array_filter($raw));
    }
    $ids = [];
    foreach (array_keys($size_options) as $id) {
      if (isset($chosen[$id])) {
        $ids[] = (int) $id;
      }
    }
    return $ids;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function linesFromPattern(Pattern $pattern): array {
    $rows = [];
    foreach ($pattern->lines() as $line) {
      $row = ['label' => $line['label'] ?? '', 'cell' => []];
      foreach ($line['cells'] ?? [] as $sid => $cell) {
        $row['cell'][(int) $sid] = [
          'kind' => $cell['kind'],
          'material' => $cell['kind'] === 'material' ? $cell['target_id'] : '',
          'type' => $cell['kind'] === 'type' ? $cell['target_id'] : '',
          'qty' => $cell['qty'] ?? '',
        ];
      }
      $rows[] = $row;
    }
    $rows[] = $this->emptyLine();
    if (count($rows) < 2) {
      $rows[] = $this->emptyLine();
    }
    return $rows;
  }

  /**
   * @return array<string, mixed>
   */
  private function emptyLine(): array {
    return [
      'label' => '',
      'cell' => [],
    ];
  }

  private function storeWorkingLines(FormStateInterface $form_state): void {
    $input = $form_state->getUserInput();
    $lines = $input['recipe']['lines'] ?? NULL;
    if (!is_array($lines)) {
      return;
    }
    $stored = [];
    foreach ($lines as $row) {
      if (!is_array($row)) {
        continue;
      }
      $flat = [
        'label' => $row['label'] ?? '',
        'cell' => [],
      ];
      foreach ($row as $key => $value) {
        if (!is_string($key) || !str_starts_with($key, 's') || !is_array($value)) {
          continue;
        }
        $sid = (int) substr($key, 1);
        if ($sid <= 0) {
          continue;
        }
        $kind = ($value['kind'] ?? '') === 'type' ? 'type' : 'material';
        $flat['cell'][$sid] = [
          'kind' => $kind,
          'material' => $value['item']['material'] ?? ($value['material'] ?? ''),
          'type' => $value['item']['type'] ?? ($value['type'] ?? ''),
          'qty' => $value['qty'] ?? '',
        ];
      }
      $stored[] = $flat;
    }
    $form_state->set('working_lines', $stored);
  }

  private function termFromMixed(mixed $value): ?TermInterface {
    if ($value instanceof TermInterface) {
      return $value;
    }
    if (is_numeric($value) && (int) $value > 0) {
      $term = Term::load((int) $value);
      return $term instanceof TermInterface ? $term : NULL;
    }
    if (is_string($value) && preg_match('/\((\d+)\)\s*$/', $value, $match)) {
      $term = Term::load((int) $match[1]);
      return $term instanceof TermInterface ? $term : NULL;
    }
    return NULL;
  }

  /**
   * @param array<string, mixed> $cell
   */
  private function targetIdFromCell(array $cell, string $kind): int {
    $raw = $cell['item'][$kind] ?? ($cell[$kind] ?? 0);
    if (is_numeric($raw)) {
      return (int) $raw;
    }
    $term = $this->termFromMixed($raw);
    return $term ? (int) $term->id() : 0;
  }

  /**
   * @param array<int, array<string, mixed>> $rows
   * @param int[] $size_ids
   *
   * @return array<int, array{label: string, cells: array<int, array{kind: string, target_id: int, qty: string}>}>
   */
  private function linesFromValues(array $rows, array $size_ids): array {
    $lines = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $cells = [];
      foreach ($size_ids as $sid) {
        $cell = $row['s' . $sid] ?? [];
        if (!is_array($cell)) {
          continue;
        }
        $kind = ($cell['kind'] ?? '') === 'type' ? 'type' : 'material';
        $target = $this->targetIdFromCell($cell, $kind);
        if ($target <= 0) {
          continue;
        }
        $cells[(int) $sid] = [
          'kind' => $kind,
          'target_id' => $target,
          'qty' => trim((string) ($cell['qty'] ?? '')),
        ];
      }
      $label = trim((string) ($row['label'] ?? ''));
      $lines[] = [
        'label' => $label,
        'cells' => $cells,
      ];
    }
    while ($lines) {
      $last = $lines[array_key_last($lines)];
      if ($last['label'] !== '' || $last['cells']) {
        break;
      }
      array_pop($lines);
    }
    return $lines;
  }

}
