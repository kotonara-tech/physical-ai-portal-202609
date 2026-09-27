<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Immutable value object describing a LeRobot episode.
 *
 * Parsed from the small YAML metadata document that accompanies a
 * trajectory file (HDF5 or Parquet). The metadata always describes a
 * "grasp -> move -> release" sequence, optionally surrounded by other
 * phases (e.g. "approach", "retreat").
 */
final class EpisodeMetadata {

  /**
   * The robot types this module currently supports.
   */
  public const ROBOT_TYPES = ['so-arm100', 'so-arm101'];

  /**
   * The phase names that must be present, in this order.
   */
  private const REQUIRED_PHASES = ['grasp', 'move', 'release'];

  /**
   * Constructs an EpisodeMetadata value object.
   *
   * @param string $robotType
   *   One of self::ROBOT_TYPES.
   * @param int $fps
   *   Frames per second, a positive integer.
   * @param string|null $task
   *   A free-text task description, if provided.
   * @param array[] $phases
   *   Each entry is ['name' => string, 'start_frame' => ?int,
   *   'end_frame' => ?int], keys in that exact order.
   */
  public function __construct(
    public readonly string $robotType,
    public readonly int $fps,
    public readonly ?string $task,
    public readonly array $phases,
  ) {}

  /**
   * Parses and validates episode metadata from a YAML document.
   *
   * @param string $yaml
   *   The YAML document.
   *
   * @return self
   *   The parsed metadata.
   *
   * @throws \Drupal\soarm_lerobot\InvalidMetadataException
   *   When the document cannot be parsed as YAML, or fails validation.
   *   Custom YAML tags (e.g. "!php/object") are never resolved: the
   *   default Symfony Yaml::parse() flags are used, so any attempt to use
   *   them surfaces as a YAML parse error instead of instantiating
   *   anything.
   */
  public static function fromYaml(string $yaml): self {
    try {
      $data = Yaml::parse($yaml);
    }
    catch (ParseException $e) {
      throw new InvalidMetadataException(['Invalid YAML: ' . $e->getMessage()]);
    }

    if (!is_array($data) || array_is_list($data)) {
      throw new InvalidMetadataException(['The document root must be a mapping.']);
    }

    $errors = [];

    $robotType = self::validateRobotType($data, $errors);
    $fps = self::validateFps($data, $errors);
    $task = isset($data['task']) && is_string($data['task']) ? $data['task'] : NULL;
    $phases = self::validatePhases($data, $errors);

    if ($errors !== []) {
      throw new InvalidMetadataException($errors);
    }

    return new self($robotType, $fps, $task, $phases);
  }

  /**
   * Validates the robot_type key.
   *
   * @param array $data
   *   The parsed YAML document.
   * @param string[] $errors
   *   Collected errors, appended to by reference.
   *
   * @return string
   *   The robot type, or an empty string when invalid.
   */
  private static function validateRobotType(array $data, array &$errors): string {
    if (!array_key_exists('robot_type', $data)) {
      $errors[] = 'robot_type is required.';
      return '';
    }
    if (!is_string($data['robot_type']) || !in_array($data['robot_type'], self::ROBOT_TYPES, TRUE)) {
      $errors[] = sprintf('robot_type must be one of: %s.', implode(', ', self::ROBOT_TYPES));
      return '';
    }
    return $data['robot_type'];
  }

  /**
   * Validates the fps key.
   *
   * @param array $data
   *   The parsed YAML document.
   * @param string[] $errors
   *   Collected errors, appended to by reference.
   *
   * @return int
   *   The fps value, or 0 when invalid.
   */
  private static function validateFps(array $data, array &$errors): int {
    if (!array_key_exists('fps', $data)) {
      $errors[] = 'fps is required.';
      return 0;
    }
    if (!is_int($data['fps']) || $data['fps'] <= 0) {
      $errors[] = 'fps must be a positive integer.';
      return 0;
    }
    return $data['fps'];
  }

