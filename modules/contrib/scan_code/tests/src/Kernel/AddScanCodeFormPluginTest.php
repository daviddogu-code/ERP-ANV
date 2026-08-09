<?php

namespace Drupal\Tests\scan_code\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the creation of a scan code form widget.
 *
 * @group scan_code
 */
class AddScanCodeFormPluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'user',
    'system',
    'field_ui',
    'scan_code',
  ];

  /**
   * Tests.
   *
   * Creating a custom content type, field,
   * form widget, and third-party settings.
   */
  public function testCreateContentTypeAndFieldWithThirdPartySettings() {
    // Install the required schema.
    $this->installEntitySchema('node');
    $this->installConfig(['node', 'field', 'field_ui', 'text']);

    // Define the machine name and human-readable name of the content type.
    $type_name = 'custom_type';
    $type_label = 'Custom Type';

    // Check if the content type already exists.
    $content_type = NodeType::load($type_name);
    if (!$content_type) {
      // Create the content type.
      $content_type = NodeType::create([
        'type' => $type_name,
        'name' => $type_label,
      ]);
      $content_type->save();
    }

    // Add a custom field to the content type.
    $field_name = 'field_custom';
    $field_label = 'Custom Field test';

    // Check if the field already exists.
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      // Create the field storage.
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        'settings' => [
          'max_length' => 255,
        ],
      ])->save();

      // Create the field instance.
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $type_name,
        'label' => $field_label,
        'required' => FALSE,
      ])->save();
    }

    // Assert that the content type was created.
    $this->assertNotNull(NodeType::load($type_name), 'Custom content type was created.');

    // Assert that the field storage was created.
    $field_storage = FieldStorageConfig::loadByName('node', $field_name);
    $this->assertNotNull($field_storage, 'Field storage for custom field was created.');

    // Assert that the field instance was created.
    $field_instance = FieldConfig::loadByName('node', $type_name, $field_name);
    $this->assertNotNull($field_instance, 'Field instance for custom field was created.');

    // Verify the field widget and add third-party settings.
    $form_display = EntityFormDisplay::load('node.' . $type_name . '.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $type_name,
        'mode' => 'default',
      ]);
    }
    $form_display->setComponent($field_name, [
      'type' => 'string_textfield',
      'weight' => 0,
      'region' => 'content',
      'settings' => [
        'size' => 60,
        'placeholder' => '',
      ],
      'third_party_settings' => [
        'scan_code' => [
          'scan_code_group' => [
            'barcode_scan_enabled' => '1',
            'scan_code_patterns' => [
              'code_128_reader' => 'code_128_reader',
              'ean_reader' => 'ean_reader',
              'ean_8_reader' => 'ean_8_reader',
              'code_39_reader' => '0',
              'codabar_reader' => '0',
              'upc_reader' => '0',
              'upc_e_reader' => '0',
              'i2of5_reader' => '0',
              'code_93_reader' => '0',
            ],
          ],
        ],
      ],
    ])->save();

    // Assert the field widget configuration.
    $this->assertNotNull(
      $form_display->getComponent($field_name),
      'Form widget for $field_label is available.');
    $this->assertEquals(
      'string_textfield',
      $form_display->getComponent($field_name)['type'],
      'Form widget type is correct.');
    $this->assertTrue(
      array_key_exists(
        'scan_code',
        $form_display->getComponent($field_name)['third_party_settings']),
      'Third-party setting is correct.');
  }

}
