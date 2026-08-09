(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.views_auto_refresh = {
    attach: function (context, settings) {
      for (let view_name in settings.views_auto_refresh) {
        for (let view_display in settings.views_auto_refresh[view_name]) {
          let interval = settings.views_auto_refresh[view_name][view_display];
          let execution_setting = '.view-' + view_name.replace(new RegExp('_', 'g'), '-') + '.view-display-id-' + view_display;
          if ($(execution_setting).length > 0) {
            if (settings.views_auto_refresh[view_name][view_display].timer) {
              clearTimeout(settings.views_auto_refresh[view_name][view_display].timer);
            }
            settings.views_auto_refresh[view_name][view_display].timer = setTimeout(
              function () {
                Drupal.behaviors.views_auto_refresh.refresh(execution_setting)
              }, interval
            );
          }
        }
      }
    },
    refresh: function (execution_setting) {
      $(execution_setting).trigger('RefreshView');
    }
  }
})(jQuery, Drupal, drupalSettings);
