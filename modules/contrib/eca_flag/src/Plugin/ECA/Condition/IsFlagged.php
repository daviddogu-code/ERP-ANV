<?php

namespace Drupal\eca_flag\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\flag\FlagService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ECA condition if content entity is flagged.
 */
#[EcaCondition(
  id: 'eca_flag_entity_is_flagged',
  label: new TranslatableMarkup('Flag: entity flagged'),
  context_definitions: [
    'entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup('Entity'),
    ),

  ],
  description: new TranslatableMarkup('Performs a lookup whether an entity is flagged.'),
  version_introduced: '1.0.0',
)]
class IsFlagged extends ConditionBase {

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagService
   */
  protected FlagService $flagService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->flagService = $container->get('flag');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    if ($entity instanceof EntityInterface) {
      $flagName = $this->configuration['flag_name'];
      if (!empty($flagName)) {
        $flagName = $this->tokenService->replaceClear($flagName);
      }
      if (!empty($flagName) && $flag = $this->flagService->getFlagById($flagName)) {
        return $this->negationCheck($flag->isFlagged($entity));
      }
    }
    return $this->negationCheck(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['flag_name' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $flags = [];
    foreach ($this->flagService->getAllFlags() as $flag) {
      $flags[$flag->id()] = $flag->label();
    }
    $form['flag_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Name of flag'),
      '#description' => $this->t('Provide the name of a flag.'),
      '#default_value' => $this->configuration['flag_name'],
      '#options' => $flags,
      '#required' => TRUE,
      '#weight' => -80,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['flag_name'] = $form_state->getValue('flag_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
