/**
 * @file
 */

 (function (Drupal, once) {
  Drupal.behaviors.tinyslider = {
    attach: function (context, settings) {
      once('tiny-slider', '.tiny-slider-wrapper', context).forEach(
        function (sliderContainer) {
          opt = JSON.parse(sliderContainer.getAttribute('data-settings'));
          opt.container = sliderContainer;
          var slider = tns(opt);
        }
      );
    }
  };
}(Drupal, once));
