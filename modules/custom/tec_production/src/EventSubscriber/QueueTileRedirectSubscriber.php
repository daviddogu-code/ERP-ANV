<?php

namespace Drupal\tec_production\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects the production landing tile nodes to their module pages.
 *
 * Tiles are plain tec_landing_page nodes (so they show on the home grid);
 * their nids live in tec_production.settings and viewing one redirects to
 * the real route (queue, log, ...).
 */
class QueueTileRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Which setting holds the node of the icon that opens which screen.
   *
   * Public because the company settings form builds itself from this list. The
   * pairing has to be stated once: two copies of it drift, and the way that
   * failure shows up is an icon that quietly opens the wrong screen.
   */
  public const TILES = [
    'queue_tile_nid' => 'tec_production.queue',
    'log_tile_nid' => 'tec_production.log',
    'report_tile_nid' => 'tec_production.report',
    'stock_tile_nid' => 'tec_production.stock',
    'purchase_tile_nid' => 'tec_production.purchase_list',
    'po_control_tile_nid' => 'tec_production.purchase_queue',
    'supplier_orders_tile_nid' => 'view.tec_supplier_orders.page_1',
    'shipments_tile_nid' => 'tec_portal.shipment_list',
  ];

  protected ConfigFactoryInterface $configFactory;
  protected RouteMatchInterface $routeMatch;

  public function __construct(ConfigFactoryInterface $config_factory, RouteMatchInterface $route_match) {
    $this->configFactory = $config_factory;
    $this->routeMatch = $route_match;
  }

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', 28]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    if ($this->routeMatch->getRouteName() !== 'entity.node.canonical') {
      return;
    }
    $node = $this->routeMatch->getParameter('node');
    if (!$node) {
      return;
    }
    $settings = $this->configFactory->get('tec_production.settings');
    foreach (self::TILES as $key => $route) {
      $tile_nid = (int) $settings->get($key);
      if ($tile_nid > 0 && (int) $node->id() === $tile_nid) {
        $event->setResponse(new RedirectResponse(Url::fromRoute($route)->toString(), 302));
        return;
      }
    }
  }

}
