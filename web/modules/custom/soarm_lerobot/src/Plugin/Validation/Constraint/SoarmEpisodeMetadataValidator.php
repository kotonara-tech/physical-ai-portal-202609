<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\soarm_lerobot\EpisodeMetadata;
use Drupal\soarm_lerobot\InvalidMetadataException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the SoarmEpisodeMetadata constraint.
 */
final class SoarmEpisodeMetadataValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof SoarmEpisodeMetadata) {
      throw new UnexpectedTypeException($constraint, SoarmEpisodeMetadata::class);
    }
    if (!$value instanceof FieldItemListInterface) {
      return;
    }

    foreach ($value as $delta => $item) {
      $file = $item->entity ?? NULL;
      // Skip items whose file entity cannot be loaded: core already reports
      // those (e.g. via the entity reference "valid reference" constraint).
      if (!$file instanceof FileInterface) {
        continue;
      }

      $contents = @file_get_contents($file->getFileUri());
      if ($contents === FALSE) {
        continue;
      }

      try {
        EpisodeMetadata::fromYaml($contents);
      }
      catch (InvalidMetadataException $e) {
        foreach ($e->getErrors() as $error) {
          $this->context->buildViolation($error)
            ->atPath((string) $delta)
            ->addViolation();
        }
      }
    }
  }

}
