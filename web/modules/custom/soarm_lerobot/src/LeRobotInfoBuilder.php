<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot;

/**
 * Builds the LeRobot "info.json"-like document served by the API.
 *
 * This assembles the small descriptor that a LeRobot-compatible client
 * (or a browser-based viewer) needs to load an episode: where the
 * trajectory data and video live, and the phase timeline from the
 * episode's metadata.
 *
 * EXTENSION POINT (ROS2 bag): a future version could accept a rosbag2
 * recording (.db3/.mcap) alongside HDF5/Parquet. That would add a
 * `bag_path` key here and a converter service turning rosbag2 topics into
 * this same phases/frames structure, so the existing browser visualiser
 * would not need to change.
 *
 * EXTENSION POINT (Isaac Sim): replaying an episode in NVIDIA Isaac Sim
 * needs a USD stage or a joint-trajectory export. That could be added as
 * a `sim_replay` key pointing at the generated asset, produced by a
 * separate export step run against this same EpisodeMetadata.
 *
 * EXTENSION POINT (WebSocket/SSE): live episodes are not covered yet.
 * A later controller could expose `/api/soarm/lerobot/{node}/stream` and
 * relay frames published over rosbridge_suite, using this builder's
 * output as the initial handshake payload before frames start streaming.
 */
final class LeRobotInfoBuilder {

  /**
   * The codebase version reported in the built document.
   */
  private const CODEBASE_VERSION = 'v2.1';

  /**
   * Builds the info document for one episode.
   *
   * @param \Drupal\soarm_lerobot\EpisodeMetadata $metadata
   *   The episode's validated metadata.
   * @param string|null $dataUrl
   *   URL to the trajectory data file, if available.
   * @param string|null $dataFormat
   *   The trajectory data format ('hdf5' or 'parquet'), if known.
   * @param string|null $videoUrl
   *   URL to the episode video, if available.
   *
   * @return array
   *   The info document, keyed by codebase_version, robot_type, fps,
   *   task, total_frames, phases, data_path, data_format, video_path.
   */
  public function build(EpisodeMetadata $metadata, ?string $dataUrl, ?string $dataFormat, ?string $videoUrl): array {
    return [
      'codebase_version' => self::CODEBASE_VERSION,
      'robot_type' => $metadata->robotType,
      'fps' => $metadata->fps,
      'task' => $metadata->task,
      'total_frames' => $this->totalFrames($metadata),
      'phases' => $metadata->phases,
      'data_path' => $dataUrl,
      'data_format' => $dataFormat,
      'video_path' => $videoUrl,
    ];
  }

  /**
   * Computes the total frame count from the phases' end_frame values.
   *
   * @param \Drupal\soarm_lerobot\EpisodeMetadata $metadata
   *   The episode's validated metadata.
   *
   * @return int|null
   *   The highest known end_frame plus one, or NULL when no phase
   *   reports an end_frame.
   */
  private function totalFrames(EpisodeMetadata $metadata): ?int {
    $endFrames = array_filter(
      array_column($metadata->phases, 'end_frame'),
      static fn ($endFrame) => $endFrame !== NULL,
    );

    if ($endFrames === []) {
      return NULL;
    }

    return max($endFrames) + 1;
  }

}
