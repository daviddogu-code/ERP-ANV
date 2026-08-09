<?php

namespace Drupal\ai_interpolator_eca\Plugin\Action;

use Drupal\ai_interpolator\AiInterpolatorRuleRunner;
use Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldRuleManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\eca\EcaState;
use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\eca\Plugin\Action\ConfigurableActionTrait;
use Drupal\eca\Service\ContentEntityTypes;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca\TypedData\PropertyPathTrait;
use Drupal\eca_content\Plugin\EntitySaveTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set the value of an entity field.
 *
 * @Action(
 *   id = "eca_ai_interpolator_rule",
 *   label = @Translation("AI Interpolator Rule"),
 *   description = @Translation("Runs an AI Interpolator rule."),
 *   type = "entity"
 * )
 */
class AiInterpolatorRule extends ActionBase implements ConfigurableInterface, PluginFormInterface, DependentPluginInterface {

  use EntitySaveTrait;
  use ConfigurableActionTrait;
  use PropertyPathTrait;

  /**
   * Triggered event leading to this action.
   *
   * @var \Drupal\Component\EventDispatcher\Event|\Symfony\Contracts\EventDispatcher\Event
   */
  protected object $event;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The ECA-related token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenServices;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * ECA state service.
   *
   * @var \Drupal\eca\EcaState
   */
  protected EcaState $state;

  /**
   * The entity type service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The entity type bundle info service.
   */
  protected EntityTypeBundleInfoInterface $bundles;

  /**
   * The AI Interpolator field rule manager.
   */
  protected AiInterpolatorFieldRuleManager $fieldRules;

  /**
   * The AI Interpolator rule runner.
   */
  protected AiInterpolatorRuleRunner $ruleRunner;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    TokenInterface $token_services,
    AccountProxyInterface $current_user,
    TimeInterface $time,
    EcaState $state,
    ContentEntityTypes $entity_types,
    EntityFieldManagerInterface $fieldManager,
    EntityTypeBundleInfoInterface $bundles,
    AiInterpolatorFieldRuleManager $fieldRules,
    AiInterpolatorRuleRunner $ruleRunner) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $token_services, $current_user, $time, $state);
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenServices = $token_services;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->state = $state;
    $this->entityTypes = $entity_types;
    $this->fieldManager = $fieldManager;
    $this->bundles = $bundles;
    $this->fieldRules = $fieldRules;
    $this->ruleRunner = $ruleRunner;

    if ($this instanceof ConfigurableInterface) {
      $this->setConfiguration($configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ActionBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('eca.token_services'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('eca.state'),
      $container->get('eca.service.content_entity_types'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.ai_interpolator'),
      $container->get('ai_interpolator.rule_runner')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute($entity = NULL) {
    $parts = explode('--', $this->configuration['rule_name']);
    // The rule failed somehow.
    if (count($parts) != 3) {
      throw new \InvalidArgumentException('Invalid rule name');
    }
    // Check so the rule belongs to the entity.
    if ($entity->getEntityTypeId() != $parts[0] || $entity->bundle() != $parts[1]) {
      throw new \InvalidArgumentException('The entity and the bundle does not match the rule');
    }
    // Get field definition.
    $fieldDefinition = $this->fieldManager->getFieldDefinitions($parts[0], $parts[1])[$parts[2]];
    if (!$fieldDefinition) {
      throw new \RuntimeException('No field definition found');
    }
    // Get config.
    $config = $fieldDefinition->getConfig($parts[1])->getThirdPartySettings('ai_interpolator');
    if (!$config) {
      throw new \RuntimeException('No config found');
    }
    // Get rule name for validation.
    $ruleName = $config['interpolator_rule'] ?? NULL;
    if (!$ruleName) {
      throw new \InvalidArgumentException('No rule name found');
    }
    // Load the rule.
    $rule = $this->fieldRules->createInstance($ruleName);
    if (!$rule) {
      throw new \InvalidArgumentException('No rule found');
    }
    // Prepare the config as in the normal module.
    $interpolatorConfig = [];
    foreach ($config as $key => $setting) {
      $interpolatorConfig[substr($key, 13)] = $setting;
    }
    $value = $entity->get($fieldDefinition->getName())->getValue();
    // Rule if overwrite is on or if empty.
    if ($this->configuration['overwrite'] || empty($rule->checkIfEmpty($value))) {
      // Run the rule.
      $this->ruleRunner->generateResponse($entity, $fieldDefinition, $interpolatorConfig);
    }

    // Save entity if wanted.
    if (!empty($this->configuration['save_entity'])) {
      $this->save($entity);
    }

  }

  /**
   * The save method.
   *
   * From the SetFieldValue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which might have to be saved.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function save(EntityInterface $entity): void {
    if (empty($entity->eca_context) || !empty($this->configuration['save_entity'])) {
      $this->saveEntity($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'rule_name' => '',
      'overwrite' => FALSE,
      'save_entity' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $form['rule_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Rule'),
      '#options' => $this->getAvailableEcaRules(),
      '#weight' => -30,
      '#default_value' => $this->configuration['rule_name'],
    ];

    $form['overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Overwrite existing content.'),
      '#description' => $this->t('If checked, the existing content will be overwritten each save. This is useful when you want your ECA process to be in full controll when the content is updated. If unchecked, the ECA process will only run when the field is empty.'),
      '#default_value' => $this->configuration['overwrite'],
      '#weight' => -20,
    ];

    $form['save_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save entity after interpolation.'),
      '#description' => $this->t('If checked, this rule will also take care of saving the entity.'),
      '#default_value' => $this->configuration['save_entity'],
      '#weight' => -21,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['rule_name'] = $form_state->getValue('rule_name');
    $this->configuration['overwrite'] = $form_state->getValue('overwrite');
    $this->configuration['save_entity'] = $form_state->getValue('save_entity');
  }

  /**
   * Get available ECA rules.
   *
   * @return array
   *   The available ECA rules.
   */
  protected function getAvailableEcaRules() {
    // Get all field rules.
    $fieldrules = [];
    foreach ($this->fieldRules->getDefinitions() as $id => $definition) {
      $fieldrules[$id] = $definition['title'];
    }
    $entityTypes = $this->entityTypeManager->getDefinitions();
    $rules = [
      '' => $this->t('Select a rule'),
    ];
    foreach ($entityTypes as $entityTypeId => $entityType) {
      if (!($entityType instanceof ContentEntityTypeInterface)) {
        continue;
      }
      if ($entityType->hasKey('bundle')) {
        $bundles = $this->bundles->getBundleInfo($entityTypeId);
        foreach ($bundles as $bundleId => $bundle) {
          $fieldsDefintions = $this->fieldManager->getFieldDefinitions($entityTypeId, $bundleId);
          foreach ($fieldsDefintions as $fieldDefinition) {
            $configs = $fieldDefinition->getConfig($bundleId)->getThirdPartySettings('ai_interpolator');
            if (isset($configs['interpolator_worker_type']) && $configs['interpolator_worker_type'] == 'eca') {
              $rules[$entityTypeId . '--' . $bundleId . '--' . $fieldDefinition->getName()] = $entityType->getLabel() . ' (' . $bundle['label'] . ') - ' . $fieldDefinition->getLabel() . ': ' . $fieldrules[$configs['interpolator_rule']];
            }
          }
        }
      }
    }

    return $rules;
  }

}
