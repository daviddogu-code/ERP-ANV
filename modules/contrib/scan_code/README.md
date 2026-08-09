# Scan code

CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Usage
* Maintainers

INTRODUCTION
------------
Provides functionality for barcode's scanning -
scan barcodes using device camera.
You can update any `string_textfield` widget.
You can add this functionality to any other
text widget also.
Need to check - how it will work.

LINKS
------------

Project is based on
https://www.drupal.org/project/commerce_pos
- submodule `barcode_scanning`.

Using external libraries:
 - https://github.com/ericblade/quagga2 (Quagga2)
 - https://webrtc.github.io/adapter/adapter-latest.js

REQUIREMENTS
------------

### Browser ####

Not all browsers support the webcam spec, IE11 and older will not support it,
but all modern versions of Chrome, Firefox, Edge, Opera and Safari will.

### Security ###

Most browsers will only allow you to access
the camera over a secure connection,
so you will need to run your site via https, even for
developmenе environments unless they are hosted on localhost.
Some browsers, like Firefox, will allow you
to override this, but you have to do so on every page load.

INSTALLATION
------------
It is recommended to install the `Scan Code`
module via composer.
All external libraries will be installed during
modules installation process.

### Composer ###

   ```sh
   composer require "drupal/scan_code:^1.0"
   ```

^1.0 downloads the latest release, use 1.x-dev to
get the -dev release instead.
Use ```composer update drupal/scan_code --with-dependencies```
to update to a new
release.

CONFIGURATION
-------------

### Scan code settings ###

`Configuration / Scan code`

`/admin/config/scan-code/settings` - barcode form settings is here.

USAGE
------------

1. Navigate to Administration > Extend and enable the module.
2. After a store and register has been created, navigate to Configuration >
   Scan code.
3. Checking all form items. You can leave it by default.
You can add this functionality to any other text widget too.
Be careful with it.
4. Save form.
5. Goto "Manage form display" for needed entity and
in form element setting enable "Enable barcode scanning",
then select needed decoders and save.
6. After that should be appearing barcode button
after text input element at the entity form.

More information you could see on the project's page.

MAINTAINERS
-----------
[Sergey Bortkevich (vasyapledov)](https://www.drupal.org/u/vasyapledov)
