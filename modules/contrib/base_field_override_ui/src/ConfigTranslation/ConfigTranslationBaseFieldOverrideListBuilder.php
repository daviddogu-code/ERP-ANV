<?php

namespace Drupal\base_field_override_ui\ConfigTranslation;

use Drupal\base_field_override_ui\BaseFieldOverrideUI;
use Drupal\config_translation\Controller\ConfigTranslationFieldListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the config translation list builder for base field override entities.
 */
class ConfigTranslationBaseFieldOverrideListBuilder extends ConfigTranslationFieldListBuilder {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $baseFieldOverrideStorage;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->baseFieldOverrideStorage = $container->get('entity_type.manager')->getStorage('base_field_override');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $ids = $this->baseFieldOverrideStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $this->baseEntityType . '.', 'STARTS_WITH')
      ->execute();
    return $this->storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Base Field');
    if ($this->displayBundle()) {
      $header['bundle'] = $this->baseEntityInfo->getBundleLabel() ?: $this->t('Bundle');
    }
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    /** @var \Drupal\Core\Field\Entity\BaseFieldOverride $entity */
    $operations = [];
    $operations['translate'] = [
      'title' => $this->t('Translate'),
      'weight' => 1,
      'url' => BaseFieldOverrideUI::getTranslateRouteInfo($entity),
    ];

    return $operations;
  }

}
