<?php

namespace Drupal\eca_commerce\Plugin\ECA\Condition;

use Drupal\commerce\ConditionManagerInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derive any Commerce condition plugin into an ECA condition.
 */
class CommerceDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The commerce condition plugin manager.
   *
   * @var \Drupal\commerce\ConditionManagerInterface
   */
  protected ConditionManagerInterface $commerceConditionManager;

  /**
   * Constructs a CommerceDeriver class.
   *
   * @param \Drupal\commerce\ConditionManagerInterface $commerce_condition_manager
   *   The commerce condition manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(ConditionManagerInterface $commerce_condition_manager, TranslationInterface $string_translation) {
    $this->commerceConditionManager = $commerce_condition_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.manager.commerce_condition'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach ($this->commerceConditionManager->getDefinitions() as $definition) {
      $this->derivatives[$definition['id']] = [
        'id' => 'eca_commerce_commerce:' . $definition['id'],
        'label' => $this->t('Commerce: @label', ['@label' => $definition['display_label']->render()]),
        'original_label' => $definition['label'],
        'entity_type' => $definition['entity_type'],
        'category' => $definition['category'],
        'weight' => $definition['weight'],
        'commerce_plugin' => $definition['id'],
        'context_definitions' => [
          'entity' => new ContextDefinition('entity', $this->t('Entity'), TRUE),
        ],
      ] + $base_plugin_definition;

      if (array_key_exists('parent_entity_type', $definition)) {
        $this->derivatives[$definition['id']]['parent_entity_type'] = $definition['parent_entity_type'];
      }
    }
    return $this->derivatives;
  }

}
