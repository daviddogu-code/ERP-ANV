<?php

namespace Drupal\symfony_mailer_log\Plugin\EmailAdjuster;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\symfony_mailer\AddressInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an email adjuster to log mail messages.
 *
 * It logs mails sent via the Symfony Mailer module.
 *
 * @EmailAdjuster(
 *   id = "symfony_mailer_log",
 *   label = @Translation("Log email"),
 *   description = @Translation("Log email to Drupal entity."),
 *   weight = 9999,
 * )
 */
class LogMail extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The symfony_mail_log entity storage.
   *
   * @var \Drupal\symfony_mailer_log\SymfonyMailerLogStorageInterface
   */
  protected $symfonyMailLogStorage;

  /**
   * The log of the e-mail being processed.
   *
   * @var \Drupal\symfony_mailer_log\Entity\SymfonyMailerLog
   */
  protected $log;

  /**
   * Creates an email adjuster plugin for using Maillog via Symfony Mailer.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->symfonyMailLogStorage = $entity_type_manager->getStorage('symfony_mailer_log');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * React to EmailInterface::PHASE_POST_RENDER.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function postRender(EmailInterface $email) {
    try {
      $log = $this->symfonyMailLogStorage->create([
        'type' => $email->getType(),
        'sub_type' => $email->getSubType(),
        'from' => $this->addressesToString($email->getFrom()),
        'to' => $this->addressesToString($email->getTo()),
        'reply_to' => $this->addressesToString($email->getReplyTo()),
        'cc' => $this->addressesToString($email->getCc()),
        'bcc' => $this->addressesToString($email->getBcc()),
        'headers' => $email->getHeaders()->toArray(),
        'subject' => (string) $email->getSubject(),
        'html_body' => $email->getHtmlBody(),
        'text_body' => $email->getTextBody(),
        'account' => $email->getAccount()->id(),
        'theme' => $email->getTheme(),
        'transport_dsn' => $email->getTransportDSN(),
        'langcode' => $email->getLangcode(),
      ]);
      $log->save();

      // Store the log entity on so it can updated later when the 'errorMessage'
      // is set (or left unset) after attempting to actually send the email.
      $this->log = $log;
    }
    catch (\Exception $ex) {
      $this->getLogger('symfony_mail_log')->error($ex->getMessage());
    }
  }

  /**
   * React to EmailInterface::PHASE_POST_SEND.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function postSend(EmailInterface $email) {
    if ($log = $this->log) {
      if ($errorMessage = $email->getError()) {
        $log->setErrorMessage(substr($errorMessage, 0, 255));
      }
      $log->save();
    }
    $this->log = NULL;
  }

  /**
   * Render array of addresses as array of strings.
   *
   * @param \Drupal\symfony_mailer\AddressInterface[] $addresses
   *   An array of Address objects.
   *
   * @return array
   *   An array of strings.
   */
  protected function addressesToString(array $addresses): array {
    return array_map(function (AddressInterface $address) {
      if ($address->getDisplayName()) {
        return $address->getDisplayName() . ' <' . $address->getEmail() . '>';
      }
      return $address->getEmail();
    }, $addresses);
  }

}
