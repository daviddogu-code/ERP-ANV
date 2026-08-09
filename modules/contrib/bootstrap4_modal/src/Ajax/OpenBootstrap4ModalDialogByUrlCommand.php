<?php

namespace Drupal\bootstrap4_modal\Ajax;

use Drupal\bootstrap4_modal\Ajax\OpenBootstrap4ModalDialogCommand;

/**
 * Defines an AJAX command to open certain content in a dialog in a modal.
 *
 * @ingroup ajax
 */
class OpenBootstrap4ModalDialogByUrlCommand extends OpenBootstrap4ModalDialogCommand {
  /**
   * {@inheritdoc}
   */
  public function __construct($title, $url, array $dialog_options = [], $settings = NULL) {
    $dialog_options['modal'] = TRUE;

    $settings['url'] = $url;

    $content = NULL;
    parent::__construct($title, $content, $dialog_options, $settings);
  }

  /**
   * Implements \Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    // For consistency ensure the modal option is set to TRUE or FALSE.
    $this->dialogOptions['modal'] = isset($this->dialogOptions['modal']) && $this->dialogOptions['modal'];
    return [
      'command' => 'openBootstrap4DialogByUrl',
      'selector' => $this->selector,
      'settings' => $this->settings,
      'data' => $this->getRenderedContent(),
      'dialogOptions' => $this->dialogOptions,
    ];
  }
}
