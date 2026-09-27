<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_lerobot\Kernel;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that uploaded LeRobot files are validated when a post is saved.
 */
#[Group('soarm_lerobot')]
final class FileValidationTest extends LeRobotKernelTestBase {

  /**
   * Tests that Parquet and HDF5 trajectory files are accepted.
   */
  public function testParquetAndHdf5TrajectoriesAreAccepted(): void {
    foreach (['a.parquet' => self::PARQUET, 'b.hdf5' => self::HDF5] as $name => $bytes) {
      $node = $this->buildPost(['field_trajectory' => $this->createFile($name, $bytes)]);
      $this->assertSame([], $this->violationsOn($node, 'field_trajectory'), $name);
    }
  }

  /**
   * Tests that the trajectory format is checked by content, not extension.
   */
  public function testTrajectoryIsCheckedByContentNotByExtension(): void {
    $node = $this->buildPost(['field_trajectory' => $this->createFile('fake.parquet', 'this is not parquet')]);

    $messages = $this->violationsOn($node, 'field_trajectory');

    $this->assertCount(1, $messages);
    $this->assertStringContainsString('HDF5', $messages[0]);
    $this->assertStringContainsString('Parquet', $messages[0]);
  }

  /**
   * Tests that a valid metadata YAML file is accepted.
   */
  public function testValidMetadataYamlIsAccepted(): void {
    $node = $this->buildPost(['field_metadata_yaml' => $this->createFile('ok.yaml', self::VALID_YAML)]);

    $this->assertSame([], $this->violationsOn($node, 'field_metadata_yaml'));
  }

  /**
   * Tests that every metadata problem becomes a violation.
   */
  public function testEveryMetadataProblemBecomesViolation(): void {
    $node = $this->buildPost(['field_metadata_yaml' => $this->createFile('bad.yaml', "robot_type: ur5\nfps: 30\n")]);

    $messages = implode("\n", $this->violationsOn($node, 'field_metadata_yaml'));

    $this->assertStringContainsString('robot_type', $messages);
    $this->assertStringContainsString('phases', $messages);
  }

  /**
   * Tests that posts without trajectory or metadata files are valid.
   */
  public function testPostsWithoutFilesAreValid(): void {
    $node = $this->buildPost([]);

    $this->assertSame([], $this->violationsOn($node, 'field_trajectory'));
    $this->assertSame([], $this->violationsOn($node, 'field_metadata_yaml'));
  }

}
