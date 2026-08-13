<?php

namespace Drupal\dxpr_theme_helper;

/**
 * Service for generating color palettes using AI.
 */
class AiPaletteGenerator extends AiThemeGeneratorBase {

  /**
   * Color fields loaded from dxpr_theme's color-settings.json.
   *
   * @var array|null
   */
  protected ?array $colorFields = NULL;

  /**
   * {@inheritdoc}
   */
  protected string $aiTag = 'dxpr-palette';

  /**
   * {@inheritdoc}
   */
  protected function getRequiredKeys(): array {
    return array_keys($this->loadColorFields());
  }

  /**
   * Load color field definitions from dxpr_theme's color-settings.json.
   *
   * @return array
   *   Array of color fields keyed by field name with label as value.
   */
  protected function loadColorFields(): array {
    if ($this->colorFields !== NULL) {
      return $this->colorFields;
    }

    $this->colorFields = [];

    // Try to find the color-settings.json file in dxpr_theme.
    $themePath = $this->getDxprThemePath();
    if (empty($themePath)) {
      return $this->colorFields;
    }
    $jsonPath = $themePath . '/features/sooper-colors/color-settings.json';

    if (!file_exists($jsonPath)) {
      return $this->colorFields;
    }

    $jsonContent = file_get_contents($jsonPath);
    $colorSettings = json_decode($jsonContent, TRUE);

    if (is_array($colorSettings) && isset($colorSettings['fields'])) {
      $this->colorFields = $colorSettings['fields'];
    }

    return $this->colorFields;
  }

  /**
   * {@inheritdoc}
   */
  protected function getResponseKey(): string {
    return 'colors';
  }

  /**
   * {@inheritdoc}
   */
  protected function validateValue(string $key, mixed $value): ?string {
    if (!is_string($value)) {
      return $this->t('Invalid color value for @key: expected string.', [
        '@key' => $key,
      ]);
    }

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
      return $this->t('Invalid color format for @key: @color. Expected 6-digit hex code.', [
        '@key' => $key,
        '@color' => $value,
      ]);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSystemPrompt(): string {
    $colorFields = $this->loadColorFields();

    // Build dynamic JSON structure for the prompt.
    $jsonStructure = [];
    foreach (array_keys($colorFields) as $key) {
      $jsonStructure[$key] = '#XXXXXX';
    }
    $jsonExample = json_encode($jsonStructure, JSON_PRETTY_PRINT);

    // Build color explanations from field labels.
    $colorExplanations = "";
    foreach ($colorFields as $key => $label) {
      $colorExplanations .= "- {$key}: {$label}\n";
    }

    return <<<PROMPT
You are a professional color palette designer. Generate a cohesive, visually appealing color palette based on the user's request.

Return ONLY valid JSON with these exact keys and hex color values (no markdown, no explanation):
{$jsonExample}

Color explanations (key: description):
{$colorExplanations}
Important notes about color pairs:
- Fields ending in "text" are text colors that should have WCAG AA contrast with their corresponding background
- For example: "basetext" is text on "base" background, "cardtext" is text on "card" background
- "headerside" is the mobile/side menu background

Requirements:
- IMPORTANT: If the user specifies colors for specific elements (e.g., "red cards", "blue header"), you MUST use those colors for those elements
- Use 6-digit hex codes only (e.g., #1A2B3C)
- Ensure proper contrast between text and background pairs (WCAG AA minimum)
- Create a harmonious, professional color scheme
- The body background should typically be white or very light for light themes, or very dark for dark themes
PROMPT;
  }

}
