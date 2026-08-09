<?php

declare(strict_types=1);

namespace Drupal\entity_browser_vertical\Plugin\EntityBrowser\FieldWidgetDisplay;

use Drupal\entity_browser\Plugin\EntityBrowser\FieldWidgetDisplay\EntityLabel;

/**
 * Displays a label of the entity, stacked vertically.
 *
 * @EntityBrowserFieldWidgetDisplay(
 *   id = "entity_browser_vertical_label",
 *   label = @Translation("Entity label, stacked vertically"),
 *   description = @Translation("Displays entity with a label, stacked vertically.")
 * )
 */
class EntityBrowserVerticalEntityLabel extends EntityLabel {
}
