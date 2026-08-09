<?php

namespace Drupal\tiny_slider\Commands;

use Drupal\Core\Asset\libraryDiscovery;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://git.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://git.drupalcode.org/devel/tree/drush.services.yml
 */
class TinySliderCommands extends DrushCommands {

  /**
   * Library discovery service.
   *
   * @var Drupal\Core\Asset\libraryDiscovery
   */
  protected $libraryDiscovery;

  /**
   * {@inheritdoc}
   */
  public function __construct(libraryDiscovery $library_discovery) {
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * Download and install the Tiny Slider 2 plugin.
   *
   * @param mixed $path
   *   Optional. A path where to install the Tiny Slider 2 plugin.
   *   If omitted Drush will use the default location.
   *
   * @command tinyslider:plugin
   * @aliases tinysliderplugin,tinyslider-plugin
   */
  public function download($path = '') {

    $fs = new Filesystem();

    if (empty($path)) {
      $path = DRUPAL_ROOT . '/libraries/tiny-slider';
    }

    // Create path if it doesn't exist
    // If exits delete and recreate with a message otherwise.
    if (!$fs->exists($path)) {
      $fs->mkdir($path);
    }
    else {
      $fs->remove($path);
      $this->logger()->notice(dt('An existing Tiny Slider 2 plugin is deleted from @path and it will be reinstalled again.', ['@path' => $path]));
      $fs->mkdir($path);
    }

    // Load the tiny_slider defined library.
    if ($tiny_slider_library = $this->libraryDiscovery->getLibraryByName('tiny_slider', 'tiny_slider')) {
      // Download the file.
      $client = new Client();
      $destination = tempnam(sys_get_temp_dir(), 'tiny-slider-tmp');
      try {
        $client->get($tiny_slider_library['remote'] . '/archive/refs/tags/v2.9.3.zip', ['save_to' => $destination]);
      }
      catch (RequestException $e) {
        // Remove the directory.
        $fs->remove($path);
        $this->logger()->error(dt('Drush was unable to download the Tiny Slider library from @remote. @exception', [
          '@remote' => $tiny_slider_library['remote'] . '/archive/refs/tags/v2.9.3.zip',
          '@exception' => $e->getMessage(),
        ], 'error'));
        return;
      }

      // Move downloaded file.
      $fs->rename($destination, $path . '/tiny-slider.zip');

      // Unzip the file.
      $zip = new \ZipArchive();
      $res = $zip->open($path . '/tiny-slider.zip');
      if ($res === TRUE) {
        $zip->extractTo($path);
        $zip->close();
      }
      else {
        // Remove the directory if unzip fails and exit.
        $fs->remove($path);
        $this->logger()->error(dt('Error: unable to unzip tiny-slider file.', [], 'error'));
        return;
      }

      // Remove the downloaded zip file.
      $fs->remove($path . '/tiny-slider.zip');

      // Move the file.
      $fs->mirror($path . '/tiny-slider-2.9.3', $path, NULL, ['override' => TRUE]);
      $fs->remove($path . '/tiny-slider-2.9.3');

      // Success.
      $this->logger()->notice(dt('The tiny-slider library has been successfully downloaded to @path.', [
        '@path' => $path,
      ], 'success'));
    }
    else {
      $this->logger()->error(dt('Drush was unable to load the tiny-slider library'));
    }
  }

}
