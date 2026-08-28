<?php

namespace Drupal\tec_portal\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\PortalOrder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The customer door: /my and /my/order/{id}.
 *
 * The company comes from the session, never from the path. Factory staff who
 * land here are sent to /start. An open order is a form: quantities and
 * Confirm. A confirmed order is read-only.
 */
class MyController extends ControllerBase {

  public function __construct(
    protected CustomerCompany $companies,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tec_portal.company'),
    );
  }

  /**
   * Logged-in portal customers, and factory (who get redirected).
   */
  public static function access(AccountInterface $account) {
    $companies = \Drupal::service('tec_portal.company');
    $allowed = $account->isAuthenticated()
      && ($companies->isFactory($account) || $companies->isPortalCustomer($account));
    return AccessResult::allowedIf($allowed)
      ->addCacheContexts(['user.roles', 'user']);
  }

  /**
   * The list of this company's sales orders.
   */
  public function home(): array|RedirectResponse {
    $account = $this->currentUser();
    if ($this->companies->isFactory($account)) {
      return new RedirectResponse(Url::fromRoute('view.tec_icons_launchpad.page_1')->toString());
    }
    if (!$this->companies->isPortalCustomer($account)) {
      throw new AccessDeniedHttpException();
    }

    $company = $this->companies->company($account);
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-portal']],
      '#attached' => ['library' => ['tec_portal/portal']],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => $company ? $company->getCacheTags() : [],
      ],
    ];

    if (!$company) {
      $build['missing'] = [
        '#markup' => '<div class="tec-portal__warning">'
        . $this->t('This login is not linked to a company. Ask the factory to set your contact person.')
        . '</div>',
      ];
      return $build;
    }

    $build['head'] = [
      '#markup' => '<div class="tec-portal__head"><span class="tec-portal__company">'
        . Html::escape($company->label())
        . '</span></div>',
    ];

    $build['place'] = [
      '#type' => 'link',
      '#title' => $this->t('Place an order'),
      '#url' => Url::fromRoute('tec_portal.place'),
      '#attributes' => ['class' => ['button', 'button--primary', 'tec-portal__place']],
    ];

    $orders = $this->loadOrders();
    if (!$orders) {
      $build['empty'] = [
        '#markup' => '<div class="tec-portal__nothing">'
        . $this->t('You have no orders yet.')
        . '</div>',
      ];
      return $build;
    }

    $rows = [];
    $tags = $build['#cache']['tags'] ?? [];
    foreach ($orders as $order) {
      $tags = Cache::mergeTags($tags, $order->getCacheTags());
      [$pieces, $money] = $this->totals($order);
      $paid = PortalOrder::paidOn($order);
      $rows[] = [
        'number' => [
          'data' => [
            '#type' => 'link',
            '#title' => $order->label() ?: ('#' . $order->id()),
            '#url' => Url::fromRoute('tec_portal.order', ['tec_order' => $order->id()]),
          ],
        ],
        'status' => [
          'data' => [
            '#markup' => PortalOrder::statusMarkup($order),
            '#allowed_tags' => ['span'],
          ],
        ],
        'date' => $paid ? \Drupal::service('date.formatter')->format($paid, 'custom', 'j M Y') : '—',
        'pieces' => [
          'data' => number_format($pieces, 0),
          'class' => ['tec-portal__num'],
        ],
        'total' => [
          'data' => $this->money($money),
          'class' => ['tec-portal__num'],
        ],
      ];
    }
    $build['#cache']['tags'] = $tags;

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'number' => $this->t('Order'),
        'status' => $this->t('Status'),
        'date' => $this->t('Date'),
        'pieces' => [
          'data' => $this->t('Quantity'),
          'class' => ['tec-portal__num'],
        ],
        'total' => [
          'data' => $this->t('Total amount'),
          'class' => ['tec-portal__num'],
        ],
      ],
      '#rows' => $rows,
      '#attributes' => ['class' => ['tec-portal__table']],
    ];

    return $build;
  }

  /**
   * One sales order: quantities while Open, read-only after Confirm.
   */
  public function order($tec_order): array|RedirectResponse {
    $account = $this->currentUser();
    if ($this->companies->isFactory($account)) {
      return new RedirectResponse($tec_order->toUrl()->toString());
    }
    if (!$this->companies->isPortalCustomer($account)) {
      throw new AccessDeniedHttpException();
    }
    if ($tec_order->bundle() !== 'tec_sales_order' || !$tec_order->access('view', $account)) {
      throw new NotFoundHttpException();
    }

    return $this->formBuilder()->getForm('\Drupal\tec_portal\Form\OrderForm', $tec_order);
  }

  /**
   * The order number, for the page title.
   */
  public function orderTitle($tec_order): string {
    return (string) ($tec_order->label() ?: $this->t('Order'));
  }

  /**
   * This company's sales orders, newest first.
   */
  protected function loadOrders(): array {
    $storage = $this->entityTypeManager()->getStorage('tec_order');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'tec_sales_order')
      ->sort('created', 'DESC')
      ->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Lines on an order, in the order they were added.
   */
  protected function linesOf($order): array {
    if (!$order->hasField('field_tec_line_items') || $order->get('field_tec_line_items')->isEmpty()) {
      return [];
    }
    return $order->get('field_tec_line_items')->referencedEntities();
  }

  /**
   * Piece count and money total of an order.
   *
   * @return array{0: float, 1: float}
   */
  protected function totals($order): array {
    $pieces = 0.0;
    $money = 0.0;
    foreach ($this->linesOf($order) as $line) {
      $qty = $line->hasField('field_tec_quantity') && !$line->get('field_tec_quantity')->isEmpty()
        ? (float) $line->get('field_tec_quantity')->value
        : 0;
      $total = $line->hasField('field_tec_line_item_total_number') && !$line->get('field_tec_line_item_total_number')->isEmpty()
        ? (float) $line->get('field_tec_line_item_total_number')->value
        : 0;
      $pieces += $qty;
      $money += $total;
    }
    return [$pieces, $money];
  }

  /**
   * Baht, two decimals.
   */
  protected function money(float $amount): string {
    return '฿ ' . number_format($amount, 2);
  }

}
