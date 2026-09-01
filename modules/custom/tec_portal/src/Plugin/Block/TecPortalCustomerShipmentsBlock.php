<?php

namespace Drupal\tec_portal\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\Shipment;
use Drupal\tec_portal\ShipmentList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shipments tab on the customer card.
 *
 * @Block(
 *   id = "tec_portal_customer_shipments",
 *   admin_label = @Translation("TEC CRM: Customer shipments"),
 *   category = @Translation("TEC")
 * )
 */
class TecPortalCustomerShipmentsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomerCompany $companies,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('tec_portal.company'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $company = $this->company();
    if (!$company) {
      return [];
    }
    $build = (new ShipmentList())->companyPage((int) $company->id());
    $build['#cache']['tags'] = Cache::mergeTags(
      $build['#cache']['tags'] ?? [],
      $company->getCacheTags(),
      Shipment::listCacheTags()
    );
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if (!$this->companies->isFactory($account) || !Shipment::typesExist()) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles', 'route']);
    }
    $company = $this->company();
    if (!$company) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles', 'route']);
    }
    return $company->access('view', $account, TRUE)->addCacheContexts(['user.roles', 'route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'user.roles']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), Shipment::listCacheTags());
  }

  /**
   * Organisation on this customer card, or NULL.
   */
  protected function company(): ?EntityInterface {
    $param = $this->routeMatch->getParameter('tec_crm');
    if ($param instanceof EntityInterface) {
      return $this->organisation($param);
    }
    $raw = $this->routeMatch->getRawParameter('tec_crm');
    if (is_numeric($raw)) {
      $loaded = $this->entityTypeManager->getStorage('tec_crm')->load((int) $raw);
      if ($loaded) {
        return $this->organisation($loaded);
      }
    }
    $path = \Drupal::service('path.current')->getPath();
    if (preg_match('#^/(?:customer|tec_crm)/(\d+)#', $path, $matches)) {
      $loaded = $this->entityTypeManager->getStorage('tec_crm')->load((int) $matches[1]);
      if ($loaded) {
        return $this->organisation($loaded);
      }
    }
    return NULL;
  }

  /**
   * Shipments belong on the company card, not the person.
   */
  protected function organisation($entity): ?EntityInterface {
    if (!$entity instanceof EntityInterface || $entity->getEntityTypeId() !== 'tec_crm') {
      return NULL;
    }
    return $entity->bundle() === 'tec_contact_organization' ? $entity : NULL;
  }

}
