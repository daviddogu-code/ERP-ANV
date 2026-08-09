<?php

namespace Drupal\symfony_mailer_log;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Drupal Symfony Mailer log entry entity.
 */
class SymfonyMailerLogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface $entity */

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view symfony mailer log entries');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete symfony mailer log entries');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

}
