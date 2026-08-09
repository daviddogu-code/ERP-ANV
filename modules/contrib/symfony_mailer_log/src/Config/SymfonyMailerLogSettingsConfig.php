<?php

namespace Drupal\symfony_mailer_log\Config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * This class implements helper functions for typed configuration.
 */
class SymfonyMailerLogSettingsConfig {

  /**
   * The config factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The immutable symfony_mail_log configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Construct a SymfonyMailerLogSettingsConfig object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Get the immutable configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The immutable configuration.
   */
  public function config(): ImmutableConfig {
    if (!$this->config) {
      $this->config = $this->configFactory->get('symfony_mailer_log.settings');
    }

    return $this->config;
  }

  /**
   * Get the configured maximum age of log entries.
   *
   * Returns <code>NULL</code> if the configuration is invalid.
   *
   * @return \DateInterval|null
   *   The configured maximum age of log entries as DateInterval object.
   */
  public function getLogExpiryMaxAge(): ?\DateInterval {
    try {
      $date_interval = new \DateInterval(
        (string) $this->config()->get('log_expiry.max_age')
      );
    }
    catch (\Exception $e) {
      return NULL;
    }

    return $date_interval;
  }

  /**
   * Get the log expiry batch size.
   *
   * This function will return <code>NULL</code> for a batch size of <= 0.
   *
   * @return int|null
   *   The log expiry batch size.
   */
  public function getLogExpiryBatchSize(): ?int {
    $batch_size = $this->config()->get('log_expiry.batch_size');

    return $batch_size > 0 ? (int) $batch_size : NULL;
  }

}
