<?php

/**
 * @file
 * Hooks provided by the module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the Uppy locale map.
 *
 * @param array $map
 *   An array of locale maps.
 */
function hook_file_uploader_uppy_locale(array &$map): void {
  $map['zh-tw'] = 'zh_CN';
}

/**
 * @} End of "addtogroup hooks".
 */
