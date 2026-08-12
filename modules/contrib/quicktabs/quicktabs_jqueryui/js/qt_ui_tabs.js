(function ($, Drupal, drupalSettings, once) {

'use strict';

Drupal.behaviors.qt_ui_tabs = {
  attach: function (context, settings) {
    $(once('quicktabs-ui-wrapper', 'div.quicktabs-ui-wrapper', context)).each(function() {
      var id = $(this).attr('id');
      var qtKey = 'qt_' + this.id.substring(this.id.indexOf('-') +1);
      $(this).tabs({ active: drupalSettings.quicktabs[qtKey].default_tab });
    });
  }
}

})(jQuery, Drupal, drupalSettings, once);
