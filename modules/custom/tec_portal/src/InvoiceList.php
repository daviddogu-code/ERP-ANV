<?php

namespace Drupal\tec_portal;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Issued tax invoices on /o/inv, and the rows on one sales order.
 */
final class InvoiceList {

  use StringTranslationTrait;

  public const PAGE = 50;

  /**
   * Every issued 036x, newest number first.
   */
  public function factoryPage(): array {
    $total = TaxInvoice::issuedCount();
    $page = \Drupal::service('pager.manager')->createPager($total, self::PAGE);
    $offset = $page->getCurrentPage() * self::PAGE;
    $build = $this->table(
      TaxInvoice::issuedList(self::PAGE, $offset),
      'factory',
      $this->t('No tax invoices issued yet')
    );
    $build['#cache']['contexts'][] = 'url.query_args';
    $build['pager'] = [
      '#type' => 'pager',
      '#weight' => 20,
    ];
    return $build;
  }

  /**
   * Deposits and dispatch invoices that belong on this order.
   *
   * $open is the last column (Open / Print). The deposit screen is already
   * that ficha, so it passes false.
   */
  public function orderPage($order, bool $open = TRUE): array {
    $invoices = TaxInvoice::ofOrder($order);
    $build = $this->table(
      $invoices,
      'order',
      $this->t('No deposits recorded on this order yet.'),
      $open
    );
    $build['#cache']['tags'] = array_merge(
      $build['#cache']['tags'] ?? [],
      $order->getCacheTags()
    );
    return $build;
  }

  /**
   * @param EntityInterface[] $invoices
   */
  private function table(array $invoices, string $kind, $empty, bool $open = TRUE): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-portal', 'tec-inv-list']],
      '#attached' => ['library' => ['tec_portal/shipment_form']],
      '#cache' => [
        'tags' => TaxInvoice::listCacheTags(),
        'contexts' => ['url', 'user.roles'],
      ],
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->headers($kind, $open),
      '#empty' => $empty,
      '#attributes' => ['class' => ['tec-portal__table']],
      '#weight' => 10,
    ];
    foreach ($invoices as $invoice) {
      $build['table'][] = $this->row($invoice, $kind, $open);
    }
    return $build;
  }

  /**
   * @return array<int, mixed>
   */
  private function headers(string $kind, bool $open = TRUE): array {
    $amount = ['data' => $this->t('Amount'), 'class' => ['tec-inv-amount']];
    if ($kind === 'order') {
      $cols = [
        $this->t('Number'),
        $this->t('Date'),
        $this->t('What'),
        $amount,
        $this->t('Status'),
      ];
      if ($open) {
        $cols[] = '';
      }
      return $cols;
    }
    return [
      $this->t('Number'),
      $this->t('Date'),
      $this->t('Customer'),
      $this->t('Order'),
      $this->t('What'),
      $amount,
      '',
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function row(EntityInterface $invoice, string $kind, bool $open = TRUE): array {
    $issued = TaxInvoice::isIssued($invoice);
    $number = $issued
      ? TaxInvoice::formatNumber(TaxInvoice::number($invoice))
      : '—';
    $print = $issued ? $this->printLink($invoice, $number) : ['#plain_text' => $number];
    $date = ['#plain_text' => TaxInvoice::dateValue($invoice) ?: '—'];
    $what = ['#plain_text' => TaxInvoice::kindLabel($invoice)];
    $amount = [
      '#plain_text' => TaxInvoice::money(TaxInvoice::grossOf($invoice)),
      '#wrapper_attributes' => ['class' => ['tec-inv-amount']],
    ];
    if ($kind === 'order') {
      $status = TaxInvoice::isIssued($invoice) ? $this->t('Issued') : $this->t('Recorded');
      $row = [
        'number' => $print,
        'date' => $date,
        'what' => $what,
        'amount' => $amount,
        'status' => ['#plain_text' => $status],
      ];
      if ($open) {
        $row['action'] = $this->orderAction($invoice);
      }
      return $row;
    }
    return [
      'number' => $print,
      'date' => $date,
      'customer' => $this->entityLink(TaxInvoice::customer($invoice)),
      'order' => $this->orderLink($invoice),
      'what' => $what,
      'amount' => $amount,
      'print' => $this->printLink($invoice, $this->t('Print')),
    ];
  }

  private function printLink(EntityInterface $invoice, $title): array {
    $path = TaxInvoice::printPath($invoice);
    if ($path === '') {
      return ['#plain_text' => '—'];
    }
    return [
      '#type' => 'link',
      '#title' => $title,
      '#url' => Url::fromUri('internal:' . $path),
      '#attributes' => ['target' => '_blank'],
    ];
  }

  private function orderAction(EntityInterface $invoice): array {
    $order = TaxInvoice::orderOf($invoice);
    if (TaxInvoice::isIssued($invoice)) {
      return $this->printLink($invoice, $this->t('Print'));
    }
    if (!$order || !TaxInvoice::isDeposit($invoice)) {
      return ['#plain_text' => '—'];
    }
    return [
      '#type' => 'link',
      '#title' => $this->t('Open'),
      '#url' => Url::fromUri('internal:' . TaxInvoice::depositEditPath($order, $invoice)),
    ];
  }

  private function orderLink(EntityInterface $invoice): array {
    $order = TaxInvoice::orderOf($invoice);
    $ref = TaxInvoice::orderRefOf($invoice);
    if (!$order) {
      return ['#plain_text' => $ref !== '' ? $ref : '—'];
    }
    return [
      '#type' => 'link',
      '#title' => $ref !== '' ? $ref : (string) $order->label(),
      '#url' => Url::fromRoute('tec_portal.factory_order', ['tec_order' => $order->id()]),
    ];
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

}
