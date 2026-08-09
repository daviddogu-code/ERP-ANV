<?php

namespace Drupal\tiny_slider\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render each item into TinySlider.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *     id = "tiny_slider",
 *     title = @Translation("TinySlider"),
 *     help = @Translation("Displays rows as Tiny Slider."),
 *     theme = "tiny_slider_views",
 *     display_types = {"normal"}
 * )
 */
class TinySlider extends StylePluginBase {

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $settings = _tiny_slider_default_settings();
    if(is_array($settings)) {
      foreach ($settings as $k => $v) {
        $options[$k] = ['default' => $v];
      }
    }

    return $options;
  }

  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $hideOnAdvancedMode = [
      'visible' => [
        ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
      ],
    ];

    // Items.
    $form['items'] = [
      '#type' => 'number',
      '#step' => '.1',
      '#title' => $this->t('Items'),
      '#default_value' => isset($this->options['items']) ? $this->options['items'] : '',
      '#description' => $this->t('Maximum amount of items displayed at a time with the widest browser width.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // Gutter.
    $form['gutter'] = [
      '#type' => 'number',
      '#title' => $this->t('Gutter'),
      '#default_value' => isset($this->options['gutter']) ? $this->options['gutter'] : '',
      '#description' => $this->t('Gutter from items.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // Mode.
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => [
        'carousel' => $this->t('Carousel'),
        'gallery' => $this->t('Gallery'),
      ],
      '#default_value' => isset($this->options['mode']) ? $this->options['mode'] : '',
      '#description' => $this->t('With carousel everything slides to the side, while gallery uses fade animations and changes all slides at once.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // Navigation.
    $form['nav'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Navigation'),
      '#default_value' => isset($this->options['nav']) ? $this->options['nav'] : '',
      '#description' => $this->t('Controls the display and functionalities of nav components (dots).'),
      '#states' => $hideOnAdvancedMode,
    ];
    // navPosition.
    $form['navPosition'] = [
      '#type' => 'select',
      '#title' => $this->t('Navigation position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => isset($this->options['navPosition']) ? $this->options['navPosition'] : '',
      '#description' => $this->t('Display navigation above/below slides.'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[nav]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // navAsThumbnails.
    $form['navAsThumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Navigation as thumbnails'),
      '#default_value' => isset($this->options['navAsThumbnails']) ? $this->options['navAsThumbnails'] : '',
      '#description' => $this->t('Use image thumbnails in navigation instead of dots.'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[nav]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // Autoplay.
    $form['autoplay'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autoplay'),
      '#default_value' => isset($this->options['autoplay']) ? $this->options['autoplay'] : '',
      '#description' => $this->t('Toggles the automatic change of slides.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // AutoplayHoverPause.
    $form['autoplayHoverPause'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause on hover'),
      '#default_value' => $this->options['autoplayHoverPause'],
      '#description' => $this->t('Pause autoplay on mouse hover.'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[autoplay]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // AutoplayButtonOutput.
    $form['autoplayButtonOutput'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autoplay buttons'),
      '#default_value' => isset($this->options['autoplayButtonOutput']) ? $this->options['autoplayButtonOutput'] : '',
      '#description' => $this->t('Turn off/on arrow autoplay buttons.'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[autoplay]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // autoplayPosition.
    $form['autoplayPosition'] = [
      '#type' => 'select',
      '#title' => $this->t('Autoplay position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => isset($this->options['autoplayPosition']) ? $this->options['autoplayPosition'] : '',
      '#description' => $this->t('Display autoplay controls above/below slides.'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[autoplay]"]' => ['checked' => TRUE],
          ':input[name="style_options[autoplayButtonOutput]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // Autoplay text start.
    $form['autoplayTextStart'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start Text'),
      '#default_value' => isset($this->options['autoplayTextStart']) ? $this->options['autoplayTextStart'] : '',
      '#states' => [
        'visible' => [
          ':input[name="style_options[autoplay]"]' => ['checked' => TRUE],
          ':input[name="style_options[autoplayButtonOutput]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // Autoplay text stop.
    $form['autoplayTextStop'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stop Text'),
      '#default_value' => isset($this->options['autoplayTextStop']) ? $this->options['autoplayTextStop'] : '',
      '#states' => [
        'visible' => [
          ':input[name="style_options[autoplay]"]' => ['checked' => TRUE],
          ':input[name="style_options[autoplayButtonOutput]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // Controls.
    $form['controls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Controls'),
      '#default_value' => isset($this->options['controls']) ? $this->options['controls'] : '',
      '#description' => $this->t('Controls the display and functionalities of (prev/next buttons).'),
      '#states' => $hideOnAdvancedMode,
    ];
    // controlsPosition.
    $form['controlsPosition'] = [
      '#type' => 'select',
      '#title' => $this->t('Controls position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => isset($this->options['controlsPosition']) ? $this->options['controlsPosition'] : '',
      '#description' => $this->t('Display controls above/below slides.'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[controls]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // Controls text prev.
    $form['controlsTextPrev'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prev Text'),
      '#default_value' => isset($this->options['controlsTextPrev']) ? $this->options['controlsTextPrev'] : '',
      '#states' => [
        'visible' => [
          ':input[name="style_options[controls]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // Controls next text.
    $form['controlsTextNext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Next Text'),
      '#default_value' => isset($this->options['controlsTextNext']) ? $this->options['controlsTextNext'] : '',
      '#states' => [
        'visible' => [
          ':input[name="style_options[controls]"]' => ['checked' => TRUE],
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => FALSE],
        ],
      ],
    ];
    // Slide by.
    $form['slideBy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slide by'),
      '#default_value' => isset($this->options['slideBy']) ? $this->options['slideBy'] : '',
      '#description' => $this->t('Number of slides going on one "click". Enter a positive number or "page" to slide one page at a time.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // arrowKeys.
    $form['arrowKeys'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Arrow Keys'),
      '#default_value' => isset($this->options['arrowKeys']) ? $this->options['arrowKeys'] : '',
      '#description' => $this->t('Allows using arrow keys to switch slides.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // mouseDrag.
    $form['mouseDrag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mouse Drag'),
      '#default_value' => isset($this->options['mouseDrag']) ? $this->options['mouseDrag'] : '',
      '#description' => $this->t('Turn off/on mouse drag.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // Loop.
    $form['loop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Loop'),
      '#default_value' => isset($this->options['loop']) ? $this->options['loop'] : '',
      '#description' => $this->t('Moves throughout all the slides seamlessly.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // Center.
    $form['center'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Center'),
      '#default_value' => isset($this->options['center']) ? $this->options['center'] : '',
      '#description' => $this->t('Center the active slide in the viewport.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // Speed.
    $form['speed'] = [
      '#type' => 'number',
      '#title' => $this->t('Speed'),
      '#default_value' => isset($this->options['speed']) ? $this->options['speed'] : '',
      '#description' => $this->t('Pagination speed in milliseconds.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // DimensionMobile.
    $form['dimensionMobile'] = [
      '#type' => 'number',
      '#title' => $this->t('Mobile dimension'),
      '#default_value' => isset($this->options['dimensionMobile']) ? $this->options['dimensionMobile'] : '',
      '#description' => $this->t('Set the mobile dimensions in px.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // ItemsMobile.
    $form['itemsMobile'] = [
      '#type' => 'number',
      '#step' => '.1',
      '#title' => $this->t('Mobile items'),
      '#default_value' => isset($this->options['itemsMobile']) ? $this->options['itemsMobile'] : '',
      '#description' => $this->t('Maximum amount of items displayed at mobile.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // DimensionDesktop.
    $form['dimensionDesktop'] = [
      '#type' => 'number',
      '#title' => $this->t('Desktop dimension'),
      '#default_value' => isset($this->options['dimensionDesktop']) ? $this->options['dimensionDesktop'] : '',
      '#description' => $this->t('Set the desktop dimension in px.'),
      '#states' => $hideOnAdvancedMode,
    ];
    // itemsDesktop.
    $form['itemsDesktop'] = [
      '#type' => 'number',
      '#step' => '.1',
      '#title' => $this->t('Desktop items'),
      '#default_value' => isset($this->options['itemsDesktop']) ? $this->options['itemsDesktop'] : '',
      '#description' => $this->t('Maximum amount of items displayed at desktop.'),
      '#states' => $hideOnAdvancedMode,
    ];

    // Advanced mode.
    $form['advancedMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Advanced mode'),
      '#default_value' => $this->options['advancedMode'],
      '#description' => $this->t('In advanced mode you can use JSON object to override config.'),
      '#attributes' => ['class' => ['tns--toggle-advanced-mode']],
    ];

    $form['configJson'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Use a JSON object to configure the slider'),
      '#default_value' => json_encode(json_decode($this->options['configJson']), JSON_PRETTY_PRINT),
      '#description' => $this->t('Tiny Slider configuration expressed as JSON.<br />
        <b>WARNING:</b> this will override any existing settings'),
      '#rows' => 30,
      '#states' => [
        'visible' => [
          ':input[type="checkbox"].tns--toggle-advanced-mode' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);
    $styleOptions = $form_state->getValue('style_options')['slideBy'];
    if (!preg_match('~[0-9]+~', $styleOptions) && ($styleOptions !== 'page')) {
      $form_state->setError($form['slideBy'], $this->t('@value is not right. The "Slide by" value should either be an Integer or equal to the term "page".', ['@value' => $styleOptions]));
    }
  }

}
