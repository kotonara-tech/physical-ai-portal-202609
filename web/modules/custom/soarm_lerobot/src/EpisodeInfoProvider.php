<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot;

use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;

/**
 * Builds the LeRobot info document for a robot_knowledge post.
 *
 * This is the read side used by both the detail page's extra field and the
 * `/api/soarm/lerobot/{node}` controller: it never throws, so callers can
 * treat a NULL return as "nothing to show" instead of handling exceptions.
 */
final class EpisodeInfoProvider {

  /**
   * Constructs an EpisodeInfoProvider.
   *
   * @param \Drupal\soarm_lerobot\LeRobotInfoBuilder $infoBuilder
   *   Builds the info document from validated metadata.
   * @param \Drupal\soarm_lerobot\TrajectoryFormatDetector $formatDetector
   *   Detects the trajectory file's format by content.
   */
  public function __construct(
    private readonly LeRobotInfoBuilder $infoBuilder,
    private readonly TrajectoryFormatDetector $formatDetector,
  ) {}

  /**
   * Builds the info document for a post, if it has valid metadata.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The post to describe.
   *
   * @return array|null
   *   The info document (see LeRobotInfoBuilder::build()), or NULL when the
   *   post has no metadata YAML file, the file cannot be read, or its
   *   contents no longer parse as valid episode metadata.
   */
  public function forNode(NodeInterface $node): ?array {
    $yamlFile = $this->fileFromField($node, 'field_metadata_yaml');
    if ($yamlFile === NULL) {
      return NULL;
    }

    $contents = @file_get_contents($yamlFile->getFileUri());
    if ($contents === FALSE) {
      return NULL;
    }

    try {
      $metadata = EpisodeMetadata::fromYaml($contents);
    }
    catch (InvalidMetadataException) {
      return NULL;
    }

    $trajectoryFile = $this->fileFromField($node, 'field_trajectory');
    $dataFormat = $trajectoryFile !== NULL ? $this->formatDetector->detect($trajectoryFile->getFileUri()) : NULL;
    $videoFile = $this->fileFromField($node, 'field_video');

    return $this->infoBuilder->build(
      $metadata,
      $trajectoryFile?->createFileUrl(FALSE),
      $dataFormat,
      $videoFile?->createFileUrl(FALSE),
    );
  }

  /**
   * Loads the file entity referenced by a single-value file field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The post to inspect.
   * @param string $fieldName
   *   The file field's machine name.
   *
   * @return \Drupal\file\FileInterface|null
   *   The referenced file, or NULL when the field is absent, empty, or its
   *   target file entity no longer exists.
   */
  private function fileFromField(NodeInterface $node, string $fieldName): ?FileInterface {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $entity = $node->get($fieldName)->entity;
    return $entity instanceof FileInterface ? $entity : NULL;
  }

}
