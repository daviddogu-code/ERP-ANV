<?php

namespace Drupal\Tests\phone_number\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides methods to create a phone number field with default settings.
 *
 * This trait is meant to be used only by test classes.
 */
trait PhoneNumberCreationTrait {

  /**
   * Creates a phone number field with default settings.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field_name.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPhoneNumberField(string $entity_type = 'node', string $bundle = 'article', string $field_name = 'field_phone_number'): void {

    // Create the content type.
    $this->drupalCreateContentType(['type' => $bundle]);
    $this->webUser = $this->drupalCreateUser([
      'create ' . $bundle . ' content',
      'edit own ' . $bundle . ' content',
    ]);
    $this->drupalLogin($this->webUser);

    // Add the phone_number field to the content type.
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'phone_number',
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'label' => 'Phone Number',
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ])->save();

    // Set the form display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getFormDisplay($entity_type, $bundle)
      ->setComponent($field_name, [
        'type' => 'phone_number_default',
      ])
      ->save();

    // Set the view display for the phone number on the content type.
    $this->container->get('entity_display.repository')
      ->getViewDisplay($entity_type, $bundle)
      ->setComponent($field_name, [
        'type' => 'phone_number_country',
        'weight' => 1,
      ])
      ->save();
  }

}
