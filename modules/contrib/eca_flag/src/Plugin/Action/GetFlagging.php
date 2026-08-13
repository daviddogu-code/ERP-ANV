<?php

namespace Drupal\eca_flag\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gets a flagging entity for a content entity and stores it as a token.
 */
#[Action(
  id: 'eca_flag_get_flagging',
  label: new TranslatableMarkup('Flag: get flagging for entity'),
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Get a flagging for a content entity.'),
  version_introduced: '1.0.0',
)]
class GetFlagging extends ConfigurableActionBase {

  use PluginFormTrait;

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
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->flagService = $container->get('flag');
    return $plugin;
  }

  /**
   * Get the configured flag config entity.
   *
   * @return \Drupal\flag\FlagInterface|null
   *   The configured flag config entity.
   */
  private function getFlag(): ?FlagInterface {
    $flag_name = $this->configuration['flag_name'];
    if ($flag_name === '_eca_token') {
      $flag_name = $this->getTokenValue('flag_name', '');
    }
    if (($flag_name !== '') && $flag = $this->flagService->getFlagById($flag_name)) {
      return $flag;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $flag = $this->getFlag();
    if ($flag !== NULL) {
      $dependencies[$flag->getConfigDependencyKey()][] = $flag->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * Get a list of flaggings for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\flag\FlaggingInterface[]
   *   The list of flagging.
   */
  protected function getEntityFlaggings(ContentEntityInterface $entity): array {
    $flag = $this->getFlag();
    if ($flag !== NULL) {
      return $this->flagService->getEntityFlaggings($flag, $entity);
    }
    return $this->flagService->getAllEntityFlaggings($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;
    $access_result = AccessResult::forbidden();
    if ($object instanceof ContentEntityInterface &&
      $object->access('view', $account) &&
      $this->getEntityFlaggings($object)
    ) {
      $access_result = AccessResult::allowed();
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $entity = NULL): void {
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }
    $tokenName = $this->tokenService->replaceClear($this->configuration['token_name']);
    if ($flagging = $this->getEntityFlaggings($entity)) {
      if (count($flagging) === 1) {
        $flagging = reset($flagging);
      }
      $this->tokenService->addTokenData($tokenName, $flagging);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'flag_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $flags = [
      '' => $this->t('all'),
    ];
    foreach ($this->flagService->getAllFlags() as $flag) {
      $flags[$flag->id()] = $flag->label();
    }
    $form['flag_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Name of flag'),
      '#description' => $this->t('Provide the name of a flag.'),
      '#default_value' => $this->configuration['flag_name'],
      '#options' => $flags,
      '#weight' => -80,
      '#eca_token_select_option' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('Provide the name of a token that holds the loaded entity.'),
      '#default_value' => $this->configuration['token_name'],
      '#required' => TRUE,
      '#weight' => -90,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['flag_name'] = $form_state->getValue('flag_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
