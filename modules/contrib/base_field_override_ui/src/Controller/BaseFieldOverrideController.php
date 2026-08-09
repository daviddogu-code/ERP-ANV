<?php

namespace Drupal\base_field_override_ui\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\Controller\EntityListController;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to list base field override and create one.
 */
class BaseFieldOverrideController extends EntityListController {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * Shows the 'Manage base fields' page.
   *
   * @param string|null $entity_type_id
   *   The entity type id.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function listing($entity_type_id = NULL, $bundle = NULL) {
    return $this->entityTypeManager()->getListBuilder('base_field_override')->render($entity_type_id, $bundle);
  }

  /**
   * Initialize a create form for base field override.
   *
   * @param string $base_field_name
   *   The machine name of the base field.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function add($base_field_name, $entity_type_id, $bundle) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    if (!isset($fields[$base_field_name])) {
      throw new NotFoundHttpException();
    }

    $config = $fields[$base_field_name]->getConfig($bundle);

    return $this->entityFormBuilder()->getForm($config, 'edit');
  }

  /**
   * The _title_callback for add a base field override form.
   *
   * @param string $base_field_name
   *   The machine name of the base field.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label of the field.
   */
  public function getAddTitle($base_field_name, $entity_type_id, $bundle) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    $config = $fields[$base_field_name]->getConfig($bundle);

    return $this->t('Add @label base field override', ['@label' => $config->label()]);
  }

  /**
   * The _access_callback for add a base field override form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $base_field_name
   *   The machine name of the base field.
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function addAccess(RouteMatchInterface $route_match, AccountInterface $account, $base_field_name, $entity_type_id) {
    $bundle = $route_match->getParameter('bundle');
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    if (!isset($fields[$base_field_name]) || !$fields[$base_field_name]->isDisplayConfigurable('form')) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIfHasPermission($account, 'administer ' . $entity_type_id . ' fields');
  }

}
