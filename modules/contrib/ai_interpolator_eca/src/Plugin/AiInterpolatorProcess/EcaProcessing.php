<?php

namespace Drupal\ai_interpolator_eca\Plugin\AiInterpolatorProcess;

use Drupal\ai_interpolator\AiInterpolatorRuleRunner;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldProcessInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The eca processor.
 *
 * @AiInterpolatorProcessRule(
 *   id = "eca",
 *   title = @Translation("ECA"),
 *   description = @Translation("Uses ECA to trigger the rule. You need to set it up in ECA after setting it up here."),
 * )
 */
class EcaProcessing implements AiInterpolatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * The batch.
   */
  protected array $batch;

  /**
   * AI Runner.
   */
  protected AiInterpolatorRuleRunner $aiRunner;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructor.
   */
  final public function __construct(AiInterpolatorRuleRunner $aiRunner, LoggerChannelFactoryInterface $logger) {
    $this->aiRunner = $aiRunner;
    $this->loggerFactory = $logger;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai_interpolator.rule_runner'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {

  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {

  }

}
