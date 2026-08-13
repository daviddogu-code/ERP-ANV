<?php

namespace Drupal\quicktabs\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a tab renderer plugin attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class TabRenderer extends Plugin {

  /**
   * Constructs a tab renderer attribute object.
   */
  public function __construct(
    string $id,
    public readonly ?TranslatableMarkup $name = NULL,
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
  }

}
