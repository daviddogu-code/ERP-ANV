<?php

namespace Drupal\eca_vbo\Plugin\Action\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides actions for any existing ECA config reacting upon VBO execution.
 */
class VboExecuteDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The generated derivative definitions.
   *
   * @var array|null
   */
  protected ?array $definitions = NULL;

  /**
   * Constructs a new EntityActionDeriverBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!isset($this->definitions)) {
      $definitions = [];
      $plugin_ids = ['vbo:execute', 'vbo:execute_multiple'];
      /** @var \Drupal\eca\Entity\Eca $eca */
      foreach ($this->entityTypeManager->getStorage('eca')->loadMultiple() as $eca) {
        if (!$eca->status()) {
          continue;
        }
        foreach (($eca->get('events') ?? []) as $event) {
          if (!in_array($event['plugin'], $plugin_ids, TRUE)) {
            continue;
          }
          $operation_name = $event['configuration']['operation_name'] ?? '';
          $action_id = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "_", trim($operation_name)));
          if ($action_id !== '' && $action_id !== '_') {
            if (!isset($definitions[$action_id])) {
              $definitions[$action_id] = [
                'label' => str_replace('_', ' ', ucfirst($operation_name)),
                'operation_name' => $operation_name,
              ] + $base_plugin_definition;
            }
            $definitions[$action_id]['eca_config'][] = $eca->id();
          }
        }
      }
      $this->definitions = $definitions;
      $this->derivatives = $definitions;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
