<?php

namespace Drupal\quicktabs\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quicktabs\Form\QuickTabsInstanceDeleteForm;
use Drupal\quicktabs\Form\QuickTabsInstanceDuplicateForm;
use Drupal\quicktabs\Form\QuickTabsInstanceEditForm;
use Drupal\quicktabs\QuickTabsInstanceListBuilder;

/**
 * Defines the QuickTabsInstance entity.
 *
 * The QuickTabsInstnace entity stores information about a quicktab.
 *
 * @ConfigEntityType(
 *   id = "quicktabs_instance",
 *   label = @Translation("Quick Tabs"),
 *   module = "quicktabs",
 *   handlers = {
 *     "list_builder" = "Drupal\quicktabs\QuickTabsInstanceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\quicktabs\Form\QuickTabsInstanceEditForm",
 *       "edit" = "Drupal\quicktabs\Form\QuickTabsInstanceEditForm",
 *       "delete" = "Drupal\quicktabs\Form\QuickTabsInstanceDeleteForm",
 *       "duplicate" = "Drupal\quicktabs\Form\QuickTabsInstanceDuplicateForm",
 *     },
 *   },
 *   config_prefix = "quicktabs_instance",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/quicktabs/{quicktabs_instance}/edit",
 *     "add-form" = "/admin/structure/quicktabs/add",
 *     "delete-form" = "/admin/structure/quicktabs/{quicktabs_instance}/delete",
 *     "duplicate-form" = "/admin/structure/quicktabs/{quicktabs_instance}/duplicate"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label",
 *     "renderer" = "renderer",
 *     "options" = "options",
 *     "hide_empty_tabs" = "hide_empty_tabs",
 *     "remember_last_clicked_tab" = "remember_last_clicked_tab",
 *     "default_tab" = "default_tab",
 *     "configuration_data" = "configuration_data"
 *   },
 *   admin_permission = "administer quicktabs",
 * )
 */
#[ConfigEntityType(
  id: 'quicktabs_instance',
  label: new TranslatableMarkup('Quick Tabs'),
  handlers: [
    'list_builder' => QuickTabsInstanceListBuilder::class,
    'form' => [
      'add' => QuickTabsInstanceEditForm::class,
      'edit' => QuickTabsInstanceEditForm::class,
      'delete' => QuickTabsInstanceDeleteForm::class,
      'duplicate' => QuickTabsInstanceDuplicateForm::class,
    ],
  ],
  config_prefix: 'quicktabs_instance',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  links: [
    'edit-form' => '/admin/structure/quicktabs/{quicktabs_instance}/edit',
    'add-form' => '/admin/structure/quicktabs/add',
    'delete-form' => '/admin/structure/quicktabs/{quicktabs_instance}/delete',
    'duplicate-form' => '/admin/structure/quicktabs/{quicktabs_instance}/duplicate',
  ],
  config_export: [
    'id',
    'label',
    'renderer',
    'options',
    'hide_empty_tabs',
    'remember_last_clicked_tab',
    'default_tab',
    'configuration_data',
  ],
  admin_permission: 'administer quicktabs',
)]
class QuickTabsInstance extends ConfigEntityBase implements QuickTabsInstanceInterface {

  const QUICKTABS_DELTA_NONE = '9999';

  /**
   * The QuickTabs Instance ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the QuickTabs Instance.
   *
   * @var string
   */
  protected $label;

  /**
   * The renderer of the QuickTabs Instance.
   *
   * @var string
   */
  protected $renderer;

  /**
   * Options provided by renderer plugins.
   *
   * @var array
   */
  protected $options;

  /**
   * Whether or not to remember the last clicked tab in browser storage.
   *
   * @var bool
   */
  protected $remember_last_clicked_tab;

  /**
   * Whether or not to hide empty tabs.
   *
   * @var bool
   */
  protected $hide_empty_tabs;

  /**
   * Whether or not to hide empty tabs.
   *
   * @var bool
   */
  protected $default_tab;

  /**
   * Required to render this instance.
   *
   * @var array
   */
  protected $configuration_data;

  /**
   * Whether the pre-render alter has run for this request.
   *
   * Transient state, not part of config_export.
   */
  protected bool $prepared = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderer() {
    return $this->renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    if (empty($this->options)) {
      return [];
    }
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function getHideEmptyTabs() {
    return $this->hide_empty_tabs;
  }

  /**
   * {@inheritdoc}
   */
  public function getRememberLastClickedTab() {
    return $this->remember_last_clicked_tab;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTab() {
    return $this->default_tab;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationData() {
    return $this->configuration_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurationData(array $configuration_data) {
    $this->configuration_data = $configuration_data;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    static::invalidateBlockPluginCache();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);
    static::invalidateBlockPluginCache();
  }

  /**
   * Invalidates the block plugin cache after changes and deletions.
   */
  protected static function invalidateBlockPluginCache(): void {
    // Invalidate the block cache to update QuickTabs-based derivatives.
    \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
  }

  /**
   * Returns a render array to be used in a block or page.
   *
   * @return array
   *   A render array.
   */
  public function getRenderArray() {
    $this->prepareRender();

    $type = \Drupal::service('plugin.manager.tab_renderer');
    $renderer = $type->createInstance($this->getRenderer());

    $build = $renderer->render($this);
    $build['#contextual_links']['quicktabs_instance'] = [
      'route_parameters' => ['quicktabs_instance' => $this->id()],
    ];
    return $build;
  }

  /**
   * Renders the content of a single tab, by its delta in the configuration.
   *
   * Used by the AJAX controller to render an individual tab without building
   * the full tabset. Goes through the same prepare step as getRenderArray() so
   * hook_quicktabs_instance_alter() fires on this path too.
   */
  public function renderTab(int|string $index): array|string {
    $this->prepareRender();

    $configuration_data = $this->getConfigurationData();
    // The tab index comes straight from the request; an unknown one (stale
    // link, or a count an alter reduced) renders as empty rather than erroring.
    if (!isset($configuration_data[$index])) {
      return [];
    }
    $tab = $configuration_data[$index];
    $object = \Drupal::service('plugin.manager.tab_type')
      ->createInstance($tab['type']);
    return $object->render($tab);
  }

  /**
   * Invokes hook_quicktabs_instance_alter() once per request before rendering.
   *
   * Both getRenderArray() and renderTab() call this; the guard prevents a
   * second invocation, which matters because an implementation may append
   * rather than replace.
   */
  protected function prepareRender(): void {
    if (!$this->prepared) {
      $this->prepared = TRUE;
      \Drupal::moduleHandler()->alter('quicktabs_instance', $this);
    }
  }

  /**
   * Loads a quicktabs_instance from configuration and returns it.
   *
   * @param string $id
   *   The qti ID to load.
   *
   * @return \Drupal\quicktabs\Entity\QuickTabsInstance
   *   The loaded entity.
   */
  public static function getQuickTabsInstance($id) {
    $qt = \Drupal::service('entity_type.manager')->getStorage('quicktabs_instance')->load($id);
    return $qt;
  }

}
