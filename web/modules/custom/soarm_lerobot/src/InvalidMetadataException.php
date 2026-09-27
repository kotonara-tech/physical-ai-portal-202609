<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot;

/**
 * Thrown when episode metadata YAML fails validation.
 *
 * All validation problems found in a single document are collected and
 * reported together via getErrors(), rather than failing on the first one.
 */
final class InvalidMetadataException extends \InvalidArgumentException {

  /**
   * The individual validation error messages.
   *
   * @var string[]
   */
  private array $errors;

  /**
   * Constructs an InvalidMetadataException.
   *
   * @param string[] $errors
   *   All validation error messages found for the document.
   */
  public function __construct(array $errors) {
    parent::__construct(implode(' ', $errors));
    $this->errors = $errors;
  }

  /**
   * Gets all validation error messages.
   *
   * @return string[]
   *   The validation error messages.
   */
  public function getErrors(): array {
    return $this->errors;
  }

}
