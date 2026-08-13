<?php

namespace Drupal\phone_number\Tests\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\phone_number\Traits\PhoneNumberCreationTrait;

/**
 * Tests phone number field validation.
 *
 * @group phone_number
 */
class PhoneNumberFieldValidationTest extends BrowserTestBase {

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
   * Tests that an invalid number for a country shows validation error.
   *
   * @covers \Drupal\phone_number\Element\PhoneNumber::phoneNumberValidate
   * @covers \Drupal\phone_number\PhoneNumberUtil::testPhoneNumber
   */
  public function testInvalidNumberForCountry() {
    $this->drupalGet('node/add/article');

    $this->submitForm([
      'field_phone_number[0][phone]' => '999',
      'field_phone_number[0][country-code]' => 'IN',
    ], 'Save');

    $this->assertSession()->pageTextContains('The phone number 999 provided for Phone Number is not a valid phone number for country India.');
  }

  /**
   * Tests that a valid number for a country passes validation.
   *
   * @covers \Drupal\phone_number\Element\PhoneNumber::phoneNumberValidate
   * @covers \Drupal\phone_number\PhoneNumberUtil::testPhoneNumber
   */
  public function testValidNumberForCountry() {
    $this->drupalGet('node/add/article');

    $this->submitForm([
      'title[0][value]' => 'Test Article',
      'field_phone_number[0][phone]' => '6502530000',
      'field_phone_number[0][country-code]' => 'US',
    ], 'Save');

    $this->assertSession()->pageTextContains('Article Test Article has been created.');
  }

}
