<?php

namespace Drupal\eca_vbo\Event;

/**
 * Events provided by the eca_vbo module.
 */
final class EcaVboEvents {

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboExecuteEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const EXECUTE = 'eca_vbo.execute';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboExecuteMultipleEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const EXECUTE_MULTIPLE = 'eca_vbo.execute_multiple';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboCustomAccessEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const CUSTOM_ACCESS = 'eca_vbo.custom_access';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboFormBuildEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const FORM_BUILD = 'eca_vbo.form_build';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboFormValidateEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const FORM_VALIDATE = 'eca_vbo.form_validate';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboFormSubmitEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const FORM_SUBMIT = 'eca_vbo.form_submit';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboConfirmFormBuildEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const CONFIRM_FORM_BUILD = 'eca_vbo.confirm_form_build';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboConfirmFormValidateEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const CONFIRM_FORM_VALIDATE = 'eca_vbo.confirm_form_validate';

  /**
   * Identifies the \Drupal\eca_vbo\Event\VboConfirmFormSubmitEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const CONFIRM_FORM_SUBMIT = 'eca_vbo.confirm_form_submit';

}
