<?php

namespace Drupal\quicktabs\Plugin\TabType;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quicktabs\Attribute\TabType;
use Drupal\quicktabs\QuickTabsAjaxSourceRequestTrait;
use Drupal\quicktabs\TabTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'block content' tab type.
 */
#[TabType(
  id: 'block_content',
  name: new TranslatableMarkup('block'),
)]
class BlockContent extends TabTypeBase implements ContainerFactoryPluginInterface {

  use MessengerTrait;
  use QuickTabsAjaxSourceRequestTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected BlockManagerInterface $blockManager,
    protected ContextRepositoryInterface $contextRepository,
    protected AccountProxyInterface $currentUser,
    protected CurrentPathStack $currentPath,
    protected RequestStack $requestStack,
    protected AccessAwareRouterInterface $router,
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
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.block'),
      $container->get('context.repository'),
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('router')
    );
  }

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function optionsForm(array $tab): array {
    $plugin_id = $this->getPluginDefinition()['id'];
    $form = [];
    $form['bid'] = [
      '#type' => 'select',
      '#options' => $this->getBlockOptions(),
      '#default_value' => $tab['content'][$plugin_id]['options']['bid'] ?? '',
      '#title' => $this->t('Select a block'),
      '#ajax' => [
        'callback' => [$this, 'blockTitleAjaxCallback'],
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => 'Please wait...',
        ],
        'effect' => 'fade',
      ],
    ];
    $form['block_title'] = [
      '#type' => 'textfield',
      '#default_value' => $tab['content'][$plugin_id]['options']['block_title'] ?? '',
      '#title' => $this->t('Block Title'),
      '#prefix' => '<div id="block-title-textfield-' . $tab['delta'] . '">',
      '#suffix' => '</div>',
    ];
    $form['display_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display block title'),
      '#default_value' => $tab['content'][$plugin_id]['options']['display_title'] ?? 0,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $tab) {
    $source_request = NULL;
    if ($this->isAjaxRequest()) {
      $source_request = $this->createSourceRequest($this->getSourcePath());
      $this->requestStack->push($source_request);
    }

    try {
      return $this->doRender($tab);
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
  protected function doRender(array $tab) {
    $options = $tab['content'][$tab['type']]['options'];
    $render = [];
    try {
      if (str_contains($options['bid'], 'block_content')) {
        $parts = explode(':', $options['bid']);
        $block = $this->entityRepository->loadEntityByUuid($parts[0], $parts[1]);
        $block_content = $this->entityTypeManager->getStorage('block_content')
          ->load($block->id());

        $access_result = $block_content->access('view', $this->currentUser, TRUE);
        if (!$access_result->isAllowed()) {
          $render = [];
          CacheableMetadata::createFromObject($access_result)
            ->addCacheableDependency($block_content)
            ->applyTo($render);
          return $render;
        }

        $render = $this->entityTypeManager->getViewBuilder('block_content')
          ->view($block_content);
        CacheableMetadata::createFromRenderArray($render)
          ->addCacheableDependency($access_result)
          ->applyTo($render);
      }
      else {
        // You can hard code configuration, or you load from settings.
        $config = [];
        $plugin_block = $this->blockManager->createInstance($options['bid'], $config);

        // Some blocks might implement access check. Only an explicitly allowed
        // result grants access: a neutral result is not a grant, and testing
        // isForbidden() here would render a block the user cannot see.
        $access_result = $plugin_block->access($this->currentUser, TRUE);
        // The empty render array still carries the access result's
        // cacheability, so the tabset is not cached as empty for users the
        // block is visible to.
        if (!$access_result->isAllowed()) {
          $render = [];
          CacheableMetadata::createFromObject($access_result)->applyTo($render);
          return $render;
        }
        $render = $plugin_block->build();
        // A block plugin declares its cacheability on the plugin object, not
        // only in the array ::build() returns, so the caller has to merge it in
        // before anything can render cache the result. Same for the access
        // result, which may itself vary per user.
        // @see \Drupal\block\BlockViewBuilder::viewMultiple()
        CacheableMetadata::createFromRenderArray($render)
          ->addCacheableDependency($plugin_block)
          ->addCacheableDependency($access_result)
          ->applyTo($render);
      }
    }
    catch (ContextException | InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException | PluginException$e) {
      $this->messenger()->addError($this->t('Unable to render block content with @id. @error',
        ['@id' => $options['bid'], '@error' => $e->getMessage()]));
    }

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(array $tab, array $render, bool $is_ajax): bool {
    if ($is_ajax) {
      $render = $this->render($tab);
    }

    return empty(array_diff(array_keys($render), ['#cache']));
  }

  /**
   * Get options for the block.
   */
  private function getBlockOptions(): array {

    // Only add blocks which work without any available context.
    $definitions = $this->blockManager->getDefinitionsForContexts($this->contextRepository->getAvailableContexts());
    // Order by category, and then by admin label.
    $definitions = $this->blockManager->getSortedDefinitions($definitions);

    $blocks = [];
    foreach ($definitions as $block_id => $definition) {
      // Pass the admin label through an @-placeholder. When it is already a
      // Markup object (e.g. a Views block titled "A & B"), it is preserved
      // rather than re-encoded; plain-string labels are still escaped exactly
      // once. This avoids double-encoding ("&amp;" -> "&amp;amp;").
      $blocks[$block_id] = new FormattableMarkup('@label (@provider)', [
        '@label' => $definition['admin_label'],
        '@provider' => $definition['provider'],
      ]);
    }

    return $blocks;
  }

  /**
   * Ajax callback to change block title when block is selected.
   */
  public function blockTitleAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $tab_index = $form_state->getTriggeringElement()['#array_parents'][2];
    $element_id = '#block-title-textfield-' . $tab_index;
    $selected_block = $form_state->getValue('configuration_data')[$tab_index]['content']['block_content']['options']['bid'];

    $definitions = $this->blockManager->getDefinitionsForContexts($this->contextRepository->getAvailableContexts());

    $form['block_title'] = [
      '#type' => 'textfield',
      '#value' => $definitions[$selected_block]['admin_label'],
      '#title' => $this->t('Block Title'),
      '#prefix' => '<div id="block-title-textfield-' . $tab_index . '">',
      '#suffix' => '</div>',
    ];

    $form_state->setRebuild();
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new ReplaceCommand($element_id, $form['block_title']));

    return $ajax_response;
  }

}
