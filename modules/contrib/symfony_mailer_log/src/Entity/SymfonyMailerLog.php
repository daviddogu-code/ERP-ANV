<?php

namespace Drupal\symfony_mailer_log\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the Symfony Mailer log entry entity class.
 *
 * @ContentEntityType(
 *   id = "symfony_mailer_log",
 *   label = @Translation("Drupal Symfony Mailer log entry"),
 *   label_singular = @Translation("log entry"),
 *   label_plural = @Translation("log entries"),
 *   label_count = @PluralTranslation(
 *     singular = "@count log entry",
 *     plural = "@count log entries",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\symfony_mailer_log\SymfonyMailerLogAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\symfony_mailer_log\SymfonyMailerLogListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "storage" = "Drupal\symfony_mailer_log\SymfonyMailerLogStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "symfony_mailer_log",
 *   internal = TRUE,
 *   admin_permission = "administer symfony mailer entity log entries",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/admin/reports/symfony_mailer_log/{symfony_mailer_log}",
 *     "delete-form" = "/admin/reports/symfony_mailer_log/{symfony_mailer_log}/delete",
 *     "collection" = "/admin/reports/symfony_mailer_log"
 *   },
 *   field_ui_base_route = "entity.symfony_mailer_log.collection"
 * )
 */
class SymfonyMailerLog extends ContentEntityBase implements SymfonyMailerLogInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getSubject() ?? $this->t('Mail log entry #@id', ['@id' => $this->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setType(string $type): SymfonyMailerLogInterface {
    $this->set('type', $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubType(): ?string {
    return $this->get('sub_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubType(string $sub_type): SymfonyMailerLogInterface {
    $this->set('sub_type', $sub_type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject(): ?string {
    return $this->get('subject')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject(string $subject): SymfonyMailerLogInterface {
    $this->set('subject', $subject);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlBody(): ?string {
    return $this->get('html_body')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHtmlBody(string $body): SymfonyMailerLogInterface {
    $this->set('html_body', $body);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextBody(): ?string {
    return $this->get('text_body')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTextBody(string $body): SymfonyMailerLogInterface {
    $this->set('text_body', $body);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTo(): array {
    if (!empty($this->get('to')->getValue())) {
      return array_map(function (array $data) {
        return $data['value'];
      }, $this->get('to')->getValue());
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): SymfonyMailerLogInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorMessage(): ?string {
    return $this->get('error_message');
  }

  /**
   * {@inheritdoc}
   */
  public function setErrorMessage(string $errorMessage) {
    $this->set('error_message', $errorMessage);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The email type.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['sub_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sub type'))
      ->setDescription(t('The email sub type.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['from'] = BaseFieldDefinition::create('string')
      ->setLabel(t('From'))
      ->setDescription(t('The email from addresses.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['to'] = BaseFieldDefinition::create('string')
      ->setLabel(t('To'))
      ->setDescription(t('The email to addresses.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['reply_to'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reply-to'))
      ->setDescription(t('The email reply_to addresses.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['cc'] = BaseFieldDefinition::create('string')
      ->setLabel(t('CC'))
      ->setDescription(t('The email cc addresses.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['bcc'] = BaseFieldDefinition::create('string')
      ->setLabel(t('BCC'))
      ->setDescription(t('The email bcc addresses.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['headers'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Headers'))
      ->setDescription(t('The email headers.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject'))
      ->setDescription(t('The email subject.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['html_body'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('HTML body'))
      ->setDescription(t('The email HTML body.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'symfony_mailer_log_html_body',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['text_body'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Text body'))
      ->setDescription(t('The email text body.'))
      ->setRequired(FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['account'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Account'))
      ->setDescription(t('The account associated with the recipient of this email.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['theme'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Theme'))
      ->setDescription(t('The email theme.'))
      ->setRequired(FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['transport_dsn'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Transport DSN'))
      ->setDescription(t('The email transport DSN.'))
      ->setRequired(FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the log entry was created.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['error_message'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Error message'))
      ->setRequired(FALSE)
      ->setDescription(t('The error message if sending the email failed.'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
