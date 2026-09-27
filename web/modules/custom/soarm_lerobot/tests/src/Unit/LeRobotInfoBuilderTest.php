<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_lerobot\Unit;

use Drupal\soarm_lerobot\EpisodeMetadata;
use Drupal\soarm_lerobot\LeRobotInfoBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests building the LeRobot "info.json"-like document served by the API.
 */
#[CoversClass(LeRobotInfoBuilder::class)]
#[Group('soarm_lerobot')]
final class LeRobotInfoBuilderTest extends UnitTestCase {

  /**
   * Returns metadata built around the grasp/move/release phases.
   */
  private function metadata(): EpisodeMetadata {
    return EpisodeMetadata::fromYaml("robot_type: so-arm101\nfps: 30\ntask: pick\nphases:\n  - name: grasp\n    start_frame: 0\n    end_frame: 40\n  - name: move\n  - name: release\n    start_frame: 121\n    end_frame: 150\n");
  }

  /**
   * Tests that build() assembles the info document from metadata and URLs.
   */
  public function testBuildsInfoFromMetadataAndFileUrls(): void {
    $info = (new LeRobotInfoBuilder())->build($this->metadata(), 'http://x/traj.parquet', 'parquet', 'http://x/demo.mp4');

    $this->assertSame('v2.1', $info['codebase_version']);
    $this->assertSame('so-arm101', $info['robot_type']);
    $this->assertSame(30, $info['fps']);
    $this->assertSame('pick', $info['task']);
    $this->assertSame('http://x/traj.parquet', $info['data_path']);
    $this->assertSame('parquet', $info['data_format']);
    $this->assertSame('http://x/demo.mp4', $info['video_path']);
    $this->assertSame(['grasp', 'move', 'release'], array_column($info['phases'], 'name'));
  }

  /**
   * Tests that total_frames is the last known end_frame plus one.
   */
  public function testTotalFramesIsTheLastKnownEndFramePlusOne(): void {
    $info = (new LeRobotInfoBuilder())->build($this->metadata(), NULL, NULL, NULL);

    $this->assertSame(151, $info['total_frames']);
  }

  /**
   * Tests that missing trajectory and video files become null.
   */
  public function testMissingFilesBecomeNull(): void {
    $info = (new LeRobotInfoBuilder())->build($this->metadata(), NULL, NULL, NULL);

    $this->assertNull($info['data_path']);
    $this->assertNull($info['data_format']);
    $this->assertNull($info['video_path']);
  }

}
