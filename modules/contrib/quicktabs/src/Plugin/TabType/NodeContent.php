<?php

namespace Drupal\quicktabs\Plugin\TabType;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quicktabs\Attribute\TabType;
use Drupal\quicktabs\TabTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'node content' tab type.
 */
#[TabType(
  id: 'node_content',
  name: new TranslatableMarkup('node'),
)]
class NodeContent extends TabTypeBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected AccountProxyInterface $currentUser,
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
      $container->get('entity_display.repository'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function optionsForm(array $tab): array {
    $plugin_id = $this->getPluginDefinition()['id'];

    $form = [];
    $form['nid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Node'),
      '#description' => $this->t('The node ID of the node.'),
      '#maxlength' => 10,
      '#size' => 20,
      '#default_value' => $tab['content'][$plugin_id]['options']['nid'] ?? '',
    ];
    $view_modes = $this->entityDisplayRepository->getViewModes('node');
    $options = [];
    foreach ($view_modes as $view_mode_name => $view_mode) {
      $options[$view_mode_name] = $view_mode['label'];
    }
    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#options' => $options,
      '#default_value' => $tab['content'][$plugin_id]['options']['view_mode'] ?? 'full',
    ];
    $form['hide_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide the title of this node'),
      '#default_value' => $tab['content'][$plugin_id]['options']['hide_title'] ?? 1,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $tab): array|string {
    $options = $tab['content'][$tab['type']]['options'];
    $node = $this->entityTypeManager->getStorage('node')->load($options['nid']);

    if ($node !== NULL) {

      $access_result = $node->access('view', $this->currentUser, TRUE);
      // Only an explicitly allowed result grants access. Node access returns
      // neutral rather than forbidden in most deny cases — an unpublished node
      // among them — so testing isForbidden() here would render content the
      // user cannot see.
      //
      // The empty render array still carries the access result's cacheability,
      // which may vary by more than the node's own cache contexts (e.g. a node
      // access module), so the tab is not cached as empty for users who are
      // allowed to see the node, nor as visible for users who are not.
      if (!$access_result->isAllowed()) {
        $render = [];
        CacheableMetadata::createFromObject($access_result)->applyTo($render);
        return $render;
      }

      $build = $this->entityTypeManager->getViewBuilder('node')->view($node, $options['view_mode']);
      CacheableMetadata::createFromRenderArray($build)
        ->addCacheableDependency($access_result)
        ->applyTo($build);

      if ($options['hide_title']) {
        $build['#node']->setTitle(NULL);
      }

      return $build;
    }

    return [];
  }

}
