(function ($, Drupal, drupalSettings, once) {

'use strict';

Drupal.behaviors.qt_accordion = {
  attach: function (context, settings) {
    $(once('quicktabs-accordion', 'div.quicktabs-accordion', context)).each(function() {
      var id = $(this).attr('id');
      var qtKey = 'qt_' + this.id.substring(this.id.indexOf('-') +1);
      var options = drupalSettings.quicktabs[qtKey].options;
      $(this).accordion(options);
    });
  }
}

})(jQuery, Drupal, drupalSettings, once);
