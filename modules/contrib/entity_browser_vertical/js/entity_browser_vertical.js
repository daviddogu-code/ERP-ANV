/**
 * @file
 * Contains entity_browser_vertical.js.
 */
(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.EntityBrowserVerticalBehavior = {
    attach: function (context, drupalSettings) {

      // Attach a listener to our "tabledrag" containers, so a warning message
      // can be displayed when they are drag-n-drop'ed.
      $(once('entity-browser-vertical', '.entity-browser-vertical', context)).each(function () {
        var $this = $(this);
        $this.on('drop', function (e) {
          // Delete previously-placed warning messages, if any.
          $this.parent().find('.tabledrag-changed-warning').hide();
          // Print a warning message, regardless of the current order.
          $(Drupal.theme('tableDragChangedWarning'))
            .insertBefore($this)
            .hide()
            .fadeIn('slow');
        });
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
