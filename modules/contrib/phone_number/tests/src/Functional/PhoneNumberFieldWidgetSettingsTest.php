<?php

namespace Drupal\phone_number\Tests\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\phone_number\Traits\PhoneNumberCreationTrait;

/**
 * Tests the widget of phone number fields.
 *
 * @group phone_number
 */
class PhoneNumberFieldWidgetSettingsTest extends BrowserTestBase {

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
   * Tests to confirm the widget is setup.
   *
   * @covers \Drupal\phone_number\Plugin\Field\FieldWidget\PhoneNumberWidget::formElement
   */
  public function testPhoneNumberWidget() {
    $this->drupalGet('node/add/article');
    $this->assertSession()->fieldValueEquals("field_phone_number[0][country-code]", 'US');
    $this->assertSession()->fieldValueEquals("field_phone_number[0][phone]", '');
    $this->assertSession()->elementAttributeContains('css', 'input[name="field_phone_number[0][phone]"]', 'size', 60);
    $this->assertSession()->elementAttributeContains('css', 'input[name="field_phone_number[0][phone]"]', 'placeholder', 'Phone number');
  }

  /**
   * Tests to confirm the widget is setup with different default country.
   *
   * @covers \Drupal\phone_number\Plugin\Field\FieldWidget\PhoneNumberWidget::formElement
   */
  public function testPhoneNumberWidgetDefaultCountry() {
    // Set the form display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_phone_number', [
        'type' => 'phone_number_default',
        'settings' => [
          'default_country' => 'BE',
        ],
      ])
      ->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->fieldValueEquals("field_phone_number[0][country-code]", 'BE');
  }

  /**
   * Tests to confirm the widget is setup with different placeholder.
   *
   * @covers \Drupal\phone_number\Plugin\Field\FieldWidget\PhoneNumberWidget::formElement
   */
  public function testPhoneNumberWidgetPlaceholder() {
    // Set the form display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_phone_number', [
        'type' => 'phone_number_default',
        'settings' => [
          'placeholder' => 'Different placeholder',
        ],
      ])
      ->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementAttributeContains('css', 'input[name="field_phone_number[0][phone]"]', 'placeholder', 'Different placeholder');
  }

  /**
   * Tests to confirm the widget is setup with different phone size.
   *
   * @covers \Drupal\phone_number\Plugin\Field\FieldWidget\PhoneNumberWidget::formElement
   */
  public function testPhoneNumberWidgetPhoneSize() {
    // Set the form display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_phone_number', [
        'type' => 'phone_number_default',
        'settings' => [
          'phone_size' => 100,
        ],
      ])
      ->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementAttributeContains('css', 'input[name="field_phone_number[0][phone]"]', 'size', 100);
  }

  /**
   * Tests to confirm the widget is setup with different extension size.
   *
   * @covers \Drupal\phone_number\Plugin\Field\FieldWidget\PhoneNumberWidget::formElement
   */
  public function testPhoneNumberWidgetExtensionSize() {
    // Enable the extension field.
    FieldConfig::loadByName('node', 'article', 'field_phone_number')
      ->setSetting('extension_field', TRUE)
      ->save();

    // Set the form display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_phone_number', [
        'type' => 'phone_number_default',
        'settings' => [
          'extension_size' => 10,
        ],
      ])
      ->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementAttributeContains('css', 'input[name="field_phone_number[0][extension]"]', 'size', 10);
  }

}
