<?php

namespace Drupal\tec_portal;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * The shipment table on the customer card and on /o/ship.
 *
 * Same rows, two filters: one company, or the whole factory. Packing and
 * invoice are the live prints of that shipment, not a second document.
 */
final class ShipmentList {

  use StringTranslationTrait;

  /**
   * Factory list page size. The customer tab is short; it prints every row.
   */
  public const PAGE = 50;

  /**
   * One company's shipments, with New shipment above the table.
   */
  public function companyPage(int $company_id): array {
    $build = $this->page(Shipment::listOf($company_id), 'company');
    $build['new'] = $this->newShipment($company_id);
    $build['new']['#weight'] = -10;
    return $build;
  }

  /**
   * Every shipment, newest first. Pager when there are more than PAGE.
   */
  public function factoryPage(): array {
    $total = Shipment::countOf();
    $page = \Drupal::service('pager.manager')->createPager($total, self::PAGE);
    $offset = $page->getCurrentPage() * self::PAGE;
    $build = $this->page(Shipment::listOf(NULL, self::PAGE, $offset), 'factory');
    $build['#cache']['contexts'][] = 'url.query_args';
    $build['pager'] = [
      '#type' => 'pager',
      '#weight' => 20,
    ];
    return $build;
  }

  /**
   * Shared wrapper and table.
   *
   * @param EntityInterface[] $shipments
   *   Newest first.
   * @param string $kind
   *   company or factory.
   */
  private function page(array $shipments, string $kind): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-portal', 'tec-ship-list']],
      '#attached' => ['library' => ['tec_portal/shipment_form']],
      '#cache' => [
        'tags' => Shipment::listCacheTags(),
        'contexts' => ['url', 'user.roles'],
      ],
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->headers($kind),
      '#empty' => $this->t('No shipments yet'),
      '#attributes' => ['class' => ['tec-portal__table']],
      '#weight' => 10,
    ];
    foreach ($shipments as $shipment) {
      $build['table'][] = $this->row($shipment, $kind);
    }
    return $build;
  }

  /**
   * @return array<int, mixed>
   */
  private function headers(string $kind): array {
    if ($kind === 'factory') {
      return [
        $this->t('Shipment'),
        $this->t('Customer'),
        $this->t('Date'),
        $this->t('Packing'),
        $this->t('Invoice'),
      ];
    }
    return [
      $this->t('Shipment'),
      $this->t('Date'),
      $this->t('Sold to'),
      $this->t('Ship to'),
      $this->t('Packing'),
      $this->t('Invoice'),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function row(EntityInterface $shipment, string $kind): array {
    $number = [
      '#type' => 'link',
      '#title' => $this->numberLabel($shipment),
      '#url' => Url::fromUri('internal:' . Shipment::viewPath($shipment)),
    ];
    $date = ['#plain_text' => Shipment::dateValue($shipment) ?: '—'];
    $packing = $this->printLink($shipment, 'packing');
    $invoice = $this->printLink($shipment, 'invoice');
    if ($kind === 'factory') {
      return [
        'number' => $number,
        'customer' => $this->entityLink(Shipment::customer($shipment)),
        'date' => $date,
        'packing' => $packing,
        'invoice' => $invoice,
      ];
    }
    return [
      'number' => $number,
      'date' => $date,
      'sold_to' => $this->entityLink(Shipment::soldTo($shipment)),
      'ship_to' => $this->entityLink(Shipment::shipTo($shipment)),
      'packing' => $packing,
      'invoice' => $invoice,
    ];
  }

  private function numberLabel(EntityInterface $shipment): string {
    $label = trim((string) $shipment->label());
    return $label !== '' ? $label : ('SHIP-' . (int) $shipment->id());
  }

  private function entityLink(?EntityInterface $entity): array {
    if (!$entity) {
      return ['#plain_text' => '—'];
    }
    return [
      '#type' => 'link',
      '#title' => (string) $entity->label(),
      '#url' => $entity->toUrl(),
    ];
  }

  private function printLink(EntityInterface $shipment, string $kind): array {
    $path = $kind === 'invoice' ? Shipment::invoicePath($shipment) : Shipment::packingPath($shipment);
    return [
      '#type' => 'link',
      '#title' => $kind === 'invoice' ? $this->t('Invoice') : $this->t('Packing'),
      '#url' => Url::fromUri('internal:' . $path),
      '#attributes' => ['target' => '_blank'],
    ];
  }

  /**
   * Same control as the Orders tab, so the factory does not hunt for it.
   */
  private function newShipment(int $company_id): array {
    if ($company_id < 1 || !Shipment::typesExist()) {
      return [];
    }
    $url = Url::fromRoute('tec_portal.shipment_new', ['tec_crm' => $company_id])->toString();
    return [
      '#markup' => '<div class="text-align-right tec-factory-company-actions"><div class="btn-default specific-flag"><a href="'
        . htmlspecialchars($url, ENT_QUOTES)
        . '">' . $this->t('New shipment') . '</a></div></div>',
    ];
  }

}
