<?php

namespace Drupal\file_resup\Event;

use Drupal\Component\EventDispatcher\Event;

class BuildFormEvent extends Event {

  private string $form_type;

  private array $parameters;

  public function __construct(string $form_type, array $parameters) {
    $this->form_type = $form_type;
    $this->parameters = $parameters;
  }

  /**
   * @var array
   *   The form with the relevant managed file.
   */
  protected array $form = [];

  /**
   * @return array
   */
  public function getForm(): array {
    return $this->form;
  }

  /**
   * @param array $form
   */
  public function setForm(array $form): void {
    $this->form = $form;
  }

  /**
   * @return string
   */
  public function getFormType(): string {
    return $this->form_type;
  }

  /**
   * @return array
   */
  public function getParameters(): array {
    return $this->parameters;
  }

}
