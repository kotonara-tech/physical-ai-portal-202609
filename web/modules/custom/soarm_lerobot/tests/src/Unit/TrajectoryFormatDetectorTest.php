<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_lerobot\Unit;

use Drupal\soarm_lerobot\TrajectoryFormatDetector;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests detecting HDF5 / Parquet by magic bytes (not by file extension).
 */
#[CoversClass(TrajectoryFormatDetector::class)]
#[Group('soarm_lerobot')]
final class TrajectoryFormatDetectorTest extends UnitTestCase {

  private array $files = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    array_map('unlink', array_filter($this->files, 'file_exists'));
    parent::tearDown();
  }

  private function fileWith(string $bytes): string {
    $path = tempnam(sys_get_temp_dir(), 'soarm');
    file_put_contents($path, $bytes);
    return $this->files[] = $path;
  }

  public function testDetectsHdf5(): void {
    $path = $this->fileWith("\x89HDF\r\n\x1a\n" . str_repeat("\0", 64));
    $this->assertSame('hdf5', (new TrajectoryFormatDetector())->detect($path));
  }

  public function testDetectsParquetByHeaderAndFooter(): void {
    $path = $this->fileWith('PAR1' . str_repeat("\0", 32) . 'PAR1');
    $this->assertSame('parquet', (new TrajectoryFormatDetector())->detect($path));
  }

  public function testParquetWithoutFooterIsNotParquet(): void {
    $path = $this->fileWith('PAR1' . str_repeat("\0", 32));
    $this->assertNull((new TrajectoryFormatDetector())->detect($path));
  }

  public function testPlainTextIsUnknown(): void {
    $this->assertNull((new TrajectoryFormatDetector())->detect($this->fileWith('this is not parquet')));
  }

  public function testEmptyAndMissingFilesAreUnknown(): void {
    $detector = new TrajectoryFormatDetector();
    $this->assertNull($detector->detect($this->fileWith('')));
    $this->assertNull($detector->detect('/nonexistent/soarm-trajectory.parquet'));
  }

}
