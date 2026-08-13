<?php

namespace Drupal\quicktabs\Plugin\TabRenderer;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\quicktabs\Attribute\TabRenderer;
use Drupal\quicktabs\EmptyTabTypeInterface;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\quicktabs\QuickTabsAjax;
use Drupal\quicktabs\TabRendererBase;
use Drupal\quicktabs\TabTypeInterface;
use Drupal\quicktabs\TabTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'QuickTabs' tab renderer.
 */
#[TabRenderer(
  id: 'quick_tabs',
  name: new TranslatableMarkup('quicktabs'),
)]
class QuickTabs extends TabRendererBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected TabTypeManager $tabTypeManager, protected TransliterationInterface $transliteration, protected LanguageManagerInterface $languageManager, protected CurrentPathStack $currentPath, protected RequestStack $requestStack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.tab_type'),
      $container->get('transliteration'),
      $container->get('language_manager'),
      $container->get('path.current'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function optionsForm(QuickTabsInstance $instance): array {
    $instance_options = $instance->getOptions();
    $options = $instance_options['quick_tabs'] ?? [];
    $renderer = $instance->getRenderer();
    $form['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Tab Class'),
      '#description' => $this->t('Additional classes to provide on each tab. Separated by a space.'),
      '#default_value' => ($renderer == 'quick_tabs' && isset($options['class']) && $options['class']) ? $options['class'] : '',
      '#weight' => -8,
    ];
    $form['style'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        '' => $this->t('-None-'),
        'pamela' => $this->t('Pamela'),
        'on-the-gray' => $this->t('On the Gray'),
        'tabsbar' => $this->t('Tabs Bar'),
        'material-tabs' => $this->t('Material Tabs'),
      ],
      '#default_value' => ($renderer == 'quick_tabs' && isset($options['style']) && $options['style']) ? $options['style'] : '',
      '#weight' => -7,
    ];
    $form['ajax'] = [
      '#type' => 'radios',
      '#title' => $this->t('Ajax'),
      '#options' => [
        TRUE => $this->t('Yes: Load only the first tab on page view'),
        FALSE => $this->t('No: Load all tabs on page view.'),
      ],
      '#default_value' => ($renderer == 'quick_tabs' && $options['ajax'] !== NULL) ? intval($options['ajax']) : 0,
      '#description' => $this->t('Choose how the content of tabs should be loaded.<p>By choosing "Yes", only the first tab will be loaded when the page first viewed. Content for other tabs will be loaded only when the user clicks the other tab. This will provide faster initial page loading, but subsequent tab clicks will be slower. This can place less load on a server.</p><p>By choosing "No", all tabs will be loaded when the page is first viewed. This will provide slower initial page loading, and more server load, but subsequent tab clicks will be faster for the user. Use with care if you have heavy views.</p><p>Warning: if you enable Ajax, any block you add to this quicktabs block will be accessible to anonymous users, even if you place role restrictions on the quicktabs block. Do not enable Ajax if the quicktabs block includes any blocks with potentially sensitive information.</p>'),
      '#weight' => -6,
    ];
    $form['direct_linking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Direct linking'),
      '#description' => $this->t('Enable direct linking to tabs via URL fragments. When enabled, users can link directly to specific tabs using URLs like example.com/page#my-custom-tab and the browser back/forward buttons will work with tab navigation.'),
      '#default_value' => ($renderer == 'quick_tabs' && isset($options['direct_linking'])) ? $options['direct_linking'] : TRUE,
      '#weight' => -5,
    ];
    return $form;
  }

  /**
   * Builds the Quicktabs render array for a block or page.
   */
  public function render(QuickTabsInstance $instance): array {
    $qt_id = $instance->id();

    // The render array used to build the block.
    $build = [];
    $build['pages'] = [];
    $build['pages']['#theme_wrappers'] = [
      'container' => [
        '#attributes' => [
          'class' => ['quicktabs-main'],
          'id' => 'quicktabs-container-' . $qt_id,
        ],
      ],
    ];

    // Pages of content that will be shown or hidden.
    $tab_pages = [];

    // Tabs used to show/hide content.
    $titles = [];

    // Carries information on all direct links being created.
    $direct_links = [];

    $is_ajax = $instance->getOptions()['quick_tabs']['ajax'];
    $style = $instance->getOptions()['quick_tabs']['style'] ?? '';
    $custom_class = $instance->getOptions()['quick_tabs']['class'] ?? '';
    $direct_linking = $instance->getOptions()['quick_tabs']['direct_linking'] ?? TRUE;
    // The AJAX link query is the same for every tab, so resolve it once.
    $ajax_link_query = $is_ajax ? $this->getAjaxLinkQuery() : [];
    foreach ($instance->getConfigurationData() as $index => $tab) {
      // Build the pages //////////////////////////////////////.
      $default_tab = $instance->getDefaultTab() == 9999 ? 0 : $instance->getDefaultTab();
      $object = $this->tabTypeManager->createInstance($tab['type']);
      $use_placeholder = $is_ajax && $default_tab != $index;

      if ($use_placeholder) {
        $render = [
          'loading' => [
            '#theme' => 'quicktabs_loading',
            '#message' => t('Loading content ...'),
            '#instance' => $instance,
            '#tabid' => $index,
          ],
        ];
      }
      else {
        $render = $object->render($tab);
      }

      // If user wants to hide empty tabs and there is no content, skip to the
      // next tab. Emptiness can only be judged from the real content, never
      // an AJAX loading placeholder, so build it here if that's all we have.
      if ($instance->getHideEmptyTabs()) {
        $content = $use_placeholder ? $object->render($tab) : $render;
        // Whatever decided the tab's presence — an access check, a view's
        // results — may not hold for the next visitor, so the tabset must
        // vary by and be invalidated by it, whether the tab ends up shown or
        // hidden. The loading placeholder itself carries none of this.
        CacheableMetadata::createFromRenderArray($build)
          ->merge(CacheableMetadata::createFromRenderArray($content))
          ->applyTo($build);

        if ($this->isTabEmpty($object, $tab, $content)) {
          continue;
        }
      }

      $classes = ['quicktabs-tabpage'];

      if ($default_tab != $index) {
        $classes[] = 'quicktabs-hide';
      }

      $render['#prefix'] = '<div class="quicktabs-tabpage-content">';
      $render['#suffix'] = '</div>';
      $block_id = 'quicktabs-tabpage-' . $qt_id . '-' . $index;
      $tab_id = 'quicktabs-tab-' . $qt_id . '-' . $index;

      if (!empty($tab['content'][$tab['type']]['options']['display_title']) && !empty($tab['content'][$tab['type']]['options']['block_title'])) {
        $build['pages'][$index]['#title'] = $tab['content'][$tab['type']]['options']['block_title'];
      }
      $build['pages'][$index]['#block'] = $render;
      $build['pages'][$index]['#classes'] = implode(' ', $classes);
      $build['pages'][$index]['#id'] = $block_id;
      $build['pages'][$index]['#tabid'] = $tab_id;
      $build['pages'][$index]['#theme'] = 'quicktabs_block_content';

      // Build the tabs ///////////////////////////////.
      $wrapper_attributes = [
        'role' => 'tab',
        'aria-controls' => $block_id,
        'aria-selected' => 'false',
        'id' => $tab_id,
      ];
      if ($default_tab == $index) {
        $wrapper_attributes['class'] = ['active'];
        $wrapper_attributes['aria-selected'] = 'true';
      }
      if ($custom_class) {
        $wrapper_attributes['class'][] = $custom_class;
      }
      $title_class = strip_tags($tab['title']);
      $wrapper_attributes['class'][] = strtolower(Html::cleanCssIdentifier($title_class));

      $link_classes = [];
      if ($is_ajax) {
        $link_classes[] = 'use-ajax';

        if ($default_tab == $index) {
          $link_classes[] = 'quicktabs-loaded';
        }
      }
      else {
        $link_classes[] = 'quicktabs-loaded';
      }

      // Filter the raw title to allow limited HTML. Avoid translating or
      // pre-escaping here to prevent double-encoding (e.g., "&" -> "&amp;").
      $value = Xss::filter(
        $tab['title'] ?? '',
        [
          'img',
          'em',
          'strong',
          'h2',
          'h3',
          'h4',
          'h5',
          'h6',
          'small',
          'span',
          'i',
          'br',
        ]
      );

      $tab_name = $this->createUniqueSlug($tab['title'], $direct_links);
      $link_options = [
        'attributes' => [
          'class' => $link_classes,
          'data-quicktabs-tab-index' => $index,
          'data-quicktabs-tab-name' => $tab_name,
          'tabindex' => $default_tab == $index ? 0 : -1,
          'rel' => 'noindex',
        ],
      ];
      if ($is_ajax) {
        $link_options['query'] = $ajax_link_query;
      }

      $titles[] = [
        '0' => Link::fromTextAndUrl(
          Markup::create($value),
          Url::fromRoute(
            'quicktabs.ajax_content',
            [
              'js' => 'nojs',
              'instance' => $qt_id,
              'tab' => $index,
            ],
            $link_options
          )
        )->toRenderable(),
        '#wrapper_attributes' => $wrapper_attributes,
      ];

      // Array of tab pages to pass as settings ////////////.
      $tab['tab_page'] = $index;
      $tab_pages[] = $tab;
    }

    $tabs = [
      '#theme' => 'item_list__quicktabs__' . $qt_id,
      '#items' => $titles,
      '#attributes' => [
        'class' => ['quicktabs-tabs'],
        'role' => 'tablist',
      ],
    ];

    // Add tabs to the build.
    array_unshift($build, $tabs);

    // Attach libraries.
    $libraries = ['quicktabs/quicktabs'];
    if ($style) {
      $libraries[] = 'quicktabs/' . $style;
    }
    $build['#attached'] = [
      'library' => $libraries,
      'drupalSettings' => [
        'quicktabs' => [
          'qt_' . $qt_id => [
            'tabs' => $tab_pages,
            'directLinking' => $direct_linking,
          ],
        ],
      ],
    ];

    // AJAX tab links embed the current page path and query string (see
    // ::getAjaxLinkQuery()), so the rendered output must vary by them.
    if ($is_ajax) {
      $build['#cache']['contexts'][] = 'url.path';
      $build['#cache']['contexts'][] = 'url.query_args';
    }

    // Add a wrapper.
    $classes = ['quicktabs-wrapper'];
    if ($style) {
      $classes[] = $style;
    }
    $build['#theme_wrappers'] = [
      'container' => [
        '#attributes' => [
          'class' => $classes,
          'data-remember-last' => intval($instance->getRememberLastClickedTab()),
          'id' => 'quicktabs-' . $qt_id,
        ],
      ],
    ];

    return $build;
  }

  /**
   * Gets query parameters to preserve on AJAX tab links.
   */
  protected function getAjaxLinkQuery(): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query->all() : [];
    foreach (QuickTabsAjax::filteredQueryParameters() as $parameter) {
      unset($query[$parameter]);
    }
    $query[QuickTabsAjax::SOURCE_PATH_QUERY] = $this->currentPath->getPath();

    return $query;
  }

  /**
   * Determines whether a tab has no content, given its real rendered output.
   *
   * $render must be the tab's actual content, never an AJAX loading
   * placeholder — callers resolve that before calling this. Passing FALSE for
   * "is ajax" tells implementations of EmptyTabTypeInterface not to re-render
   * internally: the real render is already in hand.
   */
  protected function isTabEmpty(TabTypeInterface $object, array $tab, array $render): bool {
    if ($object instanceof EmptyTabTypeInterface) {
      return $object->isEmpty($tab, $render, FALSE);
    }

    // A render array holding nothing but cacheability metadata has no content,
    // so it counts as empty.
    return empty(array_diff(array_keys($render), ['#cache']));
  }

  /**
   * Generates a unique slug for a tab name.
   */
  protected function createUniqueSlug(string $tab_name, array &$direct_links): string {
    // Transliterate and strip the tab name down to a URL-safe slug. Strip any
    // markup first so allowed inline tags in the title do not leak into the
    // slug.
    $langcode = $this->languageManager
      ->getCurrentLanguage()
      ->getId();
    $tab_name = strip_tags($tab_name);
    $tab_name = $this->transliteration
      ->transliterate($tab_name, $langcode);
    $tab_name = strtolower($tab_name);
    $tab_name = preg_replace('/[^a-z0-9_]+/', '-', $tab_name);
    $tab_name = preg_replace('/_+/', '-', $tab_name);
    $tab_name = trim($tab_name, '-');
    // Fall back to a generic base if the title had no slug-able characters.
    if ($tab_name === '') {
      $tab_name = 'tab';
    }

    $slug = $tab_name;
    $i = 1;
    while (in_array($slug, $direct_links, TRUE)) {
      $slug = $tab_name . '-' . $i;
      $i++;
    }
    $direct_links[] = $slug;
    return $slug;
  }

}
