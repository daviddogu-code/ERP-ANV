<?php

namespace Drupal\phone_number\Tests\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\phone_number\Traits\PhoneNumberCreationTrait;

/**
 * Tests the country formatting of phone number fields.
 *
 * @group phone_number
 */
class PhoneNumberFieldCountryFormatterTest extends BrowserTestBase {

  use PhoneNumberCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'node',
    'phone_number',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to create articles.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createPhoneNumberField();
  }

  /**
   * Tests the phone number country formatter.
   *
   * @covers \Drupal\phone_number\Plugin\Field\FieldFormatter\PhoneNumberCountryFormatter::viewElements
   *
   * @dataProvider providerPhoneNumbersCountry
   */
  public function testPhoneNumberCountryFormatter($input, $expected, $country) {
    // Data input.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_phone_number[0][country-code]' => $country,
      'field_phone_number[0][phone]' => $input,
    ];

    // Assertions.
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains($expected);
  }

  /**
   * Provides the phone numbers to check and expected results.
   */
  public function providerPhoneNumbersCountry() {
    return [
      'standard phone number from US' => [
        '(650) 253-0000', 'United States', 'US',
      ],
      'standard phone number from CH' => [
        '44 668 18 00', 'Switzerland', 'CH',
      ],
      'standard phone number from GB' => [
        '0161 496 0000', 'United Kingdom', 'GB',
      ],
      'standard phone number from US with country code' => [
        '+1 650 253 0000', 'United States', 'US',
      ],
    ];
  }

}
