<?php

namespace Drupal\tec_production\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-save endpoint for the Stock Control board checkboxes.
 *
 * Every checkbox toggle on /stock POSTs here immediately (no Save button),
 * so nothing is lost on refresh. Accepts a batch so a master (whole column)
 * toggle is a single request. A row in {tec_stock_check} means checked.
 */
class StockCheckController extends ControllerBase {

  protected Connection $database;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * Saves checkbox state. Payload:
   * { "order_id": 348, "materials": [123, 456], "checked": true }
   */
  public function save(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    $order_id = (int) ($payload['order_id'] ?? 0);
    $materials = array_filter(array_map('intval', (array) ($payload['materials'] ?? [])), static fn(int $tid) => $tid > 0);
    $checked = !empty($payload['checked']);

    if ($order_id <= 0 || !$materials) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Invalid payload.'], 400);
    }

    $uid = (int) $this->currentUser()->id();
    $now = \Drupal::time()->getRequestTime();

    foreach ($materials as $tid) {
      if ($checked) {
        $this->database->merge('tec_stock_check')
          ->keys(['order_id' => $order_id, 'material_tid' => $tid])
          ->fields(['uid' => $uid, 'changed' => $now])
          ->execute();
      }
      else {
        $this->database->delete('tec_stock_check')
          ->condition('order_id', $order_id)
          ->condition('material_tid', $tid)
          ->execute();
      }
    }

    return new JsonResponse(['ok' => TRUE, 'count' => count($materials), 'checked' => $checked]);
  }

}
