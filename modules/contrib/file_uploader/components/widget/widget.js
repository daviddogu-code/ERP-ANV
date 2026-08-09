/**
 * File uploader class storage.
 *
 * @type {Object}
 */
window.DrupalFileUploader = {};

((Drupal, once) => {
  /**
   * Attach file uploader widget to instances.
   */
  Drupal.behaviors.fileUploader = {
    attach(context, settings) {
      once("file-uploader", "div.file-uploader", context).forEach((element) => {
        const options = settings.file_uploader[element.id];
        if (!options) {
          return;
        }
        element.fileUploader = new window.DrupalFileUploader[options.provider](
          element,
          options
        );
      });
    },
  };
})(Drupal, once);
