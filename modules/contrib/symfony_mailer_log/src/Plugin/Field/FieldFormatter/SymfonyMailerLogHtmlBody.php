<?php

namespace Drupal\symfony_mailer_log\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'symfony_mailer_log_html_body' formatter.
 *
 * @FieldFormatter(
 *   id = "symfony_mailer_log_html_body",
 *   label = @Translation("Email HTML body in iframe"),
 *   field_types = {
 *     "string_long",
 *   },
 * )
 */
class SymfonyMailerLogHtmlBody extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    /** @var \Drupal\symfony_mailer_log\Entity\SymfonyMailerLogInterface $symfony_mailer_log */
    $symfony_mailer_log = $items->getEntity();
    $elements = [];
    $elements[0] = [
      '#type' => 'inline_template',
      '#template' => '<iframe sandbox srcdoc="{{ html_body }}" style="overflow: hidden; height: 80vh; width: 100%; position: absolute;"></iframe>',
      '#context' => [
        'html_body' => $symfony_mailer_log->getHtmlBody(),
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'symfony_mailer_log' && $field_name == 'html_body';
  }

}
