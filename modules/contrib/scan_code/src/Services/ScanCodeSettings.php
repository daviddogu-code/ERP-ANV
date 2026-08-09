<?php

namespace Drupal\scan_code\Services;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure settings for scan_code module.
 */
class ScanCodeSettings {

  /**
   * Line separator for managing configuration values.
   */
  const SEPARATOR = "\r\n";

  /**
   * Allowed form widgets.
   *
   * @var array
   */
  const WIDGETS = [
    'string_textfield',
    'string_textarea',
    'text_textarea_with_summary',
  ];

  /**
   * Default allowed scan patterns.
   *
   * @var array
   */
  const PATTERNS = [
    'code_128_reader' => 'code_128_reader',
    'ean_reader' => 'ean_reader',
    'ean_8_reader' => 'ean_8_reader',
    'code_39_reader' => 'code_39_reader',
    'code_39_vin_reader' => 'code_39_vin_reader',
    'codabar_reader' => 'codabar_reader',
    'upc_reader' => 'upc_reader',
    'upc_e_reader' => 'upc_e_reader',
    'i2of5_reader' => 'i2of5_reader',
    '2of5_reader' => '2of5_reader',
    'code_93_reader' => 'code_93_reader',
  ];


  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ScanCodeSettings object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * Return default scan patterns like string.
   *
   * @return string
   *   String - list of patterns.
   */
  public function getDefaultPaternString() {
    return implode(static::SEPARATOR, static::PATTERNS);
  }

  /**
   * Return default scan patterns like array.
   *
   * @return array
   *   Array of default patterns.
   */
  public function getDefaultPaternArray() {
    return static::PATTERNS;
  }

  /**
   * Return scan patterns like array.
   *
   * @return array
   *   Array of patterns.
   */
  public function getPaternArray() {
    $config = $this->configFactory->getEditable('scan_code.settings');

    return $config->get('patterns')
      ? $this->convertStringArray($config->get('patterns'))
      : $this->getDefaultPaternArray();
  }

  /**
   * Return default widgets like string.
   *
   * @return string
   *   String - list of default widgets.
   */
  public function getDefaultWidgetsString() {
    return implode(static::SEPARATOR, static::WIDGETS);
  }

  /**
   * Return allowed widgets like array.
   *
   * @return array
   *   Array of allowed widgets.
   */
  public function getWidgetsArray() {
    $config = $this->configFactory->getEditable('scan_code.settings');

    return $config->get('widgets')
      ? $this->convertStringArray($config->get('widgets'))
      : static::WIDGETS;
  }

  /**
   * Return separator.
   *
   * @return string
   *   Separator.
   */
  public function getSeparator() {
    return static::SEPARATOR;
  }

  /**
   * Convert array to textarea.
   *
   * Array structure: arr[$value] = $value.
   *
   * @param string|null $input_string
   *   Input string.
   * @param string $separator
   *   String separator.
   *
   * @return array
   *   Return array with structure.
   */
  public function convertStringArray($input_string, string $separator = "\r\n") {
    if ($input_string && is_string($input_string)) {
      $index_array = explode($separator, trim($input_string, $separator));
      $return_array = [];
      foreach ($index_array as $value) {
        $return_array[$value] = $value;
      }

      return $return_array;
    }

    return [];
  }

}
