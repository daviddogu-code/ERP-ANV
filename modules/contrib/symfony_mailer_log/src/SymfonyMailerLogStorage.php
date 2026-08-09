<?php

namespace Drupal\symfony_mailer_log;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Storage handler for symfony_mailer_log entities.
 */
class SymfonyMailerLogStorage extends SqlContentEntityStorage implements SymfonyMailerLogStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function deleteExpiredBatched(\DateInterval $maximum_age, int $batch_size = NULL): void {
    $now = new \DateTimeImmutable();
    $min_created = $now->sub($maximum_age);
    $query = $this->getQuery()
      ->accessCheck(FALSE)
      ->condition('created', $min_created->getTimestamp(), '<')
      ->sort('created')
      ->range(0, $batch_size);
    $result = $query->execute();
    $result = $result ? $this->loadMultiple($result) : [];
    $this->delete($result);
  }

}
