<?php

namespace Drupal\tec_production\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\tec_production\Purchasing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-save endpoint for the Purchase Control screen.
 *
 * There is no Save button on /supplier-orders/queue. Each cell posts here as
 * soon as it is changed, the same arrangement the stock board has had since it
 * was built: this list gets edited a row at a time between phone calls, and a
 * screen that needs a button at the bottom is a screen where somebody
 * eventually closes the tab with a morning's worth of answers still in it.
 *
 * Only two things can be written, and only one of the two is a free choice.
 * Where the goods are is a person's report, so it is typed; when they arrive
 * and whether they were invoiced are facts, so they are read off the receipts
 * and the file and cannot be posted here at all.
 */
class PurchaseQueueController extends ControllerBase {

  /**
   * Saves one cell. Payload:
   * { "order": 943, "field": "status"|"expected", "value": "on_the_way" }
   *
   * The reply carries the recomputed status rather than an acknowledgement,
   * because the two are not always the same: an order that was received while
   * the page sat open comes back as Delivered no matter what was posted, and
   * the row corrects itself instead of showing a choice that no longer holds.
   */
  public function save(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    $oid = (int) ($payload['order'] ?? 0);
    $field = (string) ($payload['field'] ?? '');
    $value = trim((string) ($payload['value'] ?? ''));

    if ($oid <= 0 || !in_array($field, ['status', 'expected'], TRUE)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Invalid payload.'], 400);
    }

    $order = $this->entityTypeManager()->getStorage('tec_order')->load($oid);
    if (!$order || $order->bundle() !== 'tec_purchase_order') {
      return new JsonResponse(['ok' => FALSE, 'error' => 'No such purchase order.'], 404);
    }

    if ($field === 'status') {
      // The three steps and nothing else. Delivered, Closed and Cancelled are
      // worked out, and a hand-made post must not be able to claim one of them.
      if (!in_array($value, Purchasing::PROGRESS_MANUAL, TRUE)) {
        return new JsonResponse(['ok' => FALSE, 'error' => 'Not a status anyone may set.'], 400);
      }
      $order->set('field_tec_po_queue_status', $value);
    }
    else {
      if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return new JsonResponse(['ok' => FALSE, 'error' => 'Not a date.'], 400);
      }
      $order->set('field_tec_expected_delivery', $value === '' ? NULL : $value);
    }

    $order->save();
    $progress = Purchasing::progress($order);

    return new JsonResponse([
      'ok' => TRUE,
      'progress' => $progress,
      'label' => (string) Purchasing::PROGRESS[$progress],
    ]);
  }

}
