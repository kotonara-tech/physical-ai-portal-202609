<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_lerobot\Unit;

use Drupal\soarm_lerobot\EpisodeMetadata;
use Drupal\soarm_lerobot\InvalidMetadataException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;

/**
 * Tests parsing the YAML metadata built around grasp -> move -> release.
 */
#[CoversClass(EpisodeMetadata::class)]
#[Group('soarm_lerobot')]
#[Small]
final class EpisodeMetadataTest extends UnitTestCase {

  private const VALID = <<<YAML
robot_type: so-arm101
fps: 30
task: pick a block and place it in the tray
phases:
  - name: grasp
    start_frame: 0
    end_frame: 40
  - name: move
    start_frame: 41
    end_frame: 120
  - name: release
    start_frame: 121
    end_frame: 150
YAML;

  /**
   * Tests parsing a valid metadata document.
   */
  public function testParsesValidDocument(): void {
    $metadata = EpisodeMetadata::fromYaml(self::VALID);

    $this->assertSame('so-arm101', $metadata->robotType);
    $this->assertSame(30, $metadata->fps);
    $this->assertSame('pick a block and place it in the tray', $metadata->task);
    $this->assertSame(['grasp', 'move', 'release'], array_column($metadata->phases, 'name'));
    $this->assertSame(['name' => 'move', 'start_frame' => 41, 'end_frame' => 120], $metadata->phases[1]);
  }

  /**
   * Tests that extra phases are allowed around the core grasp/move/release.
   */
  public function testExtraPhasesAreAllowedAroundTheCoreThree(): void {
    $yaml = "robot_type: so-arm100\nfps: 15\nphases:\n  - name: approach\n  - name: grasp\n  - name: move\n  - name: regrasp\n  - name: release\n  - name: retreat\n";

    $metadata = EpisodeMetadata::fromYaml($yaml);

    $this->assertCount(6, $metadata->phases);
    $this->assertSame(['name' => 'approach', 'start_frame' => NULL, 'end_frame' => NULL], $metadata->phases[0]);
  }

  /**
   * Tests that invalid documents report what is wrong.
   *
   * @param string $yaml
   *   The invalid document.
   * @param string $expectedError
   *   A fragment that must appear in one of the reported errors.
   */
  #[DataProvider('invalidProvider')]
  public function testInvalidDocumentsReportWhatIsWrong(string $yaml, string $expectedError): void {
    try {
      EpisodeMetadata::fromYaml($yaml);
      $this->fail('Expected InvalidMetadataException.');
    }
    catch (InvalidMetadataException $e) {
      $this->assertStringContainsString($expectedError, implode("\n", $e->getErrors()));
    }
  }

  /**
   * Data provider for testInvalidDocumentsReportWhatIsWrong().
   */
  public static function invalidProvider(): array {
    $phases = "phases:\n  - name: grasp\n  - name: move\n  - name: release\n";
    return [
      'not yaml' => ["robot_type: [unclosed", 'YAML'],
      'not a mapping' => ["- just\n- a list\n", 'mapping'],
      'missing robot_type' => ["fps: 30\n$phases", 'robot_type'],
      'unknown robot_type' => ["robot_type: ur5\nfps: 30\n$phases", 'robot_type'],
      'missing fps' => ["robot_type: so-arm100\n$phases", 'fps'],
      'fps not a positive integer' => ["robot_type: so-arm100\nfps: 0\n$phases", 'fps'],
      'missing phases' => ["robot_type: so-arm100\nfps: 30\n", 'phases'],
      'missing release' => ["robot_type: so-arm100\nfps: 30\nphases:\n  - name: grasp\n  - name: move\n", 'release'],
      'wrong order' => [
        "robot_type: so-arm100\nfps: 30\nphases:\n  - name: move\n  - name: grasp\n  - name: release\n",
        'order',
      ],
      'phase without name' => ["robot_type: so-arm100\nfps: 30\nphases:\n  - start_frame: 0\n", 'name'],
      'end before start' => [
        "robot_type: so-arm100\nfps: 30\nphases:\n  - name: grasp\n    start_frame: 10\n    end_frame: 5\n  - name: move\n  - name: release\n",
        'end_frame',
      ],
    ];
  }

  /**
   * Tests that all problems in a document are reported at once.
   */
  public function testAllProblemsAreReportedAtOnce(): void {
    try {
      EpisodeMetadata::fromYaml("robot_type: ur5\nfps: -1\n");
      $this->fail('Expected InvalidMetadataException.');
    }
    catch (InvalidMetadataException $e) {
      $this->assertGreaterThanOrEqual(3, count($e->getErrors()));
    }
  }

  /**
   * Tests that PHP objects embedded in YAML are never instantiated.
   */
  public function testPhpObjectsInYamlAreNeverInstantiated(): void {
    $this->expectException(InvalidMetadataException::class);
    EpisodeMetadata::fromYaml("robot_type: !php/object 'O:8:\"stdClass\":0:{}'\nfps: 30\n");
  }

}
