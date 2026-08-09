<?php

namespace Drupal\file_uploader_uppy;

/**
 * Provides Uppy locale mapper.
 */
final class UppyLocaleMapper {

  /**
   * The Uppy locales URL.
   */
  const URL = "https://releases.transloadit.com/uppy/locales/v3.2.1";

  /**
   * The Drupal to Uppy locale map.
   */
  const MAP = [
    'ar' => 'ar_SA',
    'bg' => 'bg_BG',
    'hr' => 'hr_HR',
    'cs' => 'cs_CZ',
    'da' => 'da_DK',
    'nl' => 'nl_NL',
    'fi' => 'fi_FI',
    'fr' => 'fr_FR',
    'gl' => 'gl_ES',
    'de' => 'de_DE',
    'el' => 'el_GR',
    'he' => 'he_IL',
    'hu' => 'hu_HU',
    'is' => 'is_IS',
    'id' => 'id_ID',
    'it' => 'it_IT',
    'ja' => 'ja_JP',
    'ko' => 'ko_KR',
    'nb' => 'nb_NO',
    'fa' => 'fa_IR',
    'pl' => 'pl_PL',
    'pt' => '	pt_PT',
    'pt-pt' => 'pt_PT',
    'pt-br' => 'pt_BR',
    'ro' => 'ro_RO',
    'ru' => 'ru_RU',
    'sr' => 'sr_RS_Cyrillic',
    'sk' => 'sk_SK',
    'es' => 'es_ES',
    'sv' => 'sv_SE',
    'th' => 'th_TH',
    'tr' => 'tr_TR',
    'uk' => 'uk_UA',
    'uz' => 'uz_UZ',
    'vi' => 'vi_VN',
    'zh-hans' => 'zh_CN',
    'zh-hant' => 'zh_CN',
  ];

  /**
   * Get the Uppy locale map.
   *
   * @return string[]
   *   Returns the Uppy locale map.
   */
  public static function getMap(): array {
    $map = self::MAP;

    // Invoke hook_file_uploader_uppy_locale().
    \Drupal::moduleHandler()->alter('file_uploader_uppy_locale', $map, $language);

    return $map;
  }

  /**
   * Get the Uppy locale name.
   *
   * @param string|null $language
   *   The optional language ID. Defaults to current language.
   *
   * @return string|null
   *   Returns the Uppy locale name.
   */
  public static function getLocale(string $language = NULL): ?string {
    if (!$language) {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }
    $map = self::getMap();
    return !preg_match('/^en_?/', $language) && isset($map[$language]) ? $map[$language] : NULL;
  }

  /**
   * Get the Uppy locale URL.
   *
   * @param string|null $language
   *   The optional language ID. Defaults to current language.
   *
   * @return string|null
   *   Returns the Uppy locale URL.
   */
  public static function getUrl(string $language = NULL): ?string {
    return ($locale = self::getLocale($language)) ? self::URL . "/$locale.min.js" : NULL;
  }

}
