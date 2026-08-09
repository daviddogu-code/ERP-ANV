<?php

namespace Drupal\symfony_mailer_log;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List builder for symfony_mailer_log entities.
 */
class SymfonyMailerLogListBuilder extends EntityListBuilder {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new SymfonyMailerLogListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['to'] = $this->t('To');
    $header['subject'] = $this->t('Subject');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface $entity */
    $row['id'] = $entity->id();
    $row['to'] = implode(' / ', $entity->getTo());
    $row['subject'] = Link::createFromRoute($entity->getSubject(), 'entity.symfony_mailer_log.canonical', ['symfony_mailer_log' => $entity->id()]);
    $row['created'] = $this->dateFormatter->format($entity->getCreatedTime(), 'short');
    return $row + parent::buildRow($entity);
  }

}
