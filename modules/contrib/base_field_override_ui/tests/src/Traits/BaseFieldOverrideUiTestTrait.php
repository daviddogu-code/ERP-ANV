<?php

namespace Drupal\Tests\base_field_override_ui\Traits;

/**
 * Provides common functionality for the Base Field Override UI test classes.
 */
trait BaseFieldOverrideUiTestTrait {

  /**
   * Creates a new base field override through the Field UI.
   *
   * @param string $bundle_path
   *   Admin path of the bundle that the new field is to be attached to.
   * @param string $base_field_name
   *   The base field name.
   * @param string $label
   *   (optional) The label of the new field. Defaults to a random string.
   */
  public function addNewBaseFieldOverride($bundle_path, $base_field_name, $label = NULL) {
    // Generate a label containing only letters and numbers to prevent random
    // test failure.
    // See https://www.drupal.org/project/drupal/issues/3030902
    $label = $label ?: $this->randomMachineName();
    $initial_edit = [
      'label' => $label,
    ];

    // Allow the caller to set a NULL path in case they navigated to the right
    // page before calling this method.
    if ($bundle_path !== NULL) {
      $bundle_path = "$bundle_path/fields/base-field-override/$base_field_name/add";
    }

    $this->drupalGet($bundle_path);
    // First step: 'Add field' page.
    $this->submitForm($initial_edit, 'Save settings');
    $this->assertSession()->responseContains(t('Saved %label configuration.', ['%label' => $label]));

    $this->assertSession()->elementTextContains('css', sprintf('#base-field-override-overview #%s', $base_field_name), 'Overridden');
  }

  /**
   * Deletes a field through the Field UI.
   *
   * @param string $bundle_path
   *   Admin path of the bundle that the field is to be deleted from.
   * @param string $base_field_override_id
   *   The base field override id configuration.
   * @param string $label
   *   The label of the field.
   * @param string $bundle_label
   *   The label of the bundle.
   */
  public function deleteBaseFieldOverride($bundle_path, $base_field_override_id, $label, $bundle_label) {
    // Display confirmation form.
    $this->drupalGet("$bundle_path/fields/base-field-override/$base_field_override_id/delete");
    $this->assertSession()->responseContains(t('Are you sure you want to delete the base field override %label', ['%label' => $label]));

    // Submit confirmation form.
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains(t('The base field override has been deleted.'));

    // Extract the base field name from the id.
    [,, $base_field_name] = explode('.', $base_field_override_id);

    $this->assertSession()->elementTextContains('css', sprintf('#base-field-override-overview #%s', $base_field_name), 'Default');
  }

}
