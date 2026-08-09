<?php

namespace Drupal\commerce_pricelist\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the price list/item controller for bundle fields.
 */
class PriceListBundleController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Constructs a new PriceListBundleController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $route_name_parts = explode('.', $route_match->getRouteName());
    // Extract the entity type ID from the route name.
    $this->entityTypeId = $route_name_parts[1];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * Callback for the field UI base route.
   */
  public function bundlePage($bundle = NULL) {
    $entity_bundle_info = $this->entityTypeBundleInfo->getBundleInfo($this->entityTypeId);

    return [
      '#markup' => $this->t('The @bundle-label bundle has no settings.', [
        '@bundle-label' => $entity_bundle_info[$bundle]['label'],
      ]),
    ];
  }

  /**
   * Handles base route for the field UI.
   *
   * Field UI needs some base route to attach its routes to.
   *
   * @return array
   *   The page content.
   */
  public function adminPage() {
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
    $entity_bundle_info = $this->entityTypeBundleInfo->getBundleInfo($this->entityTypeId);
    $build = [];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Description'),
        $this->t('Operations'),
      ],
      '#rows' => [],
      '#empty' => $this->t('There are no @label yet.', [
        '@label' => $entity_type->getPluralLabel(),
      ]),
    ];

    foreach ($entity_bundle_info as $bundle_name => $bundle_info) {
      $build['table']['#rows'][$bundle_name] = [
        'name' => ['data' => $bundle_info['label']],
        'description' => ['data' => $bundle_info['description'] ?? ''],
        'operations' => ['data' => $this->buildOperations($bundle_name)],
      ];

    }

    return $build;
  }

  /**
   * Builds a renderable list of operation links for the bundle.
   *
   * @return array
   *   A renderable array of operation links.
   *
   * @see \Drupal\Core\Entity\EntityListBuilder::buildRow()
   */
  protected function buildOperations($bundle_name) {
    $operations = [
      'manage-fields' => [
        'title' => t('Manage fields'),
        'weight' => 15,
        'url' => Url::fromRoute("entity.{$this->entityTypeId}.field_ui_fields", [
          'bundle' => $bundle_name,
        ]),
      ],
      'manage-form-display' => [
        'title' => t('Manage form display'),
        'weight' => 20,
        'url' => Url::fromRoute("entity.entity_form_display.{$this->entityTypeId}.default", [
          'bundle' => $bundle_name,
        ]),
      ],
      'manage-display' => [
        'title' => t('Manage display'),
        'weight' => 25,
        'url' => Url::fromRoute("entity.entity_view_display.{$this->entityTypeId}.default", [
          'bundle' => $bundle_name,
        ]),
      ],
    ];

    return [
      '#type' => 'operations',
      '#links' => $operations,
    ];
  }

}
