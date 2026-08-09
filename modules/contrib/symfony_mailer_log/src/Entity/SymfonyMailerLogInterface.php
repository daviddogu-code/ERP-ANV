<?php

namespace Drupal\symfony_mailer_log\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for the SymfonyMailerLog entity type.
 */
interface SymfonyMailerLogInterface extends ContentEntityInterface {

  /**
   * Returns the email type.
   *
   * @return string
   *   The email type.
   */
  public function getType(): string;

  /**
   * Sets the email type.
   *
   * @param string $type
   *   The email type.
   *
   * @return \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface
   *   The called entity.
   */
  public function setType(string $type): SymfonyMailerLogInterface;

  /**
   * Returns the email sub_type.
   *
   * @return string|null
   *   The email sub_type.
   */
  public function getSubType(): ?string;

  /**
   * Sets the email sub_type.
   *
   * @param string $sub_type
   *   The email sub_type.
   *
   * @return \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface
   *   The called entity.
   */
  public function setSubType(string $sub_type): SymfonyMailerLogInterface;

  /**
   * Returns the email subject.
   *
   * @return string|null
   *   The email subject.
   */
  public function getSubject(): ?string;

  /**
   * Sets the email subject.
   *
   * @param string $subject
   *   The email subject.
   *
   * @return \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface
   *   The called entity.
   */
  public function setSubject(string $subject): SymfonyMailerLogInterface;

  /**
   * Returns the email HTML body.
   *
   * @return string|null
   *   The email HTML body.
   */
  public function getHtmlBody(): ?string;

  /**
   * Sets the email HTML body.
   *
   * @param string $body
   *   The email HTML body.
   *
   * @return \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface
   *   The called entity.
   */
  public function setHtmlBody(string $body): SymfonyMailerLogInterface;

  /**
   * Returns the email text body.
   *
   * @return string|null
   *   The email text body.
   */
  public function getTextBody(): ?string;

  /**
   * Sets the email text body.
   *
   * @param string $body
   *   The email text body.
   *
   * @return \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface
   *   The called entity.
   */
  public function setTextBody(string $body): SymfonyMailerLogInterface;

  /**
   * Get the email To recipients.
   *
   * @return array
   *   List of email To recipients.
   */
  public function getTo(): array;

  /**
   * Returns the creation timestamp.
   *
   * @todo Remove and use the new interface when #2833378 is done.
   * @see https://www.drupal.org/node/2833378
   *
   * @return int
   *   Creation timestamp of the entity.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the creation timestamp.
   *
   * @todo Remove and use the new interface when #2833378 is done.
   * @see https://www.drupal.org/node/2833378
   *
   * @param int $timestamp
   *   The entity creation timestamp.
   *
   * @return \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface
   *   The called entity.
   */
  public function setCreatedTime(int $timestamp): SymfonyMailerLogInterface;

  /**
   * Returns the error message if sending failed.
   *
   * @return string|null
   *   The error message or null if there was no error sending the email.
   */
  public function getErrorMessage(): ?string;

  /**
   * Sets the error message if sending failed.
   *
   * @param string $errorMessage
   *   The error message to set.
   */
  public function setErrorMessage(string $errorMessage);

}
