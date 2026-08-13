<?php

namespace Drupal\dxpr_theme_helper;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for AI-powered theme generators.
 *
 * Provides common functionality for generating theme settings using AI.
 */
abstract class AiThemeGeneratorBase {

  use StringTranslationTrait;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager|null
   */
  protected ?AiProviderPluginManager $aiProvider;

  /**
   * The JSON decoder service for extracting JSON from AI responses.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder|null
   */
  protected ?PromptJsonDecoder $jsonDecoder;

  /**
   * The theme extension list service.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected ThemeExtensionList $themeExtensionList;

  /**
   * Tag used for AI request logging and filtering.
   *
   * @var string
   */
  protected string $aiTag = 'dxpr-theme';

  /**
   * Constructs an AiThemeGeneratorBase object.
   *
   * @param \Drupal\ai\AiProviderPluginManager|null $ai_provider
   *   The AI provider plugin manager, or NULL if not available.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder|null $json_decoder
   *   The JSON decoder service, or NULL if not available.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list
   *   The theme extension list service.
   */
  public function __construct(
    ?AiProviderPluginManager $ai_provider = NULL,
    ?PromptJsonDecoder $json_decoder = NULL,
    ?ThemeExtensionList $theme_extension_list = NULL,
  ) {
    $this->aiProvider = $ai_provider;
    $this->jsonDecoder = $json_decoder;
    $this->themeExtensionList = $theme_extension_list;
  }

  /**
   * Get the path to the dxpr_theme.
   *
   * @return string
   *   The path to the dxpr_theme, or empty string if not found.
   */
  protected function getDxprThemePath(): string {
    try {
      return $this->themeExtensionList->getPath('dxpr_theme');
    }
    catch (\Exception $e) {
      return '';
    }
  }

  /**
   * Check if AI is available.
   *
   * @return bool
   *   TRUE if AI provider is available, FALSE otherwise.
   */
  public function isAvailable(): bool {
    return $this->aiProvider !== NULL;
  }

  /**
   * Check if a chat provider is configured and ready.
   *
   * @return bool
   *   TRUE if a chat provider is configured, FALSE otherwise.
   */
  public function hasConfiguredProvider(): bool {
    if (!$this->isAvailable()) {
      return FALSE;
    }
    return $this->aiProvider->hasProvidersForOperationType('chat', TRUE);
  }

  /**
   * Get the required keys that must be present in AI response.
   *
   * @return array
   *   Array of required key names.
   */
  abstract protected function getRequiredKeys(): array;

  /**
   * Build the system prompt for the AI.
   *
   * @return string
   *   The system prompt text.
   */
  abstract protected function buildSystemPrompt(): string;

  /**
   * Validate a single value from the AI response.
   *
   * @param string $key
   *   The key name being validated.
   * @param mixed $value
   *   The value to validate.
   *
   * @return string|null
   *   Error message if invalid, NULL if valid.
   */
  abstract protected function validateValue(string $key, mixed $value): ?string;

  /**
   * Get the response key name (e.g., 'colors', 'fonts').
   *
   * @return string
   *   The key name used in the response array.
   */
  abstract protected function getResponseKey(): string;

  /**
   * Generate theme settings from a user prompt.
   *
   * @param string $prompt
   *   The user's prompt describing the desired settings.
   *
   * @return array
   *   An array with the response key on success, or 'error' on failure.
   */
  public function generate(string $prompt): array {
    // Check AI module availability.
    if (!$this->isAvailable()) {
      return [
        'error' => $this->t('The AI module is not installed. Please install and configure the AI module to use this feature.'),
      ];
    }

    // Validate prompt.
    if (empty(trim($prompt))) {
      return [
        'error' => $this->t('Please enter a prompt.'),
      ];
    }

    // Check for configured provider.
    if (!$this->hasConfiguredProvider()) {
      return [
        'error' => $this->t('No AI chat provider configured. Please configure an AI provider in the AI module settings.'),
      ];
    }

    // Get default provider.
    $default = $this->aiProvider->getDefaultProviderForOperationType('chat');
    if (empty($default['provider_id']) || empty($default['model_id'])) {
      return [
        'error' => $this->t('No default chat model configured. Please set a default chat model in the AI module settings.'),
      ];
    }

    try {
      // Create provider instance.
      $provider = $this->aiProvider->createInstance($default['provider_id']);

      // Build chat input.
      $input = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);
      $input->setSystemPrompt($this->buildSystemPrompt());

      // Send request.
      $response = $provider->chat($input, $default['model_id'], [$this->aiTag]);
      $normalizedResponse = $response->getNormalized();
      $responseText = $normalizedResponse->getText();

      // Decode JSON from response.
      $data = $this->decodeJsonResponse($normalizedResponse, $responseText);
      if ($data === NULL) {
        return [
          'error' => $this->t('Failed to parse AI response. Please try again.'),
          'debug' => $responseText,
        ];
      }

      // Validate required keys.
      $missingKeys = array_diff($this->getRequiredKeys(), array_keys($data));
      if (!empty($missingKeys)) {
        return [
          'error' => $this->t('AI response missing required fields: @keys', [
            '@keys' => implode(', ', $missingKeys),
          ]),
        ];
      }

      // Validate each value.
      foreach ($data as $key => $value) {
        $error = $this->validateValue($key, $value);
        if ($error !== NULL) {
          return ['error' => $error];
        }
      }

      return [$this->getResponseKey() => $data];
    }
    catch (\Exception $e) {
      return [
        'error' => $this->t('AI request failed: @message', [
          '@message' => $e->getMessage(),
        ]),
      ];
    }
  }

  /**
   * Decode JSON from AI response.
   *
   * Uses the AI module's PromptJsonDecoder service if available,
   * falls back to manual extraction.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $message
   *   The chat message response.
   * @param string $text
   *   The response text (for fallback parsing).
   *
   * @return array|null
   *   Decoded array, or NULL on failure.
   */
  protected function decodeJsonResponse(ChatMessage $message, string $text): ?array {
    // Try AI module's JSON decoder first.
    if ($this->jsonDecoder !== NULL) {
      try {
        $decoded = $this->jsonDecoder->decode($message);
        // decode() returns array on success, or ChatMessage on failure.
        if (is_array($decoded)) {
          return $decoded;
        }
      }
      catch (\Exception $e) {
        // Fall through to manual extraction.
      }
    }

    // Manual fallback: extract JSON from markdown code blocks.
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $matches)) {
      $data = json_decode($matches[1], TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
      }
    }

    // Try to find raw JSON object.
    if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
      $data = json_decode($matches[0], TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
      }
    }

    return NULL;
  }

}
