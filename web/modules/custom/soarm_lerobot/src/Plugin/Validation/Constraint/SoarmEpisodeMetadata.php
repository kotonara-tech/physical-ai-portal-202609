<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that a referenced metadata file parses as valid episode metadata.
 *
 * Unlike most constraints, this one has no fixed message: the validator
 * reports each error from InvalidMetadataException::getErrors() verbatim,
 * since those already are complete, specific violation messages.
 */
#[Constraint(
  id: 'SoarmEpisodeMetadata',
  label: new TranslatableMarkup('Episode metadata', [], ['context' => 'Validation']),
)]
final class SoarmEpisodeMetadata extends SymfonyConstraint {
}
