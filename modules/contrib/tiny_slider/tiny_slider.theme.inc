<?php

/**
 * @file
 * Theme.inc for functions.
 */

use Drupal\tiny_slider\TinySliderView;
use Drupal\Component\Serialization\Json;

/**
 * Prepares variables for views carousel template.
 *
 * Default template: views-bootstrap-carousel.html.twig.
 *
 * @param array $vars
 *   An associative array containing:
 *   - view: A ViewExecutable object.
 *   - rows: The raw row data.
 */
function template_preprocess_tiny_slider_views(array &$vars) {
  $view = $vars['view'];
  $handler = $vars['view']->style_plugin;
  $settings = $handler->options;

  $vars['id'] = TinySliderView::getUniqueId($view);
  $vars['attributes']['class'][] = 'tiny-slider-wrapper';
  $vars['attributes']['class'][] = $vars['id'];
  $vars['attributes']['class'][] = 'tiny-slider';
  $vars['#attached']['library'][] = 'tiny_slider/tiny_slider';
  // Config set for tiny_slider.
  // This is then accessible in JS via drupalSettings.
  $vars['#attached']['drupalSettings']['tiny_slider_views'][$vars['id']] = Json::encode($settings);
}
