<?php

namespace Drupal\tiny_slider\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tiny_slider\TinySliderGlobal;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'tiny_slider_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "tiny_slider_field_formatter",
 *   label = @Translation("Tiny Slider Carousel"),
 *   field_types = {
 *     "image",
 *     "entity_reference"
 *   }
 * )
 */
class TinySliderFieldFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The image style storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * The unique field ID to target nav as thumbnail.
   * This is necessary when there are more than one instances of the slider on a single page.
   *
   * @var string
   */
  protected $uniqueFieldID;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityStorageInterface $image_style_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->currentUser = $current_user;
    $this->imageStyleStorage = $image_style_storage;
    $random = new Random();
    $this->uniqueFieldID = $field_definition->getUniqueIdentifier() . '-' . $random->name();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $tiny_slider_default_settings = TinySliderGlobal::defaultSettings();
    return $tiny_slider_default_settings + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $json_config_default = json_encode(_tiny_slider_default_settings(), JSON_PRETTY_PRINT);

    $hideOnAdvancedMode = [
      'visible' => [
        ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
      ],
    ];

    $image_styles = image_style_options(FALSE);
    $description_link = Link::fromTextAndUrl(
      $this->t('Configure Image Styles'),
      Url::fromRoute('entity.image_style.collection')
    );
    $element['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
      '#description' => $description_link->toRenderable() + [
        '#access' => $this->currentUser->hasPermission('administer image styles'),
      ],
      '#states' => $hideOnAdvancedMode,
    ];


    // Message about enabling the responsive image module
    // if module is not detected, and this is made conditionally visible.
    $element['responsive_image_module_message'] = [
      '#type' => 'markup',
      '#markup' => $this->t('The Responsive Image module is not installed. To use responsive image styles, please install and enable it.'),
      '#prefix' => '<div class="messages messages--warning">',
      '#suffix' => '</div>',
      '#access' => !\Drupal::moduleHandler()->moduleExists('responsive_image'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Add the responsive image style setting if the module is enabled.
    if (\Drupal::moduleHandler()->moduleExists('responsive_image')) {
      $responsive_image_config_link = Link::fromTextAndUrl(
        $this->t('Configure Responsive Image Styles'),
        Url::fromRoute('entity.responsive_image_style.collection')
      )->toString();
  
      $description_text = $this->t('Select a responsive image style to use for different screen sizes.');
      $link_markup = '<div class="form-item__description">' . $responsive_image_config_link . '</div>';

      $responsive_image_styles = $this->getResponsiveImageStyles();
      
      $element['responsive_image_style'] = [
        '#title' => $this->t('Responsive Image Style'),
        '#type' => 'select',
        '#default_value' => $this->getSetting('responsive_image_style'),
        '#empty_option' => $this->t('None (use standard image style)'),
        '#options' => $responsive_image_styles,
        '#description' => $description_text . '<br>' . $link_markup,
        '#states' => $hideOnAdvancedMode,
      ];
    }

    // Link image to.
    $element['image_link'] = [
      '#title' => $this->t('Link image to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#empty_option' => $this->t('Nothing'),
      '#options' => [
        'content' => $this->t('Content'),
        'file' => $this->t('File'),
      ],
      '#states' => $hideOnAdvancedMode,
    ];

    // Items.
    $element['items'] = [
      '#type' => 'number',
      '#step' => '.1',
      '#title' => $this->t('Items'),
      '#default_value' => !empty($this->getSetting('items')) ? $this->getSetting('items') : 3,
      '#description' => $this->t('Maximum amount of items displayed at a time with the widest browser width.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Gutter.
    $element['gutter'] = [
      '#type' => 'number',
      '#title' => $this->t('Gutter'),
      '#default_value' => $this->getSetting('gutter'),
      '#description' => $this->t('Gutter from items.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // mode.
    $element['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => [
        'carousel' => $this->t('Carousel'),
        'gallery' => $this->t('Gallery'),
      ],
      '#default_value' => $this->getSetting('mode'),
      '#description' => $this->t('With carousel everything slides to the side, while gallery uses fade animations and changes all slides at once.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Navigation.
    $element['nav'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Navigation'),
      '#default_value' => $this->getSetting('nav'),
      '#description' => $this->t('Controls the display and functionalities of controls components (prev/next buttons).'),
      '#states' => $hideOnAdvancedMode,
    ];

    // navPosition.
    $element['navPosition'] = [
      '#type' => 'select',
      '#title' => $this->t('Navigation position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $this->getSetting('navPosition'),
      '#description' => $this->t('Display navigation above/below slides.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][nav]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Nav as thumbnails.
    $element['navAsThumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Navigation as thumbnails'),
      '#default_value' => $this->getSetting('navAsThumbnails'),
      '#description' => $this->t('Use image thumbnails in navigation instead of dots.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Autoplay.
    $element['autoplay'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autoplay'),
      '#default_value' => $this->getSetting('autoplay'),
      '#states' => $hideOnAdvancedMode,
    ];

    // AutoplayHoverPause.
    $element['autoplayHoverPause'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause on hover'),
      '#default_value' => $this->getSetting('autoplayHoverPause'),
      '#description' => $this->t('Pause autoplay on mouse hover.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][autoplay]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Autoplay Button Output.
    $element['autoplayButtonOutput'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autoplay buttons'),
      '#default_value' => $this->getSetting('autoplayButtonOutput'),
      '#description' => $this->t('Turn off/on arrow autoplay buttons.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][autoplay]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Autoplay Position.
    $element['autoplayPosition'] = [
      '#type' => 'select',
      '#title' => $this->t('Autoplay position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $this->getSetting('autoplayPosition'),
      '#description' => $this->t('Display autoplay controls above/below slides.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][autoplay]"]' => [
            'checked' => TRUE
          ],
          ':input[name="fields[field_image][settings_edit_form][settings][autoplayButtonOutput]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Autoplay text start.
    $element['autoplayTextStart'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start Text'),
      '#default_value' => $this->getSetting('autoplayTextStart'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][autoplay]"]' => [
            'checked' => TRUE
          ],
          ':input[name="fields[field_image][settings_edit_form][settings][autoplayButtonOutput]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Autoplay text stop.
    $element['autoplayTextStop'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stop Text'),
      '#default_value' => $this->getSetting('autoplayTextStop'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][autoplay]"]' => [
            'checked' => TRUE
          ],
          ':input[name="fields[field_image][settings_edit_form][settings][autoplayButtonOutput]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Controls.
    $element['controls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Controls'),
      '#default_value' => $this->getSetting('controls'),
      '#description' => $this->t('Controls the display and functionalities of nav components (dots).'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Controls Position.
    $element['controlsPosition'] = [
      '#type' => 'select',
      '#title' => $this->t('Controls position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $this->getSetting('controlsPosition'),
      '#description' => $this->t('Display controls above/below slides.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][controls]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Controls text prev.
    $element['controlsTextPrev'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prev Text'),
      '#default_value' => $this->getSetting('controlsTextPrev'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][controls]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Controls text next.
    $element['controlsTextNext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Next Text'),
      '#default_value' => $this->getSetting('controlsTextNext'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_image][settings_edit_form][settings][controls]"]' => [
            'checked' => TRUE
          ],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];

    // Slide by.
    $element['slideBy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slide by'),
      '#default_value' => $this->getSetting('slideBy'),
      '#description' => $this->t('Number of slides going on one "click". Enter a positive number or "page" to slide one page at a time.'),
      '#element_validate' => [
        [$this, 'settingsFormSlideByValidate'],
      ],
      '#states' => $hideOnAdvancedMode,
    ];

    // arrowKeys.
    $element['arrowKeys'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Arrow Keys'),
      '#default_value' => $this->getSetting('arrowKeys'),
      '#description' => $this->t('Allows using arrow keys to switch slides.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // mouseDrag.
    $element['mouseDrag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mouse Drag'),
      '#default_value' => $this->getSetting('mouseDrag'),
      '#description' => $this->t('Turn off/on mouse drag.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Loop.
    $element['loop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Loop'),
      '#default_value' => $this->getSetting('loop'),
      '#description' => $this->t('Moves throughout all the slides seamlessly.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Center.
    $element['center'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Center'),
      '#default_value' => $this->getSetting('center'),
      '#description' => $this->t('Center the active slide in the viewport.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Speed.
    $element['speed'] = [
      '#type' => 'number',
      '#title' => $this->t('Speed'),
      '#default_value' => $this->getSetting('speed'),
      '#description' => $this->t('Pagination speed in milliseconds.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // DimensionMobile.
    $element['dimensionMobile'] = [
      '#type' => 'number',
      '#title' => $this->t('Mobile dimension'),
      '#default_value' => $this->getSetting('dimensionMobile'),
      '#description' => $this->t('Set the mobile dimensions in px.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // ItemsMobile.
    $element['itemsMobile'] = [
      '#type' => 'number',
      '#step' => '.1',
      '#title' => $this->t('Mobile items'),
      '#default_value' => $this->getSetting('itemsMobile'),
      '#description' => $this->t('Maximum amount of items displayed at mobile.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // DimensionDesktop.
    $element['dimensionDesktop'] = [
      '#type' => 'number',
      '#title' => $this->t('Desktop dimension'),
      '#default_value' => $this->getSetting('dimensionDesktop'),
      '#description' => $this->t('Set the desktop dimensions in px.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // itemsDesktop.
    $element['itemsDesktop'] = [
      '#type' => 'number',
      '#step' => '.1',
      '#title' => $this->t('Desktop items'),
      '#default_value' => $this->getSetting('itemsDesktop'),
      '#description' => $this->t('Maximum amount of items displayed at desktop.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Advanced mode.
    $element['advancedMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Advanced mode'),
      '#default_value' => $this->getSetting('advancedMode'),
      '#description' => $this->t('In advanced mode you can use JSON object to override config.'),
      '#attributes' => ['class' => ['tns--toggle-advanced-mode']],
    ];

    $json_config_current = $this->getSetting('configJson') ?: '[]';

    $element['configJson'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Use a JSON object to configure the slider'),
      '#default_value' => json_encode(json_decode($json_config_current, JSON_PRETTY_PRINT)),
      '#description' => $this->t('Tiny Slider configuration expressed as JSON.<br />
        <b>WARNING:</b> this will override any existing settings'),
      '#rows' => 30,
      '#states' => [
        'visible' => [
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => TRUE],
        ],
      ],
    ];

    return $element + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $itemsdisplay = $this->getSetting('items') ? $this->getSetting('items') : 3;
    $mode = $this->getSetting('mode') ? $this->getSetting('mode') : 'carousel';
    $nav = $this->getSetting('nav') ? 'TRUE' : 'FALSE';
    $navposition = $this->getSetting('navPosition') ? $this->getSetting('navPosition') : 'top';
    $navasthumbnails = $this->getSetting('navAsThumbnails') ? 'TRUE' : 'FALSE';
    $autoplay = $this->getSetting('autoplay') ? 'TRUE' : 'FALSE';
    $autoplaypause = $this->getSetting('autoplayHoverPause') ? 'TRUE' : 'FALSE';
    $autoplaybuttonoutput = $this->getSetting('autoplayButtonOutput') ? 'FALSE' : 'FALSE';
    $autoplayposition = $this->getSetting('autoplayPosition') ? $this->getSetting('autoPlayPosition') : 'top';
    $autoplaytextstart = $this->getSetting('autoplayTextStart') ? $this->getSetting('autoplayTextStart') : 'start';
    $autoplaytextstop = $this->getSetting('autoplayTextStop') ? $this->getSetting('autoplayTextStop') : 'stop';
    $controls = $this->getSetting('controls') ? 'TRUE' : 'FALSE';
    $controlsplayposition = $this->getSetting('controlsPosition') ? $this->getSetting('controlsPosition') : 'top';
    $controlstextprev = $this->getSetting('controlsTextPrev') ? $this->getSetting('controlsTextPrev') : 'prev';
    $controlstextnext = $this->getSetting('controlsTextNext') ? $this->getSetting('controlsTextNext') : 'next';
    $slideby = $this->getSetting('slideBy') ? $this->getSetting('slideBy') : 'page';
    $arrowkeys = $this->getSetting('arrowKeys') ? 'TRUE' : 'FALSE';
    $mousedrag = $this->getSetting('mouseDrag') ? 'TRUE' : 'FALSE';
    $loop = $this->getSetting('loop') ? 'TRUE' : 'FALSE';
    $center = $this->getSetting('center') ? 'TRUE' : 'FALSE';
    $speed = $this->getSetting('speed') ? $this->getSetting('speed') : '300';
    $advancedMode = $this->getSetting('advancedMode') ? 'TRUE' : 'FALSE';
    $json_config_default = json_encode($this->getSetting('configJson') ?: [], JSON_PRETTY_PRINT);

    if ($this->getSetting('advancedMode')) {
      $summary[] = $this->t('Advanced mode enabled: ') . $advancedMode ;
      $summary[] = $this->t('Config: ') . $this->getSetting('configJson') ?: $json_config_default;
    }
    else {
      $summary[] = $this->t('TinySlider settings summary.');
      $summary[] = $this->t('Image style: ') . $this->getSetting('image_style');

      $responsiveImageStyleSetting = $this->getSetting('responsive_image_style');
      if (\Drupal::moduleHandler()->moduleExists('responsive_image')) {
        $styleName = !empty($responsiveImageStyleSetting) ? $responsiveImageStyleSetting : $this->t('None');
        $summary[] = $this->t('Responsive image style: @style', ['@style' => $styleName]);
      }

      $summary[] = $this->t('Link image to: ') . $this->getSetting('image_link') ?? $this->t('Nothing');
      $summary[] = $this->t('Amount of items displayed: ') . $itemsdisplay;
      $summary[] = $this->t('Gutter from items: ') . $this->getSetting('gutter') . 'px';
      $summary[] = $this->t('Mode: ') . $mode;
      $summary[] = $this->t('Display nav: ') . $nav;
      $summary[] = $this->t('Nav position: ') . $navposition;
      $summary[] = $this->t('Nav as thumbnails: ') . $navasthumbnails;
      $summary[] = $this->t('Autoplay: ') . $autoplay;
      $summary[] = $this->t('Autoplay pause on mouse hover: ') . $autoplaypause;
      $summary[] = $this->t('Show autoplay buttons: ') . $autoplaybuttonoutput;
      $summary[] = $this->t('Start text: ') . $autoplaytextstart;
      $summary[] = $this->t('Stop text: ') . $autoplaytextstop;
      $summary[] = $this->t('Show controls: ') . $controls;
      $summary[] = $this->t('Prev text: ') . $controlstextprev;
      $summary[] = $this->t('Next text: ') . $controlstextnext;
      $summary[] = $this->t('Slide by: ') . $slideby;
      $summary[] = $this->t('Arrow keys: ') . $arrowkeys;
      $summary[] = $this->t('Mouse drag: ') . $mousedrag;
      $summary[] = $this->t('Loop: ') . $loop;
      $summary[] = $this->t('Center: ') . $center;
      $summary[] = $this->t('Speed: ') . $speed . 'ms';
      $summary[] = $this->t('Advanced mode enabled: ') . $advancedMode ;
    }

    if ($this->getSetting('dimensionMobile')) {
      $summary[] = $this->t('Mobile dimensions: ') . $this->getSetting('dimensionMobile') . 'px';
    }

    if ($this->getSetting('itemsMobile')) {
      $summary[] = $this->t('Mobile items to show: ') . $this->getSetting('itemsMobile');
    }

    if ($this->getSetting('dimensionDesktop')) {
      $summary[] = $this->t('Desktop dimensions: ') . $this->getSetting('dimensionDesktop') . 'px';
    }

    if ($this->getSetting('itemsDesktop')) {
      $summary[] = $this->t('Desktop items to show: ') . $this->getSetting('itemsDesktop');
    }

    return $summary;
  }

  public function settingsFormSlideByValidate($element, FormStateInterface $form_state)
  {
    $submitted_value = $form_state->getValue($element['#parents']);
    if (!preg_match('~[0-9]+~', $submitted_value) && ($submitted_value !== 'page')) {
      $form_state->setError($element, $this->t('@value is not right. The "Slide by" value should either be an Integer or equal to the term "page".', ['@value' => $submitted_value]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = [];

    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->toUrl()->toString();
      }
    }
    elseif ($image_link_setting == 'file') {
      $link_file = TRUE;
    }


    // Image style settings – standard and responsive.
    $responsive_image_style_setting = $this->getSetting('responsive_image_style');
    $image_style_setting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $cache_tags = [];
    if (!empty($image_style_setting)) {
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $cache_tags = $image_style->getCacheTags();
    }

    foreach ($files as $delta => $file) {
      if (isset($link_file)) {
        $image_uri = $this->getEntityFileUrl($file);
        $url = \Drupal::service('file_url_generator')->generate($image_uri);
      }
      $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());

      $item_info = $this->getEntityItem($file);

      if (\Drupal::moduleHandler()->moduleExists('responsive_image') && !empty($responsive_image_style_setting)) {
        // Use the responsive image style theme function.
        $elements[$delta] = [
          '#theme' => 'responsive_image',
          '#responsive_image_style_id' => $responsive_image_style_setting,
          '#item' => $item_info['item'],
          '#item_attributes' => $item_info['attributes'],
          '#uri' => $file->getFileUri(),
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      } elseif (!empty($image_style_setting)) {
        // Use the standard image style theme function.
        $elements[$delta] = [
          '#theme' => 'image_formatter',
          '#item' => $item_info['item'],
          '#item_attributes' => $item_info['attributes'],
          '#image_style' => $image_style_setting,
          '#url' => $url,
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
    }

    $tiny_slider_default_settings = TinySliderGlobal::defaultSettings();
    $settings = $tiny_slider_default_settings;

    foreach ($settings as $k => $v) {
      $s = $this->getSetting($k);
      $settings[$k] = isset($s) ? $s : $settings[$k];
    }

    // If config JSON is set use that as real config.
    if (TinySliderGlobal::isValidJson($settings['configJson'])) {
      $jsonConfig = json_decode($settings['configJson'], JSON_PRETTY_PRINT);
      $settings = $jsonConfig;
    }

    $settings['uniqueFieldID'] = $this->uniqueFieldID;

    return [
      '#theme' => 'tiny_slider',
      '#items' => $elements,
      '#settings' => $settings,
      '#attached' => ['library' => ['tiny_slider/tiny_slider']],
    ];

  }

  /**
   * Get the FieldItem
   *
   * @param EntityInterface $entity
   *   The file or media entity.
   *
   * @return mixed[]
   *   'item' is a \Drupal\Core\Field\FieldItemInterface.
   *   'attributes' is an array of attributes.
   */
  protected function getEntityItem(EntityInterface $entity) {
    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    $item = $entity->_referringItem;
    $item_attributes = $item->_attributes;
    unset($item->_attributes);

    if ($entity->getEntityTypeId() === 'media') {
      $file_id = $entity->getSource()->getSourceFieldValue($entity);
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_id);
      // @see BlazyFormatterBase::getEntitiesToView().
      $source_field = $entity->getSource()->getConfiguration()['source_field'];
      $file_meta = $entity->get($source_field)->getValue()[0] ?? [];
      $item = (object) [
        'target_id' => $file->id(),
        'alt' => $file_meta['alt'] ?? '',
        'title' => $file_meta['title'] ?? '',
        'width' => intval($file_meta['width'] ?? '0'),
        'height' => intval($file_meta['height'] ?? '0'),
        'entity' => $file,
        '_loaded' => TRUE,
        '_is_default' => TRUE,
      ];
    }

    return [
      'item' => $item,
      'attributes' => $item_attributes,
    ];
  }

  /**
   * Fetch the URL of the file attached to this entity.
   *
   * Works with file or media entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Url|null
   *   The file URL if available.
   */
  protected function getEntityFileUrl(EntityInterface $entity): ?Url {
    if ($entity->getEntityTypeId() === 'file') {
      return $entity->getFileUri();
    }
    if ($entity->getEntityTypeId() === 'media') {
      $file_id = $entity->getSource()->getSourceFieldValue($entity);
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_id);
      return $file->getFileUri();
    }
    return NULL;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return nl2br(Html::escape($item->value));
  }


  /**
   * Retrieves a list of all responsive image styles.
   * 
   * This gathers all responsive image styles available in the system
   * and returns them in an associative array. The keys of the array are the
   * machine names of the image styles, and the values are their human-readable
   * labels.
   *
   * @return array
   *   The list of responsive image styles.
   */
  protected function getResponsiveImageStyles() {
    $styles = [];
    $responsive_styles = \Drupal::entityTypeManager()->getStorage('responsive_image_style')->loadMultiple();
    foreach ($responsive_styles as $style_name => $style) {
      $styles[$style_name] = $style->label();
    }
    return $styles;
  }

}
