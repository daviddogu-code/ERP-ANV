<?php

namespace Drupal\tec_inventory;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * View vs edit for factory patterns.
 */
class PatternAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view tec patterns'),
      'update', 'delete' => AccessResult::allowedIfHasPermission($account, 'edit tec patterns'),
      default => parent::checkAccess($entity, $operation, $account),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'edit tec patterns');
  }

}
