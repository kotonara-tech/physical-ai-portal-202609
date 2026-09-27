<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\soarm_lerobot\EpisodeInfoProvider;

/**
 * Hook implementations for the soarm_lerobot module.
 */
final class SoarmLerobotHooks {

  public function __construct(
    private readonly EpisodeInfoProvider $episodeInfo,
  ) {}

  /**
   * Implements hook_entity_bundle_field_info_alter().
   *
   * Attaches the trajectory-format and episode-metadata constraints to the
   * robot_knowledge post's file fields, so that invalid files are rejected
   * when a post is validated (including over JSON:API, as a 422 response).
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() !== 'node' || $bundle !== 'robot_knowledge') {
      return;
    }

    if (!empty($fields['field_trajectory'])) {
      $fields['field_trajectory']->addConstraint('SoarmTrajectoryFormat');
    }
    if (!empty($fields['field_metadata_yaml'])) {
      $fields['field_metadata_yaml']->addConstraint('SoarmEpisodeMetadata');
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    return [
      'node' => [
        'robot_knowledge' => [
          'display' => [
            'soarm_lerobot_metadata' => [
              'label' => new TranslatableMarkup('LeRobot episode metadata'),
              'description' => new TranslatableMarkup('Robot type, fps, task, frame count and phase timeline parsed from the metadata YAML.'),
              'weight' => 12,
              'visible' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($node->bundle() !== 'robot_knowledge') {
      return;
    }

    $component = $display->getComponent('soarm_lerobot_metadata');
    if (!$component) {
      return;
    }

    $info = $this->episodeInfo->forNode($node);
    if ($info === NULL) {
      return;
    }

    $cacheTags = $node->getCacheTags();
    if ($node->hasField('field_metadata_yaml') && !$node->get('field_metadata_yaml')->isEmpty()) {
      $yamlFile = $node->get('field_metadata_yaml')->entity;
      if ($yamlFile !== NULL) {
        $cacheTags = Cache::mergeTags($cacheTags, $yamlFile->getCacheTags());
      }
    }

    $build['soarm_lerobot_metadata'] = [
      '#theme' => 'soarm_lerobot_metadata',
      '#robot_type' => $info['robot_type'],
      '#fps' => $info['fps'],
      '#task' => $info['task'],
      '#total_frames' => $info['total_frames'],
      '#phases' => $info['phases'],
      '#weight' => $component['weight'] ?? 12,
      '#cache' => [
        'tags' => $cacheTags,
      ],
    ];
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'soarm_lerobot_metadata' => [
        'variables' => [
          'robot_type' => NULL,
          'fps' => NULL,
          'task' => NULL,
          'total_frames' => NULL,
          'phases' => [],
        ],
      ],
    ];
  }

}
