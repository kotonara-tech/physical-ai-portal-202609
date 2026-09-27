<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that a referenced trajectory file is HDF5 or Parquet by content.
 */
#[Constraint(
  id: 'SoarmTrajectoryFormat',
  label: new TranslatableMarkup('Trajectory file format', [], ['context' => 'Validation']),
)]
final class SoarmTrajectoryFormat extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $message = 'The file "%filename" is not a valid HDF5 or Parquet trajectory file (checked by content, not by extension).',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
