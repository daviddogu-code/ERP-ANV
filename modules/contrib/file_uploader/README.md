# File Uploader

A JavaScript file uploader framework. This module provides a set of tools to integrate 3rd party JavaScript
based uploaders with Drupal managed file fields. The `file_uploader` form element provides integration with the
Drupal managed file form element. An XHR endpoint at `/file-uploader/upload` with CSRF and form element
protection is available. Integration modules should extend the `FileUploaderWidgetBase` base field widget. See
an existing integration module for a demonstration and more information.

## Integrations

- [Uppy](https://www.drupal.org/project/file_uploader_uppy)

## Installation

1. Require the `drupal/file_uploader` module:
   ```shell
    composer require drupal/file_uploader
   ```
2. Install one of the integration modules OR build a custom integration.

## Usage

Refer to the integration modules usage instructions.
