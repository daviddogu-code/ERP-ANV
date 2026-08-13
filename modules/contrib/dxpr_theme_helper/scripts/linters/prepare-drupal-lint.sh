#!/bin/bash

echo "$COMPOSER_HOME: $COMPOSER_HOME"

# Allow the plugin installer
composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true

# Install PHPCS plugin installer first so it registers standards as they install
composer global require dealerdirect/phpcodesniffer-composer-installer

# Install Drupal coding standards
composer global require drupal/coder

# Install PHP compatibility checker (10.x for PHPCS 4.x support)
composer global require phpcompatibility/php-compatibility:"^10.0@alpha"

export PATH="$PATH:$COMPOSER_HOME/vendor/bin"

composer global show -P
phpcs -i

# Configure PHPCS settings
phpcs --config-set colors 1
phpcs --config-set ignore_warnings_on_exit 1
phpcs --config-set drupal_core_version 11

phpcs --config-show
