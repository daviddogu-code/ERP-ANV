<?php

namespace Drupal\symfony_mailer_log;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Interface for SymfonyMailerLogStorage class.
 */
interface SymfonyMailerLogStorageInterface extends ContentEntityStorageInterface {

  /**
   * Delete a number of expired Symfony Mailer log entries.
   *
   * Older entries are deleted first.
   *
   * @param \DateInterval $maximum_age
   *   Interval defining the max age of entries after which they are deleted.
   * @param int|null $batch_size
   *   The number of entries to delete. <code>NULL</code> to delete all entries.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public function deleteExpiredBatched(\DateInterval $maximum_age, int $batch_size = NULL): void;

}
