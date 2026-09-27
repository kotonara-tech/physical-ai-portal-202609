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

  /**
   * Temporary file paths created by fileWith(), removed in tearDown().
   */
  private array $files = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    array_map('unlink', array_filter($this->files, 'file_exists'));
    parent::tearDown();
  }

  /**
   * Writes bytes to a temporary file and returns its path.
   */
  private function fileWith(string $bytes): string {
    $path = tempnam(sys_get_temp_dir(), 'soarm');
    file_put_contents($path, $bytes);
    return $this->files[] = $path;
  }

  /**
   * Tests that an HDF5 file is detected by its magic bytes.
   */
  public function testDetectsHdf5(): void {
    $path = $this->fileWith("\x89HDF\r\n\x1a\n" . str_repeat("\0", 64));
    $this->assertSame('hdf5', (new TrajectoryFormatDetector())->detect($path));
  }

  /**
   * Tests that a Parquet file is detected by its header and footer.
   */
  public function testDetectsParquetByHeaderAndFooter(): void {
    $path = $this->fileWith('PAR1' . str_repeat("\0", 32) . 'PAR1');
    $this->assertSame('parquet', (new TrajectoryFormatDetector())->detect($path));
  }

  /**
   * Tests that a file with the Parquet header but no footer is not Parquet.
   */
  public function testParquetWithoutFooterIsNotParquet(): void {
    $path = $this->fileWith('PAR1' . str_repeat("\0", 32));
    $this->assertNull((new TrajectoryFormatDetector())->detect($path));
  }

  /**
   * Tests that plain text is an unknown format.
   */
  public function testPlainTextIsUnknown(): void {
    $this->assertNull((new TrajectoryFormatDetector())->detect($this->fileWith('this is not parquet')));
  }

  /**
   * Tests that empty files and missing paths are an unknown format.
   */
  public function testEmptyAndMissingFilesAreUnknown(): void {
    $detector = new TrajectoryFormatDetector();
    $this->assertNull($detector->detect($this->fileWith('')));
    $this->assertNull($detector->detect('/nonexistent/soarm-trajectory.parquet'));
  }

}
