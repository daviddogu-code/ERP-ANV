<?php

namespace Drupal\tec_inventory\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\eck\EckEntityInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\tec_inventory\PatternRecipe;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Save extras from the product ficha and suggest inventory SKUs.
 */
final class ColorExtrasController extends ControllerBase {

  /**
   * POST JSON { lines: [ { cells: { termId: { target_id, qty } } } ] }.
   */
  public function save(Request $request, EckEntityInterface $tec_product): JsonResponse {
    $color = $tec_product;
    if ($color->bundle() !== 'tec_color_variation') {
      throw new NotFoundHttpException();
    }
    if (!$color->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $payload = json_decode((string) $request->getContent(), TRUE);
    $lines = is_array($payload['lines'] ?? NULL) ? $payload['lines'] : [];
    PatternRecipe::applyExtraLines($color, $lines);
    return new JsonResponse(['ok' => TRUE]);
  }

  /**
   * Inventory SKU names for the extra autocomplete.
   */
  public function skuAutocomplete(Request $request): JsonResponse {
    $q = trim((string) $request->query->get('q'));
    if ($q === '' || mb_strlen($q) < 1) {
      return new JsonResponse([]);
    }
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'tec_inventory')
      ->condition('name', $q, 'CONTAINS')
      ->sort('name')
      ->range(0, 15)
      ->execute();
    $matches = [];
    foreach ($storage->loadMultiple($ids) as $term) {
      $label = (string) $term->label();
      $meta = PatternRecipe::skuMeta($term instanceof TermInterface ? $term : NULL);
      $matches[] = [
        'value' => $label . ' (' . $term->id() . ')',
        'label' => $label,
        'uou' => $meta['uou'],
        'price' => $meta['price'],
      ];
    }
    return new JsonResponse($matches);
  }

  /**
   * UoU and unit price for one inventory SKU.
   */
  public function skuMeta(Request $request): JsonResponse {
    $id = (int) $request->query->get('id');
    $term = $id ? $this->entityTypeManager()->getStorage('taxonomy_term')->load($id) : NULL;
    if (!$term instanceof TermInterface || $term->bundle() !== 'tec_inventory') {
      return new JsonResponse(['uou' => '', 'price' => NULL]);
    }
    return new JsonResponse(PatternRecipe::skuMeta($term));
  }

}
