<?php

namespace Drupal\phone_number\Tests\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\phone_number\Traits\PhoneNumberCreationTrait;

/**
 * Tests the selection of the country field.
 *
 * @group phone_number
 */
class SelectCountryTest extends WebDriverTestBase {

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
   * Tests the country field selection.
   */
  public function testCountrySelect() {
    $this->drupalGet('node/add/article');

    // Check that our select field displays on the form.
    $country_dropdown = $this->assertSession()->fieldExists('field_phone_number[0][country-code]');

    // Select a country.
    $country_dropdown->setValue('BE');

    // Check if the country field has the changed value.
    $this->assertSession()->fieldValueEquals("field_phone_number[0][country-code]", 'BE');
  }

  /**
   * Tests to confirm the widget is setup with country flag.
   */
  public function testPhoneNumberWidgetCountrySelectionFlag() {
    // Set the form display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_phone_number', [
        'type' => 'phone_number_default',
        'settings' => [
          'default_country' => 'BE',
          'country_selection' => 'flag',
        ],
      ])
      ->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementTextEquals('css', '#edit-field-phone-number-0 .prefix', '(+32)');
    $this->assertSession()->elementAttributeContains('css', '#edit-field-phone-number-0 .phone-number-flag', 'class', 'be');
  }

  /**
   * Tests to confirm the widget is setup with country code.
   */
  public function testPhoneNumberWidgetCountrySelectionCode() {
    // Set the form display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_phone_number', [
        'type' => 'phone_number_default',
        'settings' => [
          'default_country' => 'BE',
          'country_selection' => 'code',
        ],
      ])
      ->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementTextEquals('css', '#edit-field-phone-number-0 .prefix', 'BE (+32)');
    $this->assertSession()->elementNotExists('css', '#edit-field-phone-number-0 .phone-number-flag');
  }

}
