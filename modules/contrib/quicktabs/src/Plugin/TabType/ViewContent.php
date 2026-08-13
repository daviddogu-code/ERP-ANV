<?php

namespace Drupal\quicktabs\Plugin\TabType;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\quicktabs\Attribute\TabType;
use Drupal\quicktabs\QuickTabsAjaxSourceRequestTrait;
use Drupal\quicktabs\TabTypeBase;
use Drupal\views\Plugin\views\display\DisplayPluginInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'view content' tab type.
 */
#[TabType(
  id: 'view_content',
  name: new TranslatableMarkup('view'),
)]
class ViewContent extends TabTypeBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use QuickTabsAjaxSourceRequestTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected CurrentPathStack $currentPath,
    protected RequestStack $requestStack,
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccessAwareRouterInterface $router,
    protected RendererInterface $renderer,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('router'),
      $container->get('renderer'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function optionsForm(array $tab): array {
    $plugin_id = $this->getPluginDefinition()['id'];
    $views = $this->getViews();
    $views_keys = array_keys($views);
    $selected_view = ($tab['content'][$plugin_id]['options']['vid'] ?? ($views_keys[0] ?? ''));

    $form = [];
    $form['vid'] = [
      '#type' => 'select',
      '#options' => $views,
      '#default_value' => $selected_view,
      '#title' => $this->t('Select a view'),
      '#ajax' => [
        'callback' => [static::class, 'viewsDisplaysAjaxCallback'],
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => 'Please wait...',
        ],
        'effect' => 'fade',
      ],
    ];
    $form['display'] = [
      '#type' => 'select',
      '#title' => 'display',
      '#options' => ViewContent::getViewDisplays($selected_view),
      '#default_value' => $tab['content'][$plugin_id]['options']['display'] ?? '',
      '#prefix' => '<div id="view-display-dropdown-' . $tab['delta'] . '">',
      '#suffix' => '</div>',
    ];
    $form['args'] = [
      '#type' => 'textfield',
      '#title' => 'arguments',
      '#size' => '40',
      '#required' => FALSE,
      '#default_value' => $tab['content'][$plugin_id]['options']['args'] ?? '',
      '#description' => $this->t('Additional arguments to send to the view as if they were part of the URL in the form of arg1/arg2/arg3. You may use %1, %2, ..., %N to grab arguments from the URL.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $tab) {
    $source_path = $this->getSourcePath();
    $source_request = NULL;
    if ($this->isAjaxRequest()) {
      $source_request = $this->createSourceRequest($source_path);
      $this->requestStack->push($source_request);
    }

    try {
      return $this->doRender($tab, $source_path, $source_request);
    }
    finally {
      if ($source_request) {
        $this->requestStack->pop();
      }
    }
  }

  /**
   * Renders the tab content.
   */
  protected function doRender(array $tab, string $source_path, ?Request $source_request = NULL) {
    $options = $tab['content'][$tab['type']]['options'];
    $args = empty($options['args']) ? [] : array_map('trim', explode('/', $options['args']));

    $url_args = explode('/', $source_path);
    foreach ($url_args as $id => $arg) {
      $args = str_replace("%$id", $arg, $args);
    }
    $args = preg_replace(',/?(%\d),', '', $args);

    $view = Views::getView($options['vid']);

    // Return an empty render array if the user doesn't have access. It still
    // carries the display's cacheability, which includes the access plugin's
    // cache contexts, so the tabset is not cached as empty for users who are
    // allowed to see the view.
    if (!$view instanceof ViewExecutable) {
      return [];
    }
    if (!$view->access($options['display'], $this->currentUser)) {
      return $this->emptyRender($view, $options['display']);
    }

    if ($source_request) {
      $view->setRequest($source_request);
    }

    $view->setArguments($args);

    // Set the display.
    if (!isset($view->current_display)) {
      $view->initDisplay();
    }

    $view->setDisplay($options['display']);

    // Execute the view.
    $view->preExecute();
    $view->execute();

    $view_results = $view->result;

    if (!$view_results && !empty($options['vid']) && !empty($options['display'])) {
      // If the initial view is empty, check the attachments.
      $view = Views::getView($options['vid']);
      $view->setDisplay($options['display']);
      $display = $view->getDisplay();
      $attachments = $display->getAttachedDisplays();

      // If there are attachments, check if they are empty.
      if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
          if (!empty($this->getViewResult($options['vid'], $attachment))) {
            $view_results = TRUE;
          }
        }
      }

      if (!$display->outputIsEmpty()) {
        $view_results = TRUE;
      }
    }

    if (empty($view_results)) {
      // No content to show, but the view's cache tags must still be recorded
      // so that adding content which populates the view re-renders the tab.
      return $this->emptyRender($view, $options['display']);
    }
    else {
      $render = $view->buildRenderable($options['display'], $args);

      $this->addContextualLinks($render, $view, $options['display']);

      if ($source_request) {
        // Render eagerly while the source request is on the stack, but keep the
        // bubbled cacheability so the AJAX response still carries the view's
        // cache tags/contexts.
        $markup = Markup::create((string) $this->renderer->renderRoot($render));
        return [
          '#markup' => $markup,
          '#attached' => $render['#attached'] ?? [],
          '#cache' => $render['#cache'] ?? [],
        ];
      }
    }

    return $render;
  }

  /**
   * Builds a contentless render array carrying the view's cacheability.
   *
   * Mirrors what Views itself applies to a rendered display, so that a tab
   * which renders as empty is still invalidated and varied correctly.
   *
   * @see \Drupal\views\Plugin\views\display\DisplayPluginBase::applyDisplayCacheabilityMetadata()
   */
  protected function emptyRender(ViewExecutable $view, string $display_id): array {
    if (!isset($view->current_display)) {
      $view->initDisplay();
    }
    $display = $view->displayHandlers->has($display_id)
      ? $view->displayHandlers->get($display_id)
      : $view->display_handler;

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags($view->getCacheTags());
    if ($display instanceof DisplayPluginInterface) {
      $cacheability = $cacheability->merge($display->getCacheMetadata());
    }

    $render = [];
    $cacheability->applyTo($render);

    return $render;
  }

  /**
   * Gets the result of a view display.
   */
  protected function getViewResult(string $view_id, ?string $display_id = NULL): array {
    $view = Views::getView($view_id);
    if (!$view instanceof ViewExecutable) {
      return [];
    }

    if ($display_id !== NULL) {
      $view->setDisplay($display_id);
    }
    else {
      $view->initDisplay();
    }

    $view->preExecute();
    $view->execute();

    return $view->result;
  }

  /**
   * Adds contextual links for a view display to a render element.
   *
   * @param array $render
   *   The renderable array to which contextual links will be added.
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   * @param string $displayId
   *   The ID of the view display.
   */
  protected function addContextualLinks(array &$render, ViewExecutable $view, string $displayId): void {
    if (!$this->moduleHandler->moduleExists('contextual') || !$view->getShowAdminLinks()) {
      return;
    }

    $pluginDefinition = $view->display_handler->getPluginDefinition();
    $location = $pluginDefinition['contextual_links_locations'][0] ?? 'view';

    $render['#view_id'] = $view->storage->id();
    $render['#view_display_show_admin_links'] = $view->getShowAdminLinks();
    $render['#view_display_plugin_id'] = $view->display_handler->getPluginId();

    $plugin = Views::pluginManager('display')->getDefinition($view->display_handler->getPluginId());
    $plugin['contextual_links_locations'] = array_filter($plugin['contextual_links_locations'] ?? ['view']);
    $plugin['contextual_links_locations'][] = 'exposed_filter';

    if (empty($plugin['contextual links']) || !in_array($location, $plugin['contextual_links_locations'])) {
      return;
    }

    $viewId = $view->storage->id();
    $viewStorage = $this->entityTypeManager->getStorage('view')->load($viewId);

    foreach ($plugin['contextual links'] as $group => $link) {
      $args = [];
      $valid = TRUE;
      if (!empty($link['route_parameters_names'])) {
        foreach ($link['route_parameters_names'] as $parameterName => $property) {
          if (!property_exists($viewStorage, $property)) {
            $valid = FALSE;
            break;
          }
          else {
            $args[$parameterName] = $viewStorage->get($property);
          }
        }
      }

      if (!$valid) {
        continue;
      }

      $render['#views_contextual_links'] = TRUE;
      $render['#contextual_links'][$group] = [
        'route_parameters' => $args,
        'metadata' => [
          'location' => $location,
          'name' => $viewId,
          'display_id' => $displayId,
        ],
      ];
    }

    if (!empty($render['#views_contextual_links'])) {
      $render['#cache']['contexts'][] = 'user.permissions';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(array $tab, array $render, bool $is_ajax): bool {
    // AJAX Views tabs render as loading placeholders, so execute the View to
    // decide whether the tab content is actually empty.
    if ($is_ajax) {
      $render = $this->render($tab);
    }

    $view = $render['#view'] ?? NULL;
    if (!$view instanceof ViewExecutable) {
      return TRUE;
    }

    $arguments = $render['#arguments'] ?? NULL;
    if ($arguments) {
      $view->setArguments($arguments);
    }
    $view->preExecute();
    $view->execute();

    return empty($view->result) || empty($render);
  }

  /**
   * Ajax callback to change views displays when view is selected.
   */
  public static function viewsDisplaysAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $tab_index = $form_state->getTriggeringElement()['#array_parents'][2];
    $element_id = '#view-display-dropdown-' . $tab_index;
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new ReplaceCommand($element_id, $form['configuration_data_wrapper']['configuration_data'][$tab_index]['content']['view_content']['options']['display']));

    return $ajax_response;
  }

  /**
   * Get list of enabled views.
   */
  private function getViews(): array {
    $views = [];
    foreach (Views::getEnabledViews() as $view_name => $view) {
      $views[$view_name] = $view->label() . ' (' . $view_name . ')';
    }

    ksort($views);
    return $views;
  }

  /**
   * Get displays for a given view.
   */
  public function getViewDisplays($view_name): array {
    $displays = [];
    if (empty($view_name)) {
      return $displays;
    }

    $view = $this->entityTypeManager->getStorage('view')->load($view_name);
    if (!$view) {
      return $displays;
    }
    foreach ($view->get('display') as $id => $display) {
      $enabled = !empty($display['display_options']['enabled']) || !array_key_exists('enabled', $display['display_options']);
      if ($enabled) {
        $displays[$id] = $id . ': ' . $display['display_title'];
      }
    }

    return $displays;
  }

}
