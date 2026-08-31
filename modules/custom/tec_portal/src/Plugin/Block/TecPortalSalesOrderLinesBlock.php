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
use Drupal\tec_portal\OrderLineGrid;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only sales-order lines, same table as /o/order after Confirm.
 *
 * @Block(
 *   id = "tec_portal_sales_order_lines",
 *   admin_label = @Translation("TEC Order: Sales order lines (portal table)"),
 *   category = @Translation("TEC")
 * )
 */
class TecPortalSalesOrderLinesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $order = $this->resolveOrder();
    return OrderLineGrid::create($this->entityTypeManager)->build($order);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $order = $this->resolveOrder();
    if (!$order) {
      return AccessResult::forbidden()->addCacheContexts(['route']);
    }
    return $order->access('view', $account, TRUE)->addCacheContexts(['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * Sales order on this card, or NULL.
   */
  protected function resolveOrder() {
    $order = $this->routeMatch->getParameter('tec_order');
    if ($order instanceof EntityInterface && $order->getEntityTypeId() === 'tec_order') {
      return $this->salesOrder($order);
    }

    $raw = $this->routeMatch->getRawParameter('tec_order');
    if (is_numeric($raw)) {
      $loaded = $this->entityTypeManager->getStorage('tec_order')->load($raw);
      if ($loaded) {
        return $this->salesOrder($loaded);
      }
    }

    $path = \Drupal::service('path.current')->getPath();
    if (preg_match('#^/tec_order/(\d+)#', $path, $matches)) {
      $loaded = $this->entityTypeManager->getStorage('tec_order')->load($matches[1]);
      if ($loaded) {
        return $this->salesOrder($loaded);
      }
    }

    return NULL;
  }

  /**
   * The Line items tab is sales only. Purchase orders keep their own view.
   */
  protected function salesOrder($order) {
    return $order->bundle() === 'tec_sales_order' ? $order : NULL;
  }

}
