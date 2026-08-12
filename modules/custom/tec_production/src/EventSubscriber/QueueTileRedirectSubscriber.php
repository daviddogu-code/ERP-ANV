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
    $map = [
      'queue_tile_nid' => 'tec_production.queue',
      'log_tile_nid' => 'tec_production.log',
      'report_tile_nid' => 'tec_production.report',
      'stock_tile_nid' => 'tec_production.stock',
    ];
    foreach ($map as $key => $route) {
      $tile_nid = (int) $settings->get($key);
      if ($tile_nid > 0 && (int) $node->id() === $tile_nid) {
        $event->setResponse(new RedirectResponse(Url::fromRoute($route)->toString(), 302));
        return;
      }
    }
  }

}
