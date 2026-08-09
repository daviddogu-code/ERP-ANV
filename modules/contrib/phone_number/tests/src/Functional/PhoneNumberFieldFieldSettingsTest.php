<?php

namespace Drupal\phone_number\Tests\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\phone_number\Traits\PhoneNumberCreationTrait;

/**
 * Tests the phone number fields with different field settings.
 *
 * @group phone_number
 */
class PhoneNumberFieldFieldSettingsTest extends BrowserTestBase {

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
   * Tests to confirm the widget is setup with extension enabled.
   *
   * @covers \Drupal\phone_number\Plugin\Field\FieldWidget\PhoneNumberWidget::formElement
   */
  public function testPhoneNumberWidgetExtension() {
    // Enable the extension field.
    FieldConfig::loadByName('node', 'article', 'field_phone_number')
      ->setSetting('extension_field', TRUE)
      ->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->fieldValueEquals("field_phone_number[0][extension]", '');
    $this->assertSession()->elementAttributeContains('css', 'input[name="field_phone_number[0][extension]"]', 'size', 5);
  }

}