  /**
   * Validates the phases key.
   *
   * @param array $data
   *   The parsed YAML document.
   * @param string[] $errors
   *   Collected errors, appended to by reference.
   *
   * @return array[]
   *   The normalized phases list.
   */
  private static function validatePhases(array $data, array &$errors): array {
    if (!array_key_exists('phases', $data) || !is_array($data['phases']) || count($data['phases']) === 0) {
      $errors[] = 'phases is required and must be a non-empty list.';
      return [];
    }

    $phases = [];
    foreach ($data['phases'] as $index => $phase) {
      $normalized = self::validatePhase($index, $phase, $errors);
      if ($normalized !== NULL) {
        $phases[] = $normalized;
      }
    }

    self::validatePhaseOrder($phases, $errors);

    return $phases;
  }

  /**
   * Validates a single phase entry.
   *
   * @param int|string $index
   *   The phase's position in the list.
   * @param mixed $phase
   *   The raw phase data.
   * @param string[] $errors
   *   Collected errors, appended to by reference.
   *
   * @return array{name: string, start_frame: ?int, end_frame: ?int}|null
   *   The normalized phase, or NULL when it has no usable name.
   */
  private static function validatePhase(int|string $index, mixed $phase, array &$errors): ?array {
    if (!is_array($phase)) {
      $errors[] = sprintf('phases.%s must be a mapping.', $index);
      return NULL;
    }

    $name = $phase['name'] ?? NULL;
    if (!is_string($name) || trim($name) === '') {
      $errors[] = sprintf('phases.%s.name is required.', $index);
      return NULL;
    }

    $startFrame = self::validateFrame($index, 'start_frame', $phase, $errors);
    $endFrame = self::validateFrame($index, 'end_frame', $phase, $errors);

    if ($startFrame !== NULL && $endFrame !== NULL && $endFrame < $startFrame) {
      $errors[] = sprintf('phases.%s.end_frame must be greater than or equal to start_frame.', $index);
    }

    return [
      'name' => $name,
      'start_frame' => $startFrame,
      'end_frame' => $endFrame,
    ];
  }

  /**
   * Validates an optional start_frame/end_frame key on a phase.
   *
   * @param int|string $index
   *   The phase's position in the list.
   * @param string $key
   *   Either 'start_frame' or 'end_frame'.
   * @param array $phase
   *   The raw phase data.
   * @param string[] $errors
   *   Collected errors, appended to by reference.
   *
   * @return int|null
   *   The frame number, or NULL when absent or invalid.
   */
  private static function validateFrame(int|string $index, string $key, array $phase, array &$errors): ?int {
    if (!array_key_exists($key, $phase) || $phase[$key] === NULL) {
      return NULL;
    }
    if (!is_int($phase[$key])) {
      $errors[] = sprintf('phases.%s.%s must be an integer.', $index, $key);
      return NULL;
    }
    return $phase[$key];
  }

  /**
   * Validates that grasp/move/release are present and correctly ordered.
   *
   * @param array[] $phases
   *   The normalized phases list.
   * @param string[] $errors
   *   Collected errors, appended to by reference.
   */
  private static function validatePhaseOrder(array $phases, array &$errors): void {
    $names = array_column($phases, 'name');
    $positions = [];
    foreach (self::REQUIRED_PHASES as $required) {
      $position = array_search($required, $names, TRUE);
      if ($position === FALSE) {
        $errors[] = sprintf('phases must include a "%s" phase.', $required);
        continue;
      }
      $positions[$required] = $position;
    }

    if (count($positions) === count(self::REQUIRED_PHASES)) {
      $previous = -1;
      foreach (self::REQUIRED_PHASES as $required) {
        if ($positions[$required] <= $previous) {
          $errors[] = 'phases must appear in the order grasp, move, release.';
          break;
        }
        $previous = $positions[$required];
      }
    }
  }

}
