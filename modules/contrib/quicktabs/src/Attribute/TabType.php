<?php

namespace Drupal\quicktabs\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a tab type plugin attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class TabType extends Plugin {

  /**
   * Constructs a tab type attribute object.
   */
  public function __construct(
    string $id,
    public readonly ?TranslatableMarkup $name = NULL,
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
  }

}
