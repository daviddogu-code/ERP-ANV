<?php

namespace Drupal\file_resup\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the file resup entity class.
 *
 * @ContentEntityType(
 *   id = "file_resup",
 *   label = @Translation("File Resup"),
 *   label_collection = @Translation("File Resups"),
 *   label_singular = @Translation("file resup"),
 *   label_plural = @Translation("file resups"),
 *   label_count = @PluralTranslation(
 *     singular = "@count file resups",
 *     plural = "@count file resups",
 *   ),
 *   base_table = "file_resup",
 *   admin_permission = "administer file resup",
 *   entity_keys = {
 *     "id" = "upload_id",
 *     "label" = "id",
 *   },
 *   internal = TRUE,
 * )
 */
class FileResup extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['upload_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Upload ID'));

    $fields['filename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('File name'));

    $fields['filesize'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('File size'))
      ->setDefaultValue(0)
      ->setSetting('unsigned', TRUE);

    $fields['uploaded_chunks'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Uploaded chunks'))
      ->setDefaultValue(0)
      ->setSetting('unsigned', TRUE);

    $fields['scheme'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Scheme'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the file was last updated.'));

    $fields['fid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('File ID'))
      ->setSetting('target_type', 'file');

    return $fields;
  }

}
