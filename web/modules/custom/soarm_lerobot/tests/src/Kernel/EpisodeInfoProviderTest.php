<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_lerobot\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;

/**
 * Tests building the LeRobot info document for a post.
 */
#[Group('soarm_lerobot')]
#[Medium]
final class EpisodeInfoProviderTest extends LeRobotKernelTestBase {

  /**
   * Tests that the info document combines the YAML, trajectory and video.
   */
  public function testInfoCombinesYamlTrajectoryAndVideo(): void {
    $trajectory = $this->createFile('ep.parquet', self::PARQUET);
    $video = $this->createFile('ep.mp4', 'mp4');
    $node = $this->buildPost([
      'field_metadata_yaml' => $this->createFile('ep.yaml', self::VALID_YAML),
      'field_trajectory' => $trajectory,
      'field_video' => $video,
    ]);
    $node->save();

    $info = $this->container->get('soarm_lerobot.episode_info')->forNode($node);

    $this->assertSame('so-arm101', $info['robot_type']);
    $this->assertSame(30, $info['fps']);
    $this->assertSame(151, $info['total_frames']);
    $this->assertSame('parquet', $info['data_format']);
    $this->assertSame($trajectory->createFileUrl(FALSE), $info['data_path']);
    $this->assertSame($video->createFileUrl(FALSE), $info['video_path']);
  }

  /**
   * Tests that a post without metadata has no info document.
   */
  public function testPostWithoutMetadataHasNoInfo(): void {
    $node = $this->buildPost(['field_trajectory' => $this->createFile('only.parquet', self::PARQUET)]);
    $node->save();

    $this->assertNull($this->container->get('soarm_lerobot.episode_info')->forNode($node));
  }

  /**
   * Tests that metadata invalidated after save gives no info, not an error.
   */
  public function testMetadataThatBecameInvalidGivesNoInfoInsteadOfAnError(): void {
    $yaml = $this->createFile('later-broken.yaml', self::VALID_YAML);
    $node = $this->buildPost(['field_metadata_yaml' => $yaml]);
    $node->save();
    file_put_contents($yaml->getFileUri(), 'robot_type: [unclosed');

    $this->assertNull($this->container->get('soarm_lerobot.episode_info')->forNode($node));
  }

  /**
   * Tests that the detail page shows the grasp/move/release phases.
   */
  public function testDetailPageShowsThePhases(): void {
    $node = $this->buildPost(['field_metadata_yaml' => $this->createFile('shown.yaml', self::VALID_YAML)]);
    $node->save();

    $build = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($node, 'full');
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    $this->assertStringContainsString('soarm-metadata', $html);
    foreach (['grasp', 'move', 'release', 'so-arm101'] as $expected) {
      $this->assertStringContainsString($expected, $html);
    }
  }

}
