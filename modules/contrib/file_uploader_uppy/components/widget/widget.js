import "./widget.scss";
import UppyCore from "@uppy/core";
import Dashboard from "@uppy/dashboard";
import XHR from "@uppy/xhr-upload";
import ImageEditor from "@uppy/image-editor";

class FileUploader {
  /**
   * FileUploader constructor.
   *
   * @param {HTMLElement} element
   *   The file uploader element.
   * @param {Object} settings
   *   The configuration options.
   */
  constructor(element, settings) {
    this.form = element.closest("form");
    this.actions = this.form.querySelectorAll(".form-actions .form-submit");
    this.input = element.querySelector(`[name="${settings.name}[fids]"]`);
    this.settings = settings;

    // Prepare the options.
    settings.options.plugin.target = element;
    settings.options.instance.restrictions = {};
    if (settings.options.validators.limit > 0) {
      settings.options.instance.restrictions.maxNumberOfFiles =
        settings.options.validators.limit;
    }
    if (settings.options.validators.extensions) {
      settings.options.instance.restrictions.allowedFileTypes =
        settings.options.validators.extensions;
    }
    if (settings.options.validators.filesize) {
      settings.options.instance.restrictions.maxFileSize =
        settings.options.validators.filesize;
    }
    settings.options.plugin.inline = true;
    settings.options.plugin.doneButtonHandler = null;
    settings.options.plugin.showRemoveButtonAfterComplete = true;
    if (
      settings.options.instance.autoProceed &&
      settings.options.plugin.autoOpenFileEditor
    ) {
      settings.options.instance.autoProceed = false;
      settings.options.instance.autoProceedImageEditor = true;
    }
    settings.options.plugin.proudlyDisplayPoweredByUppy = false;

    // Apply the locale translations.
    if (settings.options.instance.locale) {
      const locale = Uppy.locales[settings.options.instance.locale];
      if (typeof locale !== "undefined") {
        settings.options.instance.locale = locale;
      } else {
        delete settings.options.instance.locale;
      }
    }

    // Use the Dashboard plugin.
    const instance = new UppyCore(settings.options.instance).use(
      Dashboard,
      settings.options.plugin
    );
    this.instance = instance;
    if (settings.options.uploader.method === "xhr") {
      instance.use(XHR, { endpoint: settings.options.xhr });
    }
    instance.on("upload", this.onUpload.bind(this));
    instance.on("upload-success", this.onUploadSuccess.bind(this));
    instance.on("complete", this.onComplete.bind(this));
    instance.on("cancel-all", this.onComplete.bind(this));
    instance.on("file-removed", this.onFileRemoved.bind(this));

    const plugin = instance.getPlugin("Dashboard");
    const { handleDrop, openFileEditor, hideAllPanels } = plugin;
    plugin.handleDrop = (event) => {
      if (this.settings.options.validators.limit === 1) {
        // Allow drop to replace the current single file.
        plugin.hideAllPanels();
        instance.cancelAll();
      }
      return handleDrop(event);
    };
    plugin.openFileEditor = (file) => {
      plugin.el.classList.add("uppy--PanelOpen");
      openFileEditor(file);
    };
    plugin.hideAllPanels = () => {
      plugin.el.classList.remove("uppy--PanelOpen");
      hideAllPanels();
    };

    // Use the Image Editor plugin.
    if (settings.options.plugin.enableImageEditor) {
      settings.options.plugin.imageEditor.target = Dashboard;
      settings.options.plugin.imageEditor.quality = 1;
      if (settings.options.plugin.imageEditor.actions.length) {
        const actions = {
          revert: false,
          rotate: false,
          granularRotate: false,
          flip: false,
          zoomIn: false,
          zoomOut: false,
          cropSquare: false,
          cropWidescreen: false,
          cropWidescreenVertical: false,
        };
        settings.options.plugin.imageEditor.actions.forEach((action) => {
          actions[action] = true;
        });
        settings.options.plugin.imageEditor.actions = actions;
      }
      instance.use(ImageEditor, settings.options.plugin.imageEditor);
      instance.on("file-editor:complete", this.onImageEditorDone.bind(this));

      // Prevent opening image editor on existing files.
      const editorPlugin = instance.getPlugin("ImageEditor");
      const { canEditFile } = editorPlugin;
      editorPlugin.canEditFile = (file) => {
        return file && file.source && canEditFile(file);
      };
    }

    // Add the existing files.
    settings.values.forEach((file) => {
      const mockFile = new File([""], file.name);
      Object.defineProperty(mockFile, "size", {
        value: parseInt(file.size, 10),
      });

      // Use mock file to immediately display preview. Automatically
      // update previews of existing image files.
      const fileId = instance.addFile({
        name: file.name,
        type: file.type,
        data: mockFile,
      });
      instance.setFileState(fileId, {
        progress: { uploadComplete: true, uploadStarted: true },
      });
      instance.setFileMeta(fileId, { fid: file.fid });

      // Update existing image thumbnail preview.
      if (file.type.match(/^image\//)) {
        fetch(file.url)
          .then((response) => response.blob())
          .then((blob) => {
            const existingFile = instance.getFile(fileId);
            if (existingFile) {
              existingFile.data = blob;
              instance.emit("thumbnail:request", existingFile);
            }
          });
      }
    });
  }

  /**
   * Get the current files.
   *
   * @return {string[]}
   *   Returns an array of files.
   */
  getFiles() {
    if (this.input.value) {
      return this.input.value.trim().split(" ");
    }
    return [];
  }

  /**
   * Set the current files.
   *
   * @param {Array} values
   *   An array of files to set.
   */
  setFiles(values) {
    this.input.value = values.join(" ");
  }

  /**
   * Handle image editor complete event.
   */
  onImageEditorDone() {
    if (this.settings.options.instance.autoProceedImageEditor) {
      // Auto proceed with upload after image editing is complete.
      this.instance.upload();
    }
  }

  /**
   * Handle upload event.
   */
  onUpload() {
    this.actions.forEach((element) => {
      element.disabled = true;
    });
  }

  /**
   * Handle complete event.
   */
  onComplete() {
    this.actions.forEach((element) => {
      element.disabled = false;
    });
  }

  /**
   * Handle upload success event.
   *
   * @param {Object} file
   *   The file.
   * @param {Object} response
   *   The upload response.
   */
  onUploadSuccess(file, response) {
    const files = this.getFiles();
    const fid = response.body.value;
    files.push(fid);
    this.instance.setFileMeta(file.id, { fid });
    this.setFiles(files);
  }

  /**
   * Handle remove file event.
   *
   * @param {Object} file
   *   The file.
   */
  onFileRemoved(file) {
    if (file.meta.fid) {
      this.setFiles(
        this.getFiles().filter((fid) => {
          return fid !== file.meta.fid;
        })
      );
    }
    if (!Object.keys(this.instance.getState().currentUploads).length) {
      this.onComplete();
    }
  }
}

window.DrupalFileUploader.file_uploader_uppy = FileUploader;
