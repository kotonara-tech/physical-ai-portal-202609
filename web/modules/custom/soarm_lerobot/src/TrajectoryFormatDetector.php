<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot;

/**
 * Detects LeRobot trajectory file formats by magic bytes.
 *
 * Trajectory files can be hundreds of megabytes, so detection only ever
 * reads the first 8 bytes and the last 4 bytes of the file, never the
 * whole content.
 */
final class TrajectoryFormatDetector {

  /**
   * The HDF5 file signature.
   */
  private const HDF5_MAGIC = "\x89HDF\r\n\x1a\n";

  /**
   * The Parquet header/footer magic string.
   */
  private const PARQUET_MAGIC = 'PAR1';

  /**
   * Detects the trajectory file format.
   *
   * @param string $path
   *   Path to the trajectory file.
   *
   * @return string|null
   *   'hdf5', 'parquet', or NULL when the format is unknown, the file is
   *   empty, missing, or unreadable.
   */
  public function detect(string $path): ?string {
    if (!is_file($path) || !is_readable($path)) {
      return NULL;
    }

    $size = @filesize($path);
    if ($size === FALSE || $size <= 0) {
      return NULL;
    }

    $handle = @fopen($path, 'rb');
    if ($handle === FALSE) {
      return NULL;
    }

    try {
      $header = fread($handle, 8);
      if ($header === FALSE || $header === '') {
        return NULL;
      }

      if ($header === self::HDF5_MAGIC) {
        return 'hdf5';
      }

      if ($size >= 8 && substr($header, 0, 4) === self::PARQUET_MAGIC) {
        if (fseek($handle, -4, SEEK_END) === 0) {
          $footer = fread($handle, 4);
          if ($footer === self::PARQUET_MAGIC) {
            return 'parquet';
          }
        }
      }

      return NULL;
    }
    finally {
      fclose($handle);
    }
  }

}
