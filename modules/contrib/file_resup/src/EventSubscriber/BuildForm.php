<?php

namespace Drupal\file_resup\EventSubscriber;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\file_resup\Event\BuildFormEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * File Resumable Upload event subscriber.
 */
class BuildForm implements EventSubscriberInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BuildForm object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param \Drupal\file_resup\Event\BuildFormEvent $event
   *
   * @return array
   *   The form that contains the file widget.
   */
  public function onBuildForm(BuildFormEvent $event) {
    if ($event->getFormType() === 'file_widget') {
      $parameters = $event->getParameters();
      $form_object = $this->entityTypeManager->getFormObject($parameters['entity_type_id'], $parameters['operation']);
      $bundle_key = $this->entityTypeManager->getDefinition($parameters['entity_type_id'])->getKey('bundle');
      $entity = $this->entityTypeManager->getStorage($parameters['entity_type_id'])->create([$bundle_key => $parameters['bundle']]);
      $form_object->setEntity($entity);
      $form_state = new FormState();
      $form_state->setFormObject($form_object);
      $form = $this->formBuilder->buildForm($form_object, $form_state);
      $event->setForm($form);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      BuildFormEvent::class => ['onBuildForm'],
    ];
  }

}
