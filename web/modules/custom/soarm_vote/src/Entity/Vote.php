<?php

declare(strict_types=1);

namespace Drupal\soarm_vote\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the SO-ARM vote entity.
 *
 * A vote records that one user cast one type of vote (useful, improvement,
 * replication) on one node. There are no bundles and no administration UI;
 * votes are managed exclusively through \Drupal\soarm_vote\VoteManager.
 */
#[ContentEntityType(
  id: 'soarm_vote',
  label: new TranslatableMarkup('SO-ARM Vote'),
  base_table: 'soarm_vote',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
)]
final class Vote extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['node'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Node'))
      ->setSetting('target_type', 'node')
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('User'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    return $fields;
  }

}
