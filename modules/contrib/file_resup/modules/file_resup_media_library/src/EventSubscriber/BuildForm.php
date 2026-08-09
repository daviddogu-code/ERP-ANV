<?php

namespace Drupal\file_resup_media_library\EventSubscriber;

use Drupal\Core\Form\FormState;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\file_resup\Event\BuildFormEvent;
use Drupal\media_library\MediaLibraryState;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * File Resumable Upload event subscriber.
 */
class BuildForm implements EventSubscriberInterface {

  protected FormBuilderInterface $formBuilder;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param \Drupal\file_resup\Event\BuildFormEvent $event
   *
   * @return array
   *   The media library form.
   */
  public function onBuildForm(BuildFormEvent $event) {
    if ($event->getFormType() === 'media_library') {
      $parameters = $event->getParameters();
      $init_state_values = Json::decode($parameters['media_library']);
      $media_library_state = MediaLibraryState::create($init_state_values['media_library_opener_id'], $init_state_values['media_library_allowed_types'], $init_state_values['media_library_selected_type'], $init_state_values['media_library_remaining'], $init_state_values['media_library_opener_parameters']);
      $form_state = new FormState();
      $form_state->set('media_library_state', $media_library_state);
      $form = $this->formBuilder->buildForm('Drupal\media_library\Form\FileUploadForm', $form_state);
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
