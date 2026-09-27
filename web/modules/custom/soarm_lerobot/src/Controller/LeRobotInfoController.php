<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\soarm_lerobot\EpisodeInfoProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns the LeRobot info document for a robot_knowledge post.
 *
 * EXTENSION POINT (ROS2 bag): once EpisodeInfoProvider can report a
 * `bag_path`, this controller would not need to change: the info array it
 * returns already passes through unmodified.
 */
final class LeRobotInfoController extends ControllerBase {

  /**
   * Constructs a LeRobotInfoController.
   *
   * @param \Drupal\soarm_lerobot\EpisodeInfoProvider $episodeInfo
   *   Builds the info document for a post.
   */
  public function __construct(
    private readonly EpisodeInfoProvider $episodeInfo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('soarm_lerobot.episode_info'));
  }

  /**
   * Returns the LeRobot info document for a post as JSON.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The post, upcast from the {node} route parameter.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The info document.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When the post has no valid episode metadata.
   */
  public function info(NodeInterface $node): CacheableJsonResponse {
    $cacheableMetadata = CacheableMetadata::createFromObject($node);
    if ($node->hasField('field_metadata_yaml') && !$node->get('field_metadata_yaml')->isEmpty()) {
      $yamlFile = $node->get('field_metadata_yaml')->entity;
      if ($yamlFile !== NULL) {
        $cacheableMetadata->addCacheableDependency($yamlFile);
      }
    }

    $info = $this->episodeInfo->forNode($node);
    if ($info === NULL) {
      throw new NotFoundHttpException();
    }

    $response = new CacheableJsonResponse($info);
    $response->addCacheableDependency($cacheableMetadata);

    return $response;
  }

}
