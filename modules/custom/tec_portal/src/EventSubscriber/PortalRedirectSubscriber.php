<?php

namespace Drupal\tec_portal\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\OtherCharges;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A portal customer who hits a factory URL is sent to /my.
 *
 * Entity view access stays allowed on their own sales order (so /my/order/{id}
 * can load it). The factory cards (/tec_order, /customer, …) are a different
 * skin of the same entities and are not for the customer.
 *
 * Runs before routing so /start never 403s after login.
 */
final class PortalRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly CustomerCompany $companies,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // After authentication (300) and before the router (32).
      KernelEvents::REQUEST => ['onRequest', 33],
    ];
  }

  /**
   * Sends a portal customer off factory screens.
   *
   * /o/draft/{id} is the old Oscar sales form. Factory goes to /o/order/{id};
   * a portal customer goes to /my/order/{id}. Runs for everyone so a bookmark
   * or a leftover pencil cannot open Save/Cancel/Delete again.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $path = rtrim($event->getRequest()->getPathInfo(), '/') ?: '/';
    if (preg_match('#^/o/draft/(\d+)#', $path, $m)) {
      $route = $this->companies->isPortalCustomer($this->currentUser)
        ? 'tec_portal.order'
        : 'tec_portal.factory_order';
      $event->setResponse(new RedirectResponse(
        Url::fromRoute($route, ['tec_order' => $m[1]])->toString()
      ));
      return;
    }
    if (!$this->companies->isPortalCustomer($this->currentUser)) {
      return;
    }
    $to = $this->portalPath($path);
    if ($to === NULL) {
      return;
    }
    $event->setResponse(new RedirectResponse($to));
  }

  /**
   * Portal URL for a factory path, or NULL to leave the request alone.
   */
  protected function portalPath(string $path): ?string {
    if ($path === '/' || $path === '/start') {
      return Url::fromRoute('tec_portal.home')->toString();
    }
    if (preg_match('#^/tec_order/(\d+)#', $path, $m) || preg_match('#^/o/order/(\d+)#', $path, $m)) {
      $order = \Drupal::entityTypeManager()->getStorage('tec_order')->load((int) $m[1]);
      if ($order && OtherCharges::isOrder($order)) {
        return Url::fromRoute('tec_portal.home')->toString();
      }
      return Url::fromRoute('tec_portal.order', ['tec_order' => $m[1]])->toString();
    }
    foreach (['/customer/', '/tec_crm/', '/tec_product/', '/tec_line_item/', '/tec_inventory/'] as $prefix) {
      if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
        return Url::fromRoute('tec_portal.home')->toString();
      }
    }
    return NULL;
  }

}
