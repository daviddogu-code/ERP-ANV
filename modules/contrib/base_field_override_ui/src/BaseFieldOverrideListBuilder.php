<?php

namespace Drupal\base_field_override_ui;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides lists of field config entities.
 */
class BaseFieldOverrideListBuilder extends ConfigEntityListBuilder {

  /**
   * The name of the entity type the listed fields are attached to.
   *
   * @var string
   */
  protected $targetEntityTypeId;

  /**
   * The name of the bundle the listed fields are attached to.
   *
   * @var string
   */
  protected $targetBundle;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));

    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render($target_entity_type_id = NULL, $target_bundle = NULL) {
    $this->targetEntityTypeId = $target_entity_type_id;
    $this->targetBundle = $target_bundle;

    $build = parent::render();
    $build['table']['#attributes']['id'] = 'base-field-override-overview';
    $build['table']['#empty'] = $this->t('No base fields are present yet.');

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $base_fields = array_filter($this->entityFieldManager->getBaseFieldDefinitions($this->targetEntityTypeId), function ($field_definition) {
      return $field_definition->isDisplayConfigurable('form');
    });
    $entities = [];

    foreach ($base_fields as $field) {
      $config = $field->getConfig($this->targetBundle);
      $entities[$config->id()] = $config;
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Label'),
      'field_name' => [
        'data' => $this->t('Machine name'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'status' => $this->t('Status'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $base_field_override) {
    /** @var \Drupal\Core\Field\Entity\BaseFieldOverride $base_field_override */
    $row = [
      'id' => Html::getClass($base_field_override->getName()),
      'data' => [
        'label' => $base_field_override->getLabel(),
        'field_name' => $base_field_override->getName(),
        'status' => $base_field_override->isNew() ? $this->t('Default') : $this->t('Overridden'),
      ],
    ];

    // Add the operations.
    $row['data'] = $row['data'] + parent::buildRow($base_field_override);

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\Core\Field\Entity\BaseFieldOverride $entity */
    $operations = parent::getDefaultOperations($entity);

    if ($entity->access('update')) {
      $title = $this->t('Edit');
      $url = BaseFieldOverrideUI::getEditRouteInfo($entity);
      if ($entity->isNew()) {
        $title = $this->t('Add');
        $url = BaseFieldOverrideUI::getAddRouteInfo($entity);
      }
      elseif ($this->moduleHandler->moduleExists('config_translation')) {
        $operations['translate'] = [
          'title' => $this->t('Translate'),
          'weight' => 11,
          'url' => BaseFieldOverrideUI::getTranslateRouteInfo($entity),
        ];
      }
      $operations['edit'] = [
        'title' => $title,
        'weight' => 10,
        'url' => $url,
        'attributes' => [
          'title' => $this->t('Edit base field override.'),
        ],
      ];
    }
    if ($entity->access('delete')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => BaseFieldOverrideUI::getDeleteRouteInfo($entity),
        'attributes' => [
          'title' => $this->t('Delete base field override.'),
        ],
      ];
    }

    return $operations;
  }

}
