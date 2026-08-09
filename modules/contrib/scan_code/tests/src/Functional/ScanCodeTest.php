<?php

namespace Drupal\Tests\scan_code\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test class.
 *
 * @group scan_code
 */
class ScanCodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * Tests https connection - because camera works only for https://.
   */
  public function testHomepageUrlSchema() {
    // Visit the homepage.
    $this->drupalGet('<front>');

    // Get the current URL from the session.
    $current_url = $this->getSession()->getCurrentUrl();

    // Parse the URL to extract components.
    $parsed_url = parse_url($current_url);

    // Define the expected schema.
    $expected_schema = 'https';

    // Check if the 'scheme' component matches the expected schema.
    $this->assertTrue(isset(
      $parsed_url['scheme']) && $parsed_url['scheme'] === $expected_schema,
      "The URL schema is not '$expected_schema'. Actual schema: " . ($parsed_url['scheme'] ?? 'none'));
  }

}
